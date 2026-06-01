<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;
use PortLibs\Gitoxide\PromisorObjectResolver;

$buildMultiPackIndex = static function (array $packs): string {
    $packUInt64 = static function (int $value): string {
        if ($value < 0) {
            throw new RuntimeException('Cannot encode a negative 64-bit integer');
        }

        return pack('N2', intdiv($value, 4294967296), $value % 4294967296);
    };
    $padToFour = static function (string $bytes): string {
        $padding = (4 - (strlen($bytes) % 4)) % 4;

        return $bytes . str_repeat("\0", $padding);
    };

    ksort($packs, SORT_STRING);
    $indexNames = array_keys($packs);
    $entries = [];
    foreach ($indexNames as $packIndex => $indexName) {
        foreach ($packs[$indexName]->entries() as $entry) {
            $entries[] = [
                'oid' => $entry['oid'],
                'packIndex' => $packIndex,
                'offset' => $entry['offset'],
            ];
        }
    }
    usort($entries, static fn (array $a, array $b): int => strcmp($a['oid'], $b['oid']));

    $fanout = array_fill(0, 256, 0);
    foreach ($entries as $entry) {
        $fanout[hexdec(substr($entry['oid'], 0, 2))]++;
    }
    $running = 0;
    foreach ($fanout as $index => $count) {
        $running += $count;
        $fanout[$index] = $running;
    }

    $packNames = '';
    foreach ($indexNames as $name) {
        $packNames .= $name . "\0";
    }
    $fanoutBytes = '';
    foreach ($fanout as $count) {
        $fanoutBytes .= pack('N', $count);
    }
    $oidBytes = '';
    $offsetBytes = '';
    foreach ($entries as $entry) {
        $oid = hex2bin($entry['oid']);
        if ($oid === false || strlen($oid) !== 20) {
            throw new RuntimeException('Invalid object id in example multi-pack-index');
        }
        $oidBytes .= $oid;
        $offsetBytes .= pack('N2', $entry['packIndex'], $entry['offset']);
    }

    $chunks = [
        'PNAM' => $padToFour($packNames),
        'OIDF' => $fanoutBytes,
        'OIDL' => $oidBytes,
        'OOFF' => $offsetBytes,
    ];
    $header = 'MIDX' . chr(1) . chr(1) . chr(count($chunks)) . "\0" . pack('N', count($indexNames));
    $chunkOffset = strlen($header) + (count($chunks) + 1) * 12;
    $table = '';
    $body = '';
    foreach ($chunks as $id => $chunk) {
        $table .= $id . $packUInt64($chunkOffset);
        $body .= $chunk;
        $chunkOffset += strlen($chunk);
    }
    $table .= "\0\0\0\0" . $packUInt64($chunkOffset);
    $bytes = $header . $table . $body;

    return $bytes . hex2bin(hash('sha1', $bytes));
};

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$gitDir = sys_get_temp_dir() . '/port-libs-git-lazy-promisor-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack directory: {$packDir}");
}

$basename = 'pack-' . $fixture['packChecksum'];
file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);
file_put_contents($packDir . '/' . $basename . '.promisor', "blobless WordPress lazy fetch\n");
file_put_contents($gitDir . '/config', <<<CFG
[remote "origin"]
    url = https://git.example.test/wp-content.git
    promisor = 2
    partialCloneFilter = blob:none
CFG);

