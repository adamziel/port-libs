<?php

declare(strict_types=1);

use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\FetchFilterSpec;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PackBuildResult;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;
use PortLibs\Gitoxide\PromisorObjectResolver;
use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$writePromisorPackFixture = static function (): array {
    $fixture = require dirname(__DIR__) . '/fixtures/wordpress-pack-data.php';
    $gitDir = sys_get_temp_dir() . '/port-libs-git-promisor-' . bin2hex(random_bytes(4)) . '/.git';
    $packDir = $gitDir . '/objects/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create pack fixture directory: {$packDir}");
    }

    $basename = 'pack-' . $fixture['packChecksum'];
    file_put_contents($packDir . '/' . $basename . '.pack', $fixture['packBytes']);
    file_put_contents($packDir . '/' . $basename . '.idx', $fixture['indexBytes']);
    file_put_contents($packDir . '/' . $basename . '.promisor', "partial clone promisor pack\n");

    return [$gitDir, $fixture, $basename];
};

$writePromisorPackForObject = static function (string $gitDir, GitObject $object, string $promisorNote): string {
    $pack = PackBuilder::build([$object]);
    $packDir = $gitDir . '/objects/pack';
    $basename = 'pack-' . $pack->packChecksum();

    file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
    file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
    file_put_contents($packDir . '/' . $basename . '.promisor', $promisorNote);

    return $basename . '.promisor';
};

$writePromisorPackResult = static function (string $gitDir, PackBuildResult $pack, string $promisorNote): string {
    $packDir = $gitDir . '/objects/pack';
    $basename = 'pack-' . $pack->packChecksum();

    file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
    file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
    file_put_contents($packDir . '/' . $basename . '.promisor', $promisorNote);

    return $basename . '.promisor';
};

$writeOrphanPromisorIndex = static function (string $gitDir, GitObject $object, string $promisorNote): string {
    $pack = PackBuilder::build([$object]);
    $packDir = $gitDir . '/objects/pack';
    $basename = 'pack-' . $pack->packChecksum();

    file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
    file_put_contents($packDir . '/' . $basename . '.promisor', $promisorNote);

    return $basename . '.promisor';
};

$buildThinPromisorBlobs = static function (string $label): array {
    $stable = '';
    for ($i = 0; $i < 72; $i++) {
        $stable .= hash('sha1', "gitoxide-promisor-thin-base-{$label}-{$i}") . "\n";
    }

    return [
        new GitObject('blob', "wp-content blob base\n{$stable}status=draft\nchecksum=old\n"),
        new GitObject('blob', "wp-content blob base\n{$stable}status=publish\nchecksum=new\n"),
    ];
};

$writePromisorDeltaChain = static function (string $gitDir, int $deltaCount, string $label) use ($writePromisorPackForObject, $writePromisorPackResult): array {
    if ($deltaCount < 1) {
        throw new InvalidArgumentException('Promisor delta chain requires at least one delta');
    }

    $stable = '';
    for ($i = 0; $i < 96; $i++) {
        $stable .= hash('sha1', "gitoxide-promisor-deep-chain-{$label}-{$i}") . "\n";
    }

    $objects = [new GitObject('blob', "WordPress deep promisor base\n{$stable}step=00\n")];
    $packNames = [$writePromisorPackForObject($gitDir, $objects[0], "deep promisor delta chain base\n")];
    for ($i = 1; $i <= $deltaCount; $i++) {
        $object = new GitObject('blob', "WordPress deep promisor base\n{$stable}step=" . sprintf('%02d', $i) . "\n");
        $pack = PackBuilder::buildWithRefDeltas([$object], [$objects[$i - 1]]);
        if (!$pack->isThin()) {
            throw new RuntimeException("Expected deep promisor chain pack {$i} to be thin");
        }
        $packNames[] = $writePromisorPackResult($gitDir, $pack, "deep promisor delta chain {$i}\n");
        $objects[] = $object;
    }

    return [
        'objects' => $objects,
        'packNames' => $packNames,
        'target' => $objects[$deltaCount],
    ];
};

$writePromisorConfigFixture = static function (bool|string $promisor = true): string {
    $gitDir = sys_get_temp_dir() . '/port-libs-git-promisor-config-' . bin2hex(random_bytes(4)) . '/.git';
    $packDir = $gitDir . '/objects/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create promisor config fixture directory: {$packDir}");
    }

    $promisorValue = is_bool($promisor) ? ($promisor ? 'true' : 'false') : $promisor;
    file_put_contents($gitDir . '/config', <<<CFG
    [remote "origin"]
        url = https://git.example.test/wp-content.git
        promisor = {$promisorValue}
        partialCloneFilter = blob:none
    CFG);

    return $gitDir;
};

