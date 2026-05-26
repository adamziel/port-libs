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
];