$mediaBlob = new GitObject('blob', 'Lazily fetched WordPress media attachment bytes');
$thinStable = '';
for ($i = 0; $i < 72; $i++) {
    $thinStable .= hash('sha1', 'wordpress-thin-promisor-base-' . $i) . "\n";
}
$thinBaseBlob = new GitObject('blob', "WordPress template base\n{$thinStable}status=draft\nchecksum=old\n");
$thinTargetBlob = new GitObject('blob', "WordPress template base\n{$thinStable}status=publish\nchecksum=new\n");
$crossPackStable = '';
for ($i = 0; $i < 72; $i++) {
    $crossPackStable .= hash('sha1', 'wordpress-cross-pack-promisor-base-' . $i) . "\n";
}
$crossPackBaseBlob = new GitObject('blob', "WordPress cross-pack template base\n{$crossPackStable}status=draft\nchecksum=old\n");
$crossPackTargetBlob = new GitObject('blob', "WordPress cross-pack template base\n{$crossPackStable}status=publish\nchecksum=new\n");
$alternateStable = '';
for ($i = 0; $i < 72; $i++) {
    $alternateStable .= hash('sha1', 'wordpress-alternate-promisor-base-' . $i) . "\n";
}
$alternateBaseBlob = new GitObject('blob', "WordPress alternate template base\n{$alternateStable}status=draft\nchecksum=old\n");
$alternateTargetBlob = new GitObject('blob', "WordPress alternate template base\n{$alternateStable}status=publish\nchecksum=new\n");
$repairedStable = '';
for ($i = 0; $i < 72; $i++) {
    $repairedStable .= hash('sha1', 'wordpress-repaired-promisor-base-' . $i) . "\n";
}
$repairedBaseBlob = new GitObject('blob', "WordPress repaired thin-pack base\n{$repairedStable}status=draft\nchecksum=old\n");
$repairedTargetBlob = new GitObject('blob', "WordPress repaired thin-pack base\n{$repairedStable}status=publish\nchecksum=new\n");
$resolver = new class([$mediaBlob, $thinBaseBlob]) implements PromisorObjectResolver {
    public array $requests = [];
    public ?string $hydrationPack = null;
    public ?string $hydrationKeep = null;
    private array $objectsById = [];

    public function __construct(array $objects)
    {
        foreach ($objects as $object) {
            $this->objectsById[$object->oid()] = $object;
        }
    }

    public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
    {
        $this->requests[] = $oid;
        $object = $this->objectsById[$oid] ?? null;
        if ($object === null) {
            return null;
        }

        $write = $database->writePromisorPackBundle(
            PackBuilder::build([$object]),
            "WordPress media lazy hydration\n"
        );
        if ($oid === array_key_first($this->objectsById)) {
            $this->hydrationPack = $write['promisorName'];
            $this->hydrationKeep = $write['keepName'];
        }

        return $object;
    }
};

$database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);
$before = $database->objectState($mediaBlob->oid());
$resolved = $database->read($mediaBlob->oid());
$after = $database->objectState($mediaBlob->oid());

$templateBlob = new GitObject('blob', 'Externally hydrated WordPress block template bytes');
$templateOid = $templateBlob->oid();
$refreshDisabledDatabase = (new ObjectDatabase($gitDir))->withObjectStorageRefreshDisabled();
$refreshDisabledBeforeExternalHydration = $refreshDisabledDatabase->objectState($templateOid);
$beforeExternalHydration = $database->objectState($templateOid);
$objectIdsBeforeExternalHydration = $database->objectIds();
$packedObjectCountBeforeExternalHydration = $database->packedObjectCount();
$templatePack = PackBuilder::build([$templateBlob]);
$templateWrite = $database->writePromisorPackBundle($templatePack, "WordPress template external hydration\n");
$objectIdsAfterExternalHydration = $database->objectIds();
$packedObjectCountAfterExternalHydration = $database->packedObjectCount();
$containsAfterExternalHydration = $database->contains($templateOid);
$prefixAfterExternalHydration = $database->lookupPrefix(strtoupper(substr($templateOid, 0, 12)));
$afterExternalHydration = $database->objectState($templateOid);
$refreshDisabledContainsAfterExternalHydration = $refreshDisabledDatabase->contains($templateOid);
$refreshDisabledPrefixAfterExternalHydration = $refreshDisabledDatabase->lookupPrefix(strtoupper(substr($templateOid, 0, 12)), true);
$refreshDisabledAfterExternalHydration = $refreshDisabledDatabase->objectState($templateOid);
$refreshDisabledPromisorPacksAfterExternalHydration = $refreshDisabledDatabase->promisorPackNames();

$emptyPromisorPack = PackBuilder::build([]);
$emptyPromisorPackBase = 'pack-' . $emptyPromisorPack->packChecksum();
$emptyPromisorPacksBefore = $database->promisorPackNames();
$emptyPromisorObjectIdsBefore = $database->promisorObjectIds();
$emptyPromisorPackedObjectCountBefore = $database->packedObjectCount();
$emptyPromisorWrite = $database->writePromisorPackBundle(
    $emptyPromisorPack,
    "WordPress empty filtered fetch response\n"
);
$emptyPromisorPacksAfter = $database->promisorPackNames();
$emptyPromisorObjectIdsAfter = $database->promisorObjectIds();
$emptyPromisorPackedObjectCountAfter = $database->packedObjectCount();
$emptyPromisorFiles = [
    'pack' => is_file($packDir . '/' . $emptyPromisorPackBase . '.pack'),
    'index' => is_file($packDir . '/' . $emptyPromisorPackBase . '.idx'),
    'promisor' => is_file($packDir . '/' . $emptyPromisorPackBase . '.promisor'),
    'keep' => is_file($packDir . '/' . $emptyPromisorPackBase . '.keep'),
];

