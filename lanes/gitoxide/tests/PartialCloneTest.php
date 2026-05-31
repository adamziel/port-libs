<?php

declare(strict_types=1);

use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\FetchFilterSpec;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\PackBuilder;
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

$writePromisorConfigFixture = static function (bool $promisor = true): string {
    $gitDir = sys_get_temp_dir() . '/port-libs-git-promisor-config-' . bin2hex(random_bytes(4)) . '/.git';
    $packDir = $gitDir . '/objects/pack';
    if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
        throw new RuntimeException("Unable to create promisor config fixture directory: {$packDir}");
    }

    $promisorValue = $promisor ? 'true' : 'false';
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
        $t->same(true, $summary['persistedInPackStore']);
        $t->same('promised-missing', $summary['beforeExternalHydration']['status']);
        $t->same(true, $summary['containsAfterExternalHydration']);
        $t->same('found', $summary['prefixAfterExternalHydration']['status']);
        $t->same($summary['externalHydratedObject'], $summary['prefixAfterExternalHydration']['oid']);
        $t->same('promisor-present', $summary['afterExternalHydration']['status']);
        $t->same(true, in_array($summary['externalHydratedObject'], $summary['objectIdsAfterExternalHydration'], true));
        $t->same($summary['packedObjectCountBeforeExternalHydration'] + 1, $summary['packedObjectCountAfterExternalHydration']);
        $t->same(true, in_array($summary['externalHydrationPack'], $summary['promisorPacksAfterExternalHydration'], true));
    },
];
