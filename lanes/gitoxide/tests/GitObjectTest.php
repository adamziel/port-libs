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
$writeLooseCompressed = static function (string $objectsDirectory, string $oid, string $compressedBytes) use ($looseObjectPath): void {
    $path = $looseObjectPath($objectsDirectory, $oid);
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true) && !is_dir(dirname($path))) {
        throw new RuntimeException('Unable to create compressed loose object test directory');
    }
    file_put_contents($path, $compressedBytes);
};
$truncatedBeforeHeaderWindowCompletes = static function (string $storageBytes): string {
    $compressed = gzcompress($storageBytes);
    if ($compressed === false) {
        throw new RuntimeException('Unable to compress truncated loose-object fixture');
    }

    $length = strlen($compressed);
    for ($candidateLength = 2; $candidateLength < $length; $candidateLength++) {
        $candidate = substr($compressed, 0, $candidateLength);
        $context = inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new RuntimeException('Unable to initialize truncated loose-object inflate probe');
        }
        $decoded = @inflate_add($context, $candidate, ZLIB_FINISH);
        if (
            $decoded !== false
            && strpos($decoded, "\0") !== false
            && strlen($decoded) < 64
            && inflate_get_status($context) !== ZLIB_STREAM_END
        ) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to derive a truncated loose-object header fixture');
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
    'loose header decoding accepts upstream signed zero sizes and canonicalizes writes' => static function (TestRunner $t): void {
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
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob 4x\0"));

        $negativeZeroStorage = "blob -0\0";
        $t->same([
            'type' => 'blob',
            'size' => 0,
            'headerLength' => strlen("blob -0\0"),
        ], GitObject::decodeLooseHeader($negativeZeroStorage));
        $negativeZero = GitObject::fromStorageBytes($negativeZeroStorage);
        $t->same('', $negativeZero->body);
        $t->same("blob 0\0", $negativeZero->storageBytes());
        $t->same(hash('sha1', "blob 0\0"), $negativeZero->oid());
        $t->same(false, $negativeZero->oid() === hash('sha1', $negativeZeroStorage));

        $t->same(0, GitObject::decodeLooseHeader("blob -000\0")['size']);
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob -\0"));
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob -4\0"));
        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader("blob -04\0"));
    },
    'loose object integrity accepts zero-padded size headers only under canonical ids' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-zero-padded-size-' . bin2hex(random_bytes(4)) . '/objects';
        $canonicalObject = new GitObject('blob', 'abc');
        $canonicalOid = $canonicalObject->oid();
        $zeroPaddedStorage = "blob 0003\0abc";
        $rawHeaderOid = hash('sha1', $zeroPaddedStorage);
        $writeLooseStorage($objectsDirectory, $canonicalOid, $zeroPaddedStorage);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->same([
            'type' => 'blob',
            'size' => 3,
            'headerLength' => strlen("blob 0003\0"),
        ], GitObject::decodeLooseHeader($zeroPaddedStorage));
        $t->same($canonicalOid, GitObject::fromStorageBytes($zeroPaddedStorage)->oid());
        $t->same(false, $rawHeaderOid === $canonicalOid);
        $t->same([
            'type' => 'blob',
            'size' => 3,
            'headerLength' => strlen("blob 0003\0"),
        ], $store->readHeader($canonicalOid));
        $t->same('abc', $store->read($canonicalOid)->body);
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$canonicalOid],
        ], $store->verifyIntegrity());

        $nonCanonicalStore = LooseObjectStore::fromObjectsDirectory(sys_get_temp_dir() . '/port-libs-git-integrity-zero-padded-mismatch-' . bin2hex(random_bytes(4)) . '/objects');
        $writeLooseStorage($nonCanonicalStore->objectsDirectory(), $rawHeaderOid, $zeroPaddedStorage);
        $t->same('abc', $nonCanonicalStore->read($rawHeaderOid)->body);
        try {
            $nonCanonicalStore->verifyIntegrity();
            throw new RuntimeException('Expected zero-padded noncanonical loose object path to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object hash mismatch', $exception->getMessage());
            $t->contains($rawHeaderOid, $exception->getMessage());
            $t->contains($canonicalOid, $exception->getMessage());
        }
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
    'loose object store finalizes read-only files without clobbering existing objects like gix odb' => static function (TestRunner $t) use ($looseObjectPath): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-loose-finalize-' . bin2hex(random_bytes(4)) . '/objects';
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $object = new GitObject('blob', 'WordPress immutable deployment object');
        $oid = $store->write($object);
        $path = $looseObjectPath($objectsDirectory, $oid);
        $firstBytes = (string) file_get_contents($path);

        $t->same(true, is_file($path));
        $t->same(0444, fileperms($path) & 0777);
        $t->same($oid, $store->write($object));
        $t->same($firstBytes, (string) file_get_contents($path));
        $t->same('WordPress immutable deployment object', $store->read($oid)->body);
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$oid],
        ], $store->verifyIntegrity());
    },
    'loose object store refuses occupied non-regular object paths before finalization' => static function (TestRunner $t) use ($looseObjectPath): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-loose-finalize-blocked-' . bin2hex(random_bytes(4)) . '/objects';
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $blockedObject = new GitObject('blob', 'WordPress blocked loose object');
        $blockedOid = $blockedObject->oid();
        $blockedPath = $looseObjectPath($objectsDirectory, $blockedOid);
        if (!mkdir($blockedPath, 0777, true) && !is_dir($blockedPath)) {
            throw new RuntimeException('Unable to create occupied loose object path fixture');
        }

        try {
            $store->write($blockedObject);
            throw new RuntimeException('Expected occupied loose object path to block finalization');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object path already exists and is not a regular file: {$blockedOid}", $exception->getMessage());
        }

        $symlinkObject = new GitObject('blob', 'WordPress symlink loose object');
        $symlinkOid = $symlinkObject->oid();
        $symlinkPath = $looseObjectPath($objectsDirectory, $symlinkOid);
        if (!is_dir(dirname($symlinkPath)) && !mkdir(dirname($symlinkPath), 0777, true) && !is_dir(dirname($symlinkPath))) {
            throw new RuntimeException('Unable to create loose object symlink directory fixture');
        }
        if (!symlink($objectsDirectory . '/missing-target', $symlinkPath)) {
            throw new RuntimeException('Unable to create loose object symlink fixture');
        }

        try {
            $store->write($symlinkObject);
            throw new RuntimeException('Expected loose object symlink path to block finalization');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object path already exists and is not a regular file: {$symlinkOid}", $exception->getMessage());
        }
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
    'loose object integrity decodes sha256 tree commit and tag payloads with the store hash kind' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-sha256-structured-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir, false, 'sha256');

        $blob = new GitObject('blob', 'WordPress SHA-256 structured object body');
        $blobOid = $store->write($blob);
        $treeBody = "100644 block.html\0" . hex2bin($blobOid);
        $tree = new GitObject('tree', $treeBody);
        $treeOid = $store->write($tree);
        $commit = new Commit(
            $treeOid,
            [],
            'WordPress Importer <importer@example.test> 1710000000 +0000',
            'WordPress Deploy Bot <deploy@example.test> 1710000300 +0000',
            "Import SHA-256 block snapshot\n",
            [],
        );
        $commitOid = $store->write($commit->object());
        $tag = new GitTag($commitOid, 'commit', 'deploy/sha256-integrity', null, "Verified SHA-256 deployment object graph\n");
        $tagOid = $store->write($tag->object());

        $parsedTree = Tree::parse($treeBody, 'sha256');
        $t->same(1, count($parsedTree->entries));
        $t->same($blobOid, $parsedTree->entries[0]->oid);
        $t->same(64, strlen($parsedTree->entries[0]->oid));
        $t->same($treeOid, Commit::parse($commit->storageBytes(), 'sha256')->tree);
        $t->same($commitOid, GitTag::parse($tag->storageBytes(), 'sha256')->target);

        $expected = [$blobOid, $treeOid, $commitOid, $tagOid];
        sort($expected, SORT_STRING);
        $t->same([
            'numObjects' => 4,
            'verifiedObjectIds' => $expected,
        ], $store->verifyIntegrity());
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
        try {
            $store->read($shortOid);
            throw new RuntimeException('Expected short loose object body to fail exact inflation');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object inflated size mismatch', $exception->getMessage());
            $t->contains('expected 20', $exception->getMessage());
            $t->contains('got 13', $exception->getMessage());
        }
        $t->same(null, $store->tryReadHeader(str_repeat('c', 40)));

        $longHeaderOid = str_repeat('d', 40);
        $writeLooseStorage($objectsDirectory, $longHeaderOid, 'blob ' . str_repeat('1', 60) . "\0body");
        $t->throws(InvalidArgumentException::class, static fn () => $store->readHeader($longHeaderOid));

        $missingNulOid = str_repeat('6', 40);
        $writeLooseStorage($objectsDirectory, $missingNulOid, 'blob 4');
        foreach ([
            'readHeader' => static fn () => $store->readHeader($missingNulOid),
            'tryReadHeader' => static fn () => $store->tryReadHeader($missingNulOid),
            'read' => static fn () => $store->read($missingNulOid),
        ] as $operation => $callback) {
            try {
                $callback();
                throw new RuntimeException("Expected missing-NUL loose object {$operation} to fail");
            } catch (InvalidArgumentException $exception) {
                $t->same('Did not find 0 byte in header', $exception->getMessage());
            }
        }
        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected missing-NUL loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$missingNulOid} could not be read exactly", $exception->getMessage());
            $t->contains('Did not find 0 byte in header', $exception->getMessage());
        }

        $badZlibOid = str_repeat('e', 40);
        $badPath = $looseObjectPath($objectsDirectory, $badZlibOid);
        if (!is_dir(dirname($badPath)) && !mkdir(dirname($badPath), 0777, true) && !is_dir(dirname($badPath))) {
            throw new RuntimeException('Unable to create loose object bad-zlib test directory');
        }
        file_put_contents($badPath, 'not-zlib');
        $t->throws(RuntimeException::class, static fn () => $store->readHeader($badZlibOid));
    },
    'loose object overlong first header window preserves upstream decode error ordering' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $cases = [
            'knownKindDelayedNul' => [
                'oid' => str_repeat('1', 40),
                'storage' => 'blob ' . str_repeat('1', 60) . "\0body",
                'message' => 'Did not find 0 byte in header',
            ],
            'unknownKindDelayedNul' => [
                'oid' => str_repeat('2', 40),
                'storage' => 'wordpress ' . str_repeat('1', 60) . "\0body",
                'message' => 'Unknown object kind: wordpress',
            ],
            'missingDelimiterDelayedNul' => [
                'oid' => str_repeat('3', 40),
                'storage' => str_repeat('b', 64) . "\0body",
                'message' => "Expected '<type> <size>'",
            ],
        ];

        foreach ($cases as $case => $fixture) {
            $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-overlong-header-window-' . $case . '-' . bin2hex(random_bytes(4)) . '/objects';
            $writeLooseStorage($objectsDirectory, $fixture['oid'], $fixture['storage']);
            $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

            foreach ([
                'readHeader' => static fn () => $store->readHeader($fixture['oid']),
                'tryReadHeader' => static fn () => $store->tryReadHeader($fixture['oid']),
                'read' => static fn () => $store->read($fixture['oid']),
            ] as $operation => $callback) {
                try {
                    $callback();
                    throw new RuntimeException("Expected overlong loose object header {$operation} to fail for {$case}");
                } catch (InvalidArgumentException $exception) {
                    $t->same($fixture['message'], $exception->getMessage());
                }
            }

            try {
                $store->verifyIntegrity();
                throw new RuntimeException("Expected overlong loose object header integrity failure for {$case}");
            } catch (RuntimeException $exception) {
                $t->contains("Loose object {$fixture['oid']} could not be read exactly", $exception->getMessage());
                $t->contains($fixture['message'], $exception->getMessage());
            }
        }
    },
    'loose object integrity rejects missing type-size delimiter before missing-NUL checks' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-no-type-size-delimiter-' . bin2hex(random_bytes(4)) . '/objects';
        $oid = str_repeat('9', 40);
        $storage = 'blob14wordpressblock';
        $writeLooseStorage($objectsDirectory, $oid, $storage);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        foreach ([
            'decodeLooseHeader' => static fn () => GitObject::decodeLooseHeader($storage),
            'fromStorageBytes' => static fn () => GitObject::fromStorageBytes($storage),
            'readHeader' => static fn () => $store->readHeader($oid),
            'tryReadHeader' => static fn () => $store->tryReadHeader($oid),
            'read' => static fn () => $store->read($oid),
        ] as $operation => $callback) {
            try {
                $callback();
                throw new RuntimeException("Expected no-delimiter loose object {$operation} to fail");
            } catch (InvalidArgumentException $exception) {
                $t->same("Expected '<type> <size>'", $exception->getMessage());
            }
        }

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected no-delimiter loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$oid} could not be read exactly", $exception->getMessage());
            $t->contains("Expected '<type> <size>'", $exception->getMessage());
        }
    },
    'loose object integrity rejects unknown object kinds before missing-NUL and size parsing' => static function (TestRunner $t) use ($writeLooseStorage): void {
        foreach ([
            'unknownNoNul' => ['storage' => 'wordpress 123', 'oid' => str_repeat('b', 40)],
            'unknownInvalidSize' => ['storage' => "wordpress nope\0block-body", 'oid' => str_repeat('c', 40)],
            'unknownWithBody' => ['storage' => "wordpress 4\0body", 'oid' => str_repeat('d', 40)],
        ] as $case => $fixture) {
            $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-unknown-loose-kind-' . $case . '-' . bin2hex(random_bytes(4)) . '/objects';
            $writeLooseStorage($objectsDirectory, $fixture['oid'], $fixture['storage']);
            $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

            foreach ([
                'decodeLooseHeader' => static fn () => GitObject::decodeLooseHeader($fixture['storage']),
                'readHeader' => static fn () => $store->readHeader($fixture['oid']),
                'tryReadHeader' => static fn () => $store->tryReadHeader($fixture['oid']),
                'read' => static fn () => $store->read($fixture['oid']),
            ] as $operation => $callback) {
                try {
                    $callback();
                    throw new RuntimeException("Expected unknown loose object kind {$operation} to fail for {$case}");
                } catch (InvalidArgumentException $exception) {
                    $t->same('Unknown object kind: wordpress', $exception->getMessage());
                }
            }

            try {
                $store->verifyIntegrity();
                throw new RuntimeException("Expected unknown loose object kind integrity failure for {$case}");
            } catch (RuntimeException $exception) {
                $t->contains("Loose object {$fixture['oid']} could not be read exactly", $exception->getMessage());
                $t->contains('Unknown object kind: wordpress', $exception->getMessage());
            }
        }

        try {
            GitObject::decodeLooseHeader("blob nope\0body");
            throw new RuntimeException('Expected invalid loose object size to keep size parsing diagnostics');
        } catch (InvalidArgumentException $exception) {
            $t->same('Invalid Git object header: blob nope', $exception->getMessage());
        }
    },
    'loose object integrity preserves NUL-before-space unknown-kind ordering' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-nul-before-space-loose-kind-' . bin2hex(random_bytes(4)) . '/objects';
        $oid = str_repeat('4', 40);
        $storage = "blob\0 3abc";
        $writeLooseStorage($objectsDirectory, $oid, $storage);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        foreach ([
            'decodeLooseHeader' => static fn () => GitObject::decodeLooseHeader($storage),
            'readHeader' => static fn () => $store->readHeader($oid),
            'tryReadHeader' => static fn () => $store->tryReadHeader($oid),
            'read' => static fn () => $store->read($oid),
        ] as $operation => $callback) {
            try {
                $callback();
                throw new RuntimeException("Expected NUL-before-space loose object {$operation} to fail as unknown kind");
            } catch (InvalidArgumentException $exception) {
                $t->same("Unknown object kind: blob\0", $exception->getMessage());
            }
        }

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected NUL-before-space loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$oid} could not be read exactly", $exception->getMessage());
            $t->contains("Unknown object kind: blob\0", $exception->getMessage());
        }
    },
    'loose object header rejects truncated first inflate windows before trusting size' => static function (TestRunner $t) use ($writeLooseCompressed, $truncatedBeforeHeaderWindowCompletes): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-truncated-header-window-' . bin2hex(random_bytes(4)) . '/objects';
        $oid = str_repeat('7', 40);
        $writeLooseCompressed(
            $objectsDirectory,
            $oid,
            $truncatedBeforeHeaderWindowCompletes("blob 100\0" . str_repeat('A', 100))
        );
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->same(true, $store->contains($oid));
        try {
            $store->readHeader($oid);
            throw new RuntimeException('Expected truncated loose object header to fail before exposing size');
        } catch (RuntimeException $exception) {
            $t->same("Unable to inflate loose object header: {$oid}", $exception->getMessage());
        }

        try {
            $store->tryReadHeader($oid);
            throw new RuntimeException('Expected truncated loose object tryReadHeader to fail before exposing size');
        } catch (RuntimeException $exception) {
            $t->same("Unable to inflate loose object header: {$oid}", $exception->getMessage());
        }

        try {
            $store->read($oid);
            throw new RuntimeException('Expected truncated loose object read to fail during first inflate window');
        } catch (RuntimeException $exception) {
            $t->same("Unable to inflate loose object: {$oid}", $exception->getMessage());
        }

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected truncated loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$oid} could not be read exactly", $exception->getMessage());
            $t->contains("Unable to inflate loose object: {$oid}", $exception->getMessage());
        }
    },
    'loose object header validates corrupt first inflate window before exposing size' => static function (TestRunner $t) use ($writeLooseCompressed): void {
        $smallObjectsDirectory = sys_get_temp_dir() . '/port-libs-git-corrupt-header-window-' . bin2hex(random_bytes(4)) . '/objects';
        $smallOid = str_repeat('8', 40);
        $smallCompressed = gzcompress("blob 3\0abc");
        if ($smallCompressed === false) {
            throw new RuntimeException('Unable to compress corrupt first-window loose-object fixture');
        }
        $smallCompressed[strlen($smallCompressed) - 1] = chr(ord($smallCompressed[strlen($smallCompressed) - 1]) ^ 0xff);
        $writeLooseCompressed($smallObjectsDirectory, $smallOid, $smallCompressed);
        $smallStore = LooseObjectStore::fromObjectsDirectory($smallObjectsDirectory);

        $t->same(true, $smallStore->contains($smallOid));
        foreach (['readHeader' => static fn () => $smallStore->readHeader($smallOid), 'tryReadHeader' => static fn () => $smallStore->tryReadHeader($smallOid)] as $operation => $callback) {
            try {
                $callback();
                throw new RuntimeException("Expected corrupt first-window loose object {$operation} to fail");
            } catch (RuntimeException $exception) {
                $t->same("Unable to inflate loose object header: {$smallOid}", $exception->getMessage());
            }
        }
        try {
            $smallStore->verifyIntegrity();
            throw new RuntimeException('Expected corrupt first-window loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$smallOid} could not be read exactly", $exception->getMessage());
            $t->contains("Unable to inflate loose object: {$smallOid}", $exception->getMessage());
        }

        $largeObjectsDirectory = sys_get_temp_dir() . '/port-libs-git-corrupt-after-header-window-' . bin2hex(random_bytes(4)) . '/objects';
        $largeBody = str_repeat('L', 96);
        $largeObject = new GitObject('blob', $largeBody);
        $largeCompressed = gzcompress($largeObject->storageBytes());
        if ($largeCompressed === false) {
            throw new RuntimeException('Unable to compress corrupt after-window loose-object fixture');
        }
        $largeCompressed[strlen($largeCompressed) - 1] = chr(ord($largeCompressed[strlen($largeCompressed) - 1]) ^ 0xff);
        $writeLooseCompressed($largeObjectsDirectory, $largeObject->oid(), $largeCompressed);
        $largeStore = LooseObjectStore::fromObjectsDirectory($largeObjectsDirectory);

        $t->same([
            'type' => 'blob',
            'size' => strlen($largeBody),
            'headerLength' => strlen('blob ' . strlen($largeBody) . "\0"),
        ], $largeStore->readHeader($largeObject->oid()));
        $t->same($largeBody, $largeStore->read($largeObject->oid())->body);
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$largeObject->oid()],
        ], $largeStore->verifyIntegrity());
    },
    'loose object integrity ignores trailing compressed streams after the declared object' => static function (TestRunner $t) use ($writeLooseCompressed): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-trailing-loose-stream-' . bin2hex(random_bytes(4)) . '/objects';
        $object = new GitObject('blob', 'WordPress deployment loose object');
        $oid = $object->oid();
        $objectStream = gzcompress($object->storageBytes());
        $trailingStream = gzcompress("blob 13\0stale payload");
        if ($objectStream === false || $trailingStream === false) {
            throw new RuntimeException('Unable to compress trailing loose-object stream fixture');
        }
        $writeLooseCompressed($objectsDirectory, $oid, $objectStream . $trailingStream);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->same('WordPress deployment loose object', $store->read($oid)->body);
        $t->same([
            'type' => 'blob',
            'size' => strlen($object->body),
            'headerLength' => strlen('blob ' . strlen($object->body) . "\0"),
        ], $store->readHeader($oid));
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$oid],
        ], $store->verifyIntegrity());

        $overrunOid = hash('sha1', "blob 3\0abc");
        $overrunStream = gzcompress("blob 3\0abcdef");
        if ($overrunStream === false) {
            throw new RuntimeException('Unable to compress overrun loose-object stream fixture');
        }
        $writeLooseCompressed($objectsDirectory, $overrunOid, $overrunStream);
        $t->throws(RuntimeException::class, static fn () => $store->read($overrunOid));
    },
    'loose object integrity ignores late same-stream overrun after gix fixed header window' => static function (TestRunner $t) use ($writeLooseCompressed): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-late-overrun-loose-stream-' . bin2hex(random_bytes(4)) . '/objects';
        $body = str_repeat('A', 96);
        $object = new GitObject('blob', $body);
        $oid = $object->oid();
        $compressed = gzcompress($object->storageBytes() . 'late-overrun');
        if ($compressed === false) {
            throw new RuntimeException('Unable to compress late-overrun loose-object stream fixture');
        }
        $writeLooseCompressed($objectsDirectory, $oid, $compressed);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->same($body, $store->read($oid)->body);
        $t->same([
            'type' => 'blob',
            'size' => strlen($body),
            'headerLength' => strlen('blob ' . strlen($body) . "\0"),
        ], $store->readHeader($oid));
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$oid],
        ], $store->verifyIntegrity());

        $smallOverrunObject = new GitObject('blob', 'abc');
        $smallOverrun = $smallOverrunObject->storageBytes() . 'def';
        $smallOverrunCompressed = gzcompress($smallOverrun);
        if ($smallOverrunCompressed === false) {
            throw new RuntimeException('Unable to compress small overrun loose-object stream fixture');
        }
        $writeLooseCompressed($objectsDirectory, $smallOverrunObject->oid(), $smallOverrunCompressed);
        try {
            $store->read($smallOverrunObject->oid());
            throw new RuntimeException('Expected first-window loose object overrun to stay rejected');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object inflated size mismatch', $exception->getMessage());
            $t->contains('expected 10', $exception->getMessage());
            $t->contains('got 13', $exception->getMessage());
        }
    },
    'loose object store rejects empty object files before zlib header decoding' => static function (TestRunner $t) use ($looseObjectPath): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-empty-loose-' . bin2hex(random_bytes(4)) . '/objects';
        $emptyOid = str_repeat('6', 40);
        $emptyPath = $looseObjectPath($objectsDirectory, $emptyOid);
        if (!is_dir(dirname($emptyPath)) && !mkdir(dirname($emptyPath), 0777, true) && !is_dir(dirname($emptyPath))) {
            throw new RuntimeException('Unable to create empty loose object fixture directory');
        }
        file_put_contents($emptyPath, '');

        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $t->same(true, $store->contains($emptyOid));
        $t->same([$emptyOid], $store->objectIds());

        foreach (['readHeader' => static fn () => $store->readHeader($emptyOid), 'read' => static fn () => $store->read($emptyOid)] as $operation => $callback) {
            try {
                $callback();
                throw new RuntimeException("Expected empty loose object {$operation} to fail");
            } catch (RuntimeException $exception) {
                $t->contains("Loose object file is empty: {$emptyOid}", $exception->getMessage());
            }
        }

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected empty loose object to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$emptyOid} could not be read exactly", $exception->getMessage());
            $t->contains("Loose object file is empty: {$emptyOid}", $exception->getMessage());
        }
    },
    'loose object store enforces allocation limits from declared header size' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-alloc-limit-' . bin2hex(random_bytes(4)) . '/objects';
        $oversizedOid = str_repeat('1', 40);
        $writeLooseStorage($objectsDirectory, $oversizedOid, "blob 1000\0tiny");
        $boundedStore = LooseObjectStore::fromObjectsDirectory($objectsDirectory, allocationLimitBytes: 16);

        $t->same(16, $boundedStore->allocationLimitBytes());
        $t->same([
            'type' => 'blob',
            'size' => 1000,
            'headerLength' => strlen("blob 1000\0"),
        ], $boundedStore->readHeader($oversizedOid));

        try {
            $boundedStore->read($oversizedOid);
            throw new RuntimeException('Expected loose object allocation limit to reject declared body size');
        } catch (RuntimeException $exception) {
            $t->same('Loose object declared size 1000 exceeds allocation limit 16 bytes', $exception->getMessage());
        }

        try {
            $boundedStore->verifyIntegrity();
            throw new RuntimeException('Expected loose object allocation limit to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$oversizedOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object declared size 1000 exceeds allocation limit 16 bytes', $exception->getMessage());
        }

        $unboundedStore = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        try {
            $unboundedStore->read($oversizedOid);
            throw new RuntimeException('Expected unbounded loose object read to fail on exact body length');
        } catch (RuntimeException $exception) {
            $t->same('Loose object inflated size mismatch: expected 1010, got 14', $exception->getMessage());
        }

        $t->throws(InvalidArgumentException::class, static fn () => LooseObjectStore::fromObjectsDirectory($objectsDirectory, allocationLimitBytes: -1));
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
    'loose object integrity accepts empty tree entry modes like gix-object' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-integrity-empty-tree-mode-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);
        $targetOid = str_repeat('0', 40);
        $targetBytes = hex2bin($targetOid);
        if ($targetBytes === false) {
            throw new RuntimeException('Unable to decode empty-mode tree target oid');
        }
        $treeObject = new GitObject('tree', " block.html\0" . $targetBytes);
        $treeOid = $store->write($treeObject);
        $treeHeader = $store->readHeader($treeOid);
        $parsed = Tree::parse($store->read($treeOid)->body);
        $summary = $store->verifyIntegrity();

        $t->same('tree', $treeHeader['type']);
        $t->same(strlen($treeObject->body), $treeHeader['size']);
        $t->same(1, count($parsed->entries));
        $t->same('', $parsed->entries[0]->mode);
        $t->same('block.html', $parsed->entries[0]->filename);
        $t->same($targetOid, $parsed->entries[0]->oid);
        $t->same('commit', $parsed->entries[0]->kind());
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$treeOid],
        ], $summary);
    },
    'loose object integrity honors interruption callbacks after verified objects' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-integrity-interrupt-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);
        $firstOid = $store->write(new GitObject('blob', 'first block export'));
        $secondOid = $store->write(new GitObject('blob', 'second block export'));
        $expected = [$firstOid, $secondOid];
        sort($expected, SORT_STRING);

        $calls = [];
        try {
            $store->verifyIntegrity(static function (string $oid, int $verifiedCount) use (&$calls): bool {
                $calls[] = ['oid' => $oid, 'count' => $verifiedCount];

                return $verifiedCount === 1;
            });
            throw new RuntimeException('Expected interrupted loose object integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object integrity verification interrupted after ' . $expected[0], $exception->getMessage());
        }
        $t->same([['oid' => $expected[0], 'count' => 1]], $calls);

        $seen = [];
        $t->same([
            'numObjects' => 2,
            'verifiedObjectIds' => $expected,
        ], $store->verifyIntegrity(static function (string $oid, int $verifiedCount) use (&$seen): bool {
            $seen[] = ['oid' => $oid, 'count' => $verifiedCount];

            return false;
        }));
        $t->same([
            ['oid' => $expected[0], 'count' => 1],
            ['oid' => $expected[1], 'count' => 2],
        ], $seen);
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
            $t->contains('Loose object inflated size mismatch: expected 20, got 13', $exception->getMessage());
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
    'loose object integrity visits nested iterator candidates before declaring a store clean' => static function (TestRunner $t): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-nested-candidate-' . bin2hex(random_bytes(4)) . '/objects';
        $staleOid = str_repeat('1', 40);
        $nestedPath = $objectsDirectory . '/stale/' . substr($staleOid, 0, 2) . '/' . substr($staleOid, 2);
        if (!mkdir(dirname($nestedPath), 0777, true) && !is_dir(dirname($nestedPath))) {
            throw new RuntimeException('Unable to create nested loose object candidate fixture');
        }
        file_put_contents($nestedPath, 'stale loose object candidate');

        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $t->same([], $store->objectIds());

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected nested loose object candidate to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$staleOid} could not be read exactly", $exception->getMessage());
            $t->contains('Loose object not found', $exception->getMessage());
        }
    },
    'loose object integrity reports broken symlink candidates as missing objects like gix odb' => static function (TestRunner $t): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-broken-symlink-' . bin2hex(random_bytes(4)) . '/objects';
        $staleOid = str_repeat('2', 40);
        $stalePath = $objectsDirectory . '/' . substr($staleOid, 0, 2) . '/' . substr($staleOid, 2);
        if (!mkdir(dirname($stalePath), 0777, true) && !is_dir(dirname($stalePath))) {
            throw new RuntimeException('Unable to create broken loose object symlink directory');
        }
        if (!symlink($objectsDirectory . '/missing-target', $stalePath)) {
            throw new RuntimeException('Unable to create broken loose object symlink fixture');
        }

        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);
        $t->same(false, $store->contains($staleOid));
        $t->same(null, $store->tryReadHeader($staleOid));
        $t->same(null, $store->tryRead($staleOid));
        try {
            $store->readHeader($staleOid);
            throw new RuntimeException('Expected broken loose object symlink header lookup to be missing');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object not found: {$staleOid}", $exception->getMessage());
        }

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected broken loose object symlink to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$staleOid} could not be read exactly", $exception->getMessage());
            $t->contains("Loose object not found: {$staleOid}", $exception->getMessage());
        }
    },
    'loose object integrity counts duplicate case-normalized iterator candidates' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-integrity-case-duplicate-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);
        $object = null;
        $oid = null;
        for ($i = 0; $i < 100; $i++) {
            $candidate = new GitObject('blob', "WordPress case-variant loose object candidate {$i}");
            $candidateOid = $candidate->oid();
            if (strtoupper($candidateOid) !== $candidateOid) {
                $object = $candidate;
                $oid = $candidateOid;
                break;
            }
        }
        if (!$object instanceof GitObject || $oid === null) {
            throw new RuntimeException('Unable to create mixed-case loose object id fixture');
        }

        $store->write($object);
        $caseVariant = strtoupper($oid);
        $caseVariantPath = $gitDir . '/objects/' . substr($caseVariant, 0, 2) . '/' . substr($caseVariant, 2);
        if (!is_dir(dirname($caseVariantPath)) && !mkdir(dirname($caseVariantPath), 0777, true) && !is_dir(dirname($caseVariantPath))) {
            throw new RuntimeException('Unable to create loose object case-variant candidate directory');
        }
        file_put_contents($caseVariantPath, 'stale case-variant loose object candidate');

        $t->same([$oid], $store->objectIds());
        $t->same([
            'numObjects' => 2,
            'verifiedObjectIds' => [$oid, $oid],
        ], $store->verifyIntegrity());
    },
    'loose object integrity ignores traversal errors but verifies yielded objects' => static function (TestRunner $t): void {
        $gitDir = sys_get_temp_dir() . '/port-libs-git-integrity-unwalkable-' . bin2hex(random_bytes(4)) . '/.git';
        $store = new LooseObjectStore($gitDir);
        $oid = $store->write(new GitObject('blob', 'WordPress object database stays verifiable'));
        $unwalkableDirectory = $gitDir . '/objects/transient-unwalkable';
        if (!mkdir($unwalkableDirectory, 0777, true) && !is_dir($unwalkableDirectory)) {
            throw new RuntimeException("Unable to create unwalkable loose object directory: {$unwalkableDirectory}");
        }
        file_put_contents($unwalkableDirectory . '/ignored.tmp', 'transient deployment scratch file');
        chmod($unwalkableDirectory, 0000);

        try {
            $summary = $store->verifyIntegrity();
        } finally {
            chmod($unwalkableDirectory, 0777);
        }

        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$oid],
        ], $summary);
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
    'loose object integrity accepts negative zero size headers only under canonical ids' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-negative-zero-size-' . bin2hex(random_bytes(4)) . '/objects';
        $canonicalObject = new GitObject('blob', '');
        $canonicalOid = $canonicalObject->oid();
        $negativeZeroStorage = "blob -0\0";
        $writeLooseStorage($objectsDirectory, $canonicalOid, $negativeZeroStorage);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->same([
            'type' => 'blob',
            'size' => 0,
            'headerLength' => strlen("blob -0\0"),
        ], $store->readHeader($canonicalOid));
        $t->same('', $store->read($canonicalOid)->body);
        $t->same([
            'numObjects' => 1,
            'verifiedObjectIds' => [$canonicalOid],
        ], $store->verifyIntegrity());

        $nonCanonicalStore = LooseObjectStore::fromObjectsDirectory(sys_get_temp_dir() . '/port-libs-git-integrity-negative-zero-mismatch-' . bin2hex(random_bytes(4)) . '/objects');
        $nonCanonicalOid = hash('sha1', $negativeZeroStorage);
        $writeLooseStorage($nonCanonicalStore->objectsDirectory(), $nonCanonicalOid, $negativeZeroStorage);
        try {
            $nonCanonicalStore->verifyIntegrity();
            throw new RuntimeException('Expected negative-zero noncanonical loose object path to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains('Loose object hash mismatch', $exception->getMessage());
            $t->contains($nonCanonicalOid, $exception->getMessage());
            $t->contains($canonicalOid, $exception->getMessage());
        }
    },
    'loose object integrity rejects LF-tailed size headers like gix btoi parsing' => static function (TestRunner $t) use ($writeLooseStorage): void {
        $objectsDirectory = sys_get_temp_dir() . '/port-libs-git-integrity-lf-size-' . bin2hex(random_bytes(4)) . '/objects';
        $canonicalObject = new GitObject('blob', 'abc');
        $canonicalOid = $canonicalObject->oid();
        $lfSizeStorage = "blob 3\n\0abc";
        $writeLooseStorage($objectsDirectory, $canonicalOid, $lfSizeStorage);
        $store = LooseObjectStore::fromObjectsDirectory($objectsDirectory);

        $t->throws(InvalidArgumentException::class, static fn () => GitObject::decodeLooseHeader($lfSizeStorage));
        $t->same(true, $store->contains($canonicalOid));
        try {
            $store->readHeader($canonicalOid);
            throw new RuntimeException('Expected LF-tailed loose object size header lookup to fail');
        } catch (InvalidArgumentException $exception) {
            $t->contains("Invalid Git object header: blob 3\n", $exception->getMessage());
        }

        try {
            $store->read($canonicalOid);
            throw new RuntimeException('Expected LF-tailed loose object size header read to fail');
        } catch (InvalidArgumentException $exception) {
            $t->contains("Invalid Git object header: blob 3\n", $exception->getMessage());
        }

        try {
            $store->verifyIntegrity();
            throw new RuntimeException('Expected LF-tailed loose object size header to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("Loose object {$canonicalOid} could not be read exactly", $exception->getMessage());
            $t->contains("Invalid Git object header: blob 3\n", $exception->getMessage());
        }
    },
    'loose object integrity rejects CRLF-normalized structured headers like gix object decode' => static function (TestRunner $t): void {
        $treeOid = str_repeat('a', 40);
        $commitBody = "tree {$treeOid}\r\n"
            . "author WordPress Importer <importer@example.test> 1710000000 +0000\r\n"
            . "committer WordPress Deploy Bot <deploy@example.test> 1710000300 +0000\r\n"
            . "\n"
            . "Import block snapshot with CRLF object headers\n";
        $commitObject = new GitObject('commit', $commitBody);
        $commitStore = new LooseObjectStore(sys_get_temp_dir() . '/port-libs-git-integrity-crlf-commit-' . bin2hex(random_bytes(4)) . '/.git');
        $commitOid = $commitStore->write($commitObject);

        $t->same($commitOid, $commitObject->oid());
        $t->same($commitBody, $commitStore->read($commitOid)->body);
        $t->throws(InvalidArgumentException::class, static fn () => Commit::parse($commitBody));
        try {
            $commitStore->verifyIntegrity();
            throw new RuntimeException('Expected CRLF-normalized commit object headers to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("commit object {$commitOid} could not be decoded", $exception->getMessage());
            $t->contains('Commit tree must be a 40-character sha1 hex object id', $exception->getMessage());
        }

        $tagTarget = str_repeat('b', 40);
        $tagBody = "object {$tagTarget}\r\n"
            . "type commit\r\n"
            . "tag deploy/crlf-header\r\n"
            . "\n"
            . "Tag body after CRLF object headers\n";
        $tagObject = new GitObject('tag', $tagBody);
        $tagStore = new LooseObjectStore(sys_get_temp_dir() . '/port-libs-git-integrity-crlf-tag-' . bin2hex(random_bytes(4)) . '/.git');
        $tagOid = $tagStore->write($tagObject);

        $t->same($tagOid, $tagObject->oid());
        $t->same($tagBody, $tagStore->read($tagOid)->body);
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse($tagBody));
        try {
            $tagStore->verifyIntegrity();
            throw new RuntimeException('Expected CRLF-normalized tag object headers to fail integrity verification');
        } catch (RuntimeException $exception) {
            $t->contains("tag object {$tagOid} could not be decoded", $exception->getMessage());
            $t->contains('Git tag target must be a 40-character sha1 hex object id', $exception->getMessage());
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
        $t->same(true, $summary['zeroPaddedSizeHeaderAccepted']);
        $t->same($fixture['expectedBlobOid'], $summary['zeroPaddedSizeCanonicalOid']);
        $t->same($fixture['zeroPaddedSizeLooseHeaderOid'], $summary['zeroPaddedSizeRawHeaderOid']);
        $t->same(true, $summary['zeroPaddedSizeIntegrityVerified']);
        $t->same(true, $summary['zeroPaddedSizeNonCanonicalRejected']);
        $t->contains('Loose object hash mismatch', $summary['zeroPaddedSizeNonCanonicalMessage']);
        $t->same(true, $summary['negativeZeroSizeHeaderAccepted']);
        $t->same($fixture['emptyBlobOid'], $summary['negativeZeroSizeCanonicalOid']);
        $t->same($fixture['negativeZeroSizeLooseHeaderOid'], $summary['negativeZeroSizeRawHeaderOid']);
        $t->same(true, $summary['lfSizeHeaderRejected']);
        $t->contains("Invalid Git object header: blob " . strlen($fixture['blockBlobBody']) . "\n", $summary['lfSizeHeaderMessage']);
        $t->same(true, $summary['lfSizeReadRejected']);
        $t->same(true, $summary['lfSizeIntegrityRejected']);
        $t->contains('could not be read exactly', $summary['lfSizeIntegrityMessage']);
        $t->same(true, $summary['missingNulHeaderRejected']);
        $t->same('Did not find 0 byte in header', $summary['missingNulHeaderMessage']);
        $t->same(true, $summary['missingNulReadRejected']);
        $t->same(true, $summary['missingNulIntegrityRejected']);
        $t->contains('Did not find 0 byte in header', $summary['missingNulIntegrityMessage']);
        $t->same(true, $summary['noTypeSizeDelimiterHeaderRejected']);
        $t->same("Expected '<type> <size>'", $summary['noTypeSizeDelimiterHeaderMessage']);
        $t->same(true, $summary['noTypeSizeDelimiterReadRejected']);
        $t->same(true, $summary['noTypeSizeDelimiterIntegrityRejected']);
        $t->contains("Expected '<type> <size>'", $summary['noTypeSizeDelimiterIntegrityMessage']);
        $t->same(true, $summary['nulBeforeSpaceUnknownKindHeaderRejected']);
        $t->same("Unknown object kind: blob\0", $summary['nulBeforeSpaceUnknownKindHeaderMessage']);
        $t->same(true, $summary['nulBeforeSpaceUnknownKindReadRejected']);
        $t->same(true, $summary['nulBeforeSpaceUnknownKindIntegrityRejected']);
        $t->contains("Unknown object kind: blob\0", $summary['nulBeforeSpaceUnknownKindIntegrityMessage']);
        $t->same(true, $summary['unknownKindHeaderRejected']);
        $t->same('Unknown object kind: wordpress', $summary['unknownKindHeaderMessage']);
        $t->same(true, $summary['unknownKindReadRejected']);
        $t->same(true, $summary['unknownKindIntegrityRejected']);
        $t->contains('Unknown object kind: wordpress', $summary['unknownKindIntegrityMessage']);
        $t->same(true, $summary['overlongHeaderRejected']);
        $t->same('Did not find 0 byte in header', $summary['overlongHeaderMessage']);
        $t->same(true, $summary['overlongReadRejected']);
        $t->same(true, $summary['overlongIntegrityRejected']);
        $t->contains('Did not find 0 byte in header', $summary['overlongIntegrityMessage']);
        $t->same($fixture['allocationLimitBytes'], $summary['allocationLimitBytes']);
        $t->same(4096, $summary['oversizedHeaderSize']);
        $t->same(true, $summary['allocationLimitRejected']);
        $t->same($fixture['allocationLimitMessage'], $summary['allocationLimitMessage']);
        $t->same(true, $summary['trailingStreamIgnored']);
        $t->same(true, $summary['trailingStreamIntegrityVerified']);
        $t->same(true, $summary['lateSameStreamOverrunIgnored']);
        $t->same(true, $summary['lateSameStreamIntegrityVerified']);
        $t->same($fixture['lateSameStreamOid'], (new GitObject('blob', $fixture['lateSameStreamBody']))->oid());
        $t->same(true, $summary['truncatedHeaderInflateRejected']);
        $t->contains('Unable to inflate loose object header: ' . $fixture['truncatedHeaderOid'], $summary['truncatedHeaderMessage']);
        $t->same(true, $summary['corruptFirstWindowHeaderRejected']);
        $t->contains('Unable to inflate loose object header: ' . str_repeat('8', 40), $summary['corruptFirstWindowHeaderMessage']);
        $t->same(true, $summary['finalizedReadOnly']);
        $t->same(true, $summary['finalizedExistingObjectPreserved']);
        $t->same(true, $summary['integrityInterruptHandled']);
        $t->same(1, $summary['integrityInterruptChecks']);
        $t->contains('Loose object integrity verification interrupted after ', $summary['integrityInterruptMessage']);
    },
];