$refreshNeverReturnedBlob = new GitObject('blob', 'Refresh-never resolver returned WordPress template bytes');
$refreshNeverReturnedOid = $refreshNeverReturnedBlob->oid();
$refreshNeverReturnedResolver = new class($refreshNeverReturnedBlob, $gitDir) implements PromisorObjectResolver {
    public array $requests = [];
    public ?string $packName = null;

    public function __construct(
        private readonly GitObject $object,
        private readonly string $gitDir,
    ) {
    }

    public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
    {
        $this->requests[] = $oid;
        $pack = PackBuilder::build([$this->object]);
        $packDir = $this->gitDir . '/objects/pack';
        $basename = 'pack-' . $pack->packChecksum();
        $this->packName = $basename . '.promisor';

        file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
        file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
        file_put_contents($packDir . '/' . $basename . '.promisor', "WordPress refresh-never returned-object hydration\n");

        return $this->object;
    }
};
$refreshNeverReturnedDatabase = (new ObjectDatabase($gitDir))
    ->withPromisorResolver($refreshNeverReturnedResolver)
    ->withObjectStorageRefreshDisabled();
$refreshNeverReturnedBefore = $refreshNeverReturnedDatabase->objectState($refreshNeverReturnedOid);
$refreshNeverReturned = $refreshNeverReturnedDatabase->read($refreshNeverReturnedOid);
$refreshNeverReturnedAfter = $refreshNeverReturnedDatabase->objectState($refreshNeverReturnedOid);
$refreshNeverReturnedHeader = $refreshNeverReturnedDatabase->readHeader($refreshNeverReturnedOid);
$refreshNeverReturnedPromisorPacksAfter = $refreshNeverReturnedDatabase->promisorPackNames();
$refreshNeverReturnedFreshDatabase = new ObjectDatabase($gitDir);
$refreshNeverReturnedFreshState = $refreshNeverReturnedFreshDatabase->objectState($refreshNeverReturnedOid);
$refreshNeverReturnedFreshHeader = $refreshNeverReturnedFreshDatabase->readHeader($refreshNeverReturnedOid);

$returnedHeaderBlob = new GitObject('blob', 'Header-first resolver returned WordPress block style bytes');
$returnedHeaderOid = $returnedHeaderBlob->oid();
$returnedHeaderResolver = new class($returnedHeaderBlob, $gitDir) implements PromisorObjectResolver {
    public array $requests = [];
    public ?string $packName = null;

    public function __construct(
        private readonly GitObject $object,
        private readonly string $gitDir,
    ) {
    }

    public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
    {
        $this->requests[] = $oid;
        $pack = PackBuilder::build([$this->object]);
        $packDir = $this->gitDir . '/objects/pack';
        $basename = 'pack-' . $pack->packChecksum();
        $this->packName = $basename . '.promisor';

        file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
        file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
        file_put_contents($packDir . '/' . $basename . '.promisor', "WordPress header-first returned-object hydration\n");

        return $this->object;
    }
};
$returnedHeaderDatabase = (new ObjectDatabase($gitDir))->withPromisorResolver($returnedHeaderResolver);
$returnedHeaderBefore = $returnedHeaderDatabase->objectState($returnedHeaderOid);
$returnedHeader = $returnedHeaderDatabase->readHeader($returnedHeaderOid);
$returnedHeaderAfter = $returnedHeaderDatabase->objectState($returnedHeaderOid);
$returnedHeaderFreshDatabase = new ObjectDatabase($gitDir);
$returnedHeaderFreshHeader = $returnedHeaderFreshDatabase->readHeader($returnedHeaderOid);
$returnedHeaderFreshBodyMatches = $returnedHeaderFreshDatabase->read($returnedHeaderOid)->body === $returnedHeaderBlob->body;

$thinPack = PackBuilder::buildWithRefDeltas([$thinTargetBlob], [$thinBaseBlob]);
$thinPackBase = 'pack-' . $thinPack->packChecksum();
file_put_contents($packDir . '/' . $thinPackBase . '.pack', $thinPack->packBytes());
file_put_contents($packDir . '/' . $thinPackBase . '.idx', $thinPack->indexBytes());
file_put_contents($packDir . '/' . $thinPackBase . '.promisor', "WordPress thin promisor delta hydration\n");
$thinBaseBeforeHydration = $database->objectState($thinBaseBlob->oid());
$thinTargetBeforeHydration = $database->objectState($thinTargetBlob->oid());
$thinTargetHeader = $database->readHeader($thinTargetBlob->oid());
$thinTarget = $database->read($thinTargetBlob->oid());
$thinBaseAfterHydration = $database->objectState($thinBaseBlob->oid());
$thinTargetAfterHydration = $database->objectState($thinTargetBlob->oid());

