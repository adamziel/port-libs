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
];
