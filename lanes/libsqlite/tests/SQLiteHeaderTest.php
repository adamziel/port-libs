<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
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
    if ($value < 0) {
        throw new RuntimeException('Fixture varint cannot encode negative values');
    }
    if ($value <= 0x7f) {
        return chr($value);
    }
    $groups = [$value & 0x7f];
    $value >>= 7;
    while ($value > 0) {
        array_unshift($groups, 0x80 | ($value & 0x7f));
        $value >>= 7;
    }

    return implode('', array_map(chr(...), $groups));
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
    'database reader rejects missing pages during btree traversal' => static function (TestRunner $t) use ($makeFirstPage, $tableInteriorPage): void {
        $page1 = $tableInteriorPage([[2, 1]], 3, 512, 100, $makeFirstPage(512, 1));
        $database = SQLiteDatabase::fromBytes($page1);

        $t->throws(InvalidArgumentException::class, static fn () => $database->schemaRecords());
    },
    'sqlite record parser rejects reserved serial types' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecord::parse("\x02\x0a"));
    },
];