$crossPackBasePack = PackBuilder::build([$crossPackBaseBlob]);
$crossPackBasePackBase = 'pack-' . $crossPackBasePack->packChecksum();
file_put_contents($packDir . '/' . $crossPackBasePackBase . '.pack', $crossPackBasePack->packBytes());
file_put_contents($packDir . '/' . $crossPackBasePackBase . '.idx', $crossPackBasePack->indexBytes());
file_put_contents($packDir . '/' . $crossPackBasePackBase . '.promisor', "WordPress cross-pack promisor delta base\n");
$crossPackThinPack = PackBuilder::buildWithRefDeltas([$crossPackTargetBlob], [$crossPackBaseBlob]);
$crossPackThinPackBase = 'pack-' . $crossPackThinPack->packChecksum();
file_put_contents($packDir . '/' . $crossPackThinPackBase . '.pack', $crossPackThinPack->packBytes());
file_put_contents($packDir . '/' . $crossPackThinPackBase . '.idx', $crossPackThinPack->indexBytes());
file_put_contents($packDir . '/' . $crossPackThinPackBase . '.promisor', "WordPress cross-pack promisor thin delta target\n");
$crossPackBaseBeforeRead = $database->objectState($crossPackBaseBlob->oid());
$crossPackTargetBeforeRead = $database->objectState($crossPackTargetBlob->oid());
$crossPackTargetHeader = $database->readHeader($crossPackTargetBlob->oid());
$crossPackTarget = $database->read($crossPackTargetBlob->oid());
$crossPackBaseAfterRead = $database->objectState($crossPackBaseBlob->oid());
$crossPackTargetAfterRead = $database->objectState($crossPackTargetBlob->oid());

$alternateObjects = sys_get_temp_dir() . '/port-libs-git-lazy-promisor-alternate-' . bin2hex(random_bytes(4)) . '/objects';
$primaryInfo = $gitDir . '/objects/info';
if (!mkdir($alternateObjects . '/info', 0777, true) && !is_dir($alternateObjects . '/info')) {
    throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjects}");
}
if (!is_dir($primaryInfo) && !mkdir($primaryInfo, 0777, true) && !is_dir($primaryInfo)) {
    throw new RuntimeException("Unable to create objects info directory: {$primaryInfo}");
}
file_put_contents($primaryInfo . '/alternates', $alternateObjects . "\n");
$alternateBaseOid = LooseObjectStore::fromObjectsDirectory($alternateObjects)->write($alternateBaseBlob);
$alternateThinPack = PackBuilder::buildWithRefDeltas([$alternateTargetBlob], [$alternateBaseBlob]);
$alternateThinWrite = $database->writePromisorPackBundle(
    $alternateThinPack,
    "WordPress alternate-base promisor thin delta target\n"
);
$alternateObjectDirectories = $database->alternateObjectDirectories();
$alternateThinBaseState = $database->objectState($alternateBaseBlob->oid());
$alternateThinTargetState = $database->objectState($alternateTargetBlob->oid());
$alternateThinTargetHeader = $database->readHeader($alternateTargetBlob->oid());
$alternateThinTarget = $database->read($alternateTargetBlob->oid());
$promisorPacksAfterAlternateHydration = $database->promisorPackNames();

$repairedThinPack = PackBuilder::buildWithRefDeltas([$repairedTargetBlob], [$repairedBaseBlob]);
$repairedResolver = new class($repairedBaseBlob) implements PromisorObjectResolver {
    public array $requests = [];

    public function __construct(private readonly GitObject $base)
    {
    }

    public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
    {
        $this->requests[] = $oid;

        return $oid === $this->base->oid() ? $this->base : null;
    }
};
$repairedDatabase = $database->withPromisorResolver($repairedResolver);
$repairedBaseBeforeWrite = $repairedDatabase->objectState($repairedBaseBlob->oid());
$repairedWrite = $repairedDatabase->writePromisorPackBundle(
    $repairedThinPack,
    "WordPress repaired received thin promisor pack\n",
    true,
    true
);
$repairedStoredPack = PackData::open($packDir . '/' . $repairedWrite['packName']);
$repairedStoredIndex = PackIndex::open($packDir . '/' . $repairedWrite['indexName']);
$repairedTargetHeader = $repairedStoredPack->readObjectHeader($repairedStoredIndex, $repairedTargetBlob->oid());
$repairedTargetBodyMatches = $repairedStoredPack->readObject($repairedStoredIndex, $repairedTargetBlob->oid())->body === $repairedTargetBlob->body;
$repairedFreshDatabase = new ObjectDatabase($gitDir);
$repairedFreshTargetState = $repairedFreshDatabase->objectState($repairedTargetBlob->oid());
$repairedFreshTargetHeader = $repairedFreshDatabase->readHeader($repairedTargetBlob->oid());

