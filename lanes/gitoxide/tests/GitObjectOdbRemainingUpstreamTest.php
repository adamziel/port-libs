<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitTag;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\ObjectDatabase;
use PortLibs\Gitoxide\Tree;

$repoRoot = dirname(__DIR__, 3);
$odbObjects = $repoRoot . '/.upstream-cache/gitoxide/gix-odb/tests/fixtures/objects';
$objectFixtures = $repoRoot . '/.upstream-cache/gitoxide/gix-object/tests/fixtures';

$fixtureBytes = static function (string $path): string {
    $bytes = file_get_contents($path);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read upstream fixture: {$path}");
    }

    return $bytes;
};

$looseObjectPath = static fn (string $objectsDirectory, string $oid): string => $objectsDirectory . '/' . substr($oid, 0, 2) . '/' . substr($oid, 2);
$looseFixtureObject = static function (string $objectsDirectory, string $oid) use ($fixtureBytes, $looseObjectPath): GitObject {
    $compressed = $fixtureBytes($looseObjectPath($objectsDirectory, $oid));
    $storage = gzuncompress($compressed);
    if ($storage === false) {
        throw new RuntimeException("Unable to inflate upstream loose fixture: {$oid}");
    }

    return GitObject::fromStorageBytes($storage);
};
$writeRawFile = static function (string $path, string $bytes): void {
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
        throw new RuntimeException("Unable to create fixture directory: " . dirname($path));
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException("Unable to write fixture file: {$path}");
    }
};
$tempObjectsDirectoryCounter = 0;
$tempObjectsDirectory = static function (string $label) use (&$tempObjectsDirectoryCounter): string {
    return sys_get_temp_dir() . '/port-libs-gitoxide-' . $label . '-' . getmypid() . '-' . (++$tempObjectsDirectoryCounter) . '/objects';
};
$copyLooseFixture = static function (string $fromObjectsDirectory, string $toObjectsDirectory, string $oid) use ($fixtureBytes, $looseObjectPath, $writeRawFile): void {
    $writeRawFile($looseObjectPath($toObjectsDirectory, $oid), $fixtureBytes($looseObjectPath($fromObjectsDirectory, $oid)));
};
$copyDirectory = static function (string $from, string $to) use (&$copyDirectory): void {
    if (!is_dir($from)) {
        throw new RuntimeException("Fixture source directory does not exist: {$from}");
    }
    if (!is_dir($to) && !mkdir($to, 0777, true) && !is_dir($to)) {
        throw new RuntimeException("Unable to create fixture target directory: {$to}");
    }

    $entries = scandir($from);
    if ($entries === false) {
        throw new RuntimeException("Unable to read fixture source directory: {$from}");
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $sourcePath = $from . '/' . $entry;
        $targetPath = $to . '/' . $entry;
        if (is_dir($sourcePath)) {
            $copyDirectory($sourcePath, $targetPath);
            continue;
        }
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Unable to copy fixture file: {$sourcePath}");
        }
    }
};

$upstreamLooseIds = [
    '37d4e6c5c48ba0d245164c4e10d5f41140cab980',
    '595dfd62fc1ad283d61bb47a24e7a1f66398f84d',
    '6ba2a0ded519f737fd5b8d5ccfb141125ef3176f',
    '722fe60ad4f0276d5a8121970b5bb9dccdad4ef9',
    '96ae868b3539f551c88fd5f02394d022581b11b0',
    'a706d7cd20fc8ce71489f34b50cf01011c104193',
    'ffa700b4aca13b80cb6b98a078e7c96804f8e0ec',
];

