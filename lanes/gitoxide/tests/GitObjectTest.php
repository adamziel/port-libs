<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\GitTag;
use PortLibs\Gitoxide\LooseObjectStore;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$looseObjectPath = static fn (string $objectsDirectory, string $oid): string => $objectsDirectory . '/' . substr($oid, 0, 2) . '/' . substr($oid, 2);
$writeLooseStorage = static function (string $objectsDirectory, string $oid, string $storageBytes) use ($looseObjectPath): void {
    $path = $looseObjectPath($objectsDirectory, $oid);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
        throw new RuntimeException('Unable to create loose object test directory');
    }
    $compressed = gzcompress($storageBytes);
    if ($compressed === false) {
        throw new RuntimeException('Unable to compress loose object fixture');
    }
    file_put_contents($path, $compressed);
};

return [
    'git blob oid matches canonical git hashing' => static function (TestRunner $t): void {
        $object = new GitObject('blob', "hello world\n");
        $t->same('3b18e512dba79e4c8300dd08aeb37f8e728b8dad', $object->oid());
        $t->same("blob 12\0hello world\n", $object->storageBytes());
    },
    'loose header encoding and decoding follows gix object integration tests' => static function (TestRunner $t): void {
        $cases = [
            ['tree', 1234, "tree 1234\0"],
            ['blob', 0, "blob 0\0"],
            ['commit', 24241, "commit 24241\0"],
            ['tag', 9999999999, "tag 9999999999\0"],
        ];

        foreach ($cases as [$type, $size, $expected]) {
            $t->same($expected, GitObject::looseHeader($type, $size));
            $t->same([
                'type' => $type,
                'size' => $size,
                'headerLength' => strlen($expected),
            ], GitObject::decodeLooseHeader($expected));
        }
    },
    'loose header decoding accepts upstream positive signed sizes and canonicalizes writes' => static function (TestRunner $t): void {
        $storage = "blob +4\0data";
        $t->same([
            'type' => 'blob',
            'size' => 4,
            'headerLength' => strlen("blob +4\0"),
        ], GitObject::decodeLooseHeader($storage));

        $fromLoose = GitObject::fromLooseBytes($storage . 'read-ahead');
        $fromStorage = GitObject::fromStorageBytes($storage);
        $t->same('blob', $fromLoose->type);
        $t->same('data', $fromLoose->body);
        $t->same('data', $fromStorage->body);
        $t->same("blob 4\0data", $fromStorage->storageBytes());
        $t->same(hash('sha1', "blob 4\0data"), $fromStorage->oid());
        $t->same(hash('sha1', "blob 4\0data"), GitObject::fromLooseBytes($storage)->oid());
        $t->same(false, GitObject::fromLooseBytes($storage)->oid() === hash('sha1', $storage));

        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob +\0"));
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob -4\0"));
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob 4x\0"));
    },
    'from loose bytes rejects short payloads and parses the advertised body prefix' => static function (TestRunner $t): void {
        try {
            GitObject::fromLooseBytes("tree 1000\0");
            throw new RuntimeException('Expected short loose object payload to be rejected');
        } catch (InvalidArgumentException $exception) {
            $t->same('object data was shorter than its size declared in the header', $exception->getMessage());
        }

        $object = GitObject::fromLooseBytes("blob 12\0hello world\nread-ahead bytes");
        $t->same('blob', $object->type);
        $t->same("hello world\n", $object->body);
        $t->same("blob 12\0hello world\n", $object->storageBytes());

        $t->throws(InvalidArgumentException::class, static fn () => GitObject::fromStorageBytes("blob 12\0hello world\nread-ahead bytes"));
    },
    'loose object store writes and reads native zlib objects' => static function (TestRunner $t): void {
        $dir = sys_get_temp_dir() . '/port-libs-git-' . bin2hex(random_bytes(4));
        $store = new LooseObjectStore($dir);
        $oid = $store->write(new GitObject('blob', 'WordPress export'));
        $roundTrip = $store->read($oid);
        $t->same('blob', $roundTrip->type);
        $t->same('WordPress export', $roundTrip->body);
    },
    'loose object store honors sha256 object hash kind for paths headers and integrity' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-sha256-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir, false, 'sha256');
        $object = new GitObject('blob', 'WordPress sha256 content object');
        $oid = $store->write($object);
        $path = $gitDir . '/objects/' . substr($oid, 0, 2) . '/' . substr($oid, 2);

        $t->same('sha256', $store->objectHash());
        $t->same(64, strlen($oid));
        $t->same($object->oid('sha256'), $oid);
        $t->same(true, is_file($path));
        $t->same(false, is_file($gitDir . '/objects/' . substr($object->oid(), 0, 2) . '/' . substr($object->oid(), 2)));
        $t->same(true, $store->contains(strtoupper($oid)));
        $t->same('WordPress sha256 content object', $store->read(strtoupper($oid))->body);
        $t->same([
            'type' => 'blob',
            'size' => strlen($object->body),
            'headerLength' => strlen('blob ' . strlen($object->body) . "\0"),
        ], $store->readHeader(strtoupper($oid)));
        $t->same([$oid], $store->objectIds());
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$oid],
        ], $store->verifyIntegrity());
        $t->throws(InvalidArgumentException::class, static fn () => (new LooseObjectStore($gitDir))->read($oid));
        $t->throws(InvalidArgumentException::class, static fn () => new LooseObjectStore($gitDir, false, 'md5'));
    },
    'loose object store reads bounded headers before full body integrity checks' => static function (TestRunner $t) use ($looseObjectPath, $writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-header-' . bin2hex(random_bytes(4)) . '/objects';
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $shortOid = str_repeat('b', 40);
        $writeLooseStorage($objectsDirectory, $shortOid, "blob 12\0short");

        $t->same([
            'type' => 'blob',
            'size' => 12,
            'headerLength' => 8,
        ], $store->readHeader($shortOid));
        $t->throws(InvalidArgumentException::class, static fn () => $store->read($shortOid));
        $t->same(null, $store->tryReadHeader(str_repeat('c', 40)));

        $longHeaderOid = str_repeat('d', 40);
        $writeLooseStorage($objectsDirectory, $longHeaderOid, 'blob ' . str_repeat('1', 60) . "\0body");
        $t->throws(InvalidArgumentException::class, static fn () => $store->readHeader($longHeaderOid));

        $badZlibOid = str_repeat('e', 40);
        $badPath = $looseObjectPath($objectsDirectory, $badZlibOid);
        if (!is_dir(dirname($badPath)) && !mkdir(dirname($badPath), 0777, true) && !is_dir(dirname($badPath))) {
            throw new RuntimeException('Unable to create loose object bad-zlib test directory');
        }
        file_put_contents($badPath, 'not-zlib');
        $t->throws(RuntimeException::class, static fn () => $store->readHeader($badZlibOid));
    },
    'loose object integrity verifies object ids and decodes structured objects' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-integrity-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);

        $blob = new GitObject('blob', 'WordPress export blob');
        $blobOid = $store->write($blob);
        $tree = new Tree([new TreeEntry('100644', 'index.html', $blobOid)]);
        $treeOid = $store->write($tree->toObject());
        $commit = new Commit(
            $treeOid,
            [],
            'WordPress Importer <importer@example.test> 1710000000 +0000',
            'WordPress Deploy Bot <deploy@example.test> 1710000300 +0000',
            "Import block snapshot\n",
            [],
        );
        $commitOid = $store->write($commit->object());
        $tag = new GitTag($commitOid, 'commit', 'deploy/integrity', null, "Verified deployment object graph\n");
        $tagOid = $store->write($tag->object());

        $expected = [$blobOid, $treeOid, $commitOid, $tagOid];
        sort($expected, SORT_STRING);
        $summary = $store->verifyIntegrity();

        $t->same(4, $summary['numObjects']);
        $t->same($expected, $summary['verifiedObjectIds']);
        $t->same($gitDir . '/objects', $store->objectsDirectory());
    },
    'loose object integrity rejects path hash mismatches and malformed structured bodies' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-integrity-bad-path-' . bin2hex(random_bytes(4)) . '/.git';
        $objectsDirectory = $gitDir . '/objects';
        $expectedOid = (new GitObject('blob', 'expected body'))->oid();
        $tampered = new GitObject('blob', 'tampered body');
        $writeLooseStorage($objectsDirectory, $expectedOid, $tampered->storageBytes());
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected loose object hash mismatch to be rejected');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object hash mismatch', $exception->getMessage());
            $t->contains($expectedOid, $exception->getMessage());
            $t->contains($tampered->oid(), $exception->getMessage());
        }

        $badTreeStore = new LooseObjectStore(sys_get_temp_dir() . '/port-libs-git-integrity-bad-tree-' . bin2hex(random_bytes(4)) . '/.git');
        $badTreeOid = $badTreeStore->write(new GitObject('tree', "10099x file\0" . str_repeat("\0", 20)));
        try {
            $badTreeStore->verifyIntegrity();
            throw new RuntimeException('Expected malformed tree body to be rejected');
        } catch (RuntimeException $exception) {
            $t->contains("tree object {$badTreeOid} could not be decoded", $exception->getMessage());
            $t->contains('Tree entry mode must be one to seven octal digits', $exception->getMessage());
        }

        $shortStore = LooseObjectStore::fromObjectsDirectory(sys_get_temp_dir() . '/port-libs-git-integrity-short-' . bin2hex(random_bytes(4)) . '/objects');
        $shortOid = str_repeat('a', 40);
        $writeLooseStorage($shortStore->objectsDirectory(), $shortOid, "blob 12\0short");
        try {
            $shortStore->verifyIntegrity();
            throw new RuntimeException('Expected short loose object body to be rejected');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$shortOid} could not be read exactly", $exception->getMessage());
            $t->contains('Git object body length mismatch: expected 12, got 5', $exception->getMessage());
        }
    },
    'loose object integrity visits directory candidates instead of silently skipping them' => static function (TestRunner $t) use ($looseObjectPath): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-directory-blocker-' . bin2hex(random_bytes(4)) . '/objects';
        $blockedOid = str_repeat('f', 40);
        $blockedPath = $looseObjectPath($objectsDirectory, $blockedOid);
        if (!mkdir($blockedPath, 0777, true) && !is_dir($blockedPath)) {
            throw new RuntimeException('Unable to create loose object directory blocker fixture');
        }

        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $t->same([], $store->objectIds());
        $t->same(false, $store->contains($blockedOid));

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected loose object directory blocker to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$blockedOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object path is not a regular file', $exception->getMessage());
        }

        $t->throws(RuntimeException::class, static fn () => $store->readHeader($blockedOid));
    },
    'loose object integrity accepts positive signed size headers only under canonical ids' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-plus-size-' . bin2hex(random_bytes(4)) . '/objects';
        $canonicalObject = new GitObject('blob', 'data');
        $canonicalOid = $canonicalObject->oid();
        $positiveHeaderStorage = "blob +4\0data";
        $writeLooseStorage($objectsDirectory, $canonicalOid, $positiveHeaderStorage);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->same([
            'type' => 'blob',
            'size' => 4,
            'headerLength' => strlen("blob +4\0"),
        ], $store->readHeader($canonicalOid));
        $t->same('data', $store->read($canonicalOid)->body);
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$canonicalOid],
        ], $store->verifyIntegrity());

        $nonCanonicalStore = LooseObjectStore::fromObjectsDirectory(sys_get_temp_dir() . '/port-libs-git-integrity-plus-size-mismatch-' . bin2hex(random_bytes(4)) . '/objects');
        $nonCanonicalOid = hash('sha1', $positiveHeaderStorage);
        $writeLooseStorage($nonCanonicalStore->objectsDirectory(), $nonCanonicalOid, $positiveHeaderStorage);
        $t->same('data', $nonCanonicalStore->read($nonCanonicalOid)->body);
        try {
            $nonCanonicalStore->verifyIntegrity();
            throw new RuntimeException('Expected positive-size noncanonical loose object path to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object hash mismatch', $exception->getMessage());
            $t->contains($nonCanonicalOid, $exception->getMessage());
            $t->contains($canonicalOid, $exception->getMessage());
        }
    },
    'invalid storage header is rejected' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::fromStorageBytes("blob nope\0body"));
    },
    'wordpress loose object header example parses imported block content' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-object-header.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-object-header.php';

        $t->same('blob', $summary['type']);
        $t->same(strlen($fixture['blockBlobBody']), $summary['size']);
        $t->same($fixture['expectedLooseHeader'], $summary['looseHeader']);
        $t->same($fixture['expectedBlobOid'], $summary['oid']);
        $t->same($fixture['expectedBlobSha256'], $summary['sha256Oid']);
        $t->same(true, $summary['readAheadIgnored']);
        $t->same(true, $summary['strictStorageRejectsReadAhead']);
        $t->same(true, $summary['positiveSizeHeaderAccepted']);
        $t->same($fixture['expectedBlobOid'], $summary['positiveSizeCanonicalOid']);
        $t->same($fixture['positiveSizeLooseHeaderOid'], $summary['positiveSizeRawHeaderOid']);
    },
];