$inventoryBlob = new GitObject('blob', 'WordPress direct promisor inventory refresh bytes');
$inventoryPack = PackBuilder::build([$inventoryBlob]);
$inventoryWrite = $database->writePromisorPackBundle($inventoryPack, "WordPress direct promisor inventory hydration\n");
$directInventoryPackNames = $database->promisorPackNames();
$directInventoryObjectIds = $database->promisorObjectIds();
$directInventoryIsPromisor = $database->isPromisorObject($inventoryBlob->oid());

$resumedAssetBlob = new GitObject('blob', 'Interrupted WordPress filtered pack resumes under keep protection');
$resumedPack = PackBuilder::build([$resumedAssetBlob]);
$resumedPackBase = 'pack-' . $resumedPack->packChecksum();
file_put_contents($packDir . '/' . $resumedPackBase . '.pack', $resumedPack->packBytes());
$resumedWrite = $database->writePromisorPackBundle(
    $resumedPack,
    "WordPress interrupted filtered pack resume\n"
);
$resumedState = $database->objectState($resumedAssetBlob->oid());
$resumedHeader = $database->readHeader($resumedAssetBlob->oid());
$resumedBodyMatches = $database->read($resumedAssetBlob->oid())->body === $resumedAssetBlob->body;

$orphanBlob = new GitObject('blob', 'WordPress interrupted promisor index without pack bytes');
$orphanPack = PackBuilder::build([$orphanBlob]);
$orphanBase = 'pack-' . $orphanPack->packChecksum();
file_put_contents($packDir . '/' . $orphanBase . '.idx', $orphanPack->indexBytes());
file_put_contents($packDir . '/' . $orphanBase . '.promisor', "WordPress interrupted promisor index without pack bytes\n");
$orphanPromisorPacksAfterRefresh = $database->promisorPackNames();
$orphanPromisorObjectIdsAfterRefresh = $database->promisorObjectIds();
$orphanPromisorIsPromisor = $database->isPromisorObject($orphanBlob->oid());
$orphanPromisorState = $database->objectState($orphanBlob->oid());

$staleMidxValidBlob = new GitObject('blob', 'WordPress stale MIDX valid promisor pack bytes');
$staleMidxValidPack = PackBuilder::build([$staleMidxValidBlob]);
$staleMidxValidWrite = $database->writePromisorPackBundle(
    $staleMidxValidPack,
    "WordPress stale promisor MIDX valid pack\n"
);
$staleMidxValidIndexName = substr($staleMidxValidWrite['promisorName'], 0, -9) . '.idx';
$staleMidxOrphanBlob = new GitObject('blob', 'WordPress stale promisor MIDX orphan pack bytes');
$staleMidxOrphanPack = PackBuilder::build([$staleMidxOrphanBlob]);
$staleMidxOrphanBase = 'pack-' . $staleMidxOrphanPack->packChecksum();
file_put_contents($packDir . '/' . $staleMidxOrphanBase . '.idx', $staleMidxOrphanPack->indexBytes());
file_put_contents($packDir . '/' . $staleMidxOrphanBase . '.promisor', "WordPress stale promisor MIDX orphan\n");
file_put_contents($packDir . '/multi-pack-index', $buildMultiPackIndex([
    $staleMidxValidIndexName => $staleMidxValidPack,
    $staleMidxOrphanBase . '.idx' => $staleMidxOrphanPack,
]));
$staleMidxPromisorPacksAfterRefresh = $database->promisorPackNames();
$staleMidxPromisorObjectIdsAfterRefresh = $database->promisorObjectIds();
$staleMidxValidState = $database->objectState($staleMidxValidBlob->oid());
$staleMidxOrphanState = $database->objectState($staleMidxOrphanBlob->oid());
$staleMidxPrefix = $database->lookupPrefix(strtoupper(substr($staleMidxValidBlob->oid(), 0, 12)));
$staleMidxValidBodyMatches = $database->read($staleMidxValidBlob->oid())->body === $staleMidxValidBlob->body;
$staleMidxValidHeader = $database->readHeader($staleMidxValidBlob->oid());

