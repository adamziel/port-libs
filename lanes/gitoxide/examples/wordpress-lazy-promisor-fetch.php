<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PromisorObjectResolver;

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
    promisor = true
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

$inventoryBlob = new GitObject('blob', 'WordPress direct promisor inventory refresh bytes');
$inventoryPack = PackBuilder::build([$inventoryBlob]);
$inventoryWrite = $database->writePromisorPackBundle($inventoryPack, "WordPress direct promisor inventory hydration\n");
$directInventoryPackNames = $database->promisorPackNames();
$directInventoryObjectIds = $database->promisorObjectIds();
$directInventoryIsPromisor = $database->isPromisorObject($inventoryBlob->oid());

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
    'directInventoryObject' => $inventoryBlob->oid(),
    'directInventoryPack' => $inventoryWrite['promisorName'],
    'directInventoryKeep' => $inventoryWrite['keepName'],
    'directInventoryPackNames' => $directInventoryPackNames,
    'directInventoryObjectIds' => $directInventoryObjectIds,
    'directInventoryIsPromisor' => $directInventoryIsPromisor,
];
