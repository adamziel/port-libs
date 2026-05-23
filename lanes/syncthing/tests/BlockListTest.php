<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;

return [
    'maps upstream scanner block hash fixtures' => static function (TestRunner $t): void {
        $fixtures = [
            ['', 1024, [
                'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            ]],
            ['contents', 1024, [
                'd1b2a59fbea7e20077af9f91b27e95e865061b270be03ff539ab3b73587882e8',
            ]],
            ['contents', 7, [
                'ed7002b439e9ac845f22357d822bac1444730fbdb6016d3ec9432297b9ec9f73',
                '043a718774c572bd8a25adbeb1bfcd5c0256ae11cecf9f9c3f925d0e52beaf89',
            ]],
            ['contents', 3, [
                '1143da2bc54c495c4be31d3868785d39ffdfd56df5668f0645d8f14d47647952',
                'e4432baa90819aaef51d2a7f8e148bf7e679610f3173752fabb4dcb2d0f418d3',
                '44ad63f60af0f6db6fdde6d5186ef78176367df261fa06be3079b6c80c8adba4',
            ]],
        ];

        $list = new BlockList();
        foreach ($fixtures as [$bytes, $blockSize, $hashes]) {
            $blocks = $list->fromBytes($bytes, $blockSize);
            $t->same(count($hashes), count($blocks));
            foreach ($hashes as $index => $hash) {
                $t->same($hash, $blocks[$index]->hashHex);
            }
        }
    },
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
        $t->true(!$list->verify('abcdefghi', array_slice($blocks, 0, 2)));
    },
    'selects syncthing block sizes from file length' => static function (TestRunner $t): void {
        $t->same(128 << 10, BlockList::blockSizeForFileSize(0));
        $t->same(128 << 10, BlockList::blockSizeForFileSize((2000 * (128 << 10)) - 1));
        $t->same(256 << 10, BlockList::blockSizeForFileSize(2000 * (128 << 10)));
        $t->same(16 << 20, BlockList::blockSizeForFileSize(PHP_INT_MAX));
    },
    'maps upstream scanner block size hysteresis from current FileInfo' => static function (TestRunner $t): void {
        $fileSize = 500 << 20;

        $t->same(512 << 10, BlockList::blockSizeForFileSize($fileSize));
        $t->same(256 << 10, BlockList::blockSizeForFileSize($fileSize, 256 << 10));
        $t->same(1 << 20, BlockList::blockSizeForFileSize($fileSize, 1 << 20));
        $t->same(512 << 10, BlockList::blockSizeForFileSize($fileSize, 128 << 10));
        $t->same(512 << 10, BlockList::blockSizeForFileSize($fileSize, 2 << 20));
        $t->same(128 << 10, BlockList::blockSizeForFileSize(1024, 0));
    },
    'hashes block lists and validates optional hashes' => static function (TestRunner $t): void {
        $list = new BlockList();
        $blocks = $list->fromBytes('contents', 3);
        $t->same(hash('sha256', hex2bin($blocks[0]->hashHex . $blocks[1]->hashHex . $blocks[2]->hashHex)), $list->hashBlocks($blocks));
        $t->true($list->validateBytes('contents', hash('sha256', 'contents')));
        $t->true($list->validateBytes('contents', ''));
        $t->true(!$list->validateBytes('contents', hash('sha256', 'changed')));
    },
    'rejects invalid block sizing input' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new BlockList())->fromBytes('content', 0));
        $t->throws(InvalidArgumentException::class, static fn () => BlockList::blockSizeForFileSize(-1));
        $t->throws(InvalidArgumentException::class, static fn () => BlockList::blockSizeForFileSize(1, -1));
    },
];