$deepChainStable = '';
for ($i = 0; $i < 96; $i++) {
    $deepChainStable .= hash('sha1', 'wordpress-deep-promisor-chain-' . $i) . "\n";
}
$deepChainObjects = [new GitObject('blob', "WordPress deep promisor base\n{$deepChainStable}step=00\n")];
$deepChainWrites = [
    $database->writePromisorPackBundle(
        PackBuilder::build([$deepChainObjects[0]]),
        "WordPress deep promisor delta chain base\n"
    ),
];
for ($i = 1; $i <= 40; $i++) {
    $object = new GitObject('blob', "WordPress deep promisor base\n{$deepChainStable}step=" . sprintf('%02d', $i) . "\n");
    $pack = PackBuilder::buildWithRefDeltas([$object], [$deepChainObjects[$i - 1]]);
    if (!$pack->isThin()) {
        throw new RuntimeException("Expected deep promisor chain pack {$i} to be thin");
    }
    $deepChainWrites[] = $database->writePromisorPackBundle(
        $pack,
        "WordPress deep promisor delta chain {$i}\n"
    );
    $deepChainObjects[] = $object;
}
$deepChainTarget = $deepChainObjects[40];
$deepChainTargetOid = $deepChainTarget->oid();
$deepChainTargetBeforeGuard = $database->objectState($deepChainTargetOid);
$deepChainHeaderGuardMessage = null;
try {
    $database->readHeader($deepChainTargetOid);
} catch (RuntimeException $exception) {
    $deepChainHeaderGuardMessage = $exception->getMessage();
}
$deepChainReadGuardMessage = null;
try {
    $database->read($deepChainTargetOid);
} catch (RuntimeException $exception) {
    $deepChainReadGuardMessage = $exception->getMessage();
}

