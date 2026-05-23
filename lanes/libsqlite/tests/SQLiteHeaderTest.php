<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteAutoincrementState;
use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexColumn;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonExtractIndexExpression;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSequenceRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteTrimIndexExpression;
use PortLibs\LibSqlite\SQLiteWordPressOption;
use PortLibs\LibSqlite\SQLiteVarint;

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 1): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$varint = static function (int $value): string {
    return SQLiteVarint::encode($value);
};

$recordPayload = static function (array $values) use ($varint): string {
    $serialTypes = [];
    $body = '';
    foreach ($values as $value) {
        if ($value === null) {
            $serialTypes[] = 0;
        } elseif (is_int($value)) {
            if ($value === 0) {
                $serialTypes[] = 8;
            } elseif ($value === 1) {
                $serialTypes[] = 9;
            } elseif ($value >= -128 && $value <= 127) {
                $serialTypes[] = 1;
                $body .= pack('C', $value & 0xff);
            } elseif ($value >= -32768 && $value <= 32767) {
                $serialTypes[] = 2;
                $body .= pack('n', $value & 0xffff);
            } else {
                $serialTypes[] = 4;
                $body .= pack('N', $value & 0xffffffff);
            }
        } elseif (is_float($value)) {
            $serialTypes[] = 7;
            $body .= pack('E', $value);
        } elseif (is_array($value) && isset($value['__sqlite_blob']) && is_string($value['__sqlite_blob'])) {
            $serialTypes[] = 12 + (strlen($value['__sqlite_blob']) * 2);
            $body .= $value['__sqlite_blob'];
        } elseif (is_string($value)) {
            $serialTypes[] = 13 + (strlen($value) * 2);
            $body .= $value;
        } else {
            throw new RuntimeException('Unsupported fixture record value');
        }
    }

    $serialHeader = implode('', array_map($varint, $serialTypes));
    $headerSize = strlen($serialHeader) + 1;

    return $varint($headerSize) . $serialHeader . $body;
};

$blobValue = static fn (string $bytes): array => ['__sqlite_blob' => $bytes];

$schemaCell = static function (array $values, int $rowId) use ($varint, $recordPayload): string {
    $payload = $recordPayload($values);

    return $varint(strlen($payload)) . $varint($rowId) . $payload;
};

$tableLeafPage = static function (array $cells, int $pageSize = 512, int $headerOffset = 0, ?string $basePage = null): string {
    $page = $basePage ?? str_repeat("\0", $pageSize);
    $cellCount = count($cells);
    $offset = $pageSize;
    $pointers = [];

    foreach ($cells as $cell) {
        $offset -= strlen($cell);
        if ($offset < $headerOffset + 8 + ($cellCount * 2)) {
            throw new RuntimeException('Fixture table leaf page has overlapping cells');
        }
        $page = substr_replace($page, $cell, $offset, strlen($cell));
        $pointers[] = $offset;
    }

    $page[$headerOffset] = "\x0d";
    $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
    $page = substr_replace($page, pack('n', $cellCount), $headerOffset + 3, 2);
    $contentStart = $cellCount === 0 ? $pageSize : min($pointers);
    $page = substr_replace($page, pack('n', $contentStart === 65536 ? 0 : $contentStart), $headerOffset + 5, 2);
    $page[$headerOffset + 7] = "\x00";

    foreach ($pointers as $index => $pointer) {
        $page = substr_replace($page, pack('n', $pointer), $headerOffset + 8 + ($index * 2), 2);
    }

    return $page;
};

$tableInteriorPage = static function (array $cells, int $rightMostPage, int $pageSize = 512, int $headerOffset = 0, ?string $basePage = null) use ($varint): string {
    $page = $basePage ?? str_repeat("\0", $pageSize);
    $cellCount = count($cells);
    $offset = $pageSize;
    $pointers = [];

    foreach ($cells as [$leftChildPage, $key]) {
        $cell = pack('N', $leftChildPage) . $varint($key);
        $offset -= strlen($cell);
        if ($offset < $headerOffset + 12 + ($cellCount * 2)) {
            throw new RuntimeException('Fixture table interior page has overlapping cells');
        }
        $page = substr_replace($page, $cell, $offset, strlen($cell));
        $pointers[] = $offset;
    }

    $page[$headerOffset] = "\x05";
    $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
    $page = substr_replace($page, pack('n', $cellCount), $headerOffset + 3, 2);
    $contentStart = $cellCount === 0 ? $pageSize : min($pointers);
    $page = substr_replace($page, pack('n', $contentStart === 65536 ? 0 : $contentStart), $headerOffset + 5, 2);
    $page[$headerOffset + 7] = "\x00";
    $page = substr_replace($page, pack('N', $rightMostPage), $headerOffset + 8, 4);

    foreach ($pointers as $index => $pointer) {
        $page = substr_replace($page, pack('n', $pointer), $headerOffset + 12 + ($index * 2), 2);
    }

    return $page;
};

$indexCell = static function (array $values) use ($varint, $recordPayload): string {
    $payload = $recordPayload($values);

    return $varint(strlen($payload)) . $payload;
};

$indexLeafPage = static function (array $cells, int $pageSize = 512, int $headerOffset = 0, ?string $basePage = null): string {
    $page = $basePage ?? str_repeat("\0", $pageSize);
    $cellCount = count($cells);
    $offset = $pageSize;
    $pointers = [];

    foreach ($cells as $cell) {
        $offset -= strlen($cell);
        if ($offset < $headerOffset + 8 + ($cellCount * 2)) {
            throw new RuntimeException('Fixture index leaf page has overlapping cells');
        }
        $page = substr_replace($page, $cell, $offset, strlen($cell));
        $pointers[] = $offset;
    }

    $page[$headerOffset] = "\x0a";
    $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
    $page = substr_replace($page, pack('n', $cellCount), $headerOffset + 3, 2);
    $contentStart = $cellCount === 0 ? $pageSize : min($pointers);
    $page = substr_replace($page, pack('n', $contentStart === 65536 ? 0 : $contentStart), $headerOffset + 5, 2);
    $page[$headerOffset + 7] = "\x00";

    foreach ($pointers as $index => $pointer) {
        $page = substr_replace($page, pack('n', $pointer), $headerOffset + 8 + ($index * 2), 2);
    }

    return $page;
};

$indexInteriorPage = static function (array $cells, int $rightMostPage, int $pageSize = 512, int $headerOffset = 0, ?string $basePage = null) use ($indexCell): string {
    $page = $basePage ?? str_repeat("\0", $pageSize);
    $cellCount = count($cells);
    $offset = $pageSize;
    $pointers = [];

    foreach ($cells as [$leftChildPage, $values]) {
        $cell = pack('N', $leftChildPage) . $indexCell($values);
        $offset -= strlen($cell);
        if ($offset < $headerOffset + 12 + ($cellCount * 2)) {
            throw new RuntimeException('Fixture index interior page has overlapping cells');
        }
        $page = substr_replace($page, $cell, $offset, strlen($cell));
        $pointers[] = $offset;
    }

    $page[$headerOffset] = "\x02";
    $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
    $page = substr_replace($page, pack('n', $cellCount), $headerOffset + 3, 2);
    $contentStart = $cellCount === 0 ? $pageSize : min($pointers);
    $page = substr_replace($page, pack('n', $contentStart === 65536 ? 0 : $contentStart), $headerOffset + 5, 2);
    $page[$headerOffset + 7] = "\x00";
    $page = substr_replace($page, pack('N', $rightMostPage), $headerOffset + 8, 4);

    foreach ($pointers as $index => $pointer) {
        $page = substr_replace($page, pack('n', $pointer), $headerOffset + 12 + ($index * 2), 2);
    }

    return $page;
};

$overflowLeafCell = static function (string $payload, int $rowId, int $firstOverflowPage, int $usableSize = 512) use ($varint): array {
    $localLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $usableSize);

    return [
        $varint(strlen($payload)) . $varint($rowId) . substr($payload, 0, $localLength) . pack('N', $firstOverflowPage),
        substr($payload, $localLength),
        $localLength,
    ];
};