return [
    'parses common partial clone fetch filter specs' => static function (TestRunner $t): void {
        $blobNone = FetchFilterSpec::parse('blob:none');
        $t->same(FetchFilterSpec::BLOB_NONE, $blobNone->kind);
        $t->same('filter blob:none', $blobNone->requestArgument());
        $t->same(false, $blobNone->includesObject(new GitObject('blob', 'content')));
        $t->same(true, $blobNone->includesObject(new GitObject('commit', 'tree ' . str_repeat('0', 40))));

        $blobLimit = FetchFilterSpec::parse('blob:limit=4');
        $t->same(true, $blobLimit->includesObject(new GitObject('blob', 'abc')));
        $t->same(false, $blobLimit->includesObject(new GitObject('blob', 'abcd')));
        $t->same(1024, FetchFilterSpec::parse('blob:limit=1k')->limit);

        $treeDepth = FetchFilterSpec::treeDepth(1);
        $t->same(true, $treeDepth->includesObject(new GitObject('tree', ''), 0));
        $t->same(false, $treeDepth->includesObject(new GitObject('blob', 'nested'), 1));
        $t->same(false, FetchFilterSpec::treeDepth(0)->includesObject(new GitObject('tree', ''), 0));

        $sparse = FetchFilterSpec::parse('sparse:oid=FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $t->same(str_repeat('f', 40), $sparse->object);
        $t->same('sparse:oid=' . str_repeat('f', 40), (string) $sparse);
        $t->throws(InvalidArgumentException::class, static fn () => FetchFilterSpec::parse('blob:limit=-1'));
    },
    'fetch command accepts filter spec value objects' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nfetch=filter\n");
        $command = FetchCommand::createV2($capabilities);

        $command->filter(FetchFilterSpec::blobNone());
        $command->validate();

        $t->same([
            'thin-pack',
            'ofs-delta',
            'filter blob:none',
        ], $command->requestArguments());
    },
    'object database reports promisor pack state' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir, $fixture, $basename] = $writePromisorPackFixture();
        $database = new ObjectDatabase($gitDir);
        $expectedObjectIds = array_column($fixture['objects'], 'oid');
        sort($expectedObjectIds, SORT_STRING);
        $packedObject = $fixture['objects'][0]['oid'];
        $missingObject = str_repeat('f', 40);

        $t->same([$basename . '.promisor'], $database->promisorPackNames());
        $t->same(true, $database->hasPromisorPacks());
        $t->same($expectedObjectIds, $database->promisorObjectIds());
        $t->same(true, $database->isPromisorObject($packedObject));
        $t->same('promisor-present', $database->objectState($packedObject)['status']);
        $t->same('promised-missing', $database->objectState($missingObject)['status']);
        $t->throws(RuntimeException::class, static fn () => $database->read($missingObject));

        $emptyGitDir = sys_get_temp_dir() . '/port-libs-git-promisor-empty-' . bin2hex(random_bytes(4)) . '/.git';
        $emptyDatabase = new ObjectDatabase($emptyGitDir);
        $t->same(false, $emptyDatabase->hasPromisorPacks());
        $t->same('missing', $emptyDatabase->objectState($missingObject)['status']);
    },
    'object database treats promisor remote config as promised before first pack' => static function (TestRunner $t) use ($writePromisorConfigFixture): void {
        $gitDir = $writePromisorConfigFixture();
        $database = new ObjectDatabase($gitDir);
        $missingThemeBlob = new GitObject('blob', 'Config-only WordPress theme asset bytes');
        $missingThemeOid = $missingThemeBlob->oid();

        $t->same([[
            'name' => 'origin',
            'url' => 'https://git.example.test/wp-content.git',
            'partialCloneFilter' => 'blob:none',
        ]], $database->promisorRemotes());
        $t->same(false, $database->hasPromisorPacks());
        $t->same('promised-missing', $database->objectState($missingThemeOid)['status']);
        $t->throws(RuntimeException::class, static fn () => $database->read($missingThemeOid));
    },
    'object database hydrates config-only promisor remote objects through resolver' => static function (TestRunner $t) use ($writePromisorConfigFixture): void {
        $gitDir = $writePromisorConfigFixture();
        $mediaBlob = new GitObject('blob', 'Hydrated from remote.origin.promisor config');
        $mediaOid = $mediaBlob->oid();
        $resolver = new class([$mediaOid => $mediaBlob]) implements PromisorObjectResolver {
            public array $requests = [];

            public function __construct(private readonly array $objects)
            {
            }

            public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
            {
                $this->requests[] = $oid;

                return $this->objects[$oid] ?? null;
            }
        };
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same('promised-missing', $database->objectState($mediaOid)['status']);
        $t->same($mediaBlob->body, $database->read($mediaOid)->body);
        $t->same([$mediaOid], $resolver->requests);
        $t->same('present', $database->objectState($mediaOid)['status']);
        $t->same($mediaBlob->body, (new ObjectDatabase($gitDir))->read($mediaOid)->body);
    },
    'object database ignores false promisor remote config for promised state' => static function (TestRunner $t) use ($writePromisorConfigFixture): void {
        $gitDir = $writePromisorConfigFixture(false);
        $database = new ObjectDatabase($gitDir);
        $missingOid = (new GitObject('blob', 'Not promised when remote promisor is false'))->oid();

        $t->same([], $database->promisorRemotes());
        $t->same('missing', $database->objectState($missingOid)['status']);
        $t->throws(RuntimeException::class, static fn () => $database->read($missingOid));
    },
    'object database hydrates numeric promisor remote config booleans like gix-config' => static function (TestRunner $t) use ($writePromisorConfigFixture): void {
        $gitDir = $writePromisorConfigFixture('2');
        $database = new ObjectDatabase($gitDir);
        $templateBlob = new GitObject('blob', 'Hydrated from numeric remote.origin.promisor config');
        $templateOid = $templateBlob->oid();
        $resolver = new class([$templateOid => $templateBlob]) implements PromisorObjectResolver {
            public array $requests = [];

            public function __construct(private readonly array $objects)
            {
            }

            public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
            {
                $this->requests[] = $oid;

                return $this->objects[$oid] ?? null;
            }
        };

        $t->same([[
            'name' => 'origin',
            'url' => 'https://git.example.test/wp-content.git',
            'partialCloneFilter' => 'blob:none',
        ]], $database->promisorRemotes());
        $t->same('promised-missing', $database->objectState($templateOid)['status']);

        $hydratingDatabase = $database->withPromisorResolver($resolver);
        $t->same($templateBlob->body, $hydratingDatabase->read($templateOid)->body);
        $t->same([$templateOid], $resolver->requests);
        $t->same('present', $hydratingDatabase->objectState($templateOid)['status']);

        $zeroGitDir = $writePromisorConfigFixture('0');
        $zeroDatabase = new ObjectDatabase($zeroGitDir);
        $notPromisedOid = (new GitObject('blob', 'remote.origin.promisor numeric zero is false'))->oid();
        $t->same([], $zeroDatabase->promisorRemotes());
        $t->same('missing', $zeroDatabase->objectState($notPromisedOid)['status']);
    },
    'object database can lazily resolve promised missing objects into loose storage' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $missingMediaBlob = new GitObject('blob', 'Lazily fetched WordPress media attachment bytes');
        $missingMediaOid = $missingMediaBlob->oid();
        $resolver = new class([$missingMediaOid => $missingMediaBlob]) implements PromisorObjectResolver {
            public array $requests = [];

            public function __construct(private readonly array $objects)
            {
            }

            public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
            {
                $this->requests[] = $oid;

                return $this->objects[$oid] ?? null;
            }
        };
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same('promised-missing', $database->objectState($missingMediaOid)['status']);
        $t->same($missingMediaBlob->body, $database->read($missingMediaOid)->body);
        $t->same([$missingMediaOid], $resolver->requests);
        $t->same('present', $database->objectState($missingMediaOid)['status']);
        $t->same($missingMediaBlob->body, (new ObjectDatabase($gitDir))->read($missingMediaOid)->body);
    },
    'object database refreshes pack indexes after promisor resolver hydrates on disk' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $missingMediaBlob = new GitObject('blob', 'Hydrated into a fresh promisor pack after lazy fetch');
        $missingMediaOid = $missingMediaBlob->oid();
        $resolver = new class($missingMediaBlob, $gitDir) implements PromisorObjectResolver {
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
                file_put_contents($packDir . '/' . $basename . '.promisor', "lazy media hydration\n");

                return null;
            }
        };
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same('promised-missing', $database->objectState($missingMediaOid)['status']);
        $t->same($missingMediaBlob->body, $database->read($missingMediaOid)->body);
        $t->same([$missingMediaOid], $resolver->requests);
        $t->same('promisor-present', $database->objectState($missingMediaOid)['status']);
        $t->same('pack', $database->readHeader($missingMediaOid)['source']);
        $t->same(true, in_array($resolver->packName, $database->promisorPackNames(), true));
        $t->same($missingMediaBlob->body, (new ObjectDatabase($gitDir))->read($missingMediaOid)->body);
    },
    'object database refreshes promisor packs when resolver returns object after disk hydration' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $returnedThemeBlob = new GitObject('blob', 'Resolver returned decoded theme bytes and wrote a promisor pack');
        $returnedThemeOid = $returnedThemeBlob->oid();
        $resolver = new class($returnedThemeBlob, $gitDir) implements PromisorObjectResolver {
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
                file_put_contents($packDir . '/' . $basename . '.promisor', "returned object hydration\n");

                return $this->object;
            }
        };
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same('promised-missing', $database->objectState($returnedThemeOid)['status']);
        $t->same($returnedThemeBlob->body, $database->read($returnedThemeOid)->body);
        $t->same([$returnedThemeOid], $resolver->requests);
        $t->same(true, in_array($resolver->packName, $database->promisorPackNames(), true));
        $t->same('promisor-present', $database->objectState($returnedThemeOid)['status']);
        $t->same('pack', $database->readHeader($returnedThemeOid)['source']);
        $t->same($returnedThemeBlob->body, (new ObjectDatabase($gitDir))->read($returnedThemeOid)->body);
    },
    'object database refreshes headers after promisor resolver hydrates a pack' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $missingConfigBlob = new GitObject('blob', 'Hydrated theme config bytes');
        $missingConfigOid = $missingConfigBlob->oid();
        $resolver = new class($missingConfigBlob, $gitDir) implements PromisorObjectResolver {
            public array $requests = [];

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

                file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
                file_put_contents($packDir . '/' . $basename . '.idx', $pack->indexBytes());
                file_put_contents($packDir . '/' . $basename . '.promisor', "lazy config hydration\n");

                return null;
            }
        };
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same([
            'type' => 'blob',
            'size' => strlen($missingConfigBlob->body),
            'source' => 'pack',
        ], $database->readHeader($missingConfigOid));
        $t->same([$missingConfigOid], $resolver->requests);
        $t->same('promisor-present', $database->objectState($missingConfigOid)['status']);
        $t->same($missingConfigBlob->body, $database->read($missingConfigOid)->body);
        $t->same([$missingConfigOid], $resolver->requests);
    },
    'object database contains refreshes after external promisor pack hydration' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject): void {
        [$gitDir] = $writePromisorPackFixture();
        $hydratedThemeBlob = new GitObject('blob', 'Externally hydrated WordPress theme asset bytes');
        $hydratedThemeOid = $hydratedThemeBlob->oid();
        $database = new ObjectDatabase($gitDir);

        $t->same('promised-missing', $database->objectState($hydratedThemeOid)['status']);
        $t->same(1, count($database->promisorPackNames()));

        $hydrationPack = $writePromisorPackForObject($gitDir, $hydratedThemeBlob, "external promisor hydration\n");

        $t->same(true, $database->contains($hydratedThemeOid));
        $t->same('promisor-present', $database->objectState($hydratedThemeOid)['status']);
        $t->same(true, in_array($hydrationPack, $database->promisorPackNames(), true));
        $t->same($hydratedThemeBlob->body, $database->read($hydratedThemeOid)->body);
        $t->same('pack', $database->readHeader($hydratedThemeOid)['source']);
    },
    'object database prefix lookup refreshes after external promisor pack hydration' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject): void {
        [$gitDir] = $writePromisorPackFixture();
        $hydratedTemplateBlob = new GitObject('blob', 'Externally hydrated block template bytes');
        $hydratedTemplateOid = $hydratedTemplateBlob->oid();
        $prefix = strtoupper(substr($hydratedTemplateOid, 0, 12));
        $database = new ObjectDatabase($gitDir);

        $t->same('missing', $database->lookupPrefix($prefix)['status']);

        $hydrationPack = $writePromisorPackForObject($gitDir, $hydratedTemplateBlob, "external prefix hydration\n");
        $found = $database->lookupPrefix($prefix);

        $t->same('found', $found['status']);
        $t->same($hydratedTemplateOid, $found['oid']);
        $t->same(true, in_array($hydrationPack, $database->promisorPackNames(), true));
        $t->same('promisor-present', $database->objectState($hydratedTemplateOid)['status']);
        $t->same($hydratedTemplateBlob->body, $database->read($hydratedTemplateOid)->body);
    },
    'object database iterates refreshed promisor packs after external hydration' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject): void {
        [$gitDir] = $writePromisorPackFixture();
        $hydratedPatternBlob = new GitObject('blob', 'Externally hydrated block pattern bytes');
        $hydratedPatternOid = $hydratedPatternBlob->oid();
        $database = new ObjectDatabase($gitDir);
        $beforeIds = $database->objectIds();
        $beforeCount = $database->packedObjectCount();

        $t->same(false, in_array($hydratedPatternOid, $beforeIds, true));

        $hydrationPack = $writePromisorPackForObject($gitDir, $hydratedPatternBlob, "external iteration hydration\n");
        $afterIds = $database->objectIds();

        $t->same(true, in_array($hydratedPatternOid, $afterIds, true));
        $t->same(count($beforeIds) + 1, count($afterIds));
        $t->same($beforeCount + 1, $database->packedObjectCount());
        $t->same(true, in_array($hydrationPack, $database->promisorPackNames(), true));
        $t->same('promisor-present', $database->objectState($hydratedPatternOid)['status']);
        $t->same($hydratedPatternBlob->body, $database->read($hydratedPatternOid)->body);
    },
    'object database refreshes promisor inventory after external hydration' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject): void {
        [$gitDir] = $writePromisorPackFixture();
        $hydratedManifestBlob = new GitObject('blob', 'Externally hydrated deployment manifest bytes');
        $hydratedManifestOid = $hydratedManifestBlob->oid();
        $database = new ObjectDatabase($gitDir);
        $beforePacks = $database->promisorPackNames();
        $beforeObjectIds = $database->promisorObjectIds();

        $t->same(1, count($beforePacks));
        $t->same(false, in_array($hydratedManifestOid, $beforeObjectIds, true));
        $t->same(false, $database->isPromisorObject($hydratedManifestOid));

        $hydrationPack = $writePromisorPackForObject($gitDir, $hydratedManifestBlob, "external promisor inventory hydration\n");
        $afterPacks = $database->promisorPackNames();
        $afterObjectIds = $database->promisorObjectIds();

        $t->same(count($beforePacks) + 1, count($afterPacks));
        $t->same(true, in_array($hydrationPack, $afterPacks, true));
        $t->same(true, in_array($hydratedManifestOid, $afterObjectIds, true));
        $t->same(true, $database->hasPromisorPacks());
        $t->same(true, $database->isPromisorObject($hydratedManifestOid));
        $t->same('promisor-present', $database->objectState($hydratedManifestOid)['status']);
    },
    'object database writes received promisor pack bundles with keep sidecars' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $database = new ObjectDatabase($gitDir);
        $hydratedPluginBlob = new GitObject('blob', 'Received filtered pack for a WordPress plugin asset');
        $hydratedPluginOid = $hydratedPluginBlob->oid();
        $pack = PackBuilder::build([$hydratedPluginBlob]);
        $beforePacks = $database->promisorPackNames();

        $write = $database->writePromisorPackBundle($pack, "received filtered pack\n");
        $packDir = $gitDir . '/objects/pack';

        $t->same('pack-' . $pack->packChecksum() . '.pack', $write['packName']);
        $t->same('pack-' . $pack->packChecksum() . '.idx', $write['indexName']);
        $t->same('pack-' . $pack->packChecksum() . '.promisor', $write['promisorName']);
        $t->same('pack-' . $pack->packChecksum() . '.keep', $write['keepName']);
        $t->same($pack->packChecksum(), $write['packChecksum']);
        $t->same($pack->indexChecksum(), $write['indexChecksum']);
        $t->same([$hydratedPluginOid], $write['objectIds']);
        $t->same(1, $write['objectCount']);
        $t->same(false, $write['alreadyPresent']);
        $t->same(true, is_file($packDir . '/' . $write['packName']));
        $t->same(true, is_file($packDir . '/' . $write['indexName']));
        $t->same(true, is_file($packDir . '/' . $write['promisorName']));
        $t->same(true, is_file($packDir . '/' . $write['keepName']));
        $t->same("received filtered pack\n", file_get_contents($packDir . '/' . $write['promisorName']));
        $t->same(count($beforePacks) + 1, count($database->promisorPackNames()));
        $t->same('promisor-present', $database->objectState($hydratedPluginOid)['status']);
        $t->same('pack', $database->readHeader($hydratedPluginOid)['source']);
        $t->same($hydratedPluginBlob->body, $database->read($hydratedPluginOid)->body);

        $duplicate = $database->writePromisorPackBundle($pack, "duplicate filtered pack marker\n");
        $t->same(true, $duplicate['alreadyPresent']);
        $t->same(null, $duplicate['keepName']);
        $t->same($write['packName'], $duplicate['packName']);
        $t->same($write['indexName'], $duplicate['indexName']);
        $t->same($write['promisorName'], $duplicate['promisorName']);
        $t->same("duplicate filtered pack marker\n", file_get_contents($packDir . '/' . $write['promisorName']));
        $t->same(count($beforePacks) + 1, count($database->promisorPackNames()));
    },
    'object database keeps interrupted promisor pack resume protected while writing a missing index' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $database = new ObjectDatabase($gitDir);
        $resumedAssetBlob = new GitObject('blob', 'Interrupted filtered pack resumes for a WordPress asset');
        $pack = PackBuilder::build([$resumedAssetBlob]);
        $packDir = $gitDir . '/objects/pack';
        $basename = 'pack-' . $pack->packChecksum();

        file_put_contents($packDir . '/' . $basename . '.pack', $pack->packBytes());
        $t->same(true, is_file($packDir . '/' . $basename . '.pack'));
        $t->same(false, is_file($packDir . '/' . $basename . '.idx'));
        $t->same(false, is_file($packDir . '/' . $basename . '.promisor'));
        $t->same(false, is_file($packDir . '/' . $basename . '.keep'));

        $write = $database->writePromisorPackBundle($pack, "resumed interrupted filtered pack\n");

        $t->same(false, $write['alreadyPresent']);
        $t->same($basename . '.pack', $write['packName']);
        $t->same($basename . '.idx', $write['indexName']);
        $t->same($basename . '.promisor', $write['promisorName']);
        $t->same($basename . '.keep', $write['keepName']);
        $t->same(true, is_file($packDir . '/' . $write['packName']));
        $t->same(true, is_file($packDir . '/' . $write['indexName']));
        $t->same(true, is_file($packDir . '/' . $write['promisorName']));
        $t->same(true, is_file($packDir . '/' . $write['keepName']));
        $t->same([$resumedAssetBlob->oid()], $write['objectIds']);
        $t->same('promisor-present', $database->objectState($resumedAssetBlob->oid())['status']);
        $t->same('pack', $database->readHeader($resumedAssetBlob->oid())['source']);
        $t->same($resumedAssetBlob->body, $database->read($resumedAssetBlob->oid())->body);

        $duplicate = $database->writePromisorPackBundle($pack, "resumed pack duplicate marker\n");
        $t->same(true, $duplicate['alreadyPresent']);
        $t->same(null, $duplicate['keepName']);
    },
    'object database ignores orphan promisor indexes while hydrating later packs' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject, $writeOrphanPromisorIndex): void {
        [$gitDir] = $writePromisorPackFixture();
        $database = new ObjectDatabase($gitDir);
        $orphanBlob = new GitObject('blob', 'Interrupted promisor index without pack bytes');
        $hydratedBlob = new GitObject('blob', 'Hydrated WordPress asset after interrupted promisor index');
        $orphanPromisor = $writeOrphanPromisorIndex($gitDir, $orphanBlob, "interrupted promisor index without pack bytes\n");
        $hydrationPromisor = $writePromisorPackForObject($gitDir, $hydratedBlob, "valid promisor hydration after orphan index\n");

        $promisorPacks = $database->promisorPackNames();
        $promisorObjectIds = $database->promisorObjectIds();

        $t->same(false, in_array($orphanPromisor, $promisorPacks, true));
        $t->same(false, in_array($orphanBlob->oid(), $promisorObjectIds, true));
        $t->same(false, $database->isPromisorObject($orphanBlob->oid()));
        $t->same('promised-missing', $database->objectState($orphanBlob->oid())['status']);
        $t->same(true, in_array($hydrationPromisor, $promisorPacks, true));
        $t->same(true, in_array($hydratedBlob->oid(), $promisorObjectIds, true));
        $t->same('promisor-present', $database->objectState($hydratedBlob->oid())['status']);
        $t->same('pack', $database->readHeader($hydratedBlob->oid())['source']);
        $t->same($hydratedBlob->body, $database->read($hydratedBlob->oid())->body);
    },
    'object database writes promisor thin pack bundles using alternate bases' => static function (TestRunner $t) use ($writePromisorPackFixture, $buildThinPromisorBlobs): void {
        [$gitDir] = $writePromisorPackFixture();
        [$baseBlob, $targetBlob] = $buildThinPromisorBlobs('alternate-base');
        $baseOid = $baseBlob->oid();
        $targetOid = $targetBlob->oid();
        $alternateObjects = sys_get_temp_dir() . '/port-libs-git-promisor-alternate-' . bin2hex(random_bytes(4)) . '/objects';
        $primaryInfo = $gitDir . '/objects/info';

        if (!mkdir($alternateObjects . '/info', 0777, true) && !is_dir($alternateObjects . '/info')) {
            throw new RuntimeException("Unable to create alternate objects directory: {$alternateObjects}");
        }
        if (!is_dir($primaryInfo) && !mkdir($primaryInfo, 0777, true) && !is_dir($primaryInfo)) {
            throw new RuntimeException("Unable to create objects info directory: {$primaryInfo}");
        }
        file_put_contents($primaryInfo . '/alternates', $alternateObjects . "\n");

        $writtenBaseOid = LooseObjectStore::fromObjectsDirectory($alternateObjects)->write($baseBlob);
        $thinPack = PackBuilder::buildWithRefDeltas([$targetBlob], [$baseBlob]);
        $database = new ObjectDatabase($gitDir);

        $t->same($baseOid, $writtenBaseOid);
        $t->same(true, $thinPack->isThin());

        $write = $database->writePromisorPackBundle($thinPack, "thin promisor pack with alternate base\n");
        $alternateRealPath = realpath($alternateObjects);

        $t->same(true, $alternateRealPath !== false);
        $t->same(true, in_array($alternateRealPath, $database->alternateObjectDirectories(), true));
        $t->same([$targetOid], $write['objectIds']);
        $t->same(1, $write['objectCount']);
        $t->same(true, str_ends_with($write['keepName'] ?? '', '.keep'));
        $t->same('present', $database->objectState($baseOid)['status']);
        $t->same('promisor-present', $database->objectState($targetOid)['status']);
        $t->same([
            'type' => 'blob',
            'size' => strlen($baseBlob->body),
            'source' => 'loose',
        ], $database->readHeader($baseOid));
        $t->same([
            'type' => 'blob',
            'size' => strlen($targetBlob->body),
            'source' => 'pack',
        ], $database->readHeader($targetOid));
        $t->same($baseBlob->body, $database->read($baseOid)->body);
        $t->same($targetBlob->body, $database->read($targetOid)->body);
        $t->same(true, in_array($write['promisorName'], $database->promisorPackNames(), true));
    },
    'object database rejects promisor thin pack bundles with unresolved external bases' => static function (TestRunner $t) use ($writePromisorPackFixture, $buildThinPromisorBlobs): void {
        [$gitDir] = $writePromisorPackFixture();
        [$missingBaseBlob, $targetBlob] = $buildThinPromisorBlobs('unresolved-external-base');
        $thinPack = PackBuilder::buildWithRefDeltas([$targetBlob], [$missingBaseBlob]);
        $database = new ObjectDatabase($gitDir);
        $packDir = $gitDir . '/objects/pack';
        $basename = 'pack-' . $thinPack->packChecksum();
        $exceptionMessage = null;

        $t->same(true, $thinPack->isThin());

        try {
            $database->writePromisorPackBundle($thinPack, "thin promisor pack with missing external base\n");
        } catch (RuntimeException $exception) {
            $exceptionMessage = $exception->getMessage();
        }

        $t->true($exceptionMessage !== null, 'Promisor thin pack bundle should reject missing external REF_DELTA bases');
        $t->contains('external REF_DELTA base not found', (string) $exceptionMessage);
        $t->same(false, is_file($packDir . '/' . $basename . '.keep'));
        $t->same(false, is_file($packDir . '/' . $basename . '.pack'));
        $t->same(false, is_file($packDir . '/' . $basename . '.idx'));
        $t->same(false, is_file($packDir . '/' . $basename . '.promisor'));
        $t->same('promised-missing', $database->objectState($targetBlob->oid())['status']);
    },
    'object database repairs received promisor thin pack bundles with resolver hydrated bases' => static function (TestRunner $t) use ($writePromisorPackFixture, $buildThinPromisorBlobs): void {
        [$gitDir] = $writePromisorPackFixture();
        [$baseBlob, $targetBlob] = $buildThinPromisorBlobs('resolver-repaired-bundle');
        $baseOid = $baseBlob->oid();
        $targetOid = $targetBlob->oid();
        $thinPack = PackBuilder::buildWithRefDeltas([$targetBlob], [$baseBlob]);
        $resolver = new class($baseBlob) implements PromisorObjectResolver {
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
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same(true, $thinPack->isThin());
        $t->same('promised-missing', $database->objectState($baseOid)['status']);

        $write = $database->writePromisorPackBundle(
            $thinPack,
            "resolver repaired promisor thin pack\n",
            true,
            true
        );
        $packDir = $gitDir . '/objects/pack';
        $storedPack = PackData::open($packDir . '/' . $write['packName']);
        $storedIndex = PackIndex::open($packDir . '/' . $write['indexName']);
        $targetHeader = $storedPack->readObjectHeader($storedIndex, $targetOid);

        $t->same([$baseOid], $resolver->requests);
        $t->same(false, $write['packChecksum'] === $thinPack->packChecksum());
        $t->same([$baseOid, $targetOid], $write['objectIds']);
        $t->same(2, $write['objectCount']);
        $t->same(2, $storedPack->count());
        $t->same(2, $storedIndex->count());
        $t->same('blob', $targetHeader['type']);
        $t->same(1, $targetHeader['numDeltas']);
        $t->same($baseBlob->body, $storedPack->readObject($storedIndex, $baseOid)->body);
        $t->same($targetBlob->body, $storedPack->readObject($storedIndex, $targetOid)->body);
        $t->same('promisor-present', $database->objectState($baseOid)['status']);
        $t->same('promisor-present', $database->objectState($targetOid)['status']);
        $t->same('pack', (new ObjectDatabase($gitDir))->readHeader($targetOid)['source']);
        $t->same($targetBlob->body, (new ObjectDatabase($gitDir))->read($targetOid)->body);
    },
    'refresh-disabled handle writes promisor pack bundle without refreshing its cached inventory' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $staleDatabase = (new ObjectDatabase($gitDir))->withObjectStorageRefreshDisabled();
        $hydratedThemeBlob = new GitObject('blob', 'Received filtered pack hidden from a refresh-never handle');
        $hydratedThemeOid = $hydratedThemeBlob->oid();
        $pack = PackBuilder::build([$hydratedThemeBlob]);

        $t->same(1, count($staleDatabase->promisorPackNames()));
        $write = $staleDatabase->writePromisorPackBundle($pack, "refresh-never received filtered pack\n");

        $t->same(false, $staleDatabase->objectStorageRefreshesOnMiss());
        $t->same(false, in_array($write['promisorName'], $staleDatabase->promisorPackNames(), true));
        $t->same(false, $staleDatabase->contains($hydratedThemeOid));
        $t->same('promised-missing', $staleDatabase->objectState($hydratedThemeOid)['status']);

        $freshDatabase = $staleDatabase->withObjectStorageRefreshEnabled();
        $t->same(true, in_array($write['promisorName'], $freshDatabase->promisorPackNames(), true));
        $t->same('promisor-present', $freshDatabase->objectState($hydratedThemeOid)['status']);
        $t->same($hydratedThemeBlob->body, $freshDatabase->read($hydratedThemeOid)->body);
    },
    'object database refresh-disabled handle preserves promised missing state after external promisor hydration' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject): void {
        [$gitDir] = $writePromisorPackFixture();
        $hydratedBlockBlob = new GitObject('blob', 'Externally hydrated block bytes hidden from a refresh-disabled handle');
        $hydratedBlockOid = $hydratedBlockBlob->oid();
        $prefix = strtoupper(substr($hydratedBlockOid, 0, 12));
        $staleDatabase = (new ObjectDatabase($gitDir))->withObjectStorageRefreshDisabled();

        $t->same(false, $staleDatabase->objectStorageRefreshesOnMiss());
        $t->same('promised-missing', $staleDatabase->objectState($hydratedBlockOid)['status']);
        $t->same('missing', $staleDatabase->lookupPrefix($prefix)['status']);
        $t->same(1, count($staleDatabase->promisorPackNames()));

        $hydrationPack = $writePromisorPackForObject($gitDir, $hydratedBlockBlob, "external refresh-never hydration\n");

        $t->same(false, $staleDatabase->contains($hydratedBlockOid));
        $t->same('missing', $staleDatabase->lookupPrefix($prefix)['status']);
        $withCandidates = $staleDatabase->lookupPrefix($prefix, true);
        $t->same('missing', $withCandidates['status']);
        $t->same([], $withCandidates['candidates']);
        $t->same('promised-missing', $staleDatabase->objectState($hydratedBlockOid)['status']);
        $t->same(false, in_array($hydrationPack, $staleDatabase->promisorPackNames(), true));

        $freshDatabase = new ObjectDatabase($gitDir);
        $t->same(true, $freshDatabase->objectStorageRefreshesOnMiss());
        $t->same(true, $freshDatabase->contains($hydratedBlockOid));
        $t->same('found', $freshDatabase->lookupPrefix($prefix)['status']);
        $t->same('promisor-present', $freshDatabase->objectState($hydratedBlockOid)['status']);
        $t->same(true, in_array($hydrationPack, $freshDatabase->promisorPackNames(), true));
    },
    'object database refresh-disabled handle does not consume resolver side-effect promisor packs' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $sideEffectBlob = new GitObject('blob', 'Resolver wrote a pack but refresh-never kept the old snapshot');
        $sideEffectOid = $sideEffectBlob->oid();
        $resolver = new class($sideEffectBlob, $gitDir) implements PromisorObjectResolver {
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
                file_put_contents($packDir . '/' . $basename . '.promisor', "refresh-never resolver side effect\n");

                return null;
            }
        };
        $staleDatabase = (new ObjectDatabase($gitDir))
            ->withPromisorResolver($resolver)
            ->withObjectStorageRefreshDisabled();

        $t->same('promised-missing', $staleDatabase->objectState($sideEffectOid)['status']);
        $t->throws(RuntimeException::class, static fn () => $staleDatabase->read($sideEffectOid));
        $t->same([$sideEffectOid], $resolver->requests);
        $t->same('promised-missing', $staleDatabase->objectState($sideEffectOid)['status']);
        $t->same(false, in_array($resolver->packName, $staleDatabase->promisorPackNames(), true));

        $refreshedDatabase = $staleDatabase->withObjectStorageRefreshEnabled();
        $t->same(true, $refreshedDatabase->objectStorageRefreshesOnMiss());
        $t->same($sideEffectBlob->body, $refreshedDatabase->read($sideEffectOid)->body);
        $t->same('promisor-present', $refreshedDatabase->objectState($sideEffectOid)['status']);
        $t->same(true, in_array($resolver->packName, $refreshedDatabase->promisorPackNames(), true));
    },
    'object database refresh-disabled handle persists resolver-returned objects without consuming side-effect packs' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $returnedBlob = new GitObject('blob', 'Resolver returned bytes while writing a hidden refresh-never promisor pack');
        $returnedOid = $returnedBlob->oid();
        $resolver = new class($returnedBlob, $gitDir) implements PromisorObjectResolver {
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
                file_put_contents($packDir . '/' . $basename . '.promisor', "refresh-never returned-object side effect\n");

                return $this->object;
            }
        };
        $staleDatabase = (new ObjectDatabase($gitDir))
            ->withPromisorResolver($resolver)
            ->withObjectStorageRefreshDisabled();

        $t->same(false, $staleDatabase->objectStorageRefreshesOnMiss());
        $t->same('promised-missing', $staleDatabase->objectState($returnedOid)['status']);
        $t->same(1, count($staleDatabase->promisorPackNames()));

        $resolved = $staleDatabase->read($returnedOid);

        $t->same($returnedBlob->body, $resolved->body);
        $t->same([$returnedOid], $resolver->requests);
        $t->same('present', $staleDatabase->objectState($returnedOid)['status']);
        $t->same('loose', $staleDatabase->readHeader($returnedOid)['source']);
        $t->same(false, in_array($resolver->packName, $staleDatabase->promisorPackNames(), true));

        $freshDatabase = new ObjectDatabase($gitDir);
        $t->same('promisor-present', $freshDatabase->objectState($returnedOid)['status']);
        $t->same('pack', $freshDatabase->readHeader($returnedOid)['source']);
        $t->same(true, in_array($resolver->packName, $freshDatabase->promisorPackNames(), true));
    },
    'object database resolves promisor thin pack deltas from loose base objects' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackResult, $buildThinPromisorBlobs): void {
        [$gitDir] = $writePromisorPackFixture();
        [$baseBlob, $targetBlob] = $buildThinPromisorBlobs('loose-base');
        $baseOid = (new LooseObjectStore($gitDir))->write($baseBlob);
        $thinPack = PackBuilder::buildWithRefDeltas([$targetBlob], [$baseBlob]);

        $t->same(true, $thinPack->isThin());
        $packName = $writePromisorPackResult($gitDir, $thinPack, "thin promisor delta with loose base\n");
        $database = new ObjectDatabase($gitDir);

        $t->same($baseBlob->oid(), $baseOid);
        $t->same('promisor-present', $database->objectState($targetBlob->oid())['status']);
        $t->same($targetBlob->body, $database->read($targetBlob->oid())->body);
        $t->same([
            'type' => 'blob',
            'size' => strlen($targetBlob->body),
            'source' => 'pack',
        ], $database->readHeader($targetBlob->oid()));
        $t->same(true, in_array($packName, $database->promisorPackNames(), true));
    },
    'object database resolves promisor thin pack deltas from another promisor pack' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackForObject, $writePromisorPackResult, $buildThinPromisorBlobs): void {
        [$gitDir] = $writePromisorPackFixture();
        [$baseBlob, $targetBlob] = $buildThinPromisorBlobs('cross-pack-base');
        $baseOid = $baseBlob->oid();
        $targetOid = $targetBlob->oid();
        $basePackName = $writePromisorPackForObject($gitDir, $baseBlob, "cross-pack promisor delta base\n");
        $thinPack = PackBuilder::buildWithRefDeltas([$targetBlob], [$baseBlob]);

        $t->same(true, $thinPack->isThin());
        $targetPackName = $writePromisorPackResult($gitDir, $thinPack, "cross-pack promisor thin delta target\n");
        $database = new ObjectDatabase($gitDir);

        $t->same('promisor-present', $database->objectState($baseOid)['status']);
        $t->same('promisor-present', $database->objectState($targetOid)['status']);
        $t->same([
            'type' => 'blob',
            'size' => strlen($targetBlob->body),
            'source' => 'pack',
        ], $database->readHeader($targetOid));
        $t->same($targetBlob->body, $database->read($targetOid)->body);
        $t->same([
            'type' => 'blob',
            'size' => strlen($baseBlob->body),
            'source' => 'pack',
        ], $database->readHeader($baseOid));
        $t->same($baseBlob->body, $database->read($baseOid)->body);
        $promisorPacks = $database->promisorPackNames();
        $t->same(true, in_array($basePackName, $promisorPacks, true));
        $t->same(true, in_array($targetPackName, $promisorPacks, true));
    },
    'object database hydrates promisor thin pack delta bases through resolver' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorPackResult, $buildThinPromisorBlobs): void {
        [$gitDir] = $writePromisorPackFixture();
        [$baseBlob, $targetBlob] = $buildThinPromisorBlobs('resolver-base');
        $baseOid = $baseBlob->oid();
        $targetOid = $targetBlob->oid();
        $thinPack = PackBuilder::buildWithRefDeltas([$targetBlob], [$baseBlob]);
        $resolver = new class($baseBlob) implements PromisorObjectResolver {
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

        $t->same(true, $thinPack->isThin());
        $writePromisorPackResult($gitDir, $thinPack, "thin promisor delta with resolver base\n");
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->same('promised-missing', $database->objectState($baseOid)['status']);
        $t->same('promisor-present', $database->objectState($targetOid)['status']);
        $t->same([
            'type' => 'blob',
            'size' => strlen($targetBlob->body),
            'source' => 'pack',
        ], $database->readHeader($targetOid));
        $t->same([$baseOid], $resolver->requests);
        $t->same($targetBlob->body, $database->read($targetOid)->body);
        $t->same([$baseOid], $resolver->requests);
        $t->same('present', $database->objectState($baseOid)['status']);
        $t->same('promisor-present', $database->objectState($targetOid)['status']);
    },
    'object database rejects promisor external delta chains past the gix recursion bound' => static function (TestRunner $t) use ($writePromisorPackFixture, $writePromisorDeltaChain): void {
        [$withinBoundGitDir] = $writePromisorPackFixture();
        $withinBoundChain = $writePromisorDeltaChain($withinBoundGitDir, 32, 'within-recursion-bound');
        $withinBoundDatabase = new ObjectDatabase($withinBoundGitDir);
        $withinBoundTargetOid = $withinBoundChain['target']->oid();

        $t->same($withinBoundChain['target']->body, $withinBoundDatabase->read($withinBoundTargetOid)->body);
        $t->same([
            'type' => 'blob',
            'size' => strlen($withinBoundChain['target']->body),
            'source' => 'pack',
        ], $withinBoundDatabase->readHeader($withinBoundTargetOid));

        [$tooDeepGitDir] = $writePromisorPackFixture();
        $chain = $writePromisorDeltaChain($tooDeepGitDir, 40, 'recursion-bound');
        $database = new ObjectDatabase($tooDeepGitDir);
        $targetOid = $chain['target']->oid();

        $t->same('promisor-present', $database->objectState($targetOid)['status']);

        $headerGuard = null;
        try {
            $database->readHeader($targetOid);
        } catch (RuntimeException $exception) {
            $headerGuard = $exception->getMessage();
        }
        $t->true($headerGuard !== null, 'Deep promisor delta header lookup should hit the recursion guard');
        $t->contains('REF_DELTA external base recursion limit', $headerGuard);

        $readGuard = null;
        try {
            $database->read($targetOid);
        } catch (RuntimeException $exception) {
            $readGuard = $exception->getMessage();
        }
        $t->true($readGuard !== null, 'Deep promisor delta object lookup should hit the recursion guard');
        $t->contains('REF_DELTA external base recursion limit', $readGuard);
    },
    'object database rejects promisor resolver object id mismatches' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir] = $writePromisorPackFixture();
        $requestedOid = str_repeat('f', 40);
        $resolver = new class implements PromisorObjectResolver {
            public function resolvePromisedObject(string $oid, ObjectDatabase $database): ?GitObject
            {
                return new GitObject('blob', 'wrong promised object');
            }
        };
        $database = (new ObjectDatabase($gitDir))->withPromisorResolver($resolver);

        $t->throws(RuntimeException::class, static fn () => $database->read($requestedOid));
    },
    'wordpress blobless partial clone fixture keeps missing media object promised' => static function (TestRunner $t) use ($writePromisorPackFixture): void {
        [$gitDir, $fixture] = $writePromisorPackFixture();
        $database = new ObjectDatabase($gitDir);
        $looseStore = new LooseObjectStore($gitDir);
        $packedContentOid = $fixture['objects'][1]['oid'];
        $missingMediaBlob = new GitObject('blob', 'Large media bytes intentionally omitted by blob:none');
        $treeObject = (new Tree([
            new TreeEntry('100644', 'wp-posts.txt', $packedContentOid),
            new TreeEntry('100644', 'hero.jpg', $missingMediaBlob->oid()),
        ]))->toObject();
        $treeOid = $looseStore->write($treeObject);

        $tree = Tree::fromObject($database->read($treeOid));
        $mediaEntry = $tree->entryNamed('hero.jpg');

        $t->same('promisor-present', $database->objectState($packedContentOid)['status']);
        $t->same('promised-missing', $database->objectState($mediaEntry?->oid ?? str_repeat('0', 40))['status']);
        $t->same(false, FetchFilterSpec::blobNone()->includesObject($missingMediaBlob));
    },
    'wordpress lazy promisor example reports external pack hydration refresh' => static function (TestRunner $t): void {
        $summary = require dirname(__DIR__) . '/examples/wordpress-lazy-promisor-fetch.php';

        $t->same([[
            'name' => 'origin',
            'url' => 'https://git.example.test/wp-content.git',
            'partialCloneFilter' => 'blob:none',
        ]], $summary['promisorRemotes']);
        $t->same('promisor-present', $summary['afterRead']['status']);
        $t->same(true, in_array($summary['hydrationPack'], $summary['promisorPacksAfterHydration'], true));
        $t->same(true, str_ends_with($summary['hydrationKeep'], '.keep'));
        $t->same(true, $summary['persistedInPackStore']);
        $t->same(false, $summary['refreshDisabledRefreshesOnMiss']);
        $t->same('promised-missing', $summary['beforeExternalHydration']['status']);
        $t->same('promised-missing', $summary['refreshDisabledBeforeExternalHydration']['status']);
        $t->same(true, $summary['containsAfterExternalHydration']);
        $t->same('found', $summary['prefixAfterExternalHydration']['status']);
        $t->same($summary['externalHydratedObject'], $summary['prefixAfterExternalHydration']['oid']);
        $t->same('promisor-present', $summary['afterExternalHydration']['status']);
        $t->same(false, $summary['refreshDisabledContainsAfterExternalHydration']);
        $t->same('missing', $summary['refreshDisabledPrefixAfterExternalHydration']['status']);
        $t->same([], $summary['refreshDisabledPrefixAfterExternalHydration']['candidates']);
        $t->same('promised-missing', $summary['refreshDisabledAfterExternalHydration']['status']);
        $t->same(false, in_array($summary['externalHydrationPack'], $summary['refreshDisabledPromisorPacksAfterExternalHydration'], true));
        $t->same(true, str_ends_with($summary['externalHydrationKeep'], '.keep'));
        $t->same(true, in_array($summary['externalHydratedObject'], $summary['objectIdsAfterExternalHydration'], true));
        $t->same($summary['packedObjectCountBeforeExternalHydration'] + 1, $summary['packedObjectCountAfterExternalHydration']);
        $t->same(true, in_array($summary['externalHydrationPack'], $summary['promisorPacksAfterExternalHydration'], true));
        $t->same(true, $summary['thinPromisorPackIsThin']);
        $t->same(true, in_array($summary['thinPromisorPack'], $summary['promisorPacksAfterExternalHydration'], true));
        $t->same('promised-missing', $summary['thinBaseBeforeHydration']['status']);
        $t->same('promisor-present', $summary['thinTargetBeforeHydration']['status']);
        $t->same('blob', $summary['thinTargetHeader']['type']);
        $t->same($summary['thinTargetHeader']['size'], $summary['thinTargetSize']);
        $t->same('promisor-present', $summary['thinBaseAfterHydration']['status']);
        $t->same('promisor-present', $summary['thinTargetAfterHydration']['status']);
        $t->same(true, in_array($summary['crossPackBasePromisorPack'], $summary['promisorPacksAfterCrossPackHydration'], true));
        $t->same(true, in_array($summary['crossPackTargetPromisorPack'], $summary['promisorPacksAfterCrossPackHydration'], true));
        $t->same(true, $summary['crossPackThinPromisorPackIsThin']);
        $t->same('promisor-present', $summary['crossPackBaseBeforeRead']['status']);
        $t->same('promisor-present', $summary['crossPackTargetBeforeRead']['status']);
        $t->same('blob', $summary['crossPackTargetHeader']['type']);
        $t->same($summary['crossPackTargetHeader']['size'], $summary['crossPackTargetSize']);
        $t->same(true, $summary['crossPackTargetBodyMatches']);
        $t->same('promisor-present', $summary['crossPackBaseAfterRead']['status']);
        $t->same('promisor-present', $summary['crossPackTargetAfterRead']['status']);
        $t->same($summary['alternateBaseObject'], $summary['alternateBaseWriteOid']);
        $t->same(true, count($summary['alternateObjectDirectories']) >= 1);
        $t->same(true, $summary['alternateThinPromisorPackIsThin']);
        $t->same(true, in_array($summary['alternateThinPromisorPack'], $summary['promisorPacksAfterAlternateHydration'], true));
        $t->same(true, str_ends_with($summary['alternateThinPromisorKeep'], '.keep'));
        $t->same('present', $summary['alternateThinBaseState']['status']);
        $t->same('promisor-present', $summary['alternateThinTargetState']['status']);
        $t->same('blob', $summary['alternateThinTargetHeader']['type']);
        $t->same('pack', $summary['alternateThinTargetHeader']['source']);
        $t->same($summary['alternateThinTargetHeader']['size'], $summary['alternateThinTargetSize']);
        $t->same(true, $summary['alternateThinTargetBodyMatches']);
        $t->same(true, $summary['repairedThinPackWasThin']);
        $t->same('promised-missing', $summary['repairedBaseBeforeWrite']['status']);
        $t->same([$summary['repairedPromisorObjects'][0]], $summary['repairedResolverRequests']);
        $t->same(true, str_ends_with($summary['repairedPromisorPack'], '.promisor'));
        $t->same(true, str_ends_with($summary['repairedPromisorKeep'], '.keep'));
        $t->same(2, $summary['repairedPromisorObjectCount']);
        $t->same(2, count($summary['repairedPromisorObjects']));
        $t->same(true, $summary['repairedPackChecksumChanged']);
        $t->same(2, $summary['repairedStoredPackCount']);
        $t->same(2, $summary['repairedStoredIndexCount']);
        $t->same('blob', $summary['repairedTargetHeader']['type']);
        $t->same(1, $summary['repairedTargetHeader']['numDeltas']);
        $t->same(true, $summary['repairedTargetBodyMatches']);
        $t->same('promisor-present', $summary['repairedFreshTargetState']['status']);
        $t->same('pack', $summary['repairedFreshTargetHeader']['source']);
        $t->same(true, in_array($summary['directInventoryPack'], $summary['directInventoryPackNames'], true));
        $t->same(true, in_array($summary['directInventoryObject'], $summary['directInventoryObjectIds'], true));
        $t->same(true, $summary['directInventoryIsPromisor']);
        $t->same(true, str_ends_with($summary['directInventoryKeep'], '.keep'));
        $t->same(false, $summary['resumedPromisorAlreadyPresent']);
        $t->same(true, str_ends_with($summary['resumedPromisorPack'], '.pack'));
        $t->same(true, str_ends_with($summary['resumedPromisorIndex'], '.idx'));
        $t->same(true, str_ends_with($summary['resumedPromisorMarker'], '.promisor'));
        $t->same(true, str_ends_with($summary['resumedPromisorKeep'], '.keep'));
        $t->same('promisor-present', $summary['resumedPromisorState']['status']);
        $t->same($summary['resumedPromisorObject'], $summary['resumedPromisorState']['oid']);
        $t->same('pack', $summary['resumedPromisorHeader']['source']);
        $t->same(true, $summary['resumedPromisorBodyMatches']);
        $t->same(false, in_array($summary['orphanPromisorIndex'], $summary['orphanPromisorPacksAfterRefresh'], true));
        $t->same(false, in_array($summary['orphanPromisorObject'], $summary['orphanPromisorObjectIdsAfterRefresh'], true));
        $t->same(false, $summary['orphanPromisorIsPromisor']);
        $t->same('promised-missing', $summary['orphanPromisorState']['status']);
        $t->same('promised-missing', $summary['refreshNeverReturnedBefore']['status']);
        $t->same([$summary['refreshNeverReturnedObject']], $summary['refreshNeverReturnedRequests']);
        $t->same('present', $summary['refreshNeverReturnedAfter']['status']);
        $t->same('loose', $summary['refreshNeverReturnedHeader']['source']);
        $t->same(false, in_array($summary['refreshNeverReturnedPack'], $summary['refreshNeverReturnedPromisorPacksAfter'], true));
        $t->same('promisor-present', $summary['refreshNeverReturnedFreshState']['status']);
        $t->same('pack', $summary['refreshNeverReturnedFreshHeader']['source']);
        $t->same(41, $summary['deepChainObjectCount']);
        $t->same('promisor-present', $summary['deepChainTargetBeforeGuard']['status']);
        $t->contains('REF_DELTA external base recursion limit', (string) $summary['deepChainHeaderGuardMessage']);
        $t->contains('REF_DELTA external base recursion limit', (string) $summary['deepChainReadGuardMessage']);
        $t->same(true, in_array($summary['deepChainTargetPack'], $summary['promisorPacksAfterDeepChainGuard'], true));
    },
];
