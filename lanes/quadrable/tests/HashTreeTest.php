<?php

declare(strict_types=1);

use PortLibs\Quadrable\HashTree;

return [
    'blake2s primitive matches upstream key hash vectors' => static function (TestRunner $t): void {
        $tree = new HashTree();

        $t->same('69217a3079908094e11121d042354a7c1f55b6482ca1a51e1b250dfd1ed0eef9', $tree->keyHash(''));
        $t->same('19213bacc58dee6dbde3ceb9a47cbb330b3d86f8cca8997eb00be456f140ca25', $tree->keyHash('hello'));
        $t->same('214f24fe1118eb854450238e11bebe22d2e3937ed85c7c96c6c010106b752ad3', $tree->valueHash(str_repeat('a', 100)));
    },
    'empty branch preserves quadrable sparse empty root rule' => static function (TestRunner $t): void {
        $tree = new HashTree();
        $t->same(HashTree::EMPTY_HASH, $tree->branchHash(HashTree::EMPTY_HASH, HashTree::EMPTY_HASH));
    },
    'leaf hashes include blake2s hashed key value and domain separator' => static function (TestRunner $t): void {
        $tree = new HashTree();
        $t->same('cddd4a0dafbddc6e0f6e820e79f8e1aec92a891568283929549d772c16435329', $tree->leafHash('post-1', 'content'));
        $t->same('a3b0b50fe1ab6ff69ab9222c6b622b2c9f39920f7b8579790461fda909bb90c4', $tree->branchHash(str_repeat('11', 32), str_repeat('22', 32)));
    },
    'bits are read from most significant bit first' => static function (TestRunner $t): void {
        $tree = new HashTree();
        $hash = '80' . str_repeat('00', 31);
        $t->same(1, $tree->bitAt($hash, 0));
        $t->same(0, $tree->bitAt($hash, 1));
    },
];