$overflowPage = static function (string $payload, int $nextPage = 0, int $pageSize = 512): string {
    if (strlen($payload) > $pageSize - 4) {
        throw new RuntimeException('Fixture overflow payload does not fit on one page');
    }

    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, pack('N', $nextPage), 0, 4);

    return substr_replace($page, $payload, 4, strlen($payload));
};

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
    'sqlite varints encode upstream one through nine byte boundaries' => static function (TestRunner $t): void {
        $cases = [
            0 => '00',
            127 => '7f',
            128 => '8100',
            16383 => 'ff7f',
            16384 => '818000',
            0x0fffffff => 'ffffff7f',
            0x00ffffffffffffff => 'ffffffffffffff7f',
            PHP_INT_MAX => 'bfffffffffffffffff',
        ];

        foreach ($cases as $value => $hex) {
            $encoded = SQLiteVarint::encode($value);
            $t->same($hex, bin2hex($encoded), "Unexpected SQLite varint bytes for {$value}");
            $t->same([$value, strlen($encoded)], SQLiteVarint::decode($encoded), "SQLite varint did not round-trip {$value}");
        }

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteVarint::encode(-1));
    },
    'sqlite header rejects non power of two page size' => static function (TestRunner $t): void {
        $bad = str_pad("SQLite format 3\0" . pack('n', 1000), 100, "\0");
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteHeader::parse($bad));
    },
    'parses sqlite first page table leaf btree header' => static function (TestRunner $t) use ($makeFirstPage): void {
        $page = $makeFirstPage();
        $page[100] = "\x0d";
        $page = substr_replace($page, pack('n', 0), 101, 2);
        $page = substr_replace($page, pack('n', 1), 103, 2);
        $page = substr_replace($page, pack('n', 480), 105, 2);
        $page[107] = "\x00";
        $page = substr_replace($page, pack('n', 480), 108, 2);

        $btree = SQLiteBTreePageHeader::parseFirstPage($page);
        $t->same(512, $btree->pageSize);
        $t->same(100, $btree->headerOffset);
        $t->same('table-leaf', $btree->pageType);
        $t->true($btree->isLeaf());
        $t->true($btree->hasIntegerKeys());
        $t->true($btree->hasLeafData());
        $t->same(8, $btree->headerSize());
        $t->same(108, $btree->cellPointerArrayOffset());
        $t->same([480], $btree->cellPointers($page));
    },
    'parses sqlite interior btree header with rightmost pointer' => static function (TestRunner $t): void {
        $page = str_repeat("\0", 512);
        $page[0] = "\x02";
        $page = substr_replace($page, pack('n', 0), 1, 2);
        $page = substr_replace($page, pack('n', 2), 3, 2);
        $page = substr_replace($page, pack('n', 500), 5, 2);
        $page[7] = "\x05";
        $page = substr_replace($page, pack('N', 1234), 8, 4);
        $page = substr_replace($page, pack('n', 500), 12, 2);
        $page = substr_replace($page, pack('n', 506), 14, 2);

        $btree = SQLiteBTreePageHeader::parsePage($page, 512);
        $t->same('index-interior', $btree->pageType);
        $t->true($btree->isInterior());
        $t->same(false, $btree->hasIntegerKeys());
        $t->same(false, $btree->hasLeafData());
        $t->same(12, $btree->headerSize());
        $t->same(1234, $btree->rightMostPointer);
        $t->same(5, $btree->fragmentedFreeBytes);
        $t->same([500, 506], $btree->cellPointers($page));
    },
    'parses sqlite btree freeblock chains and free space accounting' => static function (TestRunner $t): void {
        $page = str_repeat("\0", 512);
        $page[0] = "\x0d";
        $page = substr_replace($page, pack('n', 420), 1, 2);
        $page = substr_replace($page, pack('n', 1), 3, 2);
        $page = substr_replace($page, pack('n', 400), 5, 2);
        $page[7] = "\x03";
        $page = substr_replace($page, pack('n', 400), 8, 2);
        $page = substr_replace($page, pack('n', 470) . pack('n', 20), 420, 4);
        $page = substr_replace($page, pack('n', 0) . pack('n', 16), 470, 4);

        $header = SQLiteBTreePageHeader::parsePage($page, 512);
        $freeblocks = $header->freeblocks($page);

        $t->same(SQLiteBTreeFreeblock::class, get_class($freeblocks[0]));
        $t->same([
            ['offset' => 420, 'size' => 20, 'end_offset' => 440, 'next_offset' => 470],
            ['offset' => 470, 'size' => 16, 'end_offset' => 486, 'next_offset' => null],
        ], array_map(static fn (SQLiteBTreeFreeblock $freeblock): array => $freeblock->toArray(), $freeblocks));
        $t->same(429, $header->freeSpaceBytes($page));
    },
    'rejects corrupt sqlite btree freeblock chains' => static function (TestRunner $t): void {
        $overlap = str_repeat("\0", 512);
        $overlap[0] = "\x0d";
        $overlap = substr_replace($overlap, pack('n', 420), 1, 2);
        $overlap = substr_replace($overlap, pack('n', 400), 5, 2);
        $overlap = substr_replace($overlap, pack('n', 430) . pack('n', 20), 420, 4);
        $overlapHeader = SQLiteBTreePageHeader::parsePage($overlap, 512);
        $t->throws(InvalidArgumentException::class, static fn () => $overlapHeader->freeblocks($overlap));

        $reserved = str_repeat("\0", 512);
        $reserved[0] = "\x0d";
        $reserved = substr_replace($reserved, pack('n', 490), 1, 2);
        $reserved = substr_replace($reserved, pack('n', 480), 5, 2);
        $reserved = substr_replace($reserved, pack('n', 0) . pack('n', 20), 490, 4);
        $reservedHeader = SQLiteBTreePageHeader::parsePage($reserved, 512);
        $t->throws(InvalidArgumentException::class, static fn () => $reservedHeader->freeblocks($reserved, 500));

        $badAccounting = str_repeat("\0", 512);
        $badAccounting[0] = "\x0d";
        $badAccounting = substr_replace($badAccounting, pack('n', 500), 5, 2);
        $badAccounting[7] = "\x14";
        $badAccountingHeader = SQLiteBTreePageHeader::parsePage($badAccounting, 512);
        $t->throws(InvalidArgumentException::class, static fn () => $badAccountingHeader->freeSpaceBytes($badAccounting));
    },
    'parses sqlite table interior cells with child page and rowid separator' => static function (TestRunner $t) use ($tableInteriorPage): void {
        $page = $tableInteriorPage([[2, 200]], 3);
        $header = SQLiteBTreePageHeader::parsePage($page, 512);
        $cells = SQLiteTableInteriorCell::parsePageCells($page, $header);

        $t->same('table-interior', $header->pageType);
        $t->same(3, $header->rightMostPointer);
        $t->same(1, count($cells));
        $t->same(2, $cells[0]->leftChildPage);
        $t->same(200, $cells[0]->key);
        $t->same(6, $cells[0]->bytesRead);
    },
    'parses sqlite 65536 byte page zero cell content start' => static function (TestRunner $t): void {
        $page = str_repeat("\0", 65536);
        $page[0] = "\x0a";
        $page = substr_replace($page, pack('n', 0), 1, 2);
        $page = substr_replace($page, pack('n', 0), 3, 2);
        $page = substr_replace($page, pack('n', 0), 5, 2);
        $page[7] = "\x00";

        $btree = SQLiteBTreePageHeader::parsePage($page, 65536);
        $t->same('index-leaf', $btree->pageType);
        $t->same(65536, $btree->cellContentAreaStart);
        $t->same([], $btree->cellPointers($page));
    },
    'sqlite btree header rejects invalid page flags' => static function (TestRunner $t): void {
        $page = str_repeat("\0", 512);
        $page[0] = "\x00";
        $page = substr_replace($page, pack('n', 512), 5, 2);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreePageHeader::parsePage($page, 512));
    },
    'parses table leaf cell rowid payload and sqlite record values' => static function (TestRunner $t) use ($varint, $recordPayload): void {
        $payload = $recordPayload(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text)']);
        $cell = $varint(strlen($payload)) . $varint(1) . $payload;
        $page = str_repeat("\0", 512);
        $offset = 512 - strlen($cell);
        $page = substr_replace($page, $cell, $offset, strlen($cell));

        $parsed = SQLiteTableLeafCell::parse($page, $offset);
        $record = SQLiteRecord::parse($parsed->payload);

        $t->same(strlen($payload), $parsed->payloadLength);
        $t->same(1, $parsed->rowId);
        $t->same('wp_options', $record->values[1]);
        $t->same(2, $record->values[3]);
        $t->contains('CREATE TABLE wp_options', $record->values[4]);
    },
    'table leaf cells reject truncated payloads' => static function (TestRunner $t) use ($varint): void {
        $page = str_repeat("\0", 512);
        $cell = $varint(12) . $varint(3) . 'short';
        $offset = 512 - strlen($cell);
        $page = substr_replace($page, $cell, $offset, strlen($cell));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTableLeafCell::parse($page, $offset));
    },
    'computes sqlite table leaf local payload length for overflow rows' => static function (TestRunner $t): void {
        $t->same(477, SQLiteTableLeafCell::localPayloadLength(477, 512));
        $t->same(39, SQLiteTableLeafCell::localPayloadLength(478, 512));
        $t->same(78, SQLiteTableLeafCell::localPayloadLength(586, 512));
    },
    'parses sqlite index leaf and interior cells' => static function (TestRunner $t) use ($indexCell, $indexLeafPage, $indexInteriorPage): void {
        $leafPage = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $leafHeader = SQLiteBTreePageHeader::parsePage($leafPage, 512);
        $leafCells = SQLiteIndexCell::parsePageCells($leafPage, $leafHeader);

        $interiorPage = $indexInteriorPage([[2, ['home', 2]]], 3);
        $interiorHeader = SQLiteBTreePageHeader::parsePage($interiorPage, 512);
        $interiorCells = SQLiteIndexCell::parsePageCells($interiorPage, $interiorHeader);

        $t->same('index-leaf', $leafHeader->pageType);
        $t->same(null, $leafCells[0]->leftChildPage);
        $t->same(['home', 2], $leafCells[0]->record()->values);
        $t->same('index-interior', $interiorHeader->pageType);
        $t->same(2, $interiorCells[0]->leftChildPage);
        $t->same(['home', 2], $interiorCells[0]->record()->values);
    },
    'computes sqlite index local payload length for overflow records' => static function (TestRunner $t): void {
        $t->same(102, SQLiteIndexCell::localPayloadLength(102, 512));
        $t->same(39, SQLiteIndexCell::localPayloadLength(103, 512));
        $t->same(82, SQLiteIndexCell::localPayloadLength(590, 512));
    },
    'sqlite records decode core serial types' => static function (TestRunner $t) use ($varint): void {
        $serialTypes = [0, 8, 9, 1, 2, 3, 4, 5, 6, 7, 18, 19];
        $header = implode('', array_map($varint, $serialTypes));
        $payload = $varint(strlen($header) + 1)
            . $header
            . "\xfe"
            . "\xff\xfe"
            . "\xff\xff\xfe"
            . "\xff\xff\xff\xfe"
            . "\xff\xff\xff\xff\xff\xfe"
            . "\xff\xff\xff\xff\xff\xff\xff\xfe"
            . pack('E', 1.5)
            . "\x00A\xff"
            . 'abc';

        $record = SQLiteRecord::parse($payload);

        $t->same($serialTypes, $record->serialTypes);
        $t->same([null, 0, 1, -2, -2, -2, -2, -2, -2, 1.5, "\x00A\xff", 'abc'], $record->values);
        $t->same(strlen($payload), $record->bytesRead);
    },
    'encodes sqlite records using upstream serial type widths' => static function (TestRunner $t): void {
        $values = [
            null,
            0,
            1,
            -128,
            -129,
            32767,
            32768,
            8388607,
            8388608,
            2147483647,
            2147483648,
            140737488355327,
            140737488355328,
            -140737488355328,
            1.5,
            SQLiteRecord::blob("\x00A"),
            'abc',
        ];

        $payload = SQLiteRecord::encode($values);
        $record = SQLiteRecord::parse($payload);

        $t->same([0, 8, 9, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 5, 7, 16, 19], $record->serialTypes);
        $t->same($values[0], $record->values[0]);
        $t->same(0, $record->values[1]);
        $t->same(1, $record->values[2]);
        $t->same(-128, $record->values[3]);
        $t->same(-129, $record->values[4]);
        $t->same(140737488355328, $record->values[12]);
        $t->same(-140737488355328, $record->values[13]);
        $t->same(1.5, $record->values[14]);
        $t->same("\x00A", $record->values[15]);
        $t->same('abc', $record->values[16]);
    },
    'encodes sqlite record headers whose size varint expands' => static function (TestRunner $t): void {
        $payload = SQLiteRecord::encode(array_fill(0, 130, null));
        $record = SQLiteRecord::parse($payload);

        $t->same([132, 2], SQLiteVarint::decode($payload));
        $t->same(array_fill(0, 130, 0), $record->serialTypes);
        $t->same(array_fill(0, 130, null), $record->values);
    },
    'encodes sqlite table leaf cells including overflow pointers and minimum cell size' => static function (TestRunner $t): void {
        $tiny = SQLiteTableLeafCell::encode(1, '');
        $overflowPayload = str_repeat('x', 586);
        $overflow = SQLiteTableLeafCell::encode(5, $overflowPayload, 512, 3);

        $t->same(4, strlen($tiny));
        $t->same('00010000', bin2hex($tiny));
        $t->same(85, strlen($overflow));
        $t->same('844a05', bin2hex(substr($overflow, 0, 3)));
        $t->same('00000003', bin2hex(substr($overflow, -4)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTableLeafCell::encode(5, $overflowPayload, 512));
    },
    'assembles wordpress wp_options table leaf pages from native record and cell encoders' => static function (TestRunner $t) use ($makeFirstPage): void {
        $schemaCell = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
            'table',
            'wp_options',
            'wp_options',
            2,
            'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
        ]));
        $siteUrlCell = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
            null,
            'siteurl',
            'https://example.test',
            'yes',
        ]));
        $homeCell = SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
            null,
            'home',
            'https://example.test',
            'yes',
        ]));

        $page1 = SQLiteTableLeafPage::assemble([$schemaCell], 512, 100, $makeFirstPage(512, 2));
        $page2 = SQLiteTableLeafPage::assemble([$siteUrlCell, $homeCell]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2);
        $options = $database->wordpressOptions();

        $t->same('table-leaf', $database->pageHeader(2)->pageType);
        $t->same(2, $database->pageHeader(2)->cellCount);
        $t->same(['siteurl', 'home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test', 'https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTableLeafPage::assemble([str_repeat('x', 500), str_repeat('y', 500)]));
    },
    'decodes sqlite_schema table records from a first-page table leaf cell' => static function (TestRunner $t) use ($makeFirstPage, $varint, $recordPayload): void {
        $payload = $recordPayload(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text)']);
        $cell = $varint(strlen($payload)) . $varint(1) . $payload;
        $page = $makeFirstPage();
        $cellOffset = 512 - strlen($cell);
        $page[100] = "\x0d";
        $page = substr_replace($page, pack('n', 0), 101, 2);
        $page = substr_replace($page, pack('n', 1), 103, 2);
        $page = substr_replace($page, pack('n', $cellOffset), 105, 2);
        $page[107] = "\x00";
        $page = substr_replace($page, pack('n', $cellOffset), 108, 2);
        $page = substr_replace($page, $cell, $cellOffset, strlen($cell));

        $header = SQLiteBTreePageHeader::parseFirstPage($page);
        $cells = SQLiteTableLeafCell::parsePageCells($page, $header);
        $schema = SQLiteSchemaRecord::fromTableLeafCell($cells[0], SQLiteHeader::parse($page)->textEncoding);

        $t->same('table', $schema->type);
        $t->true($schema->isTable('wp_options'));
        $t->same(2, $schema->rootPage);
        $t->contains('option_value text', $schema->sql ?? '');
    },
    'walks sqlite_schema interior pages to resolve wordpress table roots' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage): void {
        $page1 = $tableInteriorPage(
            [[2, 2]],
            3,
            512,
            100,
            $makeFirstPage(512, 5),
        );
        $page2 = $tableLeafPage([
            $schemaCell(['table', 'wp_posts', 'wp_posts', 4, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'], 1),
        ]);
        $page3 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text)'], 3),
        ]);
        $page4 = $tableLeafPage([]);
        $page5 = $tableLeafPage([]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $records = $database->schemaRecords();

        $t->same(5, $database->pageCount());
        $t->same(['wp_posts', 'wp_options'], array_map(static fn (SQLiteSchemaRecord $record): string => $record->name, $records));
        $t->same(4, $database->tableRootPage('wp_posts'));
        $t->same(5, $database->tableRootPage('wp_options'));
        $t->same(null, $database->tableRootPage('wp_missing'));
        $t->same('table-leaf', $database->tablePageHeader('wp_options')?->pageType);
    },
    'reads bounded wordpress options from a resolved table root page' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 2));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 2),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2);

        $rows = $database->tableRowsByName('wp_options');
        $options = $database->wordpressOptions(1);

        $t->same(2, count($rows));
        $t->same(1, $rows[0]->rowId);
        $t->same([null, 'siteurl', 'https://example.test', 'yes'], $rows[0]->values());
        $t->same(1, count($options));
        $t->true($options[0] instanceof SQLiteWordPressOption);
        $t->same([
            'option_id' => 1,
            'option_name' => 'siteurl',
            'option_value' => 'https://example.test',
            'autoload' => 'yes',
            'rowid' => 1,
        ], $options[0]->toArray());
    },
    'walks interior wp_options table pages while respecting row limits' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 4));
        $page2 = $tableInteriorPage([[3, 1]], 4);
        $page3 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);

        $options = $database->wordpressOptions(2);
        $bounded = $database->wordpressOptions(1);

        $t->same(['siteurl', 'home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same('https://example.test/blog', $options[1]->optionValue);
        $t->same(1, count($bounded));
        $t->same('siteurl', $bounded[0]->optionName);
    },
    'walks sqlite index btrees including interior index records' => static function (TestRunner $t) use ($makeFirstPage, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([], 512, 100, $makeFirstPage(512, 4));
        $page2 = $indexInteriorPage([[3, ['home', 2]]], 4);
        $page3 = $indexLeafPage([
            $indexCell(['blogname', 3]),
        ]);
        $page4 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);

        $entries = $database->indexCells(2);

        $t->same(['blogname', 'home', 'siteurl'], array_map(static fn (SQLiteIndexCell $cell): string => $cell->record()->values[0], $entries));
        $t->same([3, 2, 1], array_map(static fn (SQLiteIndexCell $cell): int => $cell->record()->values[1], $entries));
    },
    'uses a wordpress option_name index to fetch an option by rowid' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $tableInteriorPage([[4, 1]], 5);
        $page3 = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $tableLeafPage([
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $option = $database->wordpressOptionByIndexedName('home');
        $missing = $database->wordpressOptionByIndexedName('missing');

        $t->same(1, count($database->indexRecordsForTable('wp_options')));
        $t->same(3, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(2, $option->optionId);
        $t->same('home', $option->optionName);
        $t->same('https://example.test/blog', $option->optionValue);
        $t->same(null, $missing);
    },
    'parses explicit sqlite index first column collation and direction' => static function (TestRunner $t): void {
        $index = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_wp_options_name ON main.wp_options('option_name' COLLATE nocase DESC, autoload) WHERE autoload='yes'");
        $expressionIndex = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_expr ON wp_options(lower(option_name))');
        $isNotNullIndex = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_present_name ON wp_options(option_name) WHERE (main.wp_options."option_name" IS NOT NULL)');
        $autoloadedIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_autoloaded_name ON wp_options(option_name) WHERE autoload='yes'");
        $autoloadOrIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_autoloaded_name ON wp_options(option_name) WHERE autoload='yes' OR autoload='on'");
        $autoloadAndIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_autoloaded_present_name ON wp_options(option_name) WHERE autoload='yes' AND option_name IS NOT NULL");
        $rangeIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_transient_name ON wp_options(option_name) WHERE option_name >= '_transient_' AND option_name < '_transient`'");
        $betweenIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_transient_autoload ON wp_options(option_name) WHERE option_name BETWEEN '_transient_' AND '_transient`' AND autoload='yes'");
        $notEqualIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_not_no_name ON wp_options(option_name) WHERE autoload <> 'no'");
        $inListIndex = SQLiteCreateIndex::firstColumn("CREATE INDEX idx_hot_names ON wp_options(option_name) WHERE option_name IN ('siteurl','home')");

        $t->same('option_name', $index?->columnName);
        $t->same('NOCASE', $index?->collation);
        $t->same(true, $index?->descending);
        $t->same(true, $index?->partial);
        $t->same(null, $expressionIndex);
        $t->same('option_name', $isNotNullIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $isNotNullIndex?->partialPredicate?->operator);
        $t->same('autoload', $autoloadedIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::EQUALS, $autoloadedIndex?->partialPredicate?->operator);
        $t->same('yes', $autoloadedIndex?->partialPredicate?->value);
        $t->same(SQLiteIndexPredicate::OR, $autoloadOrIndex?->partialPredicate?->operator);
        $t->same(['yes', 'on'], array_map(
            static fn (SQLiteIndexPredicate $predicate): mixed => $predicate->value,
            $autoloadOrIndex?->partialPredicate?->value ?? [],
        ));
        $t->same(SQLiteIndexPredicate::AND, $autoloadAndIndex?->partialPredicate?->operator);
        $t->same([SQLiteIndexPredicate::EQUALS, SQLiteIndexPredicate::IS_NOT_NULL], array_map(
            static fn (SQLiteIndexPredicate $predicate): string => $predicate->operator,
            $autoloadAndIndex?->partialPredicate?->value ?? [],
        ));
        $t->same(SQLiteIndexPredicate::AND, $rangeIndex?->partialPredicate?->operator);
        $t->same([SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, SQLiteIndexPredicate::LESS_THAN], array_map(
            static fn (SQLiteIndexPredicate $predicate): string => $predicate->operator,
            $rangeIndex?->partialPredicate?->value ?? [],
        ));
        $t->same(SQLiteIndexPredicate::AND, $betweenIndex?->partialPredicate?->operator);
        $t->same(SQLiteIndexPredicate::BETWEEN, $betweenIndex?->partialPredicate?->value[0]->operator ?? null);
        $t->same([
            'lower' => '_transient_',
            'upper' => '_transient`',
        ], $betweenIndex?->partialPredicate?->value[0]->value ?? null);
        $t->same(SQLiteIndexPredicate::NOT_EQUALS, $notEqualIndex?->partialPredicate?->operator);
        $t->same('no', $notEqualIndex?->partialPredicate?->value);
        $t->same(SQLiteIndexPredicate::IN_LIST, $inListIndex?->partialPredicate?->operator);
        $t->same(['siteurl', 'home'], $inListIndex?->partialPredicate?->value);
    },
    'parses sqlite lower expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $lowerIndex = SQLiteCreateIndex::firstLowerExpression('CREATE INDEX idx_lower_name ON wp_options(lower(main.wp_options."option_name") COLLATE nocase DESC) WHERE option_name IS NOT NULL');
        $constantExpression = SQLiteCreateIndex::firstLowerExpression("CREATE INDEX idx_constant ON wp_options(lower('option_name'))");
        $otherExpression = SQLiteCreateIndex::firstLowerExpression('CREATE INDEX idx_substr ON wp_options(substr(option_name,1,4))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_lower_name ON wp_options(lower(option_name))');

        $t->same('option_name', $lowerIndex?->columnName);
        $t->same('NOCASE', $lowerIndex?->collation);
        $t->same(true, $lowerIndex?->descending);
        $t->same(true, $lowerIndex?->partial);
        $t->same('option_name', $lowerIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $lowerIndex?->partialPredicate?->operator);
        $t->same(null, $constantExpression);
        $t->same(null, $otherExpression);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite upper expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $upperIndex = SQLiteCreateIndex::firstUpperExpression('CREATE INDEX idx_upper_name ON wp_options(upper(main.wp_options."option_name") COLLATE nocase DESC) WHERE option_name IS NOT NULL');
        $constantExpression = SQLiteCreateIndex::firstUpperExpression("CREATE INDEX idx_constant ON wp_options(upper('option_name'))");
        $otherExpression = SQLiteCreateIndex::firstUpperExpression('CREATE INDEX idx_lower ON wp_options(lower(option_name))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_upper_name ON wp_options(upper(option_name))');

        $t->same('option_name', $upperIndex?->columnName);
        $t->same('NOCASE', $upperIndex?->collation);
        $t->same(true, $upperIndex?->descending);
        $t->same(true, $upperIndex?->partial);
        $t->same('option_name', $upperIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $upperIndex?->partialPredicate?->operator);
        $t->same(null, $constantExpression);
        $t->same(null, $otherExpression);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite trim expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $trimIndex = SQLiteCreateIndex::firstTrimExpression("CREATE INDEX idx_trim_name ON wp_options(trim(main.wp_options.\"option_name\", ' _') COLLATE nocase DESC) WHERE option_name IS NOT NULL");
        $leftIndex = SQLiteCreateIndex::firstTrimExpression('CREATE INDEX idx_ltrim_name ON wp_options(ltrim(option_name))');
        $rightIndex = SQLiteCreateIndex::firstTrimExpression("CREATE INDEX idx_rtrim_name ON wp_options(rtrim(option_name, '-'))");
        $constantExpression = SQLiteCreateIndex::firstTrimExpression("CREATE INDEX idx_constant ON wp_options(trim('option_name'))");
        $nonStringCharacters = SQLiteCreateIndex::firstTrimExpression('CREATE INDEX idx_number ON wp_options(trim(option_name, 7))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_trim_name ON wp_options(trim(option_name))');

        $t->true($trimIndex instanceof SQLiteTrimIndexExpression);
        $t->same('trim', $trimIndex?->functionName);
        $t->same('option_name', $trimIndex?->columnName);
        $t->same(' _', $trimIndex?->characters);
        $t->same('NOCASE', $trimIndex?->collation);
        $t->same(true, $trimIndex?->descending);
        $t->same(true, $trimIndex?->partial);
        $t->same('option_name', $trimIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $trimIndex?->partialPredicate?->operator);
        $t->same('ltrim', $leftIndex?->functionName);
        $t->same(null, $leftIndex?->characters);
        $t->same('rtrim', $rightIndex?->functionName);
        $t->same('-', $rightIndex?->characters);
        $t->same(null, $constantExpression);
        $t->same(null, $nonStringCharacters);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite length expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $lengthIndex = SQLiteCreateIndex::firstLengthExpression('CREATE INDEX idx_name_length ON wp_options(length(main.wp_options."option_name") DESC) WHERE option_name IS NOT NULL');
        $constantExpression = SQLiteCreateIndex::firstLengthExpression("CREATE INDEX idx_constant ON wp_options(length('option_name'))");
        $otherExpression = SQLiteCreateIndex::firstLengthExpression('CREATE INDEX idx_lower ON wp_options(lower(option_name))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_name_length ON wp_options(length(option_name))');

        $t->same('option_name', $lengthIndex?->columnName);
        $t->same('BINARY', $lengthIndex?->collation);
        $t->same(true, $lengthIndex?->descending);
        $t->same(true, $lengthIndex?->partial);
        $t->same('option_name', $lengthIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $lengthIndex?->partialPredicate?->operator);
        $t->same(null, $constantExpression);
        $t->same(null, $otherExpression);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite integer cast expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $castIndex = SQLiteCreateIndex::firstIntegerCastExpression('CREATE INDEX idx_numeric_value ON wp_options(CAST(main.wp_options."option_value" AS INTEGER) DESC) WHERE option_value IS NOT NULL');
        $textCast = SQLiteCreateIndex::firstIntegerCastExpression('CREATE INDEX idx_text_value ON wp_options(CAST(option_value AS TEXT))');
        $constantCast = SQLiteCreateIndex::firstIntegerCastExpression('CREATE INDEX idx_constant ON wp_options(CAST(123 AS INTEGER))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_numeric_value ON wp_options(CAST(option_value AS INTEGER))');

        $t->same('option_value', $castIndex?->columnName);
        $t->same('BINARY', $castIndex?->collation);
        $t->same(true, $castIndex?->descending);
        $t->same(true, $castIndex?->partial);
        $t->same('option_value', $castIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $castIndex?->partialPredicate?->operator);
        $t->same(null, $textCast);
        $t->same(null, $constantCast);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite json_extract expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $jsonIndex = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_enabled ON wp_options(json_extract(main.wp_options."option_value", \'$.enabled\') COLLATE nocase DESC) WHERE option_value IS NOT NULL');
        $quotedPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_key ON wp_options(json_extract(option_value, \'$."plugin.enabled"\'))');
        $escapedQuotedPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_hex_key ON wp_options(json_extract(option_value, \'$."a\x62c"\'))');
        $bareQuotePath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_quote_key ON wp_options(json_extract(option_value, \'$.A"Key\'))');
        $arrayPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_rule ON wp_options(json_extract(option_value, \'$.rules[0].enabled\'))');
        $reverseArrayPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_last_rule ON wp_options(json_extract(option_value, \'$.rules[#-1].enabled\'))');
        $arrayAppendPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_append ON wp_options(json_extract(option_value, \'$.rules[#]\'))');
        $constantJson = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_constant ON wp_options(json_extract(\'{"enabled":true}\', \'$.enabled\'))');
        $multiPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_multi_path ON wp_options(json_extract(option_value, \'$.enabled\', \'$.version\'))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_json_enabled ON wp_options(json_extract(option_value, \'$.enabled\'))');

        $t->true($jsonIndex instanceof SQLiteJsonExtractIndexExpression);
        $t->same('option_value', $jsonIndex?->columnName);
        $t->same('$.enabled', $jsonIndex?->path);
        $t->same('NOCASE', $jsonIndex?->collation);
        $t->same(true, $jsonIndex?->descending);
        $t->same(true, $jsonIndex?->partial);
        $t->same('option_value', $jsonIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $jsonIndex?->partialPredicate?->operator);
        $t->same('$."plugin.enabled"', $quotedPath?->path);
        $t->same('$."a\x62c"', $escapedQuotedPath?->path);
        $t->same('$.A"Key', $bareQuotePath?->path);
        $t->same('$.rules[0].enabled', $arrayPath?->path);
        $t->same('$.rules[#-1].enabled', $reverseArrayPath?->path);
        $t->same('$.rules[#]', $arrayAppendPath?->path);
        $t->same(null, $constantJson);
        $t->same(null, $multiPath);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite json text operator expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $jsonIndex = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_enabled ON wp_options(main.wp_options."option_value" ->> \'enabled\' COLLATE nocase DESC) WHERE option_value IS NOT NULL');
        $pathIndex = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_key ON wp_options(option_value ->> \'$."plugin.enabled"\')');
        $bracketPath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_first ON wp_options(option_value ->> \'[0]\')');
        $integerPath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_first_integer ON wp_options(option_value ->> 0)');
        $reverseBracketPath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_last ON wp_options(option_value ->> \'[#-1]\')');
        $negativeIntegerPath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_last_integer ON wp_options(option_value ->> -1)');
        $dottedLabel = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_dotted ON wp_options(option_value ->> \'plugin.enabled\')');
        $numericLabel = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_numeric_label ON wp_options(option_value ->> \'2\')');
        $escapedLabel = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_escaped_label ON wp_options(option_value ->> \'a\x62c\')');
        $jsonExtractIndex = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_enabled ON wp_options(json_extract(option_value, \'$.enabled\'))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_json_enabled ON wp_options(option_value ->> \'enabled\')');

        $t->true($jsonIndex instanceof SQLiteJsonExtractIndexExpression);
        $t->same('option_value', $jsonIndex?->columnName);
        $t->same('$.enabled', $jsonIndex?->path);
        $t->same('NOCASE', $jsonIndex?->collation);
        $t->same(true, $jsonIndex?->descending);
        $t->same(true, $jsonIndex?->partial);
        $t->same('option_value', $jsonIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $jsonIndex?->partialPredicate?->operator);
        $t->same('$."plugin.enabled"', $pathIndex?->path);
        $t->same('$[0]', $bracketPath?->path);
        $t->same('$[0]', $integerPath?->path);
        $t->same('$[#-1]', $reverseBracketPath?->path);
        $t->same('$[#-1]', $negativeIntegerPath?->path);
        $t->same('$."plugin.enabled"', $dottedLabel?->path);
        $t->same('$."2"', $numericLabel?->path);
        $t->same('$.abc', $escapedLabel?->path);
        $t->same(null, $jsonExtractIndex);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite json value operator expression index metadata separately from text operator indexes' => static function (TestRunner $t): void {
        $jsonIndex = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_enabled_fragment ON wp_options(main.wp_options."option_value" -> \'enabled\' COLLATE nocase DESC) WHERE option_value IS NOT NULL');
        $pathIndex = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_fragment ON wp_options(option_value -> \'$."plugin.enabled"\')');
        $bracketPath = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_first_fragment ON wp_options(option_value -> \'[0]\')');
        $integerPath = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_first_integer_fragment ON wp_options(option_value -> 0)');
        $dottedLabel = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_dotted_fragment ON wp_options(option_value -> \'plugin.enabled\')');
        $numericLabel = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_numeric_fragment ON wp_options(option_value -> \'2\')');
        $escapedLabel = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_escaped_fragment ON wp_options(option_value -> \'a\x62c\')');
        $textOperatorIndex = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_enabled ON wp_options(option_value ->> \'enabled\')');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_json_enabled_fragment ON wp_options(option_value -> \'enabled\')');

        $t->true($jsonIndex instanceof SQLiteJsonExtractIndexExpression);
        $t->same('option_value', $jsonIndex?->columnName);
        $t->same('$.enabled', $jsonIndex?->path);
        $t->same('NOCASE', $jsonIndex?->collation);
        $t->same(true, $jsonIndex?->descending);
        $t->same(true, $jsonIndex?->partial);
        $t->same('option_value', $jsonIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $jsonIndex?->partialPredicate?->operator);
        $t->same('$."plugin.enabled"', $pathIndex?->path);
        $t->same('$[0]', $bracketPath?->path);
        $t->same('$[0]', $integerPath?->path);
        $t->same('$."plugin.enabled"', $dottedLabel?->path);
        $t->same('$."2"', $numericLabel?->path);
        $t->same('$.abc', $escapedLabel?->path);
        $t->same(null, $textOperatorIndex);
        $t->same(null, $ordinaryColumn);
    },
    'parses sqlite substr expression index metadata without treating it as a column index' => static function (TestRunner $t): void {
        $prefixIndex = SQLiteCreateIndex::firstSubstringExpression('CREATE INDEX idx_name_prefix ON wp_options(substr(main.wp_options."option_name", 1, 11) COLLATE nocase DESC) WHERE option_name IS NOT NULL');
        $tailIndex = SQLiteCreateIndex::firstSubstringExpression('CREATE INDEX idx_name_tail ON wp_options(substring(option_name, 2))');
        $suffixIndex = SQLiteCreateIndex::firstSubstringExpression('CREATE INDEX idx_name_suffix ON wp_options(Substr(option_name, -9) COLLATE nocase DESC) WHERE option_name IS NOT NULL');
        $variableStart = SQLiteCreateIndex::firstSubstringExpression('CREATE INDEX idx_variable ON wp_options(substr(option_name, option_id, 3))');
        $zeroStart = SQLiteCreateIndex::firstSubstringExpression('CREATE INDEX idx_zero ON wp_options(substr(option_name, 0, 3))');
        $ordinaryColumn = SQLiteCreateIndex::firstColumn('CREATE INDEX idx_name_prefix ON wp_options(substr(option_name,1,11))');

        $t->same('option_name', $prefixIndex?->columnName);
        $t->same(1, $prefixIndex?->start);
        $t->same(11, $prefixIndex?->length);
        $t->same('NOCASE', $prefixIndex?->collation);
        $t->same(true, $prefixIndex?->descending);
        $t->same(true, $prefixIndex?->partial);
        $t->same('option_name', $prefixIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $prefixIndex?->partialPredicate?->operator);
        $t->same('option_name', $tailIndex?->columnName);
        $t->same(2, $tailIndex?->start);
        $t->same(null, $tailIndex?->length);
        $t->same('option_name', $suffixIndex?->columnName);
        $t->same(-9, $suffixIndex?->start);
        $t->same(null, $suffixIndex?->length);
        $t->same('NOCASE', $suffixIndex?->collation);
        $t->same(true, $suffixIndex?->descending);
        $t->same(null, $variableStart);
        $t->same(null, $zeroStart);
        $t->same(null, $ordinaryColumn);
    },
    'parses explicit sqlite composite index column metadata' => static function (TestRunner $t): void {
        $columns = SQLiteCreateIndex::columns('CREATE INDEX idx_autoload_name ON wp_options(autoload, option_name COLLATE nocase DESC, option_value) WHERE autoload IS NOT NULL');
        $expressionColumns = SQLiteCreateIndex::columns('CREATE INDEX idx_expr ON wp_options(autoload, lower(option_name))');

        $t->same(3, count($columns ?? []));
        $t->same(['autoload', 'option_name', 'option_value'], array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns ?? []));
        $t->same(['BINARY', 'NOCASE', 'BINARY'], array_map(static fn (SQLiteIndexColumn $column): string => $column->collation, $columns ?? []));
        $t->same([false, true, false], array_map(static fn (SQLiteIndexColumn $column): bool => $column->descending, $columns ?? []));
        $t->same('autoload', $columns[0]->partialPredicate?->columnName ?? null);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $columns[0]->partialPredicate?->operator ?? null);
        $t->same(null, $expressionColumns);
    },
    'uses explicit nocase descending wordpress option_name index lookup' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_desc', 'wp_options', 3, 'CREATE INDEX wp_options_option_name_desc ON wp_options(option_name COLLATE NOCASE DESC)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
            $indexCell(['home', 2]),
            $indexCell(['blogname', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedName('BLOGNAME');

        $t->same(3, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->optionId);
        $t->same('blogname', $option->optionName);
        $t->same('Ported SQLite', $option->optionValue);
    },
    'uses supplied custom collation callback for wordpress option_name index lookup' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_wpcase_name', 'wp_options', 3, 'CREATE INDEX wp_options_wpcase_name ON wp_options(option_name COLLATE WPCASE)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'Home', 'https://example.test/home-alt', 'no'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['Home', 3]),
            $indexCell(['SiteURL', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);
        $wpcase = static fn (string $left, string $right): int => strcmp(strtolower($left), strtolower($right));

        $matches = $database->wordpressOptionsByIndexedNameWithCollation('HOME', 'WPCASE', $wpcase);
        $limited = $database->wordpressOptionsByIndexedNameWithCollation('siteurl', 'wpcase', $wpcase, 1);

        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedName('HOME'));
        $t->same(['home', 'Home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $matches));
        $t->same(['https://example.test/blog', 'https://example.test/home-alt'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $matches));
        $t->same(['SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameWithCollation('siteurl', '', $wpcase));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameWithCollation('siteurl', 'NO_SUCH', $wpcase));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameWithCollation('siteurl', 'WPCASE', static fn (): string => '0'));
    },
    'uses supplied custom collation callback for wordpress option_name range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_slug_name', 'wp_options', 3, 'CREATE INDEX wp_options_slug_name ON wp_options(option_name COLLATE WPSLUG) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'Cache_Alpha', 'alpha-payload', 'no'], 1),
            $schemaCell([null, 'cache-beta', 'beta-payload', 'no'], 2),
            $schemaCell([null, 'cache_delta', 'delta-payload', 'no'], 3),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['Cache_Alpha', 1]),
            $indexCell(['cache-beta', 2]),
            $indexCell(['cache_delta', 3]),
            $indexCell(['siteurl', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);
        $asciiLower = static function (string $value): string {
            $bytes = $value;
            $length = strlen($bytes);
            for ($i = 0; $i < $length; $i++) {
                $ord = ord($bytes[$i]);
                if ($ord >= 0x41 && $ord <= 0x5a) {
                    $bytes[$i] = chr($ord + 0x20);
                }
            }

            return $bytes;
        };
        $wpslug = static function (string $left, string $right) use ($asciiLower): int {
            return strcmp(str_replace('_', '-', $asciiLower($left)), str_replace('_', '-', $asciiLower($right)));
        };

        $options = $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-a', 'cache-c', 'WPSLUG', $wpslug);
        $limited = $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-a', 'cache-c', 'wpslug', $wpslug, 1);
        $exclusiveEqual = $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-beta', 'cache-beta', 'WPSLUG', $wpslug);
        $inclusiveEqual = $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-beta', 'cache-beta', 'WPSLUG', $wpslug, null, true);
        $inverted = $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-z', 'cache-a', 'WPSLUG', $wpslug);

        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRange('cache-a', 'cache-c'));
        $t->same(['Cache_Alpha', 'cache-beta'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['alpha-payload', 'beta-payload'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['Cache_Alpha'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $exclusiveEqual);
        $t->same(['cache-beta'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusiveEqual));
        $t->same([], $inverted);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-a', 'cache-c', '', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithCollation(null, null, 'WPSLUG', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithCollation('cache-a', 'cache-c', 'WPSLUG', static fn (): string => '0'));
    },
    'uses supplied custom collation callback for composite wordpress option_name range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_slug_name', 'wp_options', 3, "CREATE INDEX wp_options_autoload_slug_name ON wp_options(autoload, option_name COLLATE WPSLUG) WHERE autoload='no' AND option_name IS NOT NULL"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'Cache_Alpha', 'alpha-payload', 'no'], 1),
            $schemaCell([null, 'cache-beta', 'beta-payload', 'no'], 2),
            $schemaCell([null, 'cache_delta', 'delta-payload', 'no'], 3),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['no', 'Cache_Alpha', 1]),
            $indexCell(['no', 'cache-beta', 2]),
            $indexCell(['no', 'cache_delta', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);
        $asciiLower = static function (string $value): string {
            $bytes = $value;
            $length = strlen($bytes);
            for ($i = 0; $i < $length; $i++) {
                $ord = ord($bytes[$i]);
                if ($ord >= 0x41 && $ord <= 0x5a) {
                    $bytes[$i] = chr($ord + 0x20);
                }
            }

            return $bytes;
        };
        $wpslug = static function (string $left, string $right) use ($asciiLower): int {
            return strcmp(str_replace('_', '-', $asciiLower($left)), str_replace('_', '-', $asciiLower($right)));
        };

        $options = $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(
            ['autoload' => 'no'],
            'cache-a',
            'cache-c',
            'WPSLUG',
            $wpslug,
        );
        $limited = $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(
            ['autoload' => 'no'],
            'cache-a',
            'cache-c',
            'wpslug',
            $wpslug,
            1,
        );
        $inclusiveEqual = $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(
            ['autoload' => 'no'],
            'cache-beta',
            'cache-beta',
            'WPSLUG',
            $wpslug,
            null,
            true,
        );
        $exclusiveEqual = $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(
            ['autoload' => 'no'],
            'cache-beta',
            'cache-beta',
            'WPSLUG',
            $wpslug,
        );

        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', 'cache-a', 'cache-c'));
        $t->same(['Cache_Alpha', 'cache-beta'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['alpha-payload', 'beta-payload'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['Cache_Alpha'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['cache-beta'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusiveEqual));
        $t->same([], $exclusiveEqual);
        $t->same([], $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(['autoload' => 'no'], 'cache-z', 'cache-a', 'WPSLUG', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(['autoload' => 'yes'], 'cache-a', 'cache-c', 'WPSLUG', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation([], 'cache-a', 'cache-c', 'WPSLUG', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(['autoload' => 'no'], null, null, 'WPSLUG', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefixAndCollation(['autoload' => 'no'], 'cache-a', 'cache-c', 'WPSLUG', static fn (): string => '0'));
    },
    'uses wordpress option_name indexes for IN-list option lookups without duplicate rhs rows' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['blogname', 1]),
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNames(['SITEURL', 'home', 'home', null]);
        $limited = $database->wordpressOptionsByIndexedNames(['SITEURL', 'home'], 1);
        $nullOnly = $database->wordpressOptionsByIndexedNames([null]);

        $t->same(3, $database->indexRootPageForInLookup('wp_options', 'option_name', ['SITEURL', 'home']));
        $t->same(['home', 'siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test/blog', 'https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNames([123]));
    },
    'uses IN-list seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name', 'wp_options', 2, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['blogname', 2]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedNames(['siteurl', 'missing']);

        $t->same(2, $database->indexRootPageForInLookup('wp_options', 'option_name', ['siteurl', 'missing']));
        $t->same(['siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses partial is not null option_name indexes for wordpress IN-list lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_present_name', 'wp_options', 3, 'CREATE INDEX wp_options_present_name ON wp_options(option_name) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 1),
            $schemaCell([null, null, 'draft option without name', 'no'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 1]),
            $indexCell(['siteurl', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNames(['siteurl', null]);

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(3, $database->indexRootPageForInLookup('wp_options', 'option_name', ['siteurl', null]));
        $t->same(['siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses exact IN-list partial option_name indexes only for matching wordpress name lists' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_hot_names', 'wp_options', 3, "CREATE INDEX wp_options_hot_names ON wp_options(option_name) WHERE option_name IN ('siteurl','home')"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNames(['siteurl', 'home']);

        $t->same(3, $database->indexRootPageForInLookup('wp_options', 'option_name', ['siteurl', 'home']));
        $t->same(null, $database->indexRootPageForInLookup('wp_options', 'option_name', ['home', 'siteurl']));
        $t->same(null, $database->indexRootPageForInLookup('wp_options', 'option_name', ['siteurl']));
        $t->same(null, $database->indexRootPageForInLookup('wp_options', 'option_name', ['siteurl', 'home', null]));
        $t->same(['home', 'siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNames(['home', 'siteurl']));
    },
    'uses lower expression index for case folded wordpress option_name lookup' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_lower_option_name ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['blogname', 3]),
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedLowercaseName('SITEURL');
        $missing = $database->wordpressOptionByIndexedLowercaseName('missing');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(null, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'SITEURL'));
        $t->same(3, $database->indexRootPageForLowercasePointLookup('wp_options', 'option_name', 'SITEURL'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->optionId);
        $t->same('SiteURL', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
        $t->same(null, $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedName('SITEURL'));
    },
    'uses supplied custom collation callback for lower expression wordpress option_name lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_slug', 'wp_options', 3, 'CREATE INDEX wp_options_lower_slug ON wp_options(lower(option_name) COLLATE WPSLUG) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'Plugin_Mode', 'underscore payload', 'yes'], 1),
            $schemaCell([null, 'plugin-mode', 'dash payload', 'yes'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['plugin_mode', 1]),
            $indexCell(['plugin-mode', 2]),
            $indexCell(['siteurl', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $normalizeSlug = static function (string $value): string {
            $value = strtolower($value);

            return str_replace('_', '-', $value);
        };
        $wpslug = static function (string $left, string $right) use ($normalizeSlug): int {
            return strcmp($normalizeSlug($left), $normalizeSlug($right));
        };

        $options = $database->wordpressOptionsByIndexedLowercaseNameWithCollation('PLUGIN-MODE', 'WPSLUG', $wpslug);
        $limited = $database->wordpressOptionsByIndexedLowercaseNameWithCollation('PLUGIN-MODE', 'WPSLUG', $wpslug, 1);
        $missing = $database->wordpressOptionsByIndexedLowercaseNameWithCollation('theme-mode', 'WPSLUG', $wpslug);

        $t->same(['Plugin_Mode', 'plugin-mode'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['underscore payload', 'dash payload'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['Plugin_Mode'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedLowercaseName('PLUGIN-MODE'));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedLowercaseNameWithCollation('PLUGIN-MODE', '', $wpslug));
    },
    'uses supplied custom collation callback for lower expression IN-list and range lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_slug', 'wp_options', 3, 'CREATE INDEX wp_options_lower_slug ON wp_options(lower(option_name) COLLATE WPSLUG) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'Plugin_Mode', 'underscore payload', 'yes'], 1),
            $schemaCell([null, 'plugin-mode', 'dash payload', 'yes'], 2),
            $schemaCell([null, 'theme-mode', 'theme payload', 'no'], 3),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['plugin_mode', 1]),
            $indexCell(['plugin-mode', 2]),
            $indexCell(['siteurl', 4]),
            $indexCell(['theme-mode', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $normalizeSlug = static function (string $value): string {
            $value = strtolower($value);

            return str_replace('_', '-', $value);
        };
        $wpslug = static function (string $left, string $right) use ($normalizeSlug): int {
            return strcmp($normalizeSlug($left), $normalizeSlug($right));
        };

        $inList = $database->wordpressOptionsByIndexedLowercaseNamesWithCollation(['PLUGIN-MODE', 'plugin_mode', null], 'WPSLUG', $wpslug);
        $limited = $database->wordpressOptionsByIndexedLowercaseNamesWithCollation(['PLUGIN-MODE', 'theme-mode'], 'WPSLUG', $wpslug, 1);
        $nullOnly = $database->wordpressOptionsByIndexedLowercaseNamesWithCollation([null], 'WPSLUG', $wpslug);
        $range = $database->wordpressOptionsByIndexedLowercaseNameRangeWithCollation('PLUGIN-', 'PLUGIN.', 'WPSLUG', $wpslug);
        $inclusive = $database->wordpressOptionsByIndexedLowercaseNameRangeWithCollation('THEME-MODE', 'THEME-MODE', 'WPSLUG', $wpslug, null, true);
        $emptyRange = $database->wordpressOptionsByIndexedLowercaseNameRangeWithCollation('THEME.', 'PLUGIN-', 'WPSLUG', $wpslug);

        $t->same(3, $database->indexRootPageForLowercaseInLookupWithCollation('wp_options', 'option_name', 'WPSLUG', ['PLUGIN-MODE']));
        $t->same(3, $database->indexRootPageForLowercaseRangeLookupWithCollation('wp_options', 'option_name', 'WPSLUG', 'PLUGIN-', 'PLUGIN.'));
        $t->same(['Plugin_Mode', 'plugin-mode'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inList));
        $t->same(['underscore payload', 'dash payload'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $inList));
        $t->same(['Plugin_Mode'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->same(['Plugin_Mode', 'plugin-mode'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $range));
        $t->same(['theme-mode'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusive));
        $t->same([], $emptyRange);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedLowercaseNamesWithCollation([123], 'WPSLUG', $wpslug));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedLowercaseNameRangeWithCollation(null, null, 'WPSLUG', $wpslug));
    },
    'uses lower expression index for case folded wordpress option_name IN-list lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_lower_option_name ON wp_options(lower(option_name) COLLATE NOCASE) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'HOME', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
            $schemaCell([null, null, 'draft option without name', 'no'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['blogname', 3]),
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedLowercaseNames(['SITEURL', 'home', 'HOME', null]);
        $limited = $database->wordpressOptionsByIndexedLowercaseNames(['SITEURL', 'home'], 1);
        $nullOnly = $database->wordpressOptionsByIndexedLowercaseNames([null]);

        $t->same(3, $database->indexRootPageForLowercaseInLookup('wp_options', 'option_name', ['SITEURL', 'home']));
        $t->same(null, $database->indexRootPageForInLookup('wp_options', 'option_name', ['SITEURL', 'home']));
        $t->same(['HOME', 'SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test/blog', 'https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['HOME'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedLowercaseNames([123]));
    },
    'uses upper expression index for ascii folded wordpress option_name lookup' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_upper_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_upper_option_name ON wp_options(upper(option_name) COLLATE NOCASE DESC) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'HOME', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['SITEURL', 1]),
            $indexCell(['HOME', 2]),
            $indexCell(['BLOGNAME', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedUppercaseName('blogname');
        $missing = $database->wordpressOptionByIndexedUppercaseName('missing');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(null, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'blogname'));
        $t->same(3, $database->indexRootPageForUppercasePointLookup('wp_options', 'option_name', 'blogname'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->optionId);
        $t->same('blogname', $option->optionName);
        $t->same('Ported SQLite', $option->optionValue);
        $t->same(null, $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedName('blogname'));
    },
    'uses trim expression index for whitespace-normalized wordpress option_name lookup' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_trim_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_trim_option_name ON wp_options(trim(option_name) COLLATE NOCASE) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, ' SiteURL  ', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, null, 'draft option without name', 'no'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['SiteURL', 1]),
            $indexCell(['home', 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedTrimmedName('siteurl');
        $spacePaddedLookup = $database->wordpressOptionByIndexedTrimmedName('  SITEURL ');
        $missing = $database->wordpressOptionByIndexedTrimmedName('missing');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(null, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'siteurl'));
        $t->same(3, $database->indexRootPageForTrimmedPointLookup('wp_options', 'option_name', '  SITEURL '));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->optionId);
        $t->same(' SiteURL  ', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
        $t->same(' SiteURL  ', $spacePaddedLookup?->optionName);
        $t->same(null, $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedTrimmedName('siteurl', 'ltrim'));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedName('siteurl'));

        $customPage1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_trim_option_name', 'wp_options', 3, "CREATE INDEX wp_options_trim_option_name ON wp_options(trim(option_name, ' _') COLLATE NOCASE) WHERE option_name IS NOT NULL"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $customPage2 = $tableLeafPage([
            $schemaCell([null, '__Plugin_Cache__', 'enabled', 'no'], 1),
        ]);
        $customPage3 = $indexLeafPage([
            $indexCell(['Plugin_Cache', 1]),
        ]);
        $customDatabase = SQLiteDatabase::fromBytes($customPage1 . $customPage2 . $customPage3);
        $customOption = $customDatabase->wordpressOptionByIndexedTrimmedName('__PLUGIN_CACHE__', 'trim', ' _');

        $t->same(3, $customDatabase->indexRootPageForTrimmedPointLookup('wp_options', 'option_name', '__PLUGIN_CACHE__', 'trim', ' _'));
        $t->same('__Plugin_Cache__', $customOption?->optionName);
        $t->same('enabled', $customOption?->optionValue);
    },
    'uses upper expression index for ascii folded wordpress option_name IN-list lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_upper_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_upper_option_name ON wp_options(upper(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'HOME', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
            $schemaCell([null, 'café', 'unicode-name', 'no'], 4),
            $schemaCell([null, null, 'draft option without name', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['BLOGNAME', 3]),
            $indexCell(['CAFé', 4]),
            $indexCell(['HOME', 2]),
            $indexCell(['SITEURL', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedUppercaseNames(['siteurl', 'home', 'HOME', 'café', null]);
        $limited = $database->wordpressOptionsByIndexedUppercaseNames(['siteurl', 'home', 'café'], 2);
        $nullOnly = $database->wordpressOptionsByIndexedUppercaseNames([null]);

        $t->same(3, $database->indexRootPageForUppercaseInLookup('wp_options', 'option_name', ['siteurl', 'home']));
        $t->same(null, $database->indexRootPageForInLookup('wp_options', 'option_name', ['siteurl', 'home']));
        $t->same(['café', 'HOME', 'SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['unicode-name', 'https://example.test/blog', 'https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['café', 'HOME'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedUppercaseNames([123]));
    },
    'uses lower expression IN-list seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_option_name', 'wp_options', 2, 'CREATE INDEX wp_options_lower_option_name ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['blogname', 2]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedLowercaseNames(['SITEURL', 'missing']);

        $t->same(2, $database->indexRootPageForLowercaseInLookup('wp_options', 'option_name', ['SITEURL', 'missing']));
        $t->same(['SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses length expression index for wordpress option_name length buckets' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_length', 'wp_options', 3, 'CREATE INDEX wp_options_option_name_length ON wp_options(length(option_name) DESC) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'cron', '1', 'no'], 3),
            $schemaCell([null, null, 'draft option without name', 'no'], 4),
            $schemaCell([null, 'café', 'unicode-name', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([7, 1]),
            $indexCell([4, 2]),
            $indexCell([4, 3]),
            $indexCell([4, 5]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $shortNames = $database->wordpressOptionsByIndexedNameLength(4);
        $limited = $database->wordpressOptionsByIndexedNameLength(4, 1);
        $missing = $database->wordpressOptionsByIndexedNameLength(5);

        $t->same(3, $database->indexRootPageForLengthPointLookup('wp_options', 'option_name', 4));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(['home', 'cron', 'café'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $shortNames));
        $t->same(['https://example.test/blog', '1', 'unicode-name'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $shortNames));
        $t->same(['home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameLength(-1));
    },
    'uses length expression index for wordpress option_name length IN-list buckets' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_length', 'wp_options', 3, 'CREATE INDEX wp_options_option_name_length ON wp_options(length(option_name) DESC) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'cron', '1', 'no'], 3),
            $schemaCell([null, 'café', 'unicode-name', 'no'], 4),
            $schemaCell([null, 'db_version', '58796', 'yes'], 5),
            $schemaCell([null, null, 'draft option without name', 'no'], 6),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([10, 5]),
            $indexCell([7, 1]),
            $indexCell([4, 2]),
            $indexCell([4, 3]),
            $indexCell([4, 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNameLengths([4, 10, 4, null]);
        $limited = $database->wordpressOptionsByIndexedNameLengths([4, 10], 2);
        $nullOnly = $database->wordpressOptionsByIndexedNameLengths([null]);

        $t->same(3, $database->indexRootPageForLengthInLookup('wp_options', 'option_name', [4, 10]));
        $t->same(['db_version', 'home', 'cron', 'café'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['58796', 'https://example.test/blog', '1', 'unicode-name'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['db_version', 'home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameLengths([4, '7']));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameLengths([-1]));
    },
    'uses integer cast expression index for wordpress numeric option values' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_numeric_value', 'wp_options', 3, 'CREATE INDEX wp_options_numeric_value ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
            $schemaCell([null, 'legacy_db_version', '58796abc', 'no'], 2),
            $schemaCell([null, 'cron_lock', '123.9', 'no'], 3),
            $schemaCell([null, 'non_numeric_counter', 'abc', 'no'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([0, 4]),
            $indexCell([123, 3]),
            $indexCell([58796, 1]),
            $indexCell([58796, 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $versions = $database->wordpressOptionsByIndexedIntegerOptionValue(58796);
        $limited = $database->wordpressOptionsByIndexedIntegerOptionValue(58796, 1);
        $zero = $database->wordpressOptionsByIndexedIntegerOptionValue(0);
        $missing = $database->wordpressOptionsByIndexedIntegerOptionValue(-12);

        $t->same(3, $database->indexRootPageForIntegerCastPointLookup('wp_options', 'option_value', 58796));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_value'));
        $t->same(['db_version', 'legacy_db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $versions));
        $t->same(['58796', '58796abc'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $versions));
        $t->same(['db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['non_numeric_counter'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $zero));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedIntegerOptionValue(58796, -1));
    },
    'uses integer cast expression index for wordpress numeric option value IN-list lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_numeric_value', 'wp_options', 3, 'CREATE INDEX wp_options_numeric_value ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
            $schemaCell([null, 'legacy_db_version', '58796abc', 'no'], 2),
            $schemaCell([null, 'cron_lock', '123.9', 'no'], 3),
            $schemaCell([null, 'non_numeric_counter', 'abc', 'no'], 4),
            $schemaCell([null, 'empty_numeric_counter', '', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([0, 4]),
            $indexCell([0, 5]),
            $indexCell([123, 3]),
            $indexCell([58796, 1]),
            $indexCell([58796, 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $numericOptions = $database->wordpressOptionsByIndexedIntegerOptionValues([58796, 0, 58796, null]);
        $limited = $database->wordpressOptionsByIndexedIntegerOptionValues([58796, 0], 2);
        $nullOnly = $database->wordpressOptionsByIndexedIntegerOptionValues([null]);

        $t->same(3, $database->indexRootPageForIntegerCastInLookup('wp_options', 'option_value', [58796, 0]));
        $t->same(['non_numeric_counter', 'empty_numeric_counter', 'db_version', 'legacy_db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $numericOptions));
        $t->same(['abc', '', '58796', '58796abc'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $numericOptions));
        $t->same(['non_numeric_counter', 'empty_numeric_counter'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedIntegerOptionValues([58796, '0']));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedIntegerOptionValues([58796], -1));
    },
    'uses integer cast expression IN-list seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_numeric_value', 'wp_options', 2, 'CREATE INDEX wp_options_numeric_value ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, [123, 99]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell([58796, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedIntegerOptionValues([58796, 60000]);

        $t->same(2, $database->indexRootPageForIntegerCastInLookup('wp_options', 'option_value', [58796, 60000]));
        $t->same(['db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['58796'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses integer cast expression index for wordpress numeric option value ranges' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_numeric_value', 'wp_options', 3, 'CREATE INDEX wp_options_numeric_value ON wp_options(CAST(option_value AS INTEGER) DESC) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
            $schemaCell([null, 'legacy_db_version', '58796abc', 'no'], 2),
            $schemaCell([null, 'cron_lock', '123.9', 'no'], 3),
            $schemaCell([null, 'non_numeric_counter', 'abc', 'no'], 4),
            $schemaCell([null, 'empty_numeric_counter', '', 'no'], 5),
            $schemaCell([null, 'future_db_version', '60000', 'no'], 6),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([60000, 6]),
            $indexCell([58796, 1]),
            $indexCell([58796, 2]),
            $indexCell([123, 3]),
            $indexCell([0, 4]),
            $indexCell([0, 5]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $bounded = $database->wordpressOptionsByIndexedIntegerOptionValueRange(100, 60000);
        $limited = $database->wordpressOptionsByIndexedIntegerOptionValueRange(100, 60000, 2);
        $inclusiveSingle = $database->wordpressOptionsByIndexedIntegerOptionValueRange(60000, 60000, null, true);
        $exclusiveEmpty = $database->wordpressOptionsByIndexedIntegerOptionValueRange(100, 100);
        $zeroBucket = $database->wordpressOptionsByIndexedIntegerOptionValueRange(null, 1);

        $t->same(3, $database->indexRootPageForIntegerCastRangeLookup('wp_options', 'option_value', 100, 60000));
        $t->same(['db_version', 'legacy_db_version', 'cron_lock'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $bounded));
        $t->same(['58796', '58796abc', '123.9'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $bounded));
        $t->same(['db_version', 'legacy_db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['future_db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusiveSingle));
        $t->same([], $exclusiveEmpty);
        $t->same(['non_numeric_counter', 'empty_numeric_counter'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $zeroBucket));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedIntegerOptionValueRange(null, null));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedIntegerOptionValueRange(100, 60000, -1));
    },
    'uses integer cast expression range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_numeric_value', 'wp_options', 2, 'CREATE INDEX wp_options_numeric_value ON wp_options(CAST(option_value AS INTEGER)) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, [123, 99]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell([58796, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedIntegerOptionValueRange(50000, 60000);

        $t->same(2, $database->indexRootPageForIntegerCastRangeLookup('wp_options', 'option_value', 50000, 60000));
        $t->same(['db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['58796'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses json_extract expression index for wordpress plugin option values' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_enabled', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_enabled ON wp_options(json_extract(option_value, \'$.enabled\')) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"enabled":true,"version":2}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"enabled":false,"version":3}', 'no'], 2),
            $schemaCell([null, 'theme_settings', '{"enabled":1,"label":"active"}', 'yes'], 3),
            $schemaCell([null, 'plain_text_setting', 'not-json', 'no'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([0, 2]),
            $indexCell([1, 1]),
            $indexCell([1, 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $enabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', true);
        $limited = $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', true, 1);
        $disabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', false);
        $missing = $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', 2);

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.enabled', true));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_value'));
        $t->same(['plugin_alpha_settings', 'theme_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $enabled));
        $t->same(['{"enabled":true,"version":2}', '{"enabled":1,"label":"active"}'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $enabled));
        $t->same(['plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['plugin_beta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $disabled));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$[0]', true));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', new stdClass()));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', true, -1));
    },
    'uses json5 json_extract expression indexes for wordpress plugin option values' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $json5Settings = "{enabled: true, mode: 'dark', /* import note */ rules: [{enabled:false}, {enabled:true,},],}";
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_enabled', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_enabled ON wp_options(json_extract(option_value, \'$.enabled\')) WHERE option_value IS NOT NULL'], 2),
            $schemaCell(['index', 'wp_options_last_rule_enabled', 'wp_options', 4, 'CREATE INDEX wp_options_last_rule_enabled ON wp_options(json_extract(option_value, \'$.rules[#-1].enabled\')) WHERE option_value IS NOT NULL'], 3),
        ], 1024, 100, $makeFirstPage(1024, 4));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_json5_settings', $json5Settings, 'no'], 1),
            $schemaCell([null, 'plugin_strict_settings', '{"enabled":false,"rules":[]}', 'no'], 2),
        ], 1024);
        $page3 = $indexLeafPage([
            $indexCell([0, 2]),
            $indexCell([1, 1]),
        ], 1024);
        $page4 = $indexLeafPage([
            $indexCell([null, 2]),
            $indexCell([1, 1]),
        ], 1024);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);

        $enabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', true);
        $lastRuleEnabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[#-1].enabled', true);

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.enabled', true));
        $t->same(4, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.rules[#-1].enabled', true));
        $t->same(['plugin_json5_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $enabled));
        $t->same(['plugin_json5_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $lastRuleEnabled));
        $t->same([$json5Settings], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $enabled));
    },
    'normalizes json5 non-finite numbers for wordpress json indexes and jsonb fixtures' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $json5Settings = '{limit:+Infinity,disabled:-Inf,missing:NaN}';
        $negativeSettings = '{limit:-Infinity,missing:NaN}';
        $decoded = SQLiteJson5Parser::decode($json5Settings);
        if (!is_array($decoded)) {
            throw new RuntimeException('Fixture JSON5 did not decode to an object array');
        }

        $encoded = SQLiteJsonB::encode($decoded);
        $roundTripped = SQLiteJsonB::decode($encoded);
        if (!is_array($roundTripped)) {
            throw new RuntimeException('Fixture JSONB did not decode to an object array');
        }

        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_json5_limit_scalar', 'wp_options', 3, 'CREATE INDEX wp_options_json5_limit_scalar ON wp_options(json_extract(option_value, \'$.limit\')) WHERE option_value IS NOT NULL'], 2),
            $schemaCell(['index', 'wp_options_json5_limit_fragment', 'wp_options', 4, 'CREATE INDEX wp_options_json5_limit_fragment ON wp_options(option_value -> \'limit\') WHERE option_value IS NOT NULL'], 3),
            $schemaCell(['index', 'wp_options_json5_missing_fragment', 'wp_options', 5, 'CREATE INDEX wp_options_json5_missing_fragment ON wp_options(option_value -> \'missing\') WHERE option_value IS NOT NULL'], 4),
        ], 1024, 100, $makeFirstPage(1024, 5));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_json5_limit_settings', $json5Settings, 'no'], 1),
            $schemaCell([null, 'plugin_json5_negative_limit', $negativeSettings, 'no'], 2),
        ], 1024);
        $page3 = $indexLeafPage([
            $indexCell([-INF, 2]),
            $indexCell([INF, 1]),
        ], 1024);
        $page4 = $indexLeafPage([
            $indexCell(['-9e999', 2]),
            $indexCell(['9e999', 1]),
        ], 1024);
        $page5 = $indexLeafPage([
            $indexCell(['null', 1]),
            $indexCell(['null', 2]),
        ], 1024);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $positiveLimit = $database->wordpressOptionsByIndexedJsonOptionValue('$.limit', INF);
        $negativeLimit = $database->wordpressOptionsByIndexedJsonOptionValue('$.limit', -INF);
        $positiveFragment = $database->wordpressOptionsByIndexedJsonOptionFragment('$.limit', INF);
        $negativeFragment = $database->wordpressOptionsByIndexedJsonOptionFragment('$.limit', -INF);
        $nanFragment = $database->wordpressOptionsByIndexedJsonOptionFragment('$.missing', NAN);

        $t->true(is_float($decoded['limit']) && is_infinite($decoded['limit']) && $decoded['limit'] > 0);
        $t->true(is_float($decoded['disabled']) && is_infinite($decoded['disabled']) && $decoded['disabled'] < 0);
        $t->same(null, $decoded['missing']);
        $t->same('cc25576c696d69745539653939398764697361626c6564652d3965393939776d697373696e6700', bin2hex($encoded));
        $t->true(is_float($roundTripped['limit']) && is_infinite($roundTripped['limit']) && $roundTripped['limit'] > 0);
        $t->true(is_float($roundTripped['disabled']) && is_infinite($roundTripped['disabled']) && $roundTripped['disabled'] < 0);
        $t->same(null, $roundTripped['missing']);
        $t->same(['plugin_json5_limit_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $positiveLimit));
        $t->same(['plugin_json5_negative_limit'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $negativeLimit));
        $t->same(['plugin_json5_limit_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $positiveFragment));
        $t->same(['plugin_json5_negative_limit'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $negativeFragment));
        $t->same(['plugin_json5_limit_settings', 'plugin_json5_negative_limit'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $nanFragment));
    },
    'rejects malformed json5 while verifying wordpress json expression indexes' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_enabled', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_enabled ON wp_options(json_extract(option_value, \'$.enabled\')) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_broken_settings', '{enabled:true,,}', 'no'], 1),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([1, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', true));
    },
    'decodes focused sqlite jsonb blobs for json expression verification' => static function (TestRunner $t): void {
        $jsonb = hex2bin('cc0f1761cb0b133235332e350102001778');
        if (!is_string($jsonb)) {
            throw new RuntimeException('Fixture JSONB hex is invalid');
        }
        $encoded = SQLiteJsonB::encode(['a' => [2, 3.5, true, false, null, 'x']]);
        $quoted = SQLiteJsonB::encode(['quote' => 'a"b', 'slash' => 'c\\d']);

        $t->true(SQLiteJsonB::isJsonB($jsonb));
        $t->same(['a' => [2, 3.5, true, false, null, 'x']], SQLiteJsonB::decode($jsonb));
        $t->same('cc0e1761bb133235332e350102001778', bin2hex($encoded));
        $t->same(['a' => [2, 3.5, true, false, null, 'x']], SQLiteJsonB::decode($encoded));
        $t->same(['quote' => 'a"b', 'slash' => 'c\\d'], SQLiteJsonB::decode($quoted));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::decode("\x8c\xe6\xff\xff\xff\x17\x13\x33"));
        $t->same('553965393939', bin2hex(SQLiteJsonB::encode(INF)));
        $t->same('652d3965393939', bin2hex(SQLiteJsonB::encode(-INF)));
        $t->same('00', bin2hex(SQLiteJsonB::encode(NAN)));
    },
    'inspects focused sqlite jsonb types at root and paths' => static function (TestRunner $t): void {
        $jsonb = SQLiteJsonB::encode([
            'object' => ['nested' => 1],
            'array' => [2, 3],
            'text' => 'channel',
            'integer' => 7,
            'real' => 3.5,
            'true' => true,
            'false' => false,
            'null' => null,
        ]);

        $t->same('object', SQLiteJsonB::type($jsonb));
        $t->same('object', SQLiteJsonB::type($jsonb, '$.object'));
        $t->same('array', SQLiteJsonB::type($jsonb, '$.array'));
        $t->same('text', SQLiteJsonB::type($jsonb, '$.text'));
        $t->same('integer', SQLiteJsonB::type($jsonb, '$.integer'));
        $t->same('real', SQLiteJsonB::type($jsonb, '$.real'));
        $t->same('true', SQLiteJsonB::type($jsonb, '$.true'));
        $t->same('false', SQLiteJsonB::type($jsonb, '$.false'));
        $t->same('null', SQLiteJsonB::type($jsonb, '$.null'));
        $t->same(null, SQLiteJsonB::type($jsonb, '$.missing'));
        $t->same(null, SQLiteJsonB::type($jsonb, '$.array[9]'));
        $t->same(null, SQLiteJsonB::type($jsonb, '$.text[0]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::type($jsonb, '$.array[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::type($jsonb, '$[0'));
    },
    'inspects focused sqlite jsonb array lengths at root and paths' => static function (TestRunner $t): void {
        $array = SQLiteJsonB::encode([1, 2, ['nested' => [3, 4]]]);
        $object = SQLiteJsonB::encode([
            'array' => [1, 2, 3],
            'empty' => [],
            'object' => ['nested' => true],
            'text' => 'not-array',
            'integer' => 7,
            'real' => 3.5,
            'true' => true,
            'false' => false,
            'null' => null,
        ]);

        $t->same(3, SQLiteJsonB::arrayLength($array));
        $t->same(2, SQLiteJsonB::arrayLength($array, '$[2].nested'));
        $t->same(0, SQLiteJsonB::arrayLength($object));
        $t->same(3, SQLiteJsonB::arrayLength($object, '$.array'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.empty'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.object'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.text'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.integer'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.real'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.true'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.false'));
        $t->same(0, SQLiteJsonB::arrayLength($object, '$.null'));
        $t->same(null, SQLiteJsonB::arrayLength($object, '$.missing'));
        $t->same(null, SQLiteJsonB::arrayLength($object, '$.array[#]'));
        $t->same(null, SQLiteJsonB::arrayLength($object, '$.text[0]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayLength($object, '$.array[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayLength($object, 'array'));
    },
    'inspects sqlite jsonb wordpress option and meta arrays for import preflight' => static function (TestRunner $t): void {
        $settings = SQLiteJsonB::encode([
            'optionMigrations' => [
                ['name' => 'core', 'status' => 'done'],
                ['name' => 'cache', 'status' => 'queued'],
            ],
            'metaKeys' => ['_legacy_flag', '_generated_css'],
            'legacyMode' => 'skip',
        ]);

        $t->same('array', SQLiteJsonB::type($settings, '$.optionMigrations'));
        $t->same(2, SQLiteJsonB::arrayLength($settings, '$.optionMigrations'));
        $t->same('object', SQLiteJsonB::type($settings, '$.optionMigrations[0]'));
        $t->same(0, SQLiteJsonB::arrayLength($settings, '$.optionMigrations[0]'));
        $t->same('array', SQLiteJsonB::type($settings, '$.metaKeys'));
        $t->same(2, SQLiteJsonB::arrayLength($settings, '$.metaKeys'));
        $t->same('text', SQLiteJsonB::type($settings, '$.legacyMode'));
        $t->same(0, SQLiteJsonB::arrayLength($settings, '$.legacyMode'));
        $t->same(null, SQLiteJsonB::type($settings, '$.postMetaQueue'));
        $t->same(null, SQLiteJsonB::arrayLength($settings, '$.postMetaQueue'));
    },
    'removes focused sqlite jsonb object members and array elements' => static function (TestRunner $t): void {
        $jsonb = SQLiteJsonB::encode(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]]);
        $decode = static function (?string $bytes): mixed {
            if ($bytes === null) {
                throw new RuntimeException('Expected SQLite JSONB bytes, got SQL null');
            }

            return SQLiteJsonB::decode($bytes);
        };

        $t->same(['a' => 5, 'b' => ['y' => 11], 'c' => [1, 2, 3, 4]], $decode(SQLiteJsonB::remove($jsonb, '$.b.x')));
        $t->same('3c17620c', bin2hex(SQLiteJsonB::remove(SQLiteJsonB::encode(['b' => ['x' => 10]]), '$.b.x') ?? ''));
        $t->same(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3]], $decode(SQLiteJsonB::remove($jsonb, '$.c[#-1]')));
        $t->same(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [2, 3, 4]], $decode(SQLiteJsonB::remove($jsonb, '$.c[#-4]')));
        $t->same(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]], $decode(SQLiteJsonB::remove($jsonb, '$.d')));
        $t->same(['a' => 5, 'b' => ['x' => 10, 'y' => 11], 'c' => [1, 2, 3, 4]], $decode(SQLiteJsonB::remove($jsonb, '$.c[#]')));
        $t->same(null, SQLiteJsonB::remove($jsonb, '$'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::remove($jsonb, '$.c[#-]'));
    },
    'removes multiple sqlite jsonb paths in sqlite argument order' => static function (TestRunner $t): void {
        $jsonb = SQLiteJsonB::encode([0, 1, 2, 3, 4]);
        $decode = static function (?string $bytes): mixed {
            if ($bytes === null) {
                throw new RuntimeException('Expected SQLite JSONB bytes, got SQL null');
            }

            return SQLiteJsonB::decode($bytes);
        };

        $t->same([0, 1, 3, 4], $decode(SQLiteJsonB::remove($jsonb, '$[2]')));
        $t->same([1, 3, 4], $decode(SQLiteJsonB::remove($jsonb, '$[2]', '$[0]')));
        $t->same([1, 2, 4], $decode(SQLiteJsonB::remove($jsonb, '$[0]', '$[2]')));
        $t->same([0, 1, 2, 3, 4], $decode(SQLiteJsonB::remove($jsonb, '$[42949672960]')));
    },
    'mutates focused sqlite jsonb paths with insert set and replace' => static function (TestRunner $t): void {
        $jsonb = SQLiteJsonB::encode(['a' => 2, 'c' => 4]);
        $decode = static fn (string $bytes): mixed => SQLiteJsonB::decode($bytes);

        $t->same(['a' => 2, 'c' => 4], $decode(SQLiteJsonB::insert($jsonb, '$.a', 99)));
        $t->same(['a' => 2, 'c' => 4, 'e' => 99], $decode(SQLiteJsonB::insert($jsonb, '$.e', 99)));
        $t->same(['a' => 99, 'c' => 4], $decode(SQLiteJsonB::replace($jsonb, '$.a', 99)));
        $t->same(['a' => 2, 'c' => 4], $decode(SQLiteJsonB::replace($jsonb, '$.e', 99)));
        $t->same(['a' => 99, 'c' => 4], $decode(SQLiteJsonB::set($jsonb, '$.a', 99)));
        $t->same(['a' => 2, 'c' => 4, 'e' => 99], $decode(SQLiteJsonB::set($jsonb, '$.e', 99)));
        $t->same(['a' => 2, 'c' => '[97,96]'], $decode(SQLiteJsonB::set($jsonb, '$.c', '[97,96]')));
        $t->same(['a' => 2, 'c' => [97, 96]], $decode(SQLiteJsonB::set($jsonb, '$.c', [97, 96])));
    },
    'creates focused sqlite jsonb mutation substructures and append paths' => static function (TestRunner $t): void {
        $emptyObject = SQLiteJsonB::encode(new stdClass());
        $array = SQLiteJsonB::encode([0, 1, 2]);
        $decode = static fn (string $bytes): mixed => SQLiteJsonB::decode($bytes);

        $t->same(['a' => ['b' => ['c' => 9]]], $decode(SQLiteJsonB::insert($emptyObject, '$.a.b.c', 9)));
        $t->same(['a' => ['b' => ['c' => 9]]], $decode(SQLiteJsonB::set($emptyObject, '$.a.b.c', 9)));
        $t->same('0c', bin2hex(SQLiteJsonB::replace($emptyObject, '$.a.b.c', 9)));
        $t->same([0, 1, 2, ['a' => [['b' => 9]]]], $decode(SQLiteJsonB::insert($array, '$[3].a[0].b', 9)));
        $t->same([1, 2, 9], $decode(SQLiteJsonB::set(SQLiteJsonB::encode([1, 2]), '$[#-0]', 9)));
        $t->same([1, 2], $decode(SQLiteJsonB::insert(SQLiteJsonB::encode([1, 2]), '$[#-1]', 9)));
        $t->same([0, 1, 2, 'AAA', 'BBB'], $decode(SQLiteJsonB::insert($array, '$[#]', 'AAA', '$[#]', 'BBB')));
        $t->same([0, 1, 2], $decode(SQLiteJsonB::set($array, '$[4].a', 9)));
    },
    'array-inserts focused sqlite jsonb array elements' => static function (TestRunner $t): void {
        $array = SQLiteJsonB::encode([1, 2, 3]);
        $decode = static fn (string $bytes): mixed => SQLiteJsonB::decode($bytes);

        $t->same([888, 999, 1, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[0]', 999, '$[0]', 888)));
        $t->same([999, 1, 2, 3, 888], $decode(SQLiteJsonB::arrayInsert($array, '$[0]', 999, '$[#]', 888)));
        $t->same([1, 888, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[1]', 888)));
        $t->same([1, 2, 888, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[2]', 888)));
        $t->same([1, 2, 3, 888], $decode(SQLiteJsonB::arrayInsert($array, '$[3]', 888)));
        $t->same([1, 2, 3, 888], $decode(SQLiteJsonB::arrayInsert($array, '$[#-0]', 888)));
        $t->same([1, 2, 888, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[#-1]', 888)));
        $t->same([1, 888, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[#-2]', 888)));
        $t->same([888, 1, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[#-3]', 888)));
        $t->same([1, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[#-4]', 888)));
        $t->same([1, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$[4]', 888)));
        $t->same([1, 2, 3], $decode(SQLiteJsonB::arrayInsert($array, '$', 888)));
    },
    'array-inserts focused sqlite jsonb substructures and non-array targets' => static function (TestRunner $t): void {
        $object = SQLiteJsonB::encode(['a' => [1, 2, 3]]);
        $decode = static fn (string $bytes): mixed => SQLiteJsonB::decode($bytes);

        $t->same(['a' => [888, 1, 2, 3]], $decode(SQLiteJsonB::arrayInsert($object, '$.a[0]', 888)));
        $t->same(['a' => [1, 2, 3], 'b' => [888]], $decode(SQLiteJsonB::arrayInsert($object, '$.b[0]', 888)));
        $t->same(['a' => [1, 2, 3], 'b' => ['c' => ['d' => [888]]]], $decode(SQLiteJsonB::arrayInsert($object, '$.b.c.d[0]', 888)));
        $t->same(['a' => [1, 2, 3]], $decode(SQLiteJsonB::arrayInsert($object, '$[0]', 888)));
        $t->same(['a' => 7], $decode(SQLiteJsonB::arrayInsert(SQLiteJsonB::encode(['a' => 7]), '$.a[0]', 888)));
        $t->same([1, ['a' => [999]]], $decode(SQLiteJsonB::arrayInsert(SQLiteJsonB::encode([1]), '$[1].a[0]', 999)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($object, '$.a', 888));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($object, '$.b.c.d', 888));
    },
    'rejects malformed sqlite jsonb array insert paths and roundtrips jsonb output' => static function (TestRunner $t): void {
        $array = SQLiteJsonB::encode([1, 2, 3]);
        $inserted = SQLiteJsonB::arrayInsert($array, '$[1]', ['kind' => 'cache']);

        $t->same([1, ['kind' => 'cache'], 2, 3], SQLiteJsonB::decode($inserted));
        $t->same('cb121331bc476b696e6457636163686513321333', bin2hex($inserted));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($array, '$[a]', 9));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($array, '$[0', 9));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($array, 'x[0]', 9));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($array, '$[#-]', 9));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($array, '$[0]', 9, '$[1]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonB::arrayInsert($array, '$[0]', new ArrayObject()));
    },
    'array-inserts sqlite jsonb wordpress option migration lists' => static function (TestRunner $t): void {
        $settings = SQLiteJsonB::encode([
            'optionMigrations' => [
                ['name' => 'core', 'status' => 'done'],
                ['name' => 'cache', 'status' => 'queued'],
            ],
            'metaKeys' => ['_legacy_flag'],
        ]);

        $mutated = SQLiteJsonB::arrayInsert(
            $settings,
            '$.optionMigrations[1]',
            ['name' => 'permalink', 'status' => 'queued'],
            '$.metaKeys[#]',
            '_generated_css',
        );

        $t->same([
            'optionMigrations' => [
                ['name' => 'core', 'status' => 'done'],
                ['name' => 'permalink', 'status' => 'queued'],
                ['name' => 'cache', 'status' => 'queued'],
            ],
            'metaKeys' => ['_legacy_flag', '_generated_css'],
        ], SQLiteJsonB::decode($mutated));
    },
    'patches focused sqlite jsonb objects with merge patch semantics' => static function (TestRunner $t): void {
        $decode = static fn (string $bytes): mixed => SQLiteJsonB::decode($bytes);
        $target = SQLiteJsonB::encode([
            'a' => 'b',
            'c' => [
                'd' => 'e',
                'f' => 'g',
            ],
            'tags' => ['example', 'sample'],
            'content' => 'This will be unchanged',
        ]);
        $patch = SQLiteJsonB::encode([
            'a' => 'z',
            'c' => [
                'f' => null,
            ],
            'tags' => ['example'],
            'phoneNumber' => '+01-123-456-7890',
        ]);

        $t->same([
            'a' => 'z',
            'c' => [
                'd' => 'e',
            ],
            'tags' => ['example'],
            'content' => 'This will be unchanged',
            'phoneNumber' => '+01-123-456-7890',
        ], $decode(SQLiteJsonB::patch($target, $patch)));
        $t->same('0c', bin2hex(SQLiteJsonB::patch(SQLiteJsonB::encode(['a' => 'b']), SQLiteJsonB::encode(['a' => null]))));
        $t->same(['b' => 'c'], $decode(SQLiteJsonB::patch(SQLiteJsonB::encode(['a' => 'b', 'b' => 'c']), SQLiteJsonB::encode(['a' => null]))));
        $t->same('0c', bin2hex(SQLiteJsonB::patch(SQLiteJsonB::encode([1, 2, 3]), SQLiteJsonB::encode(['x' => null]))));
        $t->same(['y' => 1], $decode(SQLiteJsonB::patch(SQLiteJsonB::encode([1, 2, 3]), SQLiteJsonB::encode(['x' => null, 'y' => 1, 'z' => null]))));
        $t->same('7c17614c2762620c', bin2hex(SQLiteJsonB::patch(SQLiteJsonB::encode(new stdClass()), SQLiteJsonB::encode(['a' => ['bb' => ['ccc' => null]]]))));
        $t->same(['a' => [1]], $decode(SQLiteJsonB::patch(SQLiteJsonB::encode(['a' => [['b' => 'c']]]), SQLiteJsonB::encode(['a' => [1]]))));
        $t->same([1, 2], $decode(SQLiteJsonB::patch(SQLiteJsonB::encode(['a' => 'b']), SQLiteJsonB::encode([1, 2]))));
        $t->same(null, $decode(SQLiteJsonB::patch(SQLiteJsonB::encode(['a' => 'foo']), SQLiteJsonB::encode(null))));
        $t->same('bar', $decode(SQLiteJsonB::patch(SQLiteJsonB::encode(['a' => 'foo']), SQLiteJsonB::encode('bar'))));
    },
    'mutates sqlite jsonb wordpress plugin settings for preflight fixtures' => static function (TestRunner $t): void {
        $settings = SQLiteJsonB::encode([
            'enabled' => true,
            'legacyToken' => 'secret',
            'rules' => [
                ['name' => 'core', 'enabled' => true],
            ],
        ]);

        $mutated = SQLiteJsonB::set(
            $settings,
            '$.enabled',
            false,
            '$.rules[#]',
            ['name' => 'cache', 'enabled' => false],
        );
        $mutated = SQLiteJsonB::replace($mutated, '$.legacyToken', 'redacted');
        $mutated = SQLiteJsonB::insert($mutated, '$.migratedBy', 'native-libsqlite');

        $t->same([
            'enabled' => false,
            'legacyToken' => 'redacted',
            'rules' => [
                ['name' => 'core', 'enabled' => true],
                ['name' => 'cache', 'enabled' => false],
            ],
            'migratedBy' => 'native-libsqlite',
        ], SQLiteJsonB::decode($mutated));
    },
    'patches sqlite jsonb wordpress plugin settings for import preflight fixtures' => static function (TestRunner $t): void {
        $settings = SQLiteJsonB::encode([
            'enabled' => true,
            'legacyToken' => 'secret',
            'rules' => [
                ['name' => 'core', 'enabled' => true],
            ],
            'channels' => ['stable', 'beta'],
        ]);
        $patch = SQLiteJsonB::encode([
            'enabled' => false,
            'legacyToken' => null,
            'rules' => [
                ['name' => 'cache', 'enabled' => false],
            ],
            'import' => [
                'source' => 'wp-cli',
                'checked' => true,
                'empty' => [
                    'drop' => null,
                ],
            ],
        ]);

        $patched = SQLiteJsonB::patch($settings, $patch);

        $t->same([
            'enabled' => false,
            'rules' => [
                ['name' => 'cache', 'enabled' => false],
            ],
            'channels' => ['stable', 'beta'],
            'import' => [
                'source' => 'wp-cli',
                'checked' => true,
                'empty' => [],
            ],
        ], SQLiteJsonB::decode($patched));
        $t->same('cc6577656e61626c6564025772756c6573cb16cc14476e616d6557636163686577656e61626c656402876368616e6e656c73cb0c67737461626c65476265746167696d706f7274cc1e67736f757263656777702d636c6977636865636b65640157656d7074790c', bin2hex($patched));
    },
    'uses jsonb option_value blobs through wordpress json expression indexes' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $blobValue): void {
        $jsonbSettings = SQLiteJsonB::encode(['a' => [2, 3.5, true, false, null, 'x']]);

        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value blob, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_jsonb_channel', 'wp_options', 3, 'CREATE INDEX wp_options_jsonb_channel ON wp_options(json_extract(option_value, \'$.a[5]\')) WHERE option_value IS NOT NULL'], 2),
            $schemaCell(['index', 'wp_options_jsonb_array', 'wp_options', 4, 'CREATE INDEX wp_options_jsonb_array ON wp_options(option_value -> \'$.a\') WHERE option_value IS NOT NULL'], 3),
        ], 1024, 100, $makeFirstPage(1024, 4));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_jsonb_settings', $blobValue($jsonbSettings), 'no'], 1),
        ], 1024);
        $page3 = $indexLeafPage([
            $indexCell(['x', 1]),
        ], 1024);
        $page4 = $indexLeafPage([
            $indexCell(['[2,3.5,true,false,null,"x"]', 1]),
        ], 1024);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);

        $scalar = $database->wordpressOptionsByIndexedJsonOptionValue('$.a[5]', 'x');
        $fragment = $database->wordpressOptionsByIndexedJsonOptionFragment('$.a', [2, 3.5, true, false, null, 'x']);

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.a[5]', 'x'));
        $t->same(4, $database->indexRootPageForJsonValueOperatorPointLookup('wp_options', 'option_value', '$.a', [2, 3.5, true, false, null, 'x']));
        $t->same('cc0e1761bb133235332e350102001778', bin2hex($jsonbSettings));
        $t->same(['plugin_jsonb_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $scalar));
        $t->same(['plugin_jsonb_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $fragment));
        $t->same($jsonbSettings, $scalar[0]->optionValue);
    },
    'uses escaped sqlite json path labels for wordpress plugin option values' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_hex_label_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_hex_label_arrow ON wp_options(option_value ->> \'a\x62c\') WHERE option_value IS NOT NULL'], 2),
            $schemaCell(['index', 'wp_options_quote_label', 'wp_options', 4, 'CREATE INDEX wp_options_quote_label ON wp_options(json_extract(option_value, \'$.A"Key\')) WHERE option_value IS NOT NULL'], 3),
            $schemaCell(['index', 'wp_options_backslash_label', 'wp_options', 5, 'CREATE INDEX wp_options_backslash_label ON wp_options(json_extract(option_value, \'$."plugin\x5cenabled"\')) WHERE option_value IS NOT NULL'], 4),
        ], 1024, 100, $makeFirstPage(1024, 5));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_hex_label_settings', '{"abc":"enabled"}', 'no'], 1),
            $schemaCell([null, 'plugin_quote_label_settings', '{"A\"Key":true}', 'no'], 2),
            $schemaCell([null, 'plugin_backslash_label_settings', '{"plugin\\\\enabled":"yes"}', 'no'], 3),
        ], 1024);
        $page3 = $indexLeafPage([
            $indexCell(['enabled', 1]),
        ], 1024);
        $page4 = $indexLeafPage([
            $indexCell([1, 2]),
        ], 1024);
        $page5 = $indexLeafPage([
            $indexCell(['yes', 3]),
        ], 1024);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $hexLabel = $database->wordpressOptionsByIndexedJsonOptionValue('$."abc"', 'enabled');
        $quotedLabel = $database->wordpressOptionsByIndexedJsonOptionValue('$."A\"Key"', true);
        $bareQuoteLabel = $database->wordpressOptionsByIndexedJsonOptionValue('$.A"Key', true);
        $backslashLabel = $database->wordpressOptionsByIndexedJsonOptionValue('$."plugin\\\\enabled"', 'yes');

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.abc', 'enabled'));
        $t->same(4, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."A\"Key"', true));
        $t->same(4, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.A"Key', true));
        $t->same(5, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."plugin\\\\enabled"', 'yes'));
        $t->same(['plugin_hex_label_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $hexLabel));
        $t->same(['plugin_quote_label_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $quotedLabel));
        $t->same(['plugin_quote_label_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $bareQuoteLabel));
        $t->same(['plugin_backslash_label_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $backslashLabel));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$."plugin\xZZ"', 'yes'));
    },
    'uses json_extract expression index for wordpress plugin settings array paths' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_first_rule_enabled', 'wp_options', 3, 'CREATE INDEX wp_options_first_rule_enabled ON wp_options(json_extract(option_value, \'$.rules[0].enabled\')) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"rules":[{"enabled":true,"role":"editor"},{"enabled":false}]}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"rules":[{"enabled":false}]}', 'no'], 2),
            $schemaCell([null, 'plugin_empty_settings', '{"rules":[]}', 'yes'], 3),
            $schemaCell([null, 'plugin_object_settings', '{"rules":{"0":{"enabled":true}}}', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 3]),
            $indexCell([null, 4]),
            $indexCell([0, 2]),
            $indexCell([1, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $enabled = $database->wordpressOptionsByIndexedJsonOptionValue('$."rules"[0].enabled', true);
        $disabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[0].enabled', false);
        $missing = $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[0].enabled', 2);

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.rules[0].enabled', true));
        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."rules"[0].enabled', true));
        $t->same(['plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $enabled));
        $t->same(['plugin_beta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $disabled));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[1].enabled', false));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[#-1].enabled', true));
    },
    'uses json_extract expression index for wordpress plugin settings reverse array paths' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_last_rule_enabled', 'wp_options', 3, 'CREATE INDEX wp_options_last_rule_enabled ON wp_options(json_extract(option_value, \'$.rules[#-1].enabled\')) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"rules":[{"enabled":false},{"enabled":true}]}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"rules":[{"enabled":true},{"enabled":false}]}', 'no'], 2),
            $schemaCell([null, 'plugin_empty_settings', '{"rules":[]}', 'yes'], 3),
            $schemaCell([null, 'plugin_object_settings', '{"rules":{"0":{"enabled":true}}}', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 3]),
            $indexCell([null, 4]),
            $indexCell([0, 2]),
            $indexCell([1, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $enabled = $database->wordpressOptionsByIndexedJsonOptionValue('$."rules"[#-000001].enabled', true);
        $disabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[#-1].enabled', false);
        $missing = $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[#-1].enabled', 2);

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.rules[#-1].enabled', true));
        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."rules"[#-000001].enabled', true));
        $t->same(['plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $enabled));
        $t->same(['plugin_beta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $disabled));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$.rules[#-].enabled', true));
    },
    'uses json text operator expression index for wordpress plugin option value lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_enabled_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_enabled_arrow ON wp_options(option_value ->> \'enabled\') WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"enabled":true,"version":2}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"enabled":false,"version":3}', 'no'], 2),
            $schemaCell([null, 'theme_settings', '{"enabled":1,"label":"active"}', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([0, 2]),
            $indexCell([1, 1]),
            $indexCell([1, 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $enabled = $database->wordpressOptionsByIndexedJsonOptionValue('$."enabled"', true);
        $disabled = $database->wordpressOptionsByIndexedJsonOptionValue('$.enabled', false);

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.enabled', true));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_value'));
        $t->same(['plugin_alpha_settings', 'theme_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $enabled));
        $t->same(['plugin_beta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $disabled));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedName('plugin_alpha_settings'));
    },
    'uses json text operator expression index for wordpress root array option values' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_first_channel_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_first_channel_arrow ON wp_options(option_value ->> \'[0]\') WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_channels_alpha', '["enabled","beta"]', 'no'], 1),
            $schemaCell([null, 'plugin_channels_beta', '["disabled"]', 'no'], 2),
            $schemaCell([null, 'plugin_channels_object', '{"0":"enabled"}', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 3]),
            $indexCell(['disabled', 2]),
            $indexCell(['enabled', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $enabled = $database->wordpressOptionsByIndexedJsonOptionValue('$[0]', 'enabled');
        $disabled = $database->wordpressOptionsByIndexedJsonOptionValue('$[0]', 'disabled');

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[0]', 'enabled'));
        $t->same(['plugin_channels_alpha'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $enabled));
        $t->same(['plugin_channels_beta'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $disabled));
    },
    'uses json text operator expression index for wordpress root array reverse lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_last_channel_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_last_channel_arrow ON wp_options(option_value ->> -1) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_channels_alpha', '["stable","beta"]', 'no'], 1),
            $schemaCell([null, 'plugin_channels_beta', '["disabled"]', 'no'], 2),
            $schemaCell([null, 'plugin_channels_object', '{"0":"beta"}', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 3]),
            $indexCell(['beta', 1]),
            $indexCell(['disabled', 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $beta = $database->wordpressOptionsByIndexedJsonOptionValue('$[#-000001]', 'beta');
        $disabled = $database->wordpressOptionsByIndexedJsonOptionValue('$[#-1]', 'disabled');

        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[#-1]', 'beta'));
        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[#-000001]', 'beta'));
        $t->same(null, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[#]', 'beta'));
        $t->same(['plugin_channels_alpha'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $beta));
        $t->same(['plugin_channels_beta'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $disabled));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$[#]', 'beta'));
    },
    'uses json value operator expression index for wordpress plugin setting fragments' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_fragment_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_fragment_arrow ON wp_options(option_value -> \'settings.v1\') WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"settings.v1":{"mode":"dark","flags":[1,2]}}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"settings.v1":"dark"}', 'no'], 2),
            $schemaCell([null, 'plugin_null_settings', '{"settings.v1":null}', 'no'], 3),
            $schemaCell([null, 'plugin_nested_settings', '{"settings":{"v1":{"mode":"dark","flags":[1,2]}}}', 'yes'], 4),
            $schemaCell([null, 'plugin_missing_settings', '{"other":true}', 'yes'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 4]),
            $indexCell([null, 5]),
            $indexCell(['"dark"', 2]),
            $indexCell(['null', 3]),
            $indexCell(['{"mode":"dark","flags":[1,2]}', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $fragment = $database->wordpressOptionsByIndexedJsonOptionFragment('$."settings.v1"', ['mode' => 'dark', 'flags' => [1, 2]]);
        $string = $database->wordpressOptionsByIndexedJsonOptionFragment('$."settings.v1"', 'dark');
        $jsonNull = $database->wordpressOptionsByIndexedJsonOptionFragment('$."settings.v1"', null);
        $wrongPath = $database->indexRootPageForJsonValueOperatorPointLookup('wp_options', 'option_value', '$.settings.v1', ['mode' => 'dark', 'flags' => [1, 2]]);

        $t->same(3, $database->indexRootPageForJsonValueOperatorPointLookup('wp_options', 'option_value', '$."settings.v1"', ['mode' => 'dark', 'flags' => [1, 2]]));
        $t->same(null, $wrongPath);
        $t->same(null, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."settings.v1"', ['mode' => 'dark', 'flags' => [1, 2]]));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_value'));
        $t->same(['plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $fragment));
        $t->same(['plugin_beta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $string));
        $t->same(['plugin_null_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $jsonNull));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionFragment('$."settings.v1"', new stdClass()));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionFragment('$."settings.v1"', ['mode' => 'dark'], -1));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValue('$."settings.v1"', ['mode' => 'dark']));
    },
    'uses json value operator expression index for wordpress plugin setting fragment IN-list lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_fragment_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_fragment_arrow ON wp_options(option_value -> \'settings.v1\') WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"settings.v1":{"mode":"dark","flags":[1,2]}}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"settings.v1":"dark"}', 'no'], 2),
            $schemaCell([null, 'plugin_null_settings', '{"settings.v1":null}', 'no'], 3),
            $schemaCell([null, 'plugin_nested_settings', '{"settings":{"v1":{"mode":"dark","flags":[1,2]}}}', 'yes'], 4),
            $schemaCell([null, 'plugin_missing_settings', '{"other":true}', 'yes'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 4]),
            $indexCell([null, 5]),
            $indexCell(['"dark"', 2]),
            $indexCell(['null', 3]),
            $indexCell(['{"mode":"dark","flags":[1,2]}', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedJsonOptionFragments('$."settings.v1"', [
            ['mode' => 'dark', 'flags' => [1, 2]],
            'dark',
            null,
            'dark',
        ]);
        $limited = $database->wordpressOptionsByIndexedJsonOptionFragments('$."settings.v1"', [
            ['mode' => 'dark', 'flags' => [1, 2]],
            'dark',
            null,
        ], 2);
        $jsonNull = $database->wordpressOptionsByIndexedJsonOptionFragments('$."settings.v1"', [null]);

        $t->same(3, $database->indexRootPageForJsonValueOperatorInLookup('wp_options', 'option_value', '$."settings.v1"', [['mode' => 'dark'], null]));
        $t->same(null, $database->indexRootPageForJsonValueOperatorInLookup('wp_options', 'option_value', '$."settings.v1"', []));
        $t->same(null, $database->indexRootPageForJsonExtractInLookup('wp_options', 'option_value', '$."settings.v1"', [['mode' => 'dark']]));
        $t->same(['plugin_beta_settings', 'plugin_null_settings', 'plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['plugin_beta_settings', 'plugin_null_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['plugin_null_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $jsonNull));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionFragments('$."settings.v1"', [new stdClass()]));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionFragments('$."settings.v1"', ['dark'], -1));
    },
    'uses json value operator expression index for wordpress plugin setting fragment ranges' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_channel_arrow', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_channel_arrow ON wp_options(option_value -> \'channel\') WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_channel', '{"channel":"alpha"}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_channel', '{"channel":"beta"}', 'no'], 2),
            $schemaCell([null, 'plugin_preview_channel', '{"channel":"preview"}', 'yes'], 3),
            $schemaCell([null, 'plugin_stable_channel', '{"channel":"stable"}', 'yes'], 4),
            $schemaCell([null, 'plugin_missing_channel', '{"other":true}', 'yes'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 5]),
            $indexCell(['"alpha"', 1]),
            $indexCell(['"beta"', 2]),
            $indexCell(['"preview"', 3]),
            $indexCell(['"stable"', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $exclusive = $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', 'beta', 'stable');
        $inclusive = $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', 'beta', 'stable', null, true);
        $limited = $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', 'beta', 'stable', 1, true);
        $beforePreview = $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', null, 'preview', null, true);
        $reversed = $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', 'stable', 'beta');

        $t->same(3, $database->indexRootPageForJsonValueOperatorRangeLookup('wp_options', 'option_value', '$.channel', 'beta', 'stable'));
        $t->same(null, $database->indexRootPageForJsonExtractRangeLookup('wp_options', 'option_value', '$.channel', 'beta', 'stable'));
        $t->same(['plugin_beta_channel', 'plugin_preview_channel'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $exclusive));
        $t->same(['plugin_beta_channel', 'plugin_preview_channel', 'plugin_stable_channel'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusive));
        $t->same(['plugin_beta_channel'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['plugin_alpha_channel', 'plugin_beta_channel', 'plugin_preview_channel'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $beforePreview));
        $t->same([], $reversed);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', null, null));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionFragmentRange('$.channel', 'beta', 'stable', -1));
    },
    'uses json_extract expression index for wordpress plugin option value IN-list lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_mode', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_mode ON wp_options(json_extract(option_value, \'$.mode\') COLLATE NOCASE) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"mode":"enabled","version":2}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"mode":"disabled","version":3}', 'no'], 2),
            $schemaCell([null, 'plugin_gamma_settings', '{"mode":"ENABLED","version":4}', 'yes'], 3),
            $schemaCell([null, 'theme_settings', '{"mode":"preview","version":1}', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['disabled', 2]),
            $indexCell(['enabled', 1]),
            $indexCell(['ENABLED', 3]),
            $indexCell(['preview', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedJsonOptionValues('$.mode', ['ENABLED', 'disabled', 'ENABLED', null]);
        $limited = $database->wordpressOptionsByIndexedJsonOptionValues('$.mode', ['enabled', 'disabled'], 2);
        $nullOnly = $database->wordpressOptionsByIndexedJsonOptionValues('$.mode', [null]);

        $t->same(3, $database->indexRootPageForJsonExtractInLookup('wp_options', 'option_value', '$.mode', ['ENABLED', 'disabled']));
        $t->same(null, $database->indexRootPageForJsonExtractInLookup('wp_options', 'option_value', '$.mode', [null]));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_value'));
        $t->same(['plugin_beta_settings', 'plugin_alpha_settings', 'plugin_gamma_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['{"mode":"disabled","version":3}', '{"mode":"enabled","version":2}', '{"mode":"ENABLED","version":4}'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['plugin_beta_settings', 'plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValues('$[0]', ['enabled']));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValues('$.mode', [new stdClass()]));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValues('$.mode', ['enabled'], -1));
    },
    'uses json_extract expression IN-list seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_mode', 'wp_options', 2, 'CREATE INDEX wp_options_plugin_mode ON wp_options(json_extract(option_value, \'$.mode\')) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['beta', 99]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"mode":"enabled"}', 'no'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['enabled', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedJsonOptionValues('$.mode', ['enabled', 'missing']);

        $t->same(2, $database->indexRootPageForJsonExtractInLookup('wp_options', 'option_value', '$.mode', ['enabled', 'missing']));
        $t->same(['plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['{"mode":"enabled"}'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses json_extract expression index for wordpress plugin option value ranges' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_priority', 'wp_options', 3, 'CREATE INDEX wp_options_plugin_priority ON wp_options(json_extract(option_value, \'$.priority\')) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"priority":1,"mode":"disabled"}', 'no'], 1),
            $schemaCell([null, 'plugin_beta_settings', '{"priority":5,"mode":"enabled"}', 'no'], 2),
            $schemaCell([null, 'plugin_gamma_settings', '{"priority":9,"mode":"enabled"}', 'yes'], 3),
            $schemaCell([null, 'plugin_delta_settings', '{"priority":15,"mode":"preview"}', 'yes'], 4),
            $schemaCell([null, 'plugin_unranked_settings', '{"mode":"manual"}', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 5]),
            $indexCell([1, 1]),
            $indexCell([5, 2]),
            $indexCell([9, 3]),
            $indexCell([15, 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $exclusive = $database->wordpressOptionsByIndexedJsonOptionValueRange('$.priority', 5, 15);
        $inclusive = $database->wordpressOptionsByIndexedJsonOptionValueRange('$.priority', 5, 15, null, true);
        $limited = $database->wordpressOptionsByIndexedJsonOptionValueRange('$.priority', 5, 15, 1);
        $reversed = $database->wordpressOptionsByIndexedJsonOptionValueRange('$.priority', 15, 5);

        $t->same(3, $database->indexRootPageForJsonExtractRangeLookup('wp_options', 'option_value', '$.priority', 5, 15));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_value'));
        $t->same(['plugin_beta_settings', 'plugin_gamma_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $exclusive));
        $t->same(['plugin_beta_settings', 'plugin_gamma_settings', 'plugin_delta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusive));
        $t->same(['plugin_beta_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $reversed);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValueRange('$[0]', 1, 10));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValueRange('$.priority', null, null));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedJsonOptionValueRange('$.priority', 1, 10, -1));
    },
    'uses json_extract expression range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_plugin_mode', 'wp_options', 2, 'CREATE INDEX wp_options_plugin_mode ON wp_options(json_extract(option_value, \'$.mode\') COLLATE NOCASE) WHERE option_value IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['disabled', 99]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'plugin_alpha_settings', '{"mode":"Enabled"}', 'no'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['Enabled', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedJsonOptionValueRange('$.mode', 'enabled', 'enabled', null, true);

        $t->same(2, $database->indexRootPageForJsonExtractRangeLookup('wp_options', 'option_value', '$.mode', 'enabled', 'enabled', true));
        $t->same(['plugin_alpha_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['{"mode":"Enabled"}'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses length expression IN-list seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_length', 'wp_options', 2, 'CREATE INDEX wp_options_option_name_length ON wp_options(length(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, [5, 99]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell([10, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedNameLengths([10, 12]);

        $t->same(2, $database->indexRootPageForLengthInLookup('wp_options', 'option_name', [10, 12]));
        $t->same(['db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['58796'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses length expression index for wordpress option_name length ranges' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_length', 'wp_options', 3, 'CREATE INDEX wp_options_option_name_length ON wp_options(length(option_name) DESC) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'cron', '1', 'no'], 3),
            $schemaCell([null, 'café', 'unicode-name', 'no'], 4),
            $schemaCell([null, 'db_version', '58796', 'yes'], 5),
            $schemaCell([null, 'very_long_option', 'payload', 'no'], 6),
            $schemaCell([null, null, 'draft option without name', 'no'], 7),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([16, 6]),
            $indexCell([10, 5]),
            $indexCell([7, 1]),
            $indexCell([4, 2]),
            $indexCell([4, 3]),
            $indexCell([4, 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $medium = $database->wordpressOptionsByIndexedNameLengthRange(5, 11);
        $limited = $database->wordpressOptionsByIndexedNameLengthRange(5, 11, 1);
        $inclusiveSingle = $database->wordpressOptionsByIndexedNameLengthRange(4, 4, null, true);
        $exclusiveEmpty = $database->wordpressOptionsByIndexedNameLengthRange(4, 4);
        $shortNames = $database->wordpressOptionsByIndexedNameLengthRange(null, 5);

        $t->same(3, $database->indexRootPageForLengthRangeLookup('wp_options', 'option_name', 5, 11));
        $t->same(['db_version', 'siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $medium));
        $t->same(['58796', 'https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $medium));
        $t->same(['db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['home', 'cron', 'café'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusiveSingle));
        $t->same([], $exclusiveEmpty);
        $t->same(['home', 'cron', 'café'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $shortNames));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameLengthRange(null, null));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameLengthRange(-1, 10));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameLengthRange(1, 10, -1));
    },
    'uses length expression range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_length', 'wp_options', 2, 'CREATE INDEX wp_options_option_name_length ON wp_options(length(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, [5, 99]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'db_version', '58796', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell([10, 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedNameLengthRange(9, 12);

        $t->same(2, $database->indexRootPageForLengthRangeLookup('wp_options', 'option_name', 9, 12));
        $t->same(['db_version'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['58796'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses substr expression index for wordpress option_name prefix scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_name_prefix', 'wp_options', 3, 'CREATE INDEX wp_options_name_prefix ON wp_options(substr(option_name,1,11) COLLATE NOCASE) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_Transient_Feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 2),
            $schemaCell([null, '_site_transient_update_plugins', 'site-cache', 'yes'], 3),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['_site_trans', 3]),
            $indexCell(['_Transient_', 1]),
            $indexCell(['_transient_', 2]),
            $indexCell(['siteurl', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedNamePrefix('_TRANSIENT_');
        $limited = $database->wordpressOptionsByIndexedNamePrefix('_TRANSIENT_', 1);
        $missing = $database->wordpressOptionsByIndexedNamePrefix('_transientX');

        $t->same(3, $database->indexRootPageForSubstringPointLookup('wp_options', 'option_name', 1, 11, '_TRANSIENT_'));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(['_Transient_Feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->same(['_Transient_Feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNamePrefix(''));
    },
    'uses substr expression index for wordpress option_name prefix IN-list scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_name_prefix', 'wp_options', 3, 'CREATE INDEX wp_options_name_prefix ON wp_options(substr(option_name,1,11) COLLATE NOCASE) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_Transient_Feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_site_transient_update_plugins', 'site-cache', 'yes'], 2),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 3),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 4),
            $schemaCell([null, null, 'draft option without name', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['_site_trans', 2]),
            $indexCell(['_Transient_', 1]),
            $indexCell(['_transient_', 3]),
            $indexCell(['siteurl', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNamePrefixes(['_TRANSIENT_', '_site_trans', '_TRANSIENT_', null]);
        $limited = $database->wordpressOptionsByIndexedNamePrefixes(['_TRANSIENT_', '_site_trans'], 2);
        $nullOnly = $database->wordpressOptionsByIndexedNamePrefixes([null]);

        $t->same(3, $database->indexRootPageForSubstringInLookup('wp_options', 'option_name', 1, 11, ['_TRANSIENT_', '_site_trans']));
        $t->same(null, $database->indexRootPageForSubstringInLookup('wp_options', 'option_name', 1, 10, ['_TRANSIENT_']));
        $t->same(['_site_transient_update_plugins', '_Transient_Feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['site-cache', 'cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
        $t->same(['_site_transient_update_plugins', '_Transient_Feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $nullOnly);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNamePrefixes([123]));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNamePrefixes(['']));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNamePrefixes(['_transient_', 'home']));
    },
    'uses substr expression IN-list seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_name_prefix', 'wp_options', 2, 'CREATE INDEX wp_options_name_prefix ON wp_options(substr(option_name,1,11)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['blogname', 99]]], 5);
        $page3 = $indexLeafPage([
            $indexCell(['_site_trans', 1]),
            $indexCell(['_transient_', 2]),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, '_site_transient_update_plugins', 'site-cache', 'yes'], 1),
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 2),
        ]);
        $page5 = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedNamePrefixes(['_transient_', '_site_trans']);

        $t->same(2, $database->indexRootPageForSubstringInLookup('wp_options', 'option_name', 1, 11, ['_transient_', '_site_trans']));
        $t->same(['_site_transient_update_plugins', '_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['site-cache', 'cached-feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses negative-start substr expression index for wordpress option_name suffix scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_name_suffix', 'wp_options', 3, 'CREATE INDEX wp_options_name_suffix ON wp_options(substr(option_name,-9) COLLATE NOCASE DESC) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'theme_settings', 'theme-payload', 'yes'], 1),
            $schemaCell([null, 'plugin_SETTINGS', 'plugin-payload', 'no'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
            $schemaCell([null, null, 'draft option without name', 'no'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['siteurl', 3]),
            $indexCell(['_settings', 1]),
            $indexCell(['_SETTINGS', 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $settings = $database->wordpressOptionsByIndexedNameSuffix('_SETTINGS');
        $limited = $database->wordpressOptionsByIndexedNameSuffix('_settings', 1);
        $missing = $database->wordpressOptionsByIndexedNameSuffix('_controls');

        $t->same(3, $database->indexRootPageForSubstringPointLookup('wp_options', 'option_name', -9, null, '_SETTINGS'));
        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(['theme_settings', 'plugin_SETTINGS'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $settings));
        $t->same(['theme-payload', 'plugin-payload'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $settings));
        $t->same(['theme_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $missing);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameSuffix(''));
        $t->throws(InvalidArgumentException::class, static fn () => $database->indexRootPageForSubstringPointLookup('wp_options', 'option_name', 0, null, ''));
    },
    'uses lower expression index range for case folded wordpress option_name scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_lower_option_name ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_Transient_Feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_Feed', '1700000000', 'no'], 2),
            $schemaCell([null, 'BLOGNAME', 'Ported SQLite', 'yes'], 3),
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['_transient_feed', 1]),
            $indexCell(['_transient_timeout_feed', 2]),
            $indexCell(['blogname', 3]),
            $indexCell(['siteurl', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedLowercaseNameRange('_TRANSIENT_', '_TRANSIENT`');
        $limited = $database->wordpressOptionsByIndexedLowercaseNameRange('_TRANSIENT_', '_TRANSIENT`', 1);
        $site = $database->wordpressOptionsByIndexedLowercaseNameRange('SITE', null);

        $t->same(3, $database->indexRootPageForLowercaseRangeLookup('wp_options', 'option_name', '_TRANSIENT_', '_TRANSIENT`'));
        $t->same(null, $database->indexRootPageForRangeLookup('wp_options', 'option_name', '_TRANSIENT_', '_TRANSIENT`'));
        $t->same(['_Transient_Feed', '_transient_timeout_Feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->same(['_Transient_Feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $site));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedLowercaseNameRange(null, null));
    },
    'uses lower expression range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_lower_option_name', 'wp_options', 2, 'CREATE INDEX wp_options_lower_option_name ON wp_options(lower(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['blogname', 2]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedLowercaseNameRange('SITEURL', null);

        $t->same(2, $database->indexRootPageForLowercaseRangeLookup('wp_options', 'option_name', 'SITEURL', null));
        $t->same(['SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses upper expression index range for ascii folded wordpress option_name scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_upper_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_upper_option_name ON wp_options(upper(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_Transient_Feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_Feed', '1700000000', 'no'], 2),
            $schemaCell([null, 'BLOGNAME', 'Ported SQLite', 'yes'], 3),
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['BLOGNAME', 3]),
            $indexCell(['SITEURL', 4]),
            $indexCell(['_TRANSIENT_FEED', 1]),
            $indexCell(['_TRANSIENT_TIMEOUT_FEED', 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedUppercaseNameRange('_transient_', '_transient`');
        $limited = $database->wordpressOptionsByIndexedUppercaseNameRange('_transient_', '_transient`', 1);
        $site = $database->wordpressOptionsByIndexedUppercaseNameRange('site', 'sitev');

        $t->same(3, $database->indexRootPageForUppercaseRangeLookup('wp_options', 'option_name', '_transient_', '_transient`'));
        $t->same(null, $database->indexRootPageForRangeLookup('wp_options', 'option_name', '_transient_', '_transient`'));
        $t->same(['_Transient_Feed', '_transient_timeout_Feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->same(['_Transient_Feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $site));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedUppercaseNameRange(null, null));
    },
    'uses upper expression range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_upper_option_name', 'wp_options', 2, 'CREATE INDEX wp_options_upper_option_name ON wp_options(upper(option_name)) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['BLOGNAME', 2]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'SiteURL', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['SITEURL', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedUppercaseNameRange('siteurl', null);

        $t->same(2, $database->indexRootPageForUppercaseRangeLookup('wp_options', 'option_name', 'siteurl', null));
        $t->same(['SiteURL'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'skips partial option_name indexes for whole-table wordpress lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoloaded_name', 'wp_options', 3, "CREATE INDEX wp_options_autoloaded_name ON wp_options(option_name) WHERE autoload='yes'"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedName('siteurl'));
    },
    'uses partial is not null option_name index for wordpress point lookups' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_present_name', 'wp_options', 3, 'CREATE INDEX wp_options_present_name ON wp_options(option_name) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedName('home');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(3, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'home'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(2, $option->optionId);
        $t->same('home', $option->optionName);
        $t->same('https://example.test/blog', $option->optionValue);
    },
    'uses equality partial option_name index when wordpress autoload predicate is known' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoloaded_name', 'wp_options', 3, "CREATE INDEX wp_options_autoloaded_name ON wp_options(option_name) WHERE autoload='yes'"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'no'], 2),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 3]),
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedNameForAutoload('siteurl', 'yes');
        $missingAutoloadedName = $database->wordpressOptionByIndexedNameForAutoload('blogname', 'yes');

        $t->same(null, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'siteurl'));
        $t->same(3, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'siteurl', [
            'autoload' => 'yes',
        ]));
        $t->same(null, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'siteurl', [
            'autoload' => 'no',
        ]));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->optionId);
        $t->same('siteurl', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
        $t->same('yes', $option->autoload);
        $t->same(null, $missingAutoloadedName);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedNameForAutoload('siteurl', 'no'));
    },
    'uses or equality partial option_name index when wordpress autoload predicate matches one term' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoloaded_name', 'wp_options', 3, "CREATE INDEX wp_options_autoloaded_name ON wp_options(option_name) WHERE autoload='yes' OR autoload='on'"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'on'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'no'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $siteurl = $database->wordpressOptionByIndexedNameForAutoload('siteurl', 'yes');
        $home = $database->wordpressOptionByIndexedNameForAutoload('home', 'on');

        $t->same(3, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'siteurl', [
            'autoload' => 'yes',
        ]));
        $t->same(3, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'home', [
            'autoload' => 'on',
        ]));
        $t->same(null, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'blogname', [
            'autoload' => 'no',
        ]));
        $t->true($siteurl instanceof SQLiteWordPressOption);
        $t->same('https://example.test', $siteurl->optionValue);
        $t->true($home instanceof SQLiteWordPressOption);
        $t->same('https://example.test/blog', $home->optionValue);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedNameForAutoload('blogname', 'no'));
    },
    'uses and equality partial option_name index only when every wordpress predicate is known' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoloaded_present_name', 'wp_options', 3, "CREATE INDEX wp_options_autoloaded_present_name ON wp_options(option_name) WHERE autoload='yes' AND option_name IS NOT NULL"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'no'], 2),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 3]),
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedNameForAutoload('home', 'yes');

        $t->same(null, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'home'));
        $t->same(3, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'home', [
            'autoload' => 'yes',
        ]));
        $t->same(null, $database->indexRootPageForPointLookupWithConstraints('wp_options', 'option_name', 'home', [
            'autoload' => 'no',
        ]));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same('home', $option->optionName);
        $t->same('https://example.test/blog', $option->optionValue);
        $t->same('yes', $option->autoload);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionByIndexedNameForAutoload('home', 'no'));
    },
    'uses nonunique composite autoload index to scan duplicate wordpress options' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_name', 'wp_options', 3, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 2),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['no', '_transient_timeout_feed', 3]),
            $indexCell(['yes', 'blogname', 2]),
            $indexCell(['yes', 'siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $autoloaded = $database->wordpressOptionsByIndexedAutoload('yes');
        $limited = $database->wordpressOptionsByIndexedAutoload('yes', 1);
        $missing = $database->wordpressOptionsByIndexedAutoload('maybe');

        $t->same(3, $database->indexRootPageForPointLookup('wp_options', 'autoload', 'yes'));
        $t->same(['blogname', 'siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $autoloaded));
        $t->same(['Ported SQLite', 'https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $autoloaded));
        $t->same(['blogname'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $missing);
    },
    'uses composite autoload and option_name index to fetch one wordpress option' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_name', 'wp_options', 3, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name COLLATE NOCASE) WHERE autoload IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 2),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['no', '_transient_timeout_feed', 3]),
            $indexCell(['yes', 'blogname', 2]),
            $indexCell(['yes', 'siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedAutoloadAndName('yes', 'SITEURL');
        $missing = $database->wordpressOptionByIndexedAutoloadAndName('yes', 'missing');

        $t->same(3, $database->indexRootPageForPointLookupColumns('wp_options', [
            'autoload' => 'yes',
            'option_name' => 'SITEURL',
        ]));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->optionId);
        $t->same('siteurl', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
        $t->same(null, $missing);
    },
    'uses composite autoload equality and option_name range to scan wordpress options' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_name', 'wp_options', 3, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_site_transient_update_plugins', 'site-cache', 'yes'], 1),
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 2),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 3),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 4),
            $schemaCell([null, 'cron_lock', '1', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['no', '_transient_feed', 2]),
            $indexCell(['no', '_transient_timeout_feed', 3]),
            $indexCell(['no', 'cron_lock', 5]),
            $indexCell(['yes', '_site_transient_update_plugins', 1]),
            $indexCell(['yes', 'blogname', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', '_transient_', '_transient`');
        $limited = $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', '_transient_', '_transient`', 1);
        $inclusiveSingle = $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', '_transient_feed', '_transient_feed', null, true);
        $exclusiveEmpty = $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', '_transient_feed', '_transient_feed');

        $t->same(3, $database->indexRootPageForPrefixRangeLookup('wp_options', [
            'autoload' => 'no',
        ], 'option_name', '_transient_', '_transient`'));
        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->same(['_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same(['_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusiveSingle));
        $t->same([], $exclusiveEmpty);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', null, null));
    },
    'uses composite equality range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_name', 'wp_options', 2, 'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['no', 'zzzz_after_transients', 4]]], 5);
        $page3 = $indexLeafPage([
            $indexCell(['no', '_transient_feed', 1]),
            $indexCell(['no', '_transient_timeout_feed', 2]),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 2),
        ]);
        $page5 = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $transients = $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', '_transient_', '_transient`');

        $t->same(2, $database->indexRootPageForPrefixRangeLookup('wp_options', [
            'autoload' => 'no',
        ], 'option_name', '_transient_', '_transient`'));
        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
    },
    'uses multi-column equality prefix and option_name range to scan wordpress options' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_value_name', 'wp_options', 2, 'CREATE INDEX wp_options_autoload_value_name ON wp_options(autoload, option_value, option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['no', 'zzzz-out-of-range', 'later_name', 99]]], 5);
        $page3 = $indexLeafPage([
            $indexCell(['no', 'cached-feed', '_transient_feed', 1]),
            $indexCell(['no', 'cached-feed', '_transient_timeout_feed', 2]),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_feed', 'cached-feed', 'no'], 2),
        ]);
        $page5 = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $prefix = [
            'autoload' => 'no',
            'option_value' => 'cached-feed',
        ];
        $transients = $database->wordpressOptionsByIndexedNameRangeWithPrefix($prefix, '_transient_', '_transient`');

        $t->same(2, $database->indexRootPageForPrefixRangeLookup('wp_options', $prefix, 'option_name', '_transient_', '_transient`'));
        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', 'cached-feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefix([], '_transient_', '_transient`'));
    },
    'uses supplied custom collation callbacks for composite equality prefix range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_value_name', 'wp_options', 2, 'CREATE INDEX wp_options_value_name ON wp_options(option_value COLLATE WPSLUG, option_name) WHERE option_value IS NOT NULL AND option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['theme-core', '_transient_feed', 99]]], 5);
        $page3 = $indexLeafPage([
            $indexCell(['Plugin-Core', '_transient_feed', 1]),
            $indexCell(['plugin_core', '_transient_timeout_feed', 2]),
            $indexCell(['Plugin-Core', 'cron_lock', 3]),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, '_transient_feed', 'Plugin-Core', 'no'], 1),
            $schemaCell([null, '_transient_timeout_feed', 'plugin_core', 'no'], 2),
            $schemaCell([null, 'cron_lock', 'Plugin-Core', 'no'], 3),
        ]);
        $page5 = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);
        $asciiLower = static function (string $value): string {
            $bytes = $value;
            $length = strlen($bytes);
            for ($i = 0; $i < $length; $i++) {
                $ord = ord($bytes[$i]);
                if ($ord >= 0x41 && $ord <= 0x5a) {
                    $bytes[$i] = chr($ord + 0x20);
                }
            }

            return $bytes;
        };
        $wpslug = static function (string $left, string $right) use ($asciiLower): int {
            return strcmp(str_replace('_', '-', $asciiLower($left)), str_replace('_', '-', $asciiLower($right)));
        };
        $prefix = ['option_value' => 'plugin_core'];

        $transients = $database->wordpressOptionsByIndexedNameRangeWithPrefixCollations(
            $prefix,
            '_transient_',
            '_transient`',
            ['WPSLUG' => $wpslug],
        );
        $limited = $database->wordpressOptionsByIndexedNameRangeWithPrefixCollations(
            $prefix,
            '_transient_',
            '_transient`',
            ['wpslug' => $wpslug],
            1,
        );

        $t->same(2, $database->indexRootPageForPrefixRangeLookupWithCollations('wp_options', $prefix, 'option_name', '_transient_', '_transient`', ['WPSLUG' => $wpslug]));
        $t->same(null, $database->indexRootPageForPrefixRangeLookupWithCollations('wp_options', $prefix, 'option_name', '_transient_', '_transient`', []));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefix($prefix, '_transient_', '_transient`'));
        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['Plugin-Core', 'plugin_core'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->same(['_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefixCollations($prefix, null, null, ['WPSLUG' => $wpslug]));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRangeWithPrefixCollations($prefix, '_transient_', '_transient`', ['WPSLUG' => static fn (): string => '0']));
    },
    'uses partial composite autoload and option_name range indexes when predicates are implied' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_no_autoload_name', 'wp_options', 3, "CREATE INDEX wp_options_no_autoload_name ON wp_options(autoload, option_name COLLATE NOCASE DESC) WHERE autoload='no' AND option_name IS NOT NULL"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_TRANSIENT_TIMEOUT_FEED', '1700000000', 'no'], 2),
            $schemaCell([null, '_transient_update_plugins', 'autoloaded-cache', 'yes'], 3),
            $schemaCell([null, null, 'draft missing name', 'no'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['no', '_TRANSIENT_TIMEOUT_FEED', 2]),
            $indexCell(['no', '_transient_feed', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedAutoloadAndNameRange('no', '_TRANSIENT_', '_TRANSIENT`');

        $t->same(3, $database->indexRootPageForPrefixRangeLookup('wp_options', [
            'autoload' => 'no',
        ], 'option_name', '_TRANSIENT_', '_TRANSIENT`'));
        $t->same(null, $database->indexRootPageForPrefixRangeLookup('wp_options', [
            'autoload' => 'yes',
        ], 'option_name', '_TRANSIENT_', '_TRANSIENT`'));
        $t->same(['_TRANSIENT_TIMEOUT_FEED', '_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['1700000000', 'cached-feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedAutoloadAndNameRange('yes', '_TRANSIENT_', '_TRANSIENT`'));
    },
    'uses partial autoload is not null index for duplicate wordpress option scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_autoload_present', 'wp_options', 3, 'CREATE INDEX wp_options_autoload_present ON wp_options(autoload, option_name) WHERE autoload IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'draft_flag', '1', null], 2),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['yes', 'siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $autoloaded = $database->wordpressOptionsByIndexedAutoload('yes');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'autoload'));
        $t->same(3, $database->indexRootPageForPointLookup('wp_options', 'autoload', 'yes'));
        $t->same(['siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $autoloaded));
    },
    'uses option_name range bounds to scan transient wordpress options through an index' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_site_transient_update_plugins', 'cached-site-transient', 'yes'], 1),
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 2),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 3),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['_site_transient_update_plugins', 1]),
            $indexCell(['_transient_feed', 2]),
            $indexCell(['_transient_timeout_feed', 3]),
            $indexCell(['blogname', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedNameRange('_transient_', '_transient`');
        $limited = $database->wordpressOptionsByIndexedNameRange('_transient_', '_transient`', 1);
        $empty = $database->wordpressOptionsByIndexedNameRange('blogname', 'blogname');

        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->same(['cached-feed', '1700000000'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $transients));
        $t->same(['_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
        $t->same([], $empty);
    },
    'uses partial option_name is not null indexes for indexed name range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_present_name', 'wp_options', 3, 'CREATE INDEX wp_options_present_name ON wp_options(option_name) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 1),
            $schemaCell([null, null, 'draft option without name', 'no'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['home', 1]),
            $indexCell(['siteurl', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNameRange('h', 'i');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(3, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'home'));
        $t->same(['home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test/blog'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses open ended and inclusive option_name range bounds through a wordpress index' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'alpha_option', 'alpha', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
            $schemaCell([null, 'zz_cleanup_marker', '1', 'no'], 4),
            $schemaCell([null, null, 'draft option without name', 'no'], 5),
        ]);
        $page3 = $indexLeafPage([
            $indexCell([null, 5]),
            $indexCell(['alpha_option', 1]),
            $indexCell(['blogname', 2]),
            $indexCell(['siteurl', 3]),
            $indexCell(['zz_cleanup_marker', 4]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $beforeBlog = $database->wordpressOptionsByIndexedNameRange(null, 'blogname');
        $throughBlog = $database->wordpressOptionsByIndexedNameRange(null, 'blogname', null, true);
        $afterSiteurl = $database->wordpressOptionsByIndexedNameRange('siteurl', null);
        $limited = $database->wordpressOptionsByIndexedNameRange(null, 'zzzz', 1);

        $t->same(3, $database->indexRootPageForRangeLookup('wp_options', 'option_name', null, 'blogname'));
        $t->same(['alpha_option'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $beforeBlog));
        $t->same(['alpha_option', 'blogname'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $throughBlog));
        $t->same(['siteurl', 'zz_cleanup_marker'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $afterSiteurl));
        $t->same(['alpha_option'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $limited));
    },
    'uses first-column range seek bounds without reading out-of-range index pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage, $indexInteriorPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name', 'wp_options', 2, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $indexInteriorPage([[3, ['blogname', 2]]], 5);
        $page3 = str_repeat("\0", 512);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByIndexedNameRange('siteurl', null);

        $t->same(2, $database->indexRootPageForRangeLookup('wp_options', 'option_name', 'siteurl', null));
        $t->same(['siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['https://example.test'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionValue, $options));
    },
    'uses upper-only bounds to imply partial is not null option_name range indexes' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_present_name', 'wp_options', 3, 'CREATE INDEX wp_options_present_name ON wp_options(option_name) WHERE option_name IS NOT NULL'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'alpha_option', 'alpha', 'yes'], 1),
            $schemaCell([null, null, 'draft option without name', 'no'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['alpha_option', 1]),
            $indexCell(['siteurl', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByIndexedNameRange(null, 'm');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(3, $database->indexRootPageForRangeLookup('wp_options', 'option_name', null, 'm'));
        $t->same(['alpha_option'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRange(null, null));
    },
    'uses comparison partial option_name indexes for wordpress transient range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_transient_name', 'wp_options', 3, "CREATE INDEX wp_options_transient_name ON wp_options(option_name) WHERE option_name >= '_transient_' AND option_name < '_transient`'"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['_transient_feed', 1]),
            $indexCell(['_transient_timeout_feed', 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $transients = $database->wordpressOptionsByIndexedNameRange('_transient_', '_transient`');
        $single = $database->wordpressOptionByIndexedName('_transient_feed');

        $t->same(null, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same(3, $database->indexRootPageForRangeLookup('wp_options', 'option_name', '_transient_', '_transient`'));
        $t->same(3, $database->indexRootPageForPointLookup('wp_options', 'option_name', '_transient_feed'));
        $t->same(null, $database->indexRootPageForPointLookup('wp_options', 'option_name', 'siteurl'));
        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $transients));
        $t->true($single instanceof SQLiteWordPressOption);
        $t->same('cached-feed', $single->optionValue);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByIndexedNameRange('blogname', 'siteurl'));
    },
    'uses between partial option_name indexes for inclusive wordpress range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_transient_name_between', 'wp_options', 3, "CREATE INDEX wp_options_transient_name_between ON wp_options(option_name) WHERE option_name BETWEEN '_transient_' AND '_transient_timeout_feed'"], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, '_transient_feed', 'cached-feed', 'no'], 1),
            $schemaCell([null, '_transient_timeout_feed', '1700000000', 'no'], 2),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['_transient_feed', 1]),
            $indexCell(['_transient_timeout_feed', 2]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $inclusive = $database->wordpressOptionsByIndexedNameRange('_transient_', '_transient_timeout_feed', null, true);
        $exclusive = $database->wordpressOptionsByIndexedNameRange('_transient_', '_transient_timeout_feed');

        $t->same(3, $database->indexRootPageForRangeLookup('wp_options', 'option_name', '_transient_', '_transient_timeout_feed', true));
        $t->same(['_transient_feed', '_transient_timeout_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusive));
        $t->same(['_transient_feed'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $exclusive));
    },
    'uses descending option_name indexes for inclusive wordpress range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_option_name_desc', 'wp_options', 3, 'CREATE INDEX wp_options_option_name_desc ON wp_options(option_name DESC)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'admin_email', 'admin@example.test', 'yes'], 1),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 2),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 3),
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 4),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['siteurl', 4]),
            $indexCell(['home', 3]),
            $indexCell(['blogname', 2]),
            $indexCell(['admin_email', 1]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $inclusive = $database->wordpressOptionsByIndexedNameRange('blogname', 'siteurl', null, true);
        $exclusive = $database->wordpressOptionsByIndexedNameRange('blogname', 'siteurl');

        $t->same(3, $database->indexRootPageForRangeLookup('wp_options', 'option_name', 'blogname', 'siteurl'));
        $t->same(['siteurl', 'home', 'blogname'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusive));
        $t->same(['home', 'blogname'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $exclusive));
    },
    'infers sqlite automatic unique index columns from create table sql' => static function (TestRunner $t): void {
        $t->same([
            'option_name',
            'slug',
        ], SQLiteCreateTable::uniqueAutoIndexFirstColumns('CREATE TABLE wp_options(option_id integer primary key, option_name text UNIQUE, option_value text, CONSTRAINT uq_slug UNIQUE("slug" COLLATE nocase))'));
        $t->same([
            'legacy_name',
        ], SQLiteCreateTable::uniqueAutoIndexFirstColumns("CREATE TABLE t(a text CHECK(a <> 'UNIQUE'), [legacy_name] text unique on conflict ignore)"));
    },
    'infers sqlite primary key autoindex columns without consuming rowid aliases' => static function (TestRunner $t): void {
        $t->same([
            'option_name',
        ], SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE wp_options(option_id integer, option_name text PRIMARY KEY, option_value text)'));
        $t->same([
            'option_name',
        ], SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE wp_options(option_id integer, option_name text, option_value text, PRIMARY KEY(option_name))'));
        $t->same([
            'option_name',
        ], SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name text UNIQUE, option_value text)'));
        $t->same([
            'option_id',
            'option_name',
        ], SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY DESC, option_name text UNIQUE, option_value text)'));
        $t->same([
            'option_name',
            'autoload',
        ], SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE wp_options(option_name text UNIQUE PRIMARY KEY, autoload text UNIQUE)'));
        $t->same([
            'autoload',
        ], SQLiteCreateTable::automaticIndexFirstColumns('CREATE TABLE wp_options(option_name text PRIMARY KEY, autoload text UNIQUE) WITHOUT ROWID'));
    },
    'infers sqlite automatic index collation metadata from create table sql' => static function (TestRunner $t): void {
        $columns = SQLiteCreateTable::automaticIndexFirstColumnMetadata(
            'CREATE TABLE wp_options(option_id integer primary key, option_name text COLLATE binary COLLATE nocase UNIQUE, slug text COLLATE binary, UNIQUE(slug COLLATE rtrim DESC))',
        );

        $t->same(['option_name', 'slug'], array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns));
        $t->same(['NOCASE', 'RTRIM'], array_map(static fn (SQLiteIndexColumn $column): string => $column->collation, $columns));
        $t->same([false, true], array_map(static fn (SQLiteIndexColumn $column): bool => $column->descending, $columns));
    },
    'uses sqlite automatic unique index rows with null sql to fetch a wordpress option' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text UNIQUE, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null], 2),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $tableInteriorPage([[4, 1]], 5);
        $page3 = $indexLeafPage([
            $indexCell(['home', 2]),
            $indexCell(['siteurl', 1]),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page5 = $tableLeafPage([
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $option = $database->wordpressOptionByIndexedName('siteurl');

        $t->same(3, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->optionId);
        $t->same('siteurl', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
    },
    'uses sqlite automatic unique index collation and direction for wordpress option lookup' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text, UNIQUE(option_name COLLATE NOCASE DESC))'], 1),
            $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
        ]);
        $page3 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
            $indexCell(['home', 2]),
            $indexCell(['blogname', 3]),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $option = $database->wordpressOptionByIndexedName('BLOGNAME');

        $t->same(3, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->optionId);
        $t->same('blogname', $option->optionName);
        $t->same('Ported SQLite', $option->optionValue);
    },
    'uses sqlite automatic primary key index rows after earlier unique autoindexes' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text, autoload text UNIQUE, PRIMARY KEY(option_name))'], 1),
            $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null], 2),
            $schemaCell(['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 4, null], 3),
        ], 512, 100, $makeFirstPage(512, 6));
        $page2 = $tableInteriorPage([[5, 1]], 6);
        $page3 = $indexLeafPage([
            $indexCell(['yes', 1]),
        ]);
        $page4 = $indexLeafPage([
            $indexCell(['siteurl', 1]),
        ]);
        $page5 = $tableLeafPage([
            $schemaCell([1, 'siteurl', 'https://example.test', 'yes'], 1),
        ]);
        $page6 = $tableLeafPage([]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5 . $page6);

        $option = $database->wordpressOptionByIndexedName('siteurl');

        $t->same(4, $database->indexRootPageForColumn('wp_options', 'option_name'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->optionId);
        $t->same('siteurl', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
        $t->same('yes', $option->autoload);
    },
    'reads sqlite_sequence autoincrement counters for wordpress tables' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID integer primary key autoincrement, post_title text)'], 1),
            $schemaCell(['table', 'wp_comments', 'wp_comments', 3, 'CREATE TABLE wp_comments(comment_ID integer primary key autoincrement, comment_content text)'], 2),
            $schemaCell(['table', 'sqlite_sequence', 'sqlite_sequence', 4, 'CREATE TABLE sqlite_sequence(name,seq)'], 3),
        ], 512, 100, $makeFirstPage(512, 4));
        $page2 = $tableLeafPage([]);
        $page3 = $tableLeafPage([]);
        $page4 = $tableLeafPage([
            $schemaCell(['wp_posts', 120], 1),
            $schemaCell(['wp_comments', 7], 2),
            $schemaCell([null, 'manually-mutated'], 3),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);

        $records = $database->sqliteSequenceRecords();
        $postsSequence = $database->sqliteSequenceForTable('wp_posts');

        $t->same(3, count($records));
        $t->true($records[0] instanceof SQLiteSequenceRecord);
        $t->same([
            ['name' => 'wp_posts', 'seq' => 120, 'rowid' => 1],
            ['name' => 'wp_comments', 'seq' => 7, 'rowid' => 2],
            ['name' => null, 'seq' => 'manually-mutated', 'rowid' => 3],
        ], array_map(static fn (SQLiteSequenceRecord $record): array => $record->toArray(), $records));
        $t->same(120, $postsSequence?->integerSequence());
        $t->same(null, $database->sqliteSequenceForTable('wp_options'));
        $t->same([], $database->sqliteSequenceRecords(0));
    },
    'allocates sqlite autoincrement rowids from sqlite_sequence state' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID integer primary key autoincrement, post_title text)'], 1),
            $schemaCell(['table', 'sqlite_sequence', 'sqlite_sequence', 3, 'CREATE TABLE sqlite_sequence(name,seq)'], 2),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'Hello world'], 1),
            $schemaCell([null, 'Imported post'], 120),
        ]);
        $page3 = $tableLeafPage([
            $schemaCell(['wp_posts', 120], 1),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $state = $database->autoincrementStateForTable('wp_posts');
        $allocated = $state->allocateRowId();
        $state->recordInsertedRowId(12);

        $t->true($state instanceof SQLiteAutoincrementState);
        $t->same(121, $state->largestTableRowId());
        $t->same(121, $allocated);
        $t->same(122, $state->peekNextRowId());
        $t->same(false, $state->sequenceRowCreated());
        $t->same(true, $state->sequenceDirty());
        $t->same([
            'name' => 'wp_posts',
            'seq' => 121,
            'rowid' => 1,
        ], $state->currentSequenceRecord()?->toArray());
    },
    'allocates sqlite autoincrement rowids with missing and invalid sequence rows' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID integer primary key autoincrement, post_title text)'], 1),
            $schemaCell(['table', 'wp_comments', 'wp_comments', 3, 'CREATE TABLE wp_comments(comment_ID integer primary key autoincrement, comment_content text)'], 2),
            $schemaCell(['table', 'wp_users', 'wp_users', 4, 'CREATE TABLE wp_users(ID integer primary key autoincrement, user_login text)'], 3),
            $schemaCell(['table', 'sqlite_sequence', 'sqlite_sequence', 5, 'CREATE TABLE sqlite_sequence(name,seq)'], 4),
        ], 1024, 100, $makeFirstPage(1024, 5));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'imported'], 77),
        ], 1024);
        $page3 = $tableLeafPage([
            $schemaCell([null, 'held for moderation'], 12),
        ], 1024);
        $page4 = $tableLeafPage([], 1024);
        $page5 = $tableLeafPage([
            $schemaCell(['wp_comments', 'a-string'], 1),
            $schemaCell(['wp_users', null], 2),
        ], 1024);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $posts = $database->autoincrementStateForTable('wp_posts');
        $comments = $database->autoincrementStateForTable('wp_comments');
        $users = $database->autoincrementStateForTable('wp_users');

        $t->same(78, $posts->allocateRowId());
        $t->same(true, $posts->sequenceRowCreated());
        $t->same([
            'name' => 'wp_posts',
            'seq' => 78,
            'rowid' => 3,
        ], $posts->currentSequenceRecord()?->toArray());
        $t->same(13, $comments->allocateRowId());
        $t->same(false, $comments->sequenceRowCreated());
        $t->same([
            'name' => 'wp_comments',
            'seq' => 13,
            'rowid' => 1,
        ], $comments->currentSequenceRecord()?->toArray());
        $t->same(1, $users->allocateRowId());
        $t->same([
            'name' => 'wp_users',
            'seq' => 1,
            'rowid' => 2,
        ], $users->currentSequenceRecord()?->toArray());
        $t->same(123, (new SQLiteSequenceRecord('wp_numeric', '123abc', 1))->autoincrementCounter());
        $t->same(0, (new SQLiteSequenceRecord('wp_invalid', 'a-string', 1))->autoincrementCounter());
    },
    'preserves wordpress autoincrement continuity for explicit imported ids' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID integer primary key autoincrement, post_title text)'], 1),
            $schemaCell(['table', 'wp_comments', 'wp_comments', 3, 'CREATE TABLE wp_comments(comment_ID integer primary key autoincrement, comment_content text)'], 2),
            $schemaCell(['table', 'sqlite_sequence', 'sqlite_sequence', 4, 'CREATE TABLE sqlite_sequence(name,seq)'], 3),
        ], 512, 100, $makeFirstPage(512, 4));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'existing'], 10),
        ]);
        $page3 = $tableLeafPage([
            $schemaCell([null, 'existing comment'], 900),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell(['wp_posts', 10], 1),
            $schemaCell(['wp_comments', 900], 2),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);

        $posts = $database->autoincrementStateForTable('wp_posts');
        $comments = $database->autoincrementStateForTable('wp_comments');

        $posts->recordInsertedRowId(500);
        $comments->recordInsertedRowId(500);

        $t->same([
            'name' => 'wp_posts',
            'seq' => 500,
            'rowid' => 1,
        ], $posts->currentSequenceRecord()?->toArray());
        $t->same(501, $posts->peekNextRowId());
        $t->same(501, $posts->allocateRowId());
        $t->same(501, $posts->currentCounter());
        $t->same(901, $comments->peekNextRowId());
        $t->same([
            'name' => 'wp_comments',
            'seq' => 900,
            'rowid' => 2,
        ], $comments->currentSequenceRecord()?->toArray());
    },
    'reads a wordpress option value from a sqlite overflow page' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $recordPayload, $tableLeafPage, $overflowLeafCell, $overflowPage): void {
        $largeValue = str_repeat('0123456789', 56) . 'endxx';
        $payload = $recordPayload([null, 'large_option', $largeValue, 'yes']);
        [$cell, $overflowPayload, $localLength] = $overflowLeafCell($payload, 1, 3);

        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([$cell]);
        $page3 = $overflowPage($overflowPayload);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);
        $options = $database->wordpressOptions();

        $t->same(78, $localLength);
        $t->same(1, count($options));
        $t->same('large_option', $options[0]->optionName);
        $t->same($largeValue, $options[0]->optionValue);
        $t->same('yes', $options[0]->autoload);
    },
    'reads a wordpress option value across chained sqlite overflow pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $recordPayload, $tableLeafPage, $overflowLeafCell, $overflowPage): void {
        $largeValue = str_repeat('A', 1100);
        $payload = $recordPayload([null, 'autoload_blob', $largeValue, 'yes']);
        [$cell, $overflowPayload] = $overflowLeafCell($payload, 1, 3);

        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 4));
        $page2 = $tableLeafPage([$cell]);
        $page3 = $overflowPage(substr($overflowPayload, 0, 508), 4);
        $page4 = $overflowPage(substr($overflowPayload, 508));
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4);
        $options = $database->wordpressOptions();

        $t->same(1, count($options));
        $t->same('autoload_blob', $options[0]->optionName);
        $t->same(1100, strlen($options[0]->optionValue));
        $t->same($largeValue, $options[0]->optionValue);
    },
    'rejects sqlite overflow chains that end before the payload is complete' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $recordPayload, $tableLeafPage, $overflowLeafCell, $overflowPage): void {
        $largeValue = str_repeat('B', 1100);
        $payload = $recordPayload([null, 'broken_blob', $largeValue, 'yes']);
        [$cell, $overflowPayload] = $overflowLeafCell($payload, 1, 3);

        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableLeafPage([$cell]);
        $page3 = $overflowPage(substr($overflowPayload, 0, 508), 0);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptions());
    },
    'database reader rejects missing pages during btree traversal' => static function (TestRunner $t) use ($makeFirstPage, $tableInteriorPage): void {
        $page1 = $tableInteriorPage([[2, 1]], 3, 512, 100, $makeFirstPage(512, 1));
        $database = SQLiteDatabase::fromBytes($page1);

        $t->throws(InvalidArgumentException::class, static fn () => $database->schemaRecords());
    },
    'standalone table leaf cells require an overflow reader for overflow payloads' => static function (TestRunner $t) use ($varint): void {
        $page = str_repeat("\0", 512);
        $cell = $varint(478) . $varint(1) . str_repeat('x', 39) . pack('N', 3);
        $offset = 512 - strlen($cell);
        $page = substr_replace($page, $cell, $offset, strlen($cell));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTableLeafCell::parse($page, $offset, 512));
    },
    'sqlite record parser rejects reserved serial types' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecord::parse("\x02\x0a"));
    },
];
