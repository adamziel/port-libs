<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteVarint;

return [
    'parses core sqlite database header fields' => static function (TestRunner $t): void {
        $header = str_repeat("\0", 100);
        $header = substr_replace($header, "SQLite format 3\0", 0, 16);
        $header = substr_replace($header, pack('n', 4096), 16, 2);
        $header[18] = "\x01";
        $header[19] = "\x01";
        $header[20] = "\x00";
        $header = substr_replace($header, pack('N', 2), 28, 4);
        $header = substr_replace($header, pack('N', 1), 56, 4);
        $parsed = SQLiteHeader::parse($header);
        $t->same(4096, $parsed->pageSize);
        $t->same(1, $parsed->writeVersion);
        $t->same(2, $parsed->databaseSizePages);
        $t->same(1, $parsed->textEncoding);
    },
    'sqlite varints decode one and multi byte values' => static function (TestRunner $t): void {
        $t->same([127, 1], SQLiteVarint::decode("\x7f"));
        $t->same([128, 2], SQLiteVarint::decode("\x81\x00"));
        $t->same([16384, 3], SQLiteVarint::decode("\x81\x80\x00"));
    },
    'sqlite header rejects non power of two page size' => static function (TestRunner $t): void {
        $bad = str_pad("SQLite format 3\0" . pack('n', 1000), 100, "\0");
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteHeader::parse($bad));
    },
];