return [
    'gix-object computes empty blob and tree ids for sha1 and sha256' => static function (TestRunner $t): void {
        $emptyBlob = new GitObject('blob', '');
        $emptyTree = new GitObject('tree', '');

        $t->same('e69de29bb2d1d6434b8b29ae775ad8c2e48c5391', $emptyBlob->oid('sha1'));
        $t->same('4b825dc642cb6eb9a060e54bf8d69288fbee4904', $emptyTree->oid('sha1'));
        $t->same('473a0f4c3be8a93681a267e3b1e9a7dcda1185436fe141f7749120a303721813', $emptyBlob->oid('sha256'));
        $t->same('6ef19b41225c5369f1c104d45d8d85efa9b057b53b14b4b9b939dd74decc5321', $emptyTree->oid('sha256'));
    },
    'gix-odb loose fixture iteration contains headers and non-existing header lookup' => static function (TestRunner $t) use ($odbObjects, $upstreamLooseIds): void {
        $store = LooseObjectStore::fromObjectsDirectory($odbObjects);
        $expectedHeaders = [
            '37d4e6c5c48ba0d245164c4e10d5f41140cab980' => ['type' => 'blob', 'size' => 9],
            '595dfd62fc1ad283d61bb47a24e7a1f66398f84d' => ['type' => 'blob', 'size' => 11],
            '6ba2a0ded519f737fd5b8d5ccfb141125ef3176f' => ['type' => 'tree', 'size' => 66],
            '722fe60ad4f0276d5a8121970b5bb9dccdad4ef9' => ['type' => 'tag', 'size' => 1024],
            '96ae868b3539f551c88fd5f02394d022581b11b0' => ['type' => 'tree', 'size' => 37],
            'a706d7cd20fc8ce71489f34b50cf01011c104193' => ['type' => 'blob', 'size' => 56915],
            'ffa700b4aca13b80cb6b98a078e7c96804f8e0ec' => ['type' => 'commit', 'size' => 1084],
        ];

        $t->same($upstreamLooseIds, $store->objectIds());
        $t->same([
            'numObjects' => 7,
            'verifiedObjectIds' => $upstreamLooseIds,
        ], $store->verifyIntegrity());

        foreach ($expectedHeaders as $oid => $expected) {
            $t->same(true, $store->contains($oid));
            $header = $store->readHeader($oid);
            $t->same($expected['type'], $header['type'], "{$oid} type");
            $t->same($expected['size'], $header['size'], "{$oid} size");
        }

        $missing = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $t->same(null, $store->tryReadHeader($missing));
        $t->same(null, $store->tryRead($missing));
        $t->same(false, $store->contains($missing));
        $t->throws(RuntimeException::class, static fn () => $store->readHeader($missing));
    },
    'gix-odb loose fixture reads decode object bodies and allocation limits preserve header reads' => static function (TestRunner $t) use ($odbObjects): void {
        $store = LooseObjectStore::fromObjectsDirectory($odbObjects);

        $smallBlob = $store->read('37d4e6c5c48ba0d245164c4e10d5f41140cab980');
        $t->same('blob', $smallBlob->type);
        $t->same("hi there\n", $smallBlob->body);

        $otherBlob = $store->read('595dfd62fc1ad283d61bb47a24e7a1f66398f84d');
        $t->same("other file\n", $otherBlob->body);

        $tree = Tree::fromObject($store->read('6ba2a0ded519f737fd5b8d5ccfb141125ef3176f'));
        $t->same(2, count($tree->entries));
        $t->same('dir', $tree->entries[0]->filename);
        $t->same('tree', $tree->entries[0]->kind());
        $t->same('file.txt', $tree->entries[1]->filename);
        $t->same('blob', $tree->entries[1]->kind());

        $tag = GitTag::parse($store->read('722fe60ad4f0276d5a8121970b5bb9dccdad4ef9')->body);
        $t->same('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec', $tag->target);
        $t->same('1.0.0', $tag->name);
        $t->same('for the signature', $tag->message);
        $t->contains('GPGTools', $tag->pgpSignature ?? '');

        $commit = Commit::parse($store->read('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec')->body);
        $t->same('6ba2a0ded519f737fd5b8d5ccfb141125ef3176f', $commit->tree);
        $t->same('initial commit', $commit->messageSummary());
        $t->contains('BEGIN PGP SIGNATURE', $commit->extraHeader('gpgsig') ?? '');

        $bigBlob = $store->read('a706d7cd20fc8ce71489f34b50cf01011c104193');
        $t->same('blob', $bigBlob->type);
        $t->same(56915, strlen($bigBlob->body));

        $limited = LooseObjectStore::fromObjectsDirectory($odbObjects, allocationLimitBytes: 1);
        $t->same([
            'type' => 'blob',
            'size' => 56915,
            'headerLength' => strlen("blob 56915\0"),
        ], $limited->readHeader('a706d7cd20fc8ce71489f34b50cf01011c104193'));
        try {
            $limited->read('a706d7cd20fc8ce71489f34b50cf01011c104193');
            throw new RuntimeException('Expected allocation-limited loose object read to fail');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object declared size 56915 exceeds allocation limit 1 bytes', $exception->getMessage());
        }
    },
    'gix-odb upstream fixture pack objects are reachable through the compound object database' => static function (TestRunner $t) use ($odbObjects, $copyDirectory, $tempObjectsDirectory): void {
        $targetObjects = $tempObjectsDirectory('upstream-pack-fixture');
        $copyDirectory($odbObjects, $targetObjects);
        $database = new ObjectDatabase(dirname($targetObjects));

        $expectedPackedObjects = [
            '501b297447a8255d3533c6858bb692575cdefaa0' => ['type' => 'commit', 'size' => 225],
            '4dac9989f96bc5b5b1263b582c08f0c5f0b58542' => ['type' => 'tree', 'size' => 34],
            'dd25c539efbb0ab018caa4cda2d133285634e9b5' => ['type' => 'blob', 'size' => 860],
        ];

        $t->same(139, $database->packedObjectCount());
        $t->same(146, count($database->objectIds()));

        foreach ($expectedPackedObjects as $oid => $expected) {
            $t->same(true, $database->contains(strtoupper($oid)), "{$oid} is present");
            $t->same([
                'type' => $expected['type'],
                'size' => $expected['size'],
                'source' => 'pack',
            ], $database->readHeader(strtoupper($oid)), "{$oid} header");

            $object = $database->read(strtoupper($oid));
            $t->same($expected['type'], $object->type, "{$oid} type");
            $t->same($expected['size'], strlen($object->body), "{$oid} body size");
            $t->same($oid, $object->oid(), "{$oid} roundtrip id");
        }

        $commit = Commit::parse($database->read('501b297447a8255d3533c6858bb692575cdefaa0')->body);
        $t->same('test', $commit->messageSummary());
        $tree = Tree::fromObject($database->read('4dac9989f96bc5b5b1263b582c08f0c5f0b58542'));
        $t->same('README', $tree->entries[0]->filename);
        $t->contains('extern const char *tree_type;', $database->read('dd25c539efbb0ab018caa4cda2d133285634e9b5')->body);
    },
    'gix-odb loose writes roundtrip fixture objects and sink hashing returns fixture ids' => static function (TestRunner $t) use ($odbObjects, $upstreamLooseIds, $looseFixtureObject, $tempObjectsDirectory): void {
        $target = $tempObjectsDirectory('loose-read-write');
        $store = LooseObjectStore::fromObjectsDirectory($target);

        foreach ($upstreamLooseIds as $oid) {
            $object = $looseFixtureObject($odbObjects, $oid);
            $t->same($oid, $object->oid(), "sink oid {$oid}");
            $t->same($oid, $store->write($object), "write oid {$oid}");
            $t->same($oid, $store->write($object), "rewrite collision oid {$oid}");
            $roundTrip = $store->read($oid);
            $t->same($object->type, $roundTrip->type, "roundtrip type {$oid}");
            $t->same($object->body, $roundTrip->body, "roundtrip body {$oid}");
        }

        $t->same($upstreamLooseIds, $store->objectIds());
        $t->same([
            'numObjects' => 7,
            'verifiedObjectIds' => $upstreamLooseIds,
        ], $store->verifyIntegrity());
    },
    'gix-odb loose prefix lookup returns missing ambiguous and varying length candidates' => static function (TestRunner $t) use ($odbObjects, $upstreamLooseIds, $copyLooseFixture, $writeRawFile, $looseObjectPath, $tempObjectsDirectory): void {
        $store = LooseObjectStore::fromObjectsDirectory($odbObjects);

        $t->same([], $store->prefixObjectIds('0000000'));
        foreach ([4, 7, 40] as $length) {
            foreach ($upstreamLooseIds as $index => $oid) {
                if ($index % 3 !== array_search($length, [4, 7, 40], true)) {
                    continue;
                }
                $t->same([$oid], $store->prefixObjectIds(substr($oid, 0, $length)), "prefix {$length} {$oid}");
            }
        }

        $target = $tempObjectsDirectory('loose-prefix');
        $inputId = '37d4e6c5c48ba0d245164c4e10d5f41140cab980';
        $fakeId = '37d4ffffffffffffffffffffffffffffffffffff';
        $copyLooseFixture($odbObjects, $target, $inputId);
        $writeRawFile($looseObjectPath($target, $fakeId), 'fake');

        $ambiguousStore = LooseObjectStore::fromObjectsDirectory($target);
        $t->same([$inputId, $fakeId], $ambiguousStore->prefixObjectIds('37d4'));
    },
    'gix-odb loose invalid object path errors do not escape as crashes' => static function (TestRunner $t) use ($writeRawFile, $looseObjectPath, $tempObjectsDirectory): void {
        $target = $tempObjectsDirectory('loose-invalid');
        $oid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $writeRawFile($looseObjectPath($target, $oid), '');
        $store = LooseObjectStore::fromObjectsDirectory($target);

        $t->same(true, $store->contains($oid));
        $t->throws(RuntimeException::class, static fn () => $store->readHeader($oid));
        $t->throws(RuntimeException::class, static fn () => $store->read($oid));
        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected empty loose fixture to fail integrity');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$oid} could not be read exactly", $exception->getMessage());
        }
    },
    'gix-object tree special fixtures parse with upstream entry counts and roundtrip' => static function (TestRunner $t) use ($objectFixtures, $fixtureBytes): void {
        $cases = [
            'maybe-special' => 160,
            'definitely-special' => 19,
            'special-1' => 5,
            'special-2' => 18,
            'special-3' => 5,
            'special-4' => 18,
            'special-5' => 17,
        ];

        foreach ($cases as $name => $expectedEntryCount) {
            $bytes = $fixtureBytes($objectFixtures . "/tree/{$name}.tree");
            $tree = Tree::parse($bytes);
            $t->same($expectedEntryCount, count($tree->entries), $name);
            $t->same($bytes, $tree->storageBytes(), "{$name} roundtrip");
        }

        $definitelySpecial = $fixtureBytes($objectFixtures . '/tree/definitely-special.tree');
        $t->throws(InvalidArgumentException::class, static fn () => Tree::parse(substr($definitelySpecial, 0, intdiv(strlen($definitelySpecial), 2))));
        $t->throws(InvalidArgumentException::class, static fn () => Tree::parse('2'));
    },
    'gix-object remaining commit and tag encode fixtures roundtrip exactly' => static function (TestRunner $t) use ($objectFixtures, $fixtureBytes): void {
        foreach ([
            'commit/email-with-space.txt',
            'commit/signed-whitespace.txt',
            'commit/two-multiline-headers.txt',
            'commit/subtle.txt',
            'commit/bogus-gpgsig-lines-in-git.git.txt',
        ] as $fixture) {
            $bytes = $fixtureBytes($objectFixtures . '/' . $fixture);
            $commit = Commit::parse($bytes);
            $t->same($bytes, $commit->storageBytes(), $fixture);
        }

        foreach ([
            'tag/with-newlines.txt',
            'tag/tagger-with-whitespace.txt',
            'tag/signed.txt',
        ] as $fixture) {
            $bytes = $fixtureBytes($objectFixtures . '/' . $fixture);
            $tag = GitTag::parse($bytes);
            $t->same($bytes, $tag->storageBytes(), $fixture);
        }
    },
];