return [
    'promisorRemotes' => $database->promisorRemotes(),
    'promisorPacks' => $database->promisorPackNames(),
    'mediaObject' => $mediaBlob->oid(),
    'beforeRead' => $before,
    'resolverRequests' => $resolver->requests,
    'hydrationPack' => $resolver->hydrationPack,
    'hydrationKeep' => $resolver->hydrationKeep,
    'promisorPacksAfterHydration' => $database->promisorPackNames(),
    'resolvedType' => $resolved->type,
    'resolvedSize' => strlen($resolved->body),
    'afterRead' => $after,
    'persistedInPackStore' => (new ObjectDatabase($gitDir))->read($mediaBlob->oid())->body === $mediaBlob->body,
    'externalHydratedObject' => $templateOid,
    'refreshDisabledRefreshesOnMiss' => $refreshDisabledDatabase->objectStorageRefreshesOnMiss(),
    'refreshDisabledBeforeExternalHydration' => $refreshDisabledBeforeExternalHydration,
    'beforeExternalHydration' => $beforeExternalHydration,
    'objectIdsBeforeExternalHydration' => $objectIdsBeforeExternalHydration,
    'packedObjectCountBeforeExternalHydration' => $packedObjectCountBeforeExternalHydration,
    'externalHydrationPack' => $templateWrite['promisorName'],
    'externalHydrationKeep' => $templateWrite['keepName'],
    'objectIdsAfterExternalHydration' => $objectIdsAfterExternalHydration,
    'packedObjectCountAfterExternalHydration' => $packedObjectCountAfterExternalHydration,
    'containsAfterExternalHydration' => $containsAfterExternalHydration,
    'prefixAfterExternalHydration' => $prefixAfterExternalHydration,
    'afterExternalHydration' => $afterExternalHydration,
    'refreshDisabledContainsAfterExternalHydration' => $refreshDisabledContainsAfterExternalHydration,
    'refreshDisabledPrefixAfterExternalHydration' => $refreshDisabledPrefixAfterExternalHydration,
    'refreshDisabledAfterExternalHydration' => $refreshDisabledAfterExternalHydration,
    'refreshDisabledPromisorPacksAfterExternalHydration' => $refreshDisabledPromisorPacksAfterExternalHydration,
    'emptyPromisorPackName' => $emptyPromisorWrite['packName'],
    'emptyPromisorIndexName' => $emptyPromisorWrite['indexName'],
    'emptyPromisorMarkerName' => $emptyPromisorWrite['promisorName'],
    'emptyPromisorKeepName' => $emptyPromisorWrite['keepName'],
    'emptyPromisorMaterialized' => $emptyPromisorWrite['materialized'],
    'emptyPromisorAlreadyPresent' => $emptyPromisorWrite['alreadyPresent'],
    'emptyPromisorObjectCount' => $emptyPromisorWrite['objectCount'],
    'emptyPromisorObjects' => $emptyPromisorWrite['objectIds'],
    'emptyPromisorFiles' => $emptyPromisorFiles,
    'emptyPromisorPacksBefore' => $emptyPromisorPacksBefore,
    'emptyPromisorPacksAfter' => $emptyPromisorPacksAfter,
    'emptyPromisorObjectIdsBefore' => $emptyPromisorObjectIdsBefore,
    'emptyPromisorObjectIdsAfter' => $emptyPromisorObjectIdsAfter,
    'emptyPromisorPackedObjectCountBefore' => $emptyPromisorPackedObjectCountBefore,
    'emptyPromisorPackedObjectCountAfter' => $emptyPromisorPackedObjectCountAfter,
    'refreshNeverReturnedObject' => $refreshNeverReturnedOid,
    'refreshNeverReturnedBefore' => $refreshNeverReturnedBefore,
    'refreshNeverReturnedRequests' => $refreshNeverReturnedResolver->requests,
    'refreshNeverReturnedPack' => $refreshNeverReturnedResolver->packName,
    'refreshNeverReturnedResolvedSize' => strlen($refreshNeverReturned->body),
    'refreshNeverReturnedAfter' => $refreshNeverReturnedAfter,
    'refreshNeverReturnedHeader' => $refreshNeverReturnedHeader,
    'refreshNeverReturnedPromisorPacksAfter' => $refreshNeverReturnedPromisorPacksAfter,
    'refreshNeverReturnedFreshState' => $refreshNeverReturnedFreshState,
    'refreshNeverReturnedFreshHeader' => $refreshNeverReturnedFreshHeader,
    'returnedHeaderObject' => $returnedHeaderOid,
    'returnedHeaderBefore' => $returnedHeaderBefore,
    'returnedHeaderRequests' => $returnedHeaderResolver->requests,
    'returnedHeaderPack' => $returnedHeaderResolver->packName,
    'returnedHeader' => $returnedHeader,
    'returnedHeaderAfter' => $returnedHeaderAfter,
    'returnedHeaderFreshHeader' => $returnedHeaderFreshHeader,
    'returnedHeaderFreshBodyMatches' => $returnedHeaderFreshBodyMatches,
    'promisorPacksAfterExternalHydration' => $database->promisorPackNames(),
    'thinPromisorPack' => $thinPackBase . '.promisor',
    'thinPromisorPackIsThin' => $thinPack->isThin(),
    'thinBaseBeforeHydration' => $thinBaseBeforeHydration,
    'thinTargetBeforeHydration' => $thinTargetBeforeHydration,
    'thinTargetHeader' => $thinTargetHeader,
    'thinTargetSize' => strlen($thinTarget->body),
    'thinBaseAfterHydration' => $thinBaseAfterHydration,
    'thinTargetAfterHydration' => $thinTargetAfterHydration,
    'crossPackBasePromisorPack' => $crossPackBasePackBase . '.promisor',
    'crossPackTargetPromisorPack' => $crossPackThinPackBase . '.promisor',
    'crossPackThinPromisorPackIsThin' => $crossPackThinPack->isThin(),
    'crossPackBaseBeforeRead' => $crossPackBaseBeforeRead,
    'crossPackTargetBeforeRead' => $crossPackTargetBeforeRead,
    'crossPackTargetHeader' => $crossPackTargetHeader,
    'crossPackTargetSize' => strlen($crossPackTarget->body),
    'crossPackTargetBodyMatches' => $crossPackTarget->body === $crossPackTargetBlob->body,
    'crossPackBaseAfterRead' => $crossPackBaseAfterRead,
    'crossPackTargetAfterRead' => $crossPackTargetAfterRead,
    'promisorPacksAfterCrossPackHydration' => $database->promisorPackNames(),
    'alternateBaseObject' => $alternateBaseBlob->oid(),
    'alternateBaseWriteOid' => $alternateBaseOid,
    'alternateThinTargetObject' => $alternateTargetBlob->oid(),
    'alternateObjectDirectories' => $alternateObjectDirectories,
    'alternateThinPromisorPack' => $alternateThinWrite['promisorName'],
    'alternateThinPromisorKeep' => $alternateThinWrite['keepName'],
    'alternateThinPromisorPackIsThin' => $alternateThinPack->isThin(),
    'alternateThinBaseState' => $alternateThinBaseState,
    'alternateThinTargetState' => $alternateThinTargetState,
    'alternateThinTargetHeader' => $alternateThinTargetHeader,
    'alternateThinTargetSize' => strlen($alternateThinTarget->body),
    'alternateThinTargetBodyMatches' => $alternateThinTarget->body === $alternateTargetBlob->body,
    'promisorPacksAfterAlternateHydration' => $promisorPacksAfterAlternateHydration,
    'repairedThinPackWasThin' => $repairedThinPack->isThin(),
    'repairedBaseBeforeWrite' => $repairedBaseBeforeWrite,
    'repairedResolverRequests' => $repairedResolver->requests,
    'repairedPromisorPack' => $repairedWrite['promisorName'],
    'repairedPromisorKeep' => $repairedWrite['keepName'],
    'repairedPromisorObjects' => $repairedWrite['objectIds'],
    'repairedPromisorObjectCount' => $repairedWrite['objectCount'],
    'repairedPackChecksumChanged' => $repairedWrite['packChecksum'] !== $repairedThinPack->packChecksum(),
    'repairedStoredPackCount' => $repairedStoredPack->count(),
    'repairedStoredIndexCount' => $repairedStoredIndex->count(),
    'repairedTargetHeader' => $repairedTargetHeader,
    'repairedTargetBodyMatches' => $repairedTargetBodyMatches,
    'repairedFreshTargetState' => $repairedFreshTargetState,
    'repairedFreshTargetHeader' => $repairedFreshTargetHeader,
    'directInventoryObject' => $inventoryBlob->oid(),
    'directInventoryPack' => $inventoryWrite['promisorName'],
    'directInventoryKeep' => $inventoryWrite['keepName'],
    'directInventoryPackNames' => $directInventoryPackNames,
    'directInventoryObjectIds' => $directInventoryObjectIds,
    'directInventoryIsPromisor' => $directInventoryIsPromisor,
    'resumedPromisorObject' => $resumedAssetBlob->oid(),
    'resumedPromisorPack' => $resumedWrite['packName'],
    'resumedPromisorIndex' => $resumedWrite['indexName'],
    'resumedPromisorMarker' => $resumedWrite['promisorName'],
    'resumedPromisorKeep' => $resumedWrite['keepName'],
    'resumedPromisorAlreadyPresent' => $resumedWrite['alreadyPresent'],
    'resumedPromisorState' => $resumedState,
    'resumedPromisorHeader' => $resumedHeader,
    'resumedPromisorBodyMatches' => $resumedBodyMatches,
    'orphanPromisorObject' => $orphanBlob->oid(),
    'orphanPromisorIndex' => $orphanBase . '.promisor',
    'orphanPromisorPacksAfterRefresh' => $orphanPromisorPacksAfterRefresh,
    'orphanPromisorObjectIdsAfterRefresh' => $orphanPromisorObjectIdsAfterRefresh,
    'orphanPromisorIsPromisor' => $orphanPromisorIsPromisor,
    'orphanPromisorState' => $orphanPromisorState,
    'staleMidxValidObject' => $staleMidxValidBlob->oid(),
    'staleMidxValidPromisorPack' => $staleMidxValidWrite['promisorName'],
    'staleMidxOrphanObject' => $staleMidxOrphanBlob->oid(),
    'staleMidxOrphanPromisorPack' => $staleMidxOrphanBase . '.promisor',
    'staleMidxPromisorPacksAfterRefresh' => $staleMidxPromisorPacksAfterRefresh,
    'staleMidxPromisorObjectIdsAfterRefresh' => $staleMidxPromisorObjectIdsAfterRefresh,
    'staleMidxValidState' => $staleMidxValidState,
    'staleMidxOrphanState' => $staleMidxOrphanState,
    'staleMidxPrefix' => $staleMidxPrefix,
    'staleMidxValidBodyMatches' => $staleMidxValidBodyMatches,
    'staleMidxValidHeader' => $staleMidxValidHeader,
    'deepChainObjectCount' => count($deepChainObjects),
    'deepChainTargetObject' => $deepChainTargetOid,
    'deepChainTargetPack' => $deepChainWrites[40]['promisorName'],
    'deepChainTargetBeforeGuard' => $deepChainTargetBeforeGuard,
    'deepChainHeaderGuardMessage' => $deepChainHeaderGuardMessage,
    'deepChainReadGuardMessage' => $deepChainReadGuardMessage,
    'promisorPacksAfterDeepChainGuard' => $database->promisorPackNames(),
];
