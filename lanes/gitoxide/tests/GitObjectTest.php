<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\LooseObjectStore;

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
    },
];
