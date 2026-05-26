<?php

declare(strict_types=1);

use PortLibs\Quadrable\HashTree;

return [
    'empty branch preserves quadrable sparse empty root rule' => static function (TestRunner $t): void {
        $tree = new HashTree();
        $t->same(HashTree::EMPTY_HASH, $tree->branchHash(HashTree::EMPTY_HASH, HashTree::EMPTY_HASH));
    },
    'leaf hashes include hashed key value and domain separator' => static function (TestRunner $t): void {
        $tree = new HashTree();
        $expected = hash('sha256', hex2bin(hash('sha256', 'post-1')) . hex2bin(hash('sha256', 'content')) . "\0");
        $t->same($expected, $tree->leafHash('post-1', 'content'));
    },
    'bits are read from most significant bit first' => static function (TestRunner $t): void {
        $tree = new HashTree();
        $hash = '80' . str_repeat('00', 31);
        $t->same(1, $tree->bitAt($hash, 0));
        $t->same(0, $tree->bitAt($hash, 1));
    },
];

