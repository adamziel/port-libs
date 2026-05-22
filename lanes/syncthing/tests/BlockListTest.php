<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;

return [
    'splits file bytes into deterministic content blocks' => static function (TestRunner $t): void {
        $blocks = (new BlockList())->fromBytes('abcdefghi', 4);
        $t->same(3, count($blocks));
        $t->same(4, $blocks[1]->offset);
        $t->same(1, $blocks[2]->size);
        $t->same(hash('sha256', 'efgh'), $blocks[1]->hashHex);
    },
    'verifies block hashes against file bytes' => static function (TestRunner $t): void {
        $list = new BlockList();
        $blocks = $list->fromBytes('abcdefghi', 4);
        $t->true($list->verify('abcdefghi', $blocks));
        $t->true(!$list->verify('abcxefghi', $blocks));
    },
];

