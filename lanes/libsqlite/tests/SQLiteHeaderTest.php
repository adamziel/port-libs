<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexColumn;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
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

        $t->same('option_name', $index?->columnName);
        $t->same('NOCASE', $index?->collation);
        $t->same(true, $index?->descending);
        $t->same(true, $index?->partial);
        $t->same(null, $expressionIndex);
        $t->same('option_name', $isNotNullIndex?->partialPredicate?->columnName);
        $t->same(SQLiteIndexPredicate::IS_NOT_NULL, $isNotNullIndex?->partialPredicate?->operator);
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
