<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteAutoincrementState;
use PortLibs\LibSqlite\SQLiteBTreeFreeblock;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCreateIndex;
use PortLibs\LibSqlite\SQLiteCreateTable;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistAllocationPlan;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexColumn;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonArrayInsert;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonConstructor;
use PortLibs\LibSqlite\SQLiteJsonErrorPosition;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJson5Parser;
use PortLibs\LibSqlite\SQLiteJsonEach;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonExtractIndexExpression;
use PortLibs\LibSqlite\SQLiteJsonMutation;
use PortLibs\LibSqlite\SQLiteJsonPath;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonQuote;
use PortLibs\LibSqlite\SQLiteJsonRemove;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteJsonTree;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSequenceRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteTableRow;
use PortLibs\LibSqlite\SQLiteTrimIndexExpression;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalFrame;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWordPressOption;
use PortLibs\LibSqlite\SQLiteWordPressOptionReplacementPlan;
use PortLibs\LibSqlite\SQLiteWordPressOptionWritePlan;
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
        $t->same(0, $parsed->firstFreelistTrunkPage);
        $t->same(0, $parsed->freelistPageCount);
        $t->same(0, $parsed->largestRootBtreePage);
        $t->same(1, $parsed->textEncoding);
        $t->same(0, $parsed->incrementalVacuum);
    },
    'parses sqlite database freelist header fields' => static function (TestRunner $t): void {
        $header = str_repeat("\0", 100);
        $header = substr_replace($header, "SQLite format 3\0", 0, 16);
        $header = substr_replace($header, pack('n', 1024), 16, 2);
        $header[18] = "\x01";
        $header[19] = "\x01";
        $header = substr_replace($header, pack('N', 8), 28, 4);
        $header = substr_replace($header, pack('N', 5), 32, 4);
        $header = substr_replace($header, pack('N', 4), 36, 4);
        $header = substr_replace($header, pack('N', 1), 56, 4);

        $parsed = SQLiteHeader::parse($header);

        $t->same(5, $parsed->firstFreelistTrunkPage);
        $t->same(4, $parsed->freelistPageCount);
    },
    'parses sqlite auto-vacuum pointer map entries for wordpress pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $overflowPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            $schemaCell([
                'table',
                'wp_options',
                'wp_options',
                3,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ], 1),
        ], $pageSize, 100, $makeFirstPage($pageSize, 7));
        $schemaPage = substr_replace($schemaPage, pack('N', 3), 52, 4);
        $schemaPage = substr_replace($schemaPage, pack('N', 1), 64, 4);

        $pointerMapPage = str_repeat("\0", $pageSize);
        foreach ([
            3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
            4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
            5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
            6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
            7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        ] as $pageNumber => [$type, $parentPageNumber]) {
            $offset = 5 * ($pageNumber - 3);
            $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), $offset, 5);
        }

        $rootPage = $tableLeafPage([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $childPage = $tableLeafPage([
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Ported SQLite', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $pointerMapPage
            . $rootPage
            . $childPage
            . $overflowPage('autoload payload chunk', 6, $pageSize)
            . $overflowPage('tail', 0, $pageSize)
            . str_repeat("\0", $pageSize),
        );

        $entries = $database->pointerMapEntries();
        $btreeEntry = $database->pointerMapEntryForPage(4);
        $overflowEntry = $database->pointerMapEntryForPage(6);
        $options = $database->wordpressOptions();

        $t->true($database->isAutoVacuum());
        $t->true($database->isIncrementalVacuum());
        $t->same(102, $database->pointerMapEntriesPerPage());
        $t->same(103, $database->pagesPerPointerMapStride());
        $t->same(2, $database->pointerMapPageFor(3));
        $t->same(105, $database->pointerMapPageFor(105));
        $t->same(null, $database->pointerMapPageFor(1));
        $t->same(0, $database->pointerMapOffsetFor(3));
        $t->same(5, $database->pointerMapOffsetFor(4));
        $t->true($database->isPointerMapPage(2));
        $t->same([3, 4, 5, 6, 7], array_keys($entries));
        $t->same('btree-page', $btreeEntry->typeName());
        $t->same(3, $btreeEntry->parentPageNumber);
        $t->same('overflow-page', $overflowEntry->typeName());
        $t->same(5, $overflowEntry->parentPageNumber);
        $t->same([
            'page_number' => 5,
            'pointer_map_page' => 2,
            'offset' => 10,
            'type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
            'type_name' => 'first-overflow-page',
            'parent_page_number' => 4,
        ], $entries[5]->toArray());
        $t->same(['siteurl'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->throws(InvalidArgumentException::class, static fn () => $database->pointerMapEntryForPage(2));

        $plainDatabase = SQLiteDatabase::fromBytes($makeFirstPage($pageSize, 1));
        $t->same([], $plainDatabase->pointerMapEntries());
        $t->throws(InvalidArgumentException::class, static fn () => $plainDatabase->pointerMapEntryForPage(2));

        $badPointerMapPage = substr_replace($pointerMapPage, "\0\0\0\0\0", 0, 5);
        $badDatabase = SQLiteDatabase::fromBytes(
            $schemaPage
            . $badPointerMapPage
            . $rootPage
            . $childPage
            . $overflowPage('autoload payload chunk', 6, $pageSize)
            . $overflowPage('tail', 0, $pageSize)
            . str_repeat("\0", $pageSize),
        );
        $t->throws(InvalidArgumentException::class, static fn () => $badDatabase->pointerMapEntryForPage(3));
    },
    'plans sqlite auto-vacuum pointer-map updates for wordpress page mutations' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $overflowPage): void {
        $pageSize = 512;
        $firstPage = $makeFirstPage($pageSize, 7);
        $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
        $firstPage = substr_replace($firstPage, pack('N', 1), 64, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            $schemaCell([
                'table',
                'wp_options',
                'wp_options',
                3,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ], 1),
        ], $pageSize, 100, $firstPage);

        $pointerMapPage = str_repeat("\0", $pageSize);
        foreach ([
            3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
            4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
            5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
            6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
            7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        ] as $pageNumber => [$type, $parentPageNumber]) {
            $pointerMapPage = substr_replace(
                $pointerMapPage,
                chr($type) . pack('N', $parentPageNumber),
                5 * ($pageNumber - 3),
                5,
            );
        }

        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $pointerMapPage
            . $tableLeafPage([
                SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            ], $pageSize)
            . $tableLeafPage([
                SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Ported SQLite', 'yes'])),
            ], $pageSize)
            . $overflowPage('autoload payload chunk', 6, $pageSize)
            . $overflowPage('tail', 0, $pageSize)
            . str_repeat("\0", $pageSize),
        );

        $pageImages = $database->planPointerMapUpdates([
            4 => ['type' => SQLitePointerMapEntry::FREE_PAGE, 'parent_page_number' => 0],
            5 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
        ]);
        $postDatabase = SQLiteDatabase::fromBytes(
            $schemaPage
            . $pageImages[2]
            . $database->page(3)
            . $database->page(4)
            . $database->page(5)
            . $database->page(6)
            . $database->page(7),
        );

        $t->same([2], array_keys($pageImages));
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(4)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(4)->parentPageNumber);
        $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(5)->typeName());
        $t->same(9, $postDatabase->pointerMapEntryForPage(5)->parentPageNumber);
        $t->same([], $postDatabase->planPointerMapUpdates([
            4 => ['type' => SQLitePointerMapEntry::FREE_PAGE, 'parent_page_number' => 0],
            5 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDatabase::fromBytes($makeFirstPage($pageSize, 1))->planPointerMapUpdates([
            3 => ['type' => SQLitePointerMapEntry::BTREE_PAGE, 'parent_page_number' => 1],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $database->planPointerMapUpdates([
            2 => ['type' => SQLitePointerMapEntry::FREE_PAGE, 'parent_page_number' => 0],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $database->planPointerMapUpdates([
            4 => ['type' => 99, 'parent_page_number' => 0],
        ]));
    },
    'plans auto-vacuum freePage2 pointer-map mutation and skips pointer-map append pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $overflowPage): void {
        $pageSize = 512;
        $firstPage = $makeFirstPage($pageSize, 7);
        $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            $schemaCell([
                'table',
                'wp_options',
                'wp_options',
                3,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ], 1),
        ], $pageSize, 100, $firstPage);
        $pointerMapPage = str_repeat("\0", $pageSize);
        foreach ([
            3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
            4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
            5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
            6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
            7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        ] as $pageNumber => [$type, $parentPageNumber]) {
            $pointerMapPage = substr_replace(
                $pointerMapPage,
                chr($type) . pack('N', $parentPageNumber),
                5 * ($pageNumber - 3),
                5,
            );
        }
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $pointerMapPage
            . $tableLeafPage([
                SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            ], $pageSize)
            . $tableLeafPage([
                SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Ported SQLite', 'yes'])),
            ], $pageSize)
            . $overflowPage('autoload payload chunk', 6, $pageSize)
            . $overflowPage('tail', 0, $pageSize)
            . str_repeat("\0", $pageSize),
        );

        $freePlan = $database->planPageFree(6);
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
            4 => $database->page(4),
            5 => $database->page(5),
            6 => $database->page(6),
            7 => $database->page(7),
        ];
        foreach ($freePlan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

        $t->same([1, 2, 6], array_keys($freePlan->pageImages()));
        $t->same([6], $freePlan->newTrunkPageNumbers);
        $t->same([6], $postDatabase->freelistPageNumbers());
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(6)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(6)->parentPageNumber);
        $t->throws(InvalidArgumentException::class, static fn () => $database->planPageFree(2));

        $autoFirstPage = $makeFirstPage($pageSize, 104);
        $autoFirstPage = substr_replace($autoFirstPage, pack('N', 3), 52, 4);
        $autoDatabase = SQLiteDatabase::fromBytes($autoFirstPage . str_repeat("\0", $pageSize * 103));
        $allocationPlan = $autoDatabase->planPageAllocation(1);
        $pointerMapPages = $autoDatabase->planPointerMapUpdates([
            106 => ['type' => SQLitePointerMapEntry::BTREE_PAGE, 'parent_page_number' => 3],
        ], $allocationPlan->databasePageCount);
        $postAutoPages = [];
        for ($pageNumber = 1; $pageNumber <= $allocationPlan->databasePageCount; $pageNumber++) {
            if ($pageNumber === 1) {
                $postAutoPages[$pageNumber] = $allocationPlan->firstPage;
                continue;
            }
            $postAutoPages[$pageNumber] = $pointerMapPages[$pageNumber]
                ?? ($pageNumber <= $autoDatabase->pageCount() ? $autoDatabase->page($pageNumber) : str_repeat("\0", $pageSize));
        }
        $postAutoDatabase = SQLiteDatabase::fromBytes(implode('', $postAutoPages));

        $t->same([106], $allocationPlan->allocatedPageNumbers);
        $t->same(106, $allocationPlan->databasePageCount);
        $t->same([105], array_keys($pointerMapPages));
        $t->true($postAutoDatabase->isPointerMapPage(105));
        $t->same('btree-page', $postAutoDatabase->pointerMapEntryForPage(106)->typeName());
        $t->same(3, $postAutoDatabase->pointerMapEntryForPage(106)->parentPageNumber);
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
    'parses sqlite freelist trunk pages and allocation order' => static function (TestRunner $t) use ($makeFirstPage): void {
        $firstPage = $makeFirstPage(512, 8);
        $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 5), 36, 4);
        $page2 = str_repeat("\0", 512);
        $page3 = str_repeat("\0", 512);
        $page4 = str_repeat("\0", 512);
        $page5 = SQLiteFreelistTrunkPage::assemble(6, [3, 7, 4]);
        $page6 = SQLiteFreelistTrunkPage::assemble(null, []);
        $page7 = str_repeat("\0", 512);
        $page8 = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes($firstPage . $page2 . $page3 . $page4 . $page5 . $page6 . $page7 . $page8);

        $trunkPages = $database->freelistTrunkPages();

        $t->same(2, count($trunkPages));
        $t->same([
            'page_number' => 5,
            'next_trunk_page' => 6,
            'leaf_page_numbers' => [3, 7, 4],
            'page_count' => 4,
            'allocation_order' => [3, 4, 7, 5],
        ], $trunkPages[0]->toArray());
        $t->same([5, 3, 7, 4, 6], $database->freelistPageNumbers());
        $t->same([3, 4, 7, 5, 6], $database->freelistAllocationOrder());
        $t->same([3, 4], $database->freelistAllocationOrder(2));
    },
    'mutates sqlite freelist metadata while allocating reusable pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $firstPage = $makeFirstPage(512, 8);
        $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 5), 36, 4);
        $emptyPage = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes(
            $firstPage
            . $emptyPage
            . $emptyPage
            . $emptyPage
            . SQLiteFreelistTrunkPage::assemble(6, [3, 7, 4])
            . SQLiteFreelistTrunkPage::assemble(null, [])
            . $emptyPage
            . $emptyPage,
        );

        $plan = $database->planPageAllocation(2, false);
        $postDatabase = SQLiteDatabase::fromBytes(
            $plan->firstPage
            . $emptyPage
            . $emptyPage
            . $emptyPage
            . $plan->updatedFreelistPages[5]
            . SQLiteFreelistTrunkPage::assemble(null, [])
            . $emptyPage
            . $emptyPage,
        );

        $t->same(SQLiteFreelistAllocationPlan::class, get_class($plan));
        $t->same([
            'allocated_page_numbers' => [3, 4],
            'appended_page_numbers' => [],
            'database_page_count' => 8,
            'first_freelist_trunk_page' => 5,
            'freelist_page_count' => 3,
            'updated_freelist_page_numbers' => [5],
        ], $plan->toArray());
        $t->same(1, unpack('N', substr($plan->updatedFreelistPages[5], 4, 4))[1]);
        $t->same(7, unpack('N', substr($plan->updatedFreelistPages[5], 8, 4))[1]);
        $t->same(7, unpack('N', substr($plan->updatedFreelistPages[5], 12, 4))[1]);
        $t->same([7, 5, 6], $postDatabase->freelistAllocationOrder());
    },
    'allocates empty freelist trunks and appends after freelist depletion' => static function (TestRunner $t) use ($makeFirstPage): void {
        $firstPage = $makeFirstPage(512, 6);
        $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 3), 36, 4);
        $emptyPage = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes(
            $firstPage
            . $emptyPage
            . $emptyPage
            . $emptyPage
            . SQLiteFreelistTrunkPage::assemble(6, [])
            . SQLiteFreelistTrunkPage::assemble(null, [3]),
        );

        $plan = $database->planPageAllocation(5);
        $postHeader = SQLiteHeader::parse($plan->firstPage);

        $t->same([
            'allocated_page_numbers' => [5, 3, 6, 7, 8],
            'appended_page_numbers' => [7, 8],
            'database_page_count' => 8,
            'first_freelist_trunk_page' => 0,
            'freelist_page_count' => 0,
            'updated_freelist_page_numbers' => [],
        ], $plan->toArray());
        $t->same(8, $postHeader->databaseSizePages);
        $t->same(0, $postHeader->firstFreelistTrunkPage);
        $t->same(0, $postHeader->freelistPageCount);
        $t->throws(InvalidArgumentException::class, static fn () => $database->planPageAllocation(4, false));
    },
    'rejects corrupt sqlite freelist trunk metadata' => static function (TestRunner $t) use ($makeFirstPage): void {
        $countTooSmall = $makeFirstPage(512, 5);
        $countTooSmall = substr_replace($countTooSmall, pack('N', 4), 32, 4);
        $countTooSmall = substr_replace($countTooSmall, pack('N', 2), 36, 4);
        $database = SQLiteDatabase::fromBytes(
            $countTooSmall
            . str_repeat("\0", 512)
            . str_repeat("\0", 512)
            . SQLiteFreelistTrunkPage::assemble(null, [2, 3])
            . str_repeat("\0", 512),
        );
        $t->throws(InvalidArgumentException::class, static fn () => $database->freelistTrunkPages());

        $duplicate = $makeFirstPage(512, 5);
        $duplicate = substr_replace($duplicate, pack('N', 4), 32, 4);
        $duplicate = substr_replace($duplicate, pack('N', 3), 36, 4);
        $duplicateTrunk = str_repeat("\0", 512);
        $duplicateTrunk = substr_replace($duplicateTrunk, pack('N', 0), 0, 4);
        $duplicateTrunk = substr_replace($duplicateTrunk, pack('N', 2), 4, 4);
        $duplicateTrunk = substr_replace($duplicateTrunk, pack('N', 2), 8, 4);
        $duplicateTrunk = substr_replace($duplicateTrunk, pack('N', 2), 12, 4);
        $duplicateDatabase = SQLiteDatabase::fromBytes(
            $duplicate
            . str_repeat("\0", 512)
            . str_repeat("\0", 512)
            . $duplicateTrunk
            . str_repeat("\0", 512),
        );
        $t->throws(InvalidArgumentException::class, static fn () => $duplicateDatabase->freelistTrunkPages());

        $badLeafCount = $makeFirstPage(512, 4);
        $badLeafCount = substr_replace($badLeafCount, pack('N', 4), 32, 4);
        $badLeafCount = substr_replace($badLeafCount, pack('N', 1), 36, 4);
        $badTrunk = str_repeat("\0", 512);
        $badTrunk = substr_replace($badTrunk, pack('N', 0), 0, 4);
        $badTrunk = substr_replace($badTrunk, pack('N', 200), 4, 4);
        $badLeafCountDatabase = SQLiteDatabase::fromBytes(
            $badLeafCount
            . str_repeat("\0", 512)
            . str_repeat("\0", 512)
            . $badTrunk,
        );
        $t->throws(InvalidArgumentException::class, static fn () => $badLeafCountDatabase->freelistTrunkPages());
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
    'assembles sqlite table interior pages with rowid separators' => static function (TestRunner $t): void {
        $page = SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode(2, 10),
            SQLiteTableInteriorCell::encode(3, 20),
        ], 4);
        $header = SQLiteBTreePageHeader::parsePage($page, 512);
        $cells = SQLiteTableInteriorCell::parsePageCells($page, $header);

        $t->same('table-interior', $header->pageType);
        $t->same(2, $header->cellCount);
        $t->same(4, $header->rightMostPointer);
        $t->same([2, 3], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $cells));
        $t->same([10, 20], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $cells));
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
    'encodes sqlite overflow page chains with upstream next page pointers' => static function (TestRunner $t): void {
        $pages = SQLiteOverflowPage::encodeChain(str_repeat('a', 509), 7);

        $t->same(0, SQLiteOverflowPage::requiredPageCount(0));
        $t->same(1, SQLiteOverflowPage::requiredPageCount(508));
        $t->same(2, SQLiteOverflowPage::requiredPageCount(509));
        $t->same(2, count($pages));
        $t->same(512, strlen($pages[0]));
        $t->same('00000008', bin2hex(substr($pages[0], 0, 4)));
        $t->same('00000000', bin2hex(substr($pages[1], 0, 4)));
        $t->same(str_repeat('a', 508), substr($pages[0], 4, 508));
        $t->same('a', substr($pages[1], 4, 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::encodeChain('x', 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::requiredPageCount(-1));
    },
    'encodes sqlite overflow chains at reusable page numbers with reserved bytes' => static function (TestRunner $t): void {
        $payload = str_repeat('A', 496) . str_repeat('B', 496) . 'C';
        $pages = SQLiteOverflowPage::encodeChainAtPages($payload, [7, 4, 9], 512, 500);

        $t->same([7, 4, 9], array_keys($pages));
        $t->same(4, unpack('N', substr($pages[7], 0, 4))[1]);
        $t->same(9, unpack('N', substr($pages[4], 0, 4))[1]);
        $t->same(0, unpack('N', substr($pages[9], 0, 4))[1]);
        $t->same(str_repeat('A', 496), substr($pages[7], 4, 496));
        $t->same(str_repeat('B', 496), substr($pages[4], 4, 496));
        $t->same('C', substr($pages[9], 4, 1));
        $t->same(str_repeat("\0", 12), substr($pages[7], 500, 12));
        $t->same(str_repeat("\0", 12), substr($pages[4], 500, 12));
        $t->same(str_repeat("\0", 12), substr($pages[9], 500, 12));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SQLiteOverflowPage::encodeChainAtPages($payload, [7, 7, 9], 512, 500),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SQLiteOverflowPage::encodeChainAtPages($payload, [7, 9], 512, 500),
        );
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
    'encodes sqlite index cells including overflow pointers and minimum cell size' => static function (TestRunner $t): void {
        $payload = SQLiteRecord::encode(['siteurl', 1]);
        $cell = SQLiteIndexCell::encode($payload);
        $page = SQLiteIndexLeafPage::assemble([$cell]);
        $header = SQLiteBTreePageHeader::parsePage($page, 512);
        $cells = SQLiteIndexCell::parsePageCells($page, $header);

        $overflowPayload = str_repeat('x', 590);
        $overflow = SQLiteIndexCell::encode($overflowPayload, 512, 7);
        $localLength = SQLiteIndexCell::localPayloadLength(strlen($overflowPayload), 512);

        $t->same(4, strlen(SQLiteIndexCell::encode('')));
        $t->same('00000000', bin2hex(SQLiteIndexCell::encode('')));
        $t->same(['siteurl', 1], $cells[0]->record()->values);
        $t->same(strlen(SQLiteVarint::encode(strlen($overflowPayload))) + $localLength + 4, strlen($overflow));
        $t->same('00000007', bin2hex(substr($overflow, -4)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexCell::encode($overflowPayload, 512));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexCell::encode($payload, 512, null, 0));
    },
    'assembles sqlite index leaf overflow pages from native encoders' => static function (TestRunner $t) use ($makeFirstPage): void {
        $largeKey = str_repeat('plugin-option-', 50);
        $payload = SQLiteRecord::encode([$largeKey, 42]);
        $allocation = SQLiteIndexCell::encodeWithOverflowPages($payload, 3);
        $indexPage = SQLiteIndexLeafPage::assemble([$allocation['cell']]);
        $database = SQLiteDatabase::fromBytes(
            $makeFirstPage(512, 2 + count($allocation['overflowPages']))
            . $indexPage
            . implode('', $allocation['overflowPages']),
        );
        $cells = $database->indexCells(2);

        $t->same(2, count($allocation['overflowPages']));
        $t->same(SQLiteIndexCell::localPayloadLength(strlen($payload), 512), $allocation['localPayloadLength']);
        $t->same('00000004', bin2hex(substr($allocation['overflowPages'][0], 0, 4)));
        $t->same('00000000', bin2hex(substr($allocation['overflowPages'][1], 0, 4)));
        $t->same(1, count($cells));
        $t->same([$largeKey, 42], $cells[0]->record()->values);
    },
    'assembles wordpress option_name index leaf pages from native index cell encoder' => static function (TestRunner $t) use ($makeFirstPage): void {
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], 512, 100, $makeFirstPage(512, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                null,
                'siteurl',
                'https://example.test',
                'yes',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                null,
                'home',
                'https://example.test/blog',
                'yes',
            ])),
        ]);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 2])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
        ]);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);
        $option = $database->wordpressOptionByIndexedName('siteurl');

        $t->same('index-leaf', $database->pageHeader(3)->pageType);
        $t->same(2, $database->pageHeader(3)->cellCount);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same('siteurl', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexLeafPage::assemble([str_repeat('x', 500), str_repeat('y', 500)]));
    },
    'assembles sqlite index interior pages from native index cell encoder' => static function (TestRunner $t): void {
        $cell = SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 2]), 512, null, 3);
        $page = SQLiteIndexInteriorPage::assemble([$cell], 5);
        $header = SQLiteBTreePageHeader::parsePage($page, 512);
        $cells = SQLiteIndexCell::parsePageCells($page, $header);

        $t->same('index-interior', $header->pageType);
        $t->same(5, $header->rightMostPointer);
        $t->same(1, $header->cellCount);
        $t->same([strlen($page) - strlen($cell)], $header->cellPointers($page));
        $t->same(3, $cells[0]->leftChildPage);
        $t->same(['home', 2], $cells[0]->record()->values);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexInteriorPage::assemble([], 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteIndexInteriorPage::assemble([str_repeat('x', 500), str_repeat('y', 500)], 3));
    },
    'assembles wordpress option_name index interior pages from native index cell encoder' => static function (TestRunner $t) use ($makeFirstPage): void {
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                4,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                2,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], 512, 100, $makeFirstPage(512, 5));
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 2]), 512, null, 3),
        ], 5);
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['blogname', 1])),
        ]);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'blogname', 'Example Site', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentyfive', 'yes'])),
        ]);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 3])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['stylesheet', 4])),
        ]);
        $database = SQLiteDatabase::fromBytes($schemaPage . $indexRootPage . $leftIndexLeafPage . $tablePage . $rightIndexLeafPage);
        $option = $database->wordpressOptionByIndexedName('siteurl');
        $indexValues = array_map(
            static fn (SQLiteIndexCell $cell): string => $cell->record()->values[0],
            $database->indexCells(2),
        );

        $t->same('index-interior', $database->pageHeader(2)->pageType);
        $t->same(5, $database->pageHeader(2)->rightMostPointer);
        $t->same(['blogname', 'home', 'siteurl', 'stylesheet'], $indexValues);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same('siteurl', $option->optionName);
        $t->same('https://example.test', $option->optionValue);
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
    'encodes sqlite text records using utf16le and utf16be database encodings' => static function (TestRunner $t): void {
        $text = 'A' . "\u{1234}";
        $utf16le = SQLiteRecord::encode([$text, 'siteurl'], 2);
        $utf16be = SQLiteRecord::encode([$text, 'siteurl'], 3);

        $leRecord = SQLiteRecord::parse($utf16le, 2);
        $beRecord = SQLiteRecord::parse($utf16be, 3);

        $t->same([21, 41], $leRecord->serialTypes);
        $t->same([21, 41], $beRecord->serialTypes);
        $t->same([$text, 'siteurl'], $leRecord->values);
        $t->same([$text, 'siteurl'], $beRecord->values);
        $t->contains(hex2bin('41003412'), $utf16le);
        $t->contains(hex2bin('00411234'), $utf16be);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecord::encode(['x'], 4));
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
    'assembles wordpress table leaf overflow pages from native cell encoders' => static function (TestRunner $t) use ($makeFirstPage): void {
        $largeValue = str_repeat('wp-cache-fragment:', 28) . 'end';
        $payload = SQLiteRecord::encode([
            null,
            'large_autoloaded_cache',
            $largeValue,
            'yes',
        ]);
        $allocation = SQLiteTableLeafCell::encodeWithOverflowPages(1, $payload, 3);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], 512, 100, $makeFirstPage(512, 2 + count($allocation['overflowPages'])));
        $tablePage = SQLiteTableLeafPage::assemble([$allocation['cell']]);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . implode('', $allocation['overflowPages']));
        $options = $database->wordpressOptions();

        $t->same(3, $database->pageCount());
        $t->same(1, count($allocation['overflowPages']));
        $t->same(SQLiteTableLeafCell::localPayloadLength(strlen($payload), 512), $allocation['localPayloadLength']);
        $t->same('00000000', bin2hex(substr($allocation['overflowPages'][0], 0, 4)));
        $t->same(1, count($options));
        $t->same('large_autoloaded_cache', $options[0]->optionName);
        $t->same($largeValue, $options[0]->optionValue);
        $t->same('yes', $options[0]->autoload);
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
    'reads wordpress options by bounded rowid range across table interior pages' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 5));
        $page2 = $tableInteriorPage([[3, 2], [4, 4]], 5);
        $page3 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
        ]);
        $page4 = $tableLeafPage([
            $schemaCell([null, 'blogname', 'Ported SQLite', 'yes'], 3),
            $schemaCell([null, 'template', 'twentytwentyfive', 'yes'], 4),
        ]);
        $page5 = $tableLeafPage([
            $schemaCell([null, 'rewrite_rules', 'a:0:{}', 'no'], 5),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $options = $database->wordpressOptionsByRowIdRange(2, 5, 3);
        $inclusive = $database->wordpressOptionsByRowIdRange(4, 4, null, true);
        $openLowerRows = $database->tableRowsByRowIdRangeByName('wp_options', null, 3);
        $empty = $database->wordpressOptionsByRowIdRange(4, 4);

        $t->same([2, 3, 4], array_map(static fn (SQLiteWordPressOption $option): int => $option->rowId, $options));
        $t->same(['home', 'blogname', 'template'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same(['template'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $inclusive));
        $t->same([1, 2], array_map(static fn (SQLiteTableRow $row): int => $row->rowId, $openLowerRows));
        $t->same([], $empty);
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptionsByRowIdRange(null, null, -1));
    },
    'prunes unrelated damaged table branches during rowid range scans' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableInteriorPage, $tableLeafPage): void {
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $makeFirstPage(512, 3));
        $page2 = $tableInteriorPage([[3, 2]], 99);
        $page3 = $tableLeafPage([
            $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 1),
            $schemaCell([null, 'home', 'https://example.test/blog', 'yes'], 2),
        ]);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3);

        $options = $database->wordpressOptionsByRowIdRange(null, 2, null, true);

        $t->same(['siteurl', 'home'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->throws(InvalidArgumentException::class, static fn () => $database->wordpressOptions());
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
        $emptyQuotedPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_empty_key ON wp_options(json_extract(option_value, \'$.""\'))');
        $arrayPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_rule ON wp_options(json_extract(option_value, \'$.rules[0].enabled\'))');
        $reverseArrayPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_last_rule ON wp_options(json_extract(option_value, \'$.rules[#-1].enabled\'))');
        $arrayAppendPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_append ON wp_options(json_extract(option_value, \'$.rules[#]\'))');
        $badEmptyBarePath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_bad_empty_key ON wp_options(json_extract(option_value, \'$.\'))');
        $badHashReversePath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_bad_reverse ON wp_options(json_extract(option_value, \'$.rules[#-]\'))');
        $badHashDigitsPath = SQLiteCreateIndex::firstJsonExtractExpression('CREATE INDEX idx_json_bad_hash_digits ON wp_options(json_extract(option_value, \'$.rules[#9]\'))');
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
        $t->same('$.""', $emptyQuotedPath?->path);
        $t->same('$.rules[0].enabled', $arrayPath?->path);
        $t->same('$.rules[#-1].enabled', $reverseArrayPath?->path);
        $t->same('$.rules[#]', $arrayAppendPath?->path);
        $t->same(null, $badEmptyBarePath);
        $t->same(null, $badHashReversePath);
        $t->same(null, $badHashDigitsPath);
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
        $emptyQuotedPath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_empty_key ON wp_options(option_value ->> \'$.""\')');
        $jsonQuoteNull = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_quote_null ON wp_options(option_value ->> json_quote(NULL))');
        $jsonQuoteInteger = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_quote_integer ON wp_options(option_value ->> json_quote(123))');
        $jsonQuoteReal = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_quote_real ON wp_options(option_value ->> json_quote(1.25))');
        $jsonQuoteNegative = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_quote_negative ON wp_options(option_value ->> json_quote(-1))');
        $jsonQuoteExponent = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_quote_exponent ON wp_options(option_value ->> json_quote(1e2))');
        $jsonQuoteString = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_quote_string ON wp_options(option_value ->> json_quote('plugin'))");
        $jsonQuoteBlob = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_quote_blob ON wp_options(option_value ->> json_quote(X'3031'))");
        $jsonQuoteArity = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_quote_arity ON wp_options(option_value ->> json_quote(1,2))');
        $minInteger = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_min_integer ON wp_options(option_value ->> min(2, 1))');
        $maxInteger = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_max_integer ON wp_options(option_value ->> max(1, 2))');
        $minString = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_min_string ON wp_options(option_value ->> min('seo', 'cache'))");
        $maxDottedString = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_max_string ON wp_options(option_value ->> max('plugin.enabled', 'plugin.disabled'))");
        $minMixed = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_min_mixed ON wp_options(option_value ->> min('1', 2))");
        $maxArity = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_max_arity ON wp_options(option_value ->> max(2))');
        $parenthesizedLabel = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_parenthesized_label ON wp_options(option_value ->> ('cache'))");
        $parenthesizedInteger = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_parenthesized_integer ON wp_options(option_value ->> (1))');
        $nestedParenthesizedMin = SQLiteCreateIndex::firstJsonTextOperatorExpression("CREATE INDEX idx_json_nested_parenthesized_min ON wp_options(option_value ->> ((min('seo', 'cache'))))");
        $parenthesizedExpression = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_parenthesized_expression ON wp_options(option_value ->> (1 + 1))');
        $badEmptyBarePath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_bad_empty_key ON wp_options(option_value ->> \'$.\')');
        $badHashReversePath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_bad_reverse ON wp_options(option_value ->> \'$.rules[#-]\')');
        $badUnterminatedQuotedPath = SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx_json_bad_quote ON wp_options(option_value ->> \'$."unterminated\')');
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
        $t->same('$.""', $emptyQuotedPath?->path);
        $t->same('$.null', $jsonQuoteNull?->path);
        $t->same('$."123"', $jsonQuoteInteger?->path);
        $t->same('$."1.25"', $jsonQuoteReal?->path);
        $t->same('$."-1"', $jsonQuoteNegative?->path);
        $t->same('$."100.0"', $jsonQuoteExponent?->path);
        $t->same(null, $jsonQuoteString);
        $t->same(null, $jsonQuoteBlob);
        $t->same(null, $jsonQuoteArity);
        $t->same('$[1]', $minInteger?->path);
        $t->same('$[2]', $maxInteger?->path);
        $t->same('$.cache', $minString?->path);
        $t->same('$."plugin.enabled"', $maxDottedString?->path);
        $t->same(null, $minMixed);
        $t->same(null, $maxArity);
        $t->same('$.cache', $parenthesizedLabel?->path);
        $t->same('$[1]', $parenthesizedInteger?->path);
        $t->same('$.cache', $nestedParenthesizedMin?->path);
        $t->same(null, $parenthesizedExpression);
        $t->same(null, $badEmptyBarePath);
        $t->same(null, $badHashReversePath);
        $t->same(null, $badUnterminatedQuotedPath);
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
        $emptyQuotedPath = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_empty_fragment ON wp_options(option_value -> \'$.""\')');
        $parenthesizedLabel = SQLiteCreateIndex::firstJsonValueOperatorExpression("CREATE INDEX idx_json_parenthesized_fragment ON wp_options(option_value -> ('settings.v1'))");
        $parenthesizedInteger = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_parenthesized_integer_fragment ON wp_options(option_value -> (0))');
        $parenthesizedExpression = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_parenthesized_expression_fragment ON wp_options(option_value -> (0 + 1))');
        $badHashDigitsPath = SQLiteCreateIndex::firstJsonValueOperatorExpression('CREATE INDEX idx_json_bad_hash_digits ON wp_options(option_value -> \'$.rules[#9]\')');
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
        $t->same('$.""', $emptyQuotedPath?->path);
        $t->same('$."settings.v1"', $parenthesizedLabel?->path);
        $t->same('$[0]', $parenthesizedInteger?->path);
        $t->same(null, $parenthesizedExpression);
        $t->same(null, $badHashDigitsPath);
        $t->same(null, $textOperatorIndex);
        $t->same(null, $ordinaryColumn);
    },
    'validates sqlite full json path syntax for expression index preflight' => static function (TestRunner $t): void {
        $t->true(SQLiteJsonPath::isWellFormed('$'));
        $t->true(SQLiteJsonPath::isWellFormed('$.""'));
        $t->true(SQLiteJsonPath::isWellFormed('$.rules[0].enabled'));
        $t->true(SQLiteJsonPath::isWellFormed('$.rules[#]'));
        $t->true(SQLiteJsonPath::isWellFormed('$.rules[#-1]'));
        $t->true(SQLiteJsonPath::isWellFormed('$."a\x62c"[01]'));
        $t->same(false, SQLiteJsonPath::isWellFormed(''));
        $t->same(false, SQLiteJsonPath::isWellFormed('$.'));
        $t->same(false, SQLiteJsonPath::isWellFormed('$.rules[#-]'));
        $t->same(false, SQLiteJsonPath::isWellFormed('$.rules[#9]'));
        $t->same(false, SQLiteJsonPath::isWellFormed('$.rules[#+2]'));
        $t->same(false, SQLiteJsonPath::isWellFormed('$.rules[#-1'));
        $t->same(false, SQLiteJsonPath::isWellFormed('$."unterminated'));
    },
    'uses wordpress json operator indexes with json_quote RHS constants' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $pageSize = 4096;
        $textPath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonTextOperatorExpression(
            'CREATE INDEX fixture ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL',
        )?->path;
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_json_quote_null', 'wp_options', 3, 'CREATE INDEX wp_options_json_quote_null ON wp_options(option_value ->> json_quote(NULL)) WHERE option_value IS NOT NULL'], 2),
            $schemaCell(['index', 'wp_options_json_quote_integer', 'wp_options', 4, 'CREATE INDEX wp_options_json_quote_integer ON wp_options(option_value ->> json_quote(123)) WHERE option_value IS NOT NULL'], 3),
            $schemaCell(['index', 'wp_options_json_quote_real', 'wp_options', 5, 'CREATE INDEX wp_options_json_quote_real ON wp_options(option_value ->> json_quote(1.25)) WHERE option_value IS NOT NULL'], 4),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_json_quote_null_settings', '{"null":"json-null"}', 'no'], 1),
            $schemaCell([null, 'plugin_json_quote_integer_settings', '{"123":"integer-label"}', 'no'], 2),
            $schemaCell([null, 'plugin_json_quote_real_settings', '{"1.25":"real-label"}', 'no'], 3),
        ], $pageSize);
        $page3 = $indexLeafPage([$indexCell(['json-null', 1])], $pageSize);
        $page4 = $indexLeafPage([$indexCell(['integer-label', 2])], $pageSize);
        $page5 = $indexLeafPage([$indexCell(['real-label', 3])], $pageSize);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $t->same('$.null', $textPath('option_value ->> json_quote(NULL)'));
        $t->same('$."123"', $textPath('option_value ->> json_quote(123)'));
        $t->same('$."1.25"', $textPath('option_value ->> json_quote(1.25)'));
        $t->same(null, $textPath("option_value ->> json_quote('plugin')"));
        $t->same(null, $textPath("option_value ->> json_quote(X'3031')"));
        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.null', 'json-null'));
        $t->same(4, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."123"', 'integer-label'));
        $t->same(5, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."1.25"', 'real-label'));
        $t->same(['plugin_json_quote_null_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$.null', 'json-null')));
        $t->same(['plugin_json_quote_integer_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$."123"', 'integer-label')));
        $t->same(['plugin_json_quote_real_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$."1.25"', 'real-label')));
    },
    'uses wordpress json operator indexes with min max RHS constants' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $pageSize = 4096;
        $textPath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonTextOperatorExpression(
            'CREATE INDEX fixture ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL',
        )?->path;
        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_json_min_cache', 'wp_options', 3, "CREATE INDEX wp_options_json_min_cache ON wp_options(option_value ->> min('seo', 'cache')) WHERE option_value IS NOT NULL"], 2),
            $schemaCell(['index', 'wp_options_json_max_plugin_enabled', 'wp_options', 4, "CREATE INDEX wp_options_json_max_plugin_enabled ON wp_options(option_value ->> max('plugin.enabled', 'plugin.disabled')) WHERE option_value IS NOT NULL"], 3),
            $schemaCell(['index', 'wp_options_json_min_slot', 'wp_options', 5, 'CREATE INDEX wp_options_json_min_slot ON wp_options(option_value ->> min(2, 1)) WHERE option_value IS NOT NULL'], 4),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_min_cache_settings', '{"cache":"hit"}', 'no'], 1),
            $schemaCell([null, 'plugin_max_enabled_settings', '{"plugin.enabled":"yes"}', 'no'], 2),
            $schemaCell([null, 'plugin_min_slot_settings', '["zero","one","two"]', 'no'], 3),
        ], $pageSize);
        $page3 = $indexLeafPage([$indexCell(['hit', 1])], $pageSize);
        $page4 = $indexLeafPage([$indexCell(['yes', 2])], $pageSize);
        $page5 = $indexLeafPage([$indexCell(['one', 3])], $pageSize);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $t->same('$.cache', $textPath("option_value ->> min('seo', 'cache')"));
        $t->same('$."plugin.enabled"', $textPath("option_value ->> max('plugin.enabled', 'plugin.disabled')"));
        $t->same('$[1]', $textPath('option_value ->> min(2, 1)'));
        $t->same(null, $textPath("option_value ->> min('1', 2)"));
        $t->same(null, $textPath('option_value ->> max(2)'));
        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.cache', 'hit'));
        $t->same(4, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$."plugin.enabled"', 'yes'));
        $t->same(5, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$[1]', 'one'));
        $t->same(['plugin_min_cache_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$.cache', 'hit')));
        $t->same(['plugin_max_enabled_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$."plugin.enabled"', 'yes')));
        $t->same(['plugin_min_slot_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$[1]', 'one')));
    },
    'skips malformed wordpress json path expression indexes during preflight' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $tableLeafPage, $indexCell, $indexLeafPage): void {
        $pageSize = 1024;
        $textPath = static fn (string $expression): ?string => SQLiteCreateIndex::firstJsonTextOperatorExpression(
            'CREATE INDEX fixture ON wp_options(' . $expression . ') WHERE option_value IS NOT NULL',
        )?->path;
        $extractPath = static fn (string $path): ?string => SQLiteCreateIndex::firstJsonExtractExpression(
            "CREATE INDEX fixture ON wp_options(json_extract(option_value, '{$path}')) WHERE option_value IS NOT NULL",
        )?->path;

        $page1 = $tableLeafPage([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
            $schemaCell(['index', 'wp_options_json_empty_label', 'wp_options', 3, 'CREATE INDEX wp_options_json_empty_label ON wp_options(option_value ->> \'$.""\') WHERE option_value IS NOT NULL'], 2),
            $schemaCell(['index', 'wp_options_json_bad_reverse', 'wp_options', 4, 'CREATE INDEX wp_options_json_bad_reverse ON wp_options(option_value ->> \'$.plugin[#-]\') WHERE option_value IS NOT NULL'], 3),
            $schemaCell(['index', 'wp_options_json_bad_extract', 'wp_options', 5, 'CREATE INDEX wp_options_json_bad_extract ON wp_options(json_extract(option_value, \'$.\')) WHERE option_value IS NOT NULL'], 4),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $page2 = $tableLeafPage([
            $schemaCell([null, 'plugin_empty_label_settings', '{"":"empty-label","plugin":["bad"]}', 'no'], 1),
        ], $pageSize);
        $page3 = $indexLeafPage([$indexCell(['empty-label', 1])], $pageSize);
        $page4 = $indexLeafPage([$indexCell(['bad', 1])], $pageSize);
        $page5 = $indexLeafPage([$indexCell(['bad-extract', 1])], $pageSize);
        $database = SQLiteDatabase::fromBytes($page1 . $page2 . $page3 . $page4 . $page5);

        $t->same('$.""', $textPath('option_value ->> \'$.""\''));
        $t->same(null, $textPath('option_value ->> \'$.plugin[#-]\''));
        $t->same(null, $extractPath('$.'));
        $t->same(3, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.""', 'empty-label'));
        $t->same(null, $database->indexRootPageForJsonExtractPointLookup('wp_options', 'option_value', '$.plugin', 'bad'));
        $t->same(['plugin_empty_label_settings'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $database->wordpressOptionsByIndexedJsonOptionValue('$.""', 'empty-label')));
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
    'checks sqlite json_valid jsonb superficial and strict blob flags' => static function (TestRunner $t): void {
        $validSettings = SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'migrations' => ['core', 'cache'],
            ],
        ]);
        $largeCorruptJsonb = "\x8b\xff" . str_repeat("\0", 7);
        $castTextJsonBlob = '{"a":35}';
        $badScalarPayload = "\x10\0";

        $t->true(SQLiteJsonB::isSuperficiallyJsonB($validSettings));
        $t->true(SQLiteJsonB::isStrictlyWellFormed($validSettings));
        $t->true(SQLiteJsonB::isJsonB($validSettings));
        $t->true(SQLiteJsonB::isSuperficiallyJsonB($largeCorruptJsonb));
        $t->same(false, SQLiteJsonB::isStrictlyWellFormed($largeCorruptJsonb));
        $t->same(false, SQLiteJsonB::isJsonB($largeCorruptJsonb));
        $t->same(false, SQLiteJsonB::isSuperficiallyJsonB($castTextJsonBlob));
        $t->same(false, SQLiteJsonB::isStrictlyWellFormed($castTextJsonBlob));
        $t->same(false, SQLiteJsonB::isSuperficiallyJsonB($badScalarPayload));
        $t->same(false, SQLiteJsonB::isStrictlyWellFormed($badScalarPayload));
        $t->same(false, SQLiteJsonB::isSuperficiallyJsonB($validSettings . "\0"));
        $t->true(SQLiteJsonB::isSuperficiallyJsonB(SQLiteJsonB::encode(null)));
        $t->true(SQLiteJsonB::isStrictlyWellFormed(SQLiteJsonB::encode(null)));
    },
    'checks sqlite json_valid text json5 and blob flag combinations' => static function (TestRunner $t): void {
        $strictJson = '{"enabled":true,"count":2}';
        $json5 = "{enabled:true, modes:['dark',], /* copied option */}";
        $controlCharacterString = '"abc' . chr(1) . 'xyz"';
        $validJsonb = SQLiteJsonB::encode(['enabled' => true]);
        $superficialOnlyJsonb = "\x8b\xff" . str_repeat("\0", 7);
        $castTextJsonBlob = new SQLiteBlobValue('{"a":1}');
        $castJson5Blob = new SQLiteBlobValue('{a:1}');

        $t->true(SQLiteJsonValidity::jsonValid($strictJson));
        $t->true(SQLiteJsonValidity::jsonValid($strictJson, 1));
        $t->true(SQLiteJsonValidity::jsonValid($strictJson, 2));
        $t->same(false, SQLiteJsonValidity::jsonValid($json5, 1));
        $t->true(SQLiteJsonValidity::jsonValid($json5, 2));
        $t->true(SQLiteJsonValidity::jsonValid($json5, 3));
        $t->same(false, SQLiteJsonValidity::jsonValid($controlCharacterString, 1));
        $t->true(SQLiteJsonValidity::jsonValid($controlCharacterString, 2));
        $t->same(false, SQLiteJsonValidity::jsonValid('{enabled:true,,}', 2));
        $t->same(null, SQLiteJsonValidity::jsonValid(null));

        $t->true(SQLiteJsonValidity::jsonValid($castTextJsonBlob, 1));
        $t->true(SQLiteJsonValidity::jsonValid($castTextJsonBlob, 2));
        $t->same(false, SQLiteJsonValidity::jsonValid($castTextJsonBlob, 4));
        $t->same(false, SQLiteJsonValidity::jsonValid($castTextJsonBlob, 8));
        $t->true(SQLiteJsonValidity::jsonValid($castTextJsonBlob, 5));
        $t->same(false, SQLiteJsonValidity::jsonValid($castJson5Blob, 1));
        $t->true(SQLiteJsonValidity::jsonValid($castJson5Blob, 2));
        $t->true(SQLiteJsonValidity::jsonValid($castJson5Blob, 6));

        $t->same(false, SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($validJsonb), 1));
        $t->true(SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($validJsonb), 4));
        $t->true(SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($validJsonb), 8));
        $t->true(SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($validJsonb), 9));
        $t->true(SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($superficialOnlyJsonb), 4));
        $t->same(false, SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($superficialOnlyJsonb), 8));
        $t->true(SQLiteJsonValidity::jsonValid(new SQLiteBlobValue($superficialOnlyJsonb), 12));
        $t->same(false, SQLiteJsonValidity::jsonValid('not json', 1));
        $t->same(false, SQLiteJsonValidity::jsonValid('not json', 2));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValid($strictJson, 0));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValid($strictJson, 16));

        $t->true(SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $strictJson));
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json5, 1));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $json5, 2));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunction('JSON_VALID', $json5, 2));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunction('json_valid', new SQLiteBlobValue($validJsonb), 8));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunction('json_valid', new SQLiteBlobValue($superficialOnlyJsonb), 4));
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $castTextJsonBlob, 4));
        $t->same(null, SQLiteJsonValidity::jsonValidSqlFunction('json_valid', null));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$strictJson]));
        $t->same(false, SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$json5]));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$json5, 2]));
        $t->true(SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [new SQLiteBlobValue($validJsonb), 8]));
        $t->same(null, SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [null]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$strictJson, 1, 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunctionArguments('JSON_VALID', [$strictJson, '1']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $strictJson, null));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunction('json_error_position', $strictJson));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonValidity::jsonValidSqlFunction('json_valid', $strictJson, 16));
    },
    'quotes sqlite sql values as json values for option preflight' => static function (TestRunner $t): void {
        $jsonb = SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'count' => 2]]);
        $controlText = 'line' . "\n" . 'tab' . "\t" . 'nul' . "\0" . 'end';

        $t->same('null', SQLiteJsonQuote::jsonQuote(null));
        $t->same('12345', SQLiteJsonQuote::jsonQuote(12345));
        $t->same('3.14159', SQLiteJsonQuote::jsonQuote(3.14159));
        $t->same('100.0', SQLiteJsonQuote::jsonQuote(1e2));
        $t->same('-0.25', SQLiteJsonQuote::jsonQuote(-0.25));
        $t->same('1', SQLiteJsonQuote::jsonQuote(true));
        $t->same('0', SQLiteJsonQuote::jsonQuote(false));
        $t->same('"abc\"xyz"', SQLiteJsonQuote::jsonQuote('abc"xyz'));
        $t->same('226c696e655c6e7461625c746e756c5c7530303030656e6422', bin2hex(SQLiteJsonQuote::jsonQuote($controlText)));
        $t->same('"{\"a\":1}"', SQLiteJsonQuote::jsonQuote('{"a":1}'));
        $t->same('{"a":1}', SQLiteJsonQuote::jsonQuote(new SQLiteJsonSubtypeValue('{"a":1}')));
        $t->same('[1,2]', SQLiteJsonQuote::jsonQuote(new SQLiteJsonSubtypeValue('[1,2]')));
        $t->same('{"plugin":{"enabled":true,"count":2}}', SQLiteJsonQuote::jsonQuote(new SQLiteBlobValue($jsonb)));
        $t->same('9.0e+999', SQLiteJsonQuote::jsonQuote(INF));
        $t->same('-9.0e+999', SQLiteJsonQuote::jsonQuote(-INF));
        $t->same('null', SQLiteJsonQuote::jsonQuote(NAN));
        $t->same('null', SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', null));
        $t->same('12345', SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', 12345));
        $t->same('"copied settings"', SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', 'copied settings'));
        $t->same('{"a":1}', SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', new SQLiteJsonSubtypeValue('{"a":1}')));
        $t->same('{"plugin":{"enabled":true,"count":2}}', SQLiteJsonQuote::jsonQuoteSqlFunction('json_quote', new SQLiteBlobValue($jsonb)));
        $t->same('null', SQLiteJsonQuote::jsonQuoteSqlFunction('JSON_QUOTE', null));
        $t->same('0', SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', [false]));
        $t->same('100.0', SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', [1e2]));
        $t->same('{"a":1}', SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', [new SQLiteJsonSubtypeValue('{"a":1}')]));
        $t->same('{"plugin":{"enabled":true,"count":2}}', SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', [new SQLiteBlobValue($jsonb)]));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuote(new SQLiteBlobValue('01234')));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuote(new SQLiteBlobValue("\x8b\xff" . str_repeat("\0", 7))));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunction('json_valid', 'copied settings'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('JSON_QUOTE', ['copied settings', 'extra']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonQuote::jsonQuoteSqlFunctionArguments('json_valid', ['copied settings']));
    },
    'constructs sqlite json arrays from sql values for option diagnostics' => static function (TestRunner $t): void {
        $jsonObject = new SQLiteJsonSubtypeValue('{"abc":2.5,"def":null,"ghi":"hello"}');
        $jsonbArray = new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3]));

        $t->same('[1,2.5,null,"hello"]', SQLiteJsonConstructor::jsonArray(1, 2.5, null, 'hello'));
        $t->same(
            '[1,"{\"abc\":2.5,\"def\":null,\"ghi\":hello}",99]',
            SQLiteJsonConstructor::jsonArray(1, '{"abc":2.5,"def":null,"ghi":hello}', 99),
        );
        $t->same(
            '[1,{"abc":2.5,"def":null,"ghi":"hello"},99]',
            SQLiteJsonConstructor::jsonArray(1, $jsonObject, 99),
        );
        $t->same('[1,0,9.0e+999,-9.0e+999]', SQLiteJsonConstructor::jsonArray(true, false, INF, -INF));
        $t->same('[1,[1,2,3]]', SQLiteJsonConstructor::jsonArray(1, $jsonbArray));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonArray(1, new SQLiteBlobValue("\xab\xcd"), 3));
    },
    'constructs sqlite json objects from text labels and sql values' => static function (TestRunner $t): void {
        $jsonArray = new SQLiteJsonSubtypeValue(SQLiteJsonConstructor::jsonArray('xyx', 77, 4.5));
        $jsonbArray = new SQLiteBlobValue(SQLiteJsonB::encode([1, 2, 3]));

        $t->same(
            '{"a":1,"b":2.5,"c":null,"d":"String Test"}',
            SQLiteJsonConstructor::jsonObject('a', 1, 'b', 2.5, 'c', null, 'd', 'String Test'),
        );
        $t->same(
            '{"a":["xyx",77,4.5],"x":2.5}',
            SQLiteJsonConstructor::jsonObject('a', $jsonArray, 'x', 2.5),
        );
        $t->same(
            '{"a":[1,2,3],"b":9.0e+999}',
            SQLiteJsonConstructor::jsonObject('a', $jsonbArray, 'b', INF),
        );
        $t->same('{"\"a\"":1}', SQLiteJsonConstructor::jsonObject(new SQLiteJsonSubtypeValue('"a"'), 1));
    },
    'dispatches sqlite json and jsonb constructor sql function names' => static function (TestRunner $t): void {
        $jsonSubtype = new SQLiteJsonSubtypeValue('{"mode":"seo"}');
        $jsonbArray = new SQLiteBlobValue(SQLiteJsonB::encode(['cache', 'media']));

        $t->same(
            '[1,{"mode":"seo"},["cache","media"],null]',
            SQLiteJsonConstructor::jsonArraySqlFunction('json_array', 1, $jsonSubtype, $jsonbArray, null),
        );
        $t->same(
            '[1,{"mode":"seo"},["cache","media"],null]',
            SQLiteJsonConstructor::jsonArraySqlFunction('JSON_ARRAY', 1, $jsonSubtype, $jsonbArray, null),
        );
        $t->same(
            '["queue",["cache","media"],0]',
            SQLiteJsonConstructor::jsonArraySqlFunctionArguments('JSON_ARRAY', ['queue', $jsonbArray, false]),
        );
        $jsonbConstructedArray = SQLiteJsonConstructor::jsonArraySqlFunction('jsonb_array', 'queue', $jsonbArray);
        $t->true($jsonbConstructedArray instanceof SQLiteBlobValue);
        $t->same(['queue', ['cache', 'media']], SQLiteJsonB::decode($jsonbConstructedArray->bytes));
        $jsonbVectorArray = SQLiteJsonConstructor::jsonArraySqlFunctionArguments('JSONB_ARRAY', ['queue', $jsonSubtype, null]);
        $t->true($jsonbVectorArray instanceof SQLiteBlobValue);
        $t->same(['queue', ['mode' => 'seo'], null], SQLiteJsonB::decode($jsonbVectorArray->bytes));

        $t->same(
            '{"option_name":"plugin_settings","payload":{"mode":"seo"}}',
            SQLiteJsonConstructor::jsonObjectSqlFunction('json_object', 'option_name', 'plugin_settings', 'payload', $jsonSubtype),
        );
        $t->same(
            '{"option_name":"plugin_settings","payload":{"mode":"seo"}}',
            SQLiteJsonConstructor::jsonObjectSqlFunction('JSON_OBJECT', 'option_name', 'plugin_settings', 'payload', $jsonSubtype),
        );
        $t->same(
            '{"option_name":"plugin_settings","enabled":1}',
            SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('JSON_OBJECT', ['option_name', 'plugin_settings', 'enabled', true]),
        );
        $jsonbConstructedObject = SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'queue', $jsonbArray, 'enabled', true);
        $t->true($jsonbConstructedObject instanceof SQLiteBlobValue);
        $t->same(['queue' => ['cache', 'media'], 'enabled' => 1], SQLiteJsonB::decode($jsonbConstructedObject->bytes));
        $jsonbVectorObject = SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('JSONB_OBJECT', ['queue', $jsonbArray, 'payload', $jsonSubtype]);
        $t->true($jsonbVectorObject instanceof SQLiteBlobValue);
        $t->same(['queue' => ['cache', 'media'], 'payload' => ['mode' => 'seo']], SQLiteJsonB::decode($jsonbVectorObject->bytes));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonArraySqlFunction('json_insert', 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonArraySqlFunctionArguments('json_insert', [1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('json_array', 'a', 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('json_array', ['a', 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunction('jsonb_object', 'a'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObjectSqlFunctionArguments('JSONB_OBJECT', ['a']));
    },
    'rejects sqlite json object argument and blob label errors' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObject('a', 1, 'b'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObject(null, 5));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObject(true, 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObject(new SQLiteBlobValue(SQLiteJsonB::encode('a')), 1));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonConstructor::jsonObject('a', new SQLiteBlobValue("\xab\xcd")));
    },
    'aggregates sqlite json arrays and objects from ordered sql rows' => static function (TestRunner $t): void {
        $jsonRules = new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]');
        $jsonbSummary = new SQLiteBlobValue(SQLiteJsonB::encode(['count' => 2, 'autoload' => true]));

        $t->same(
            '["siteurl",null,1,0,[{"name":"seo"},{"name":"cache"}],{"count":2,"autoload":true}]',
            SQLiteJsonAggregate::jsonGroupArray(['siteurl', null, true, false, $jsonRules, $jsonbSummary]),
        );
        $t->same(
            '{"siteurl":"https://example.test","autoloaded":1,"rules":[{"name":"seo"},{"name":"cache"}],"summary":{"count":2,"autoload":true}}',
            SQLiteJsonAggregate::jsonGroupObject([
                ['siteurl', 'https://example.test'],
                ['autoloaded', true],
                ['rules', $jsonRules],
                ['summary', $jsonbSummary],
            ]),
        );
        $t->same('[]', SQLiteJsonAggregate::jsonGroupArray([]));
        $t->same('{}', SQLiteJsonAggregate::jsonGroupObject([]));
        $t->same(
            '["siteurl",null,1,0,[{"name":"seo"},{"name":"cache"}],{"count":2,"autoload":true}]',
            SQLiteJsonAggregate::jsonGroupArrayDistinct(['siteurl', 'siteurl', null, null, true, 1, false, 0, $jsonRules, $jsonRules, $jsonbSummary, $jsonbSummary]),
        );
        $t->same(
            '[null,"blogname","siteurl",{"count":2,"autoload":true},[{"name":"seo"},{"name":"cache"}]]',
            SQLiteJsonAggregate::jsonGroupArrayOrderBy([
                ['siteurl', 20],
                [$jsonbSummary, 30],
                ['blogname', 10],
                [null, null],
                [$jsonRules, 30],
            ]),
        );
        $orderedJsonb = SQLiteJsonAggregate::jsonGroupArrayOrderBySqlFunction('JSONB_GROUP_ARRAY', [
            ['siteurl', 'b'],
            ['blogname', 'a'],
            [$jsonRules, 'c'],
        ]);
        $t->true($orderedJsonb instanceof SQLiteBlobValue);
        $t->same(['blogname', 'siteurl', [['name' => 'seo'], ['name' => 'cache']]], SQLiteJsonB::decode($orderedJsonb->bytes));
        $distinctJsonb = SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunction('JSONB_GROUP_ARRAY', ['siteurl', 'siteurl', null, null, $jsonRules, $jsonRules]);
        $t->true($distinctJsonb instanceof SQLiteBlobValue);
        $t->same(['siteurl', null, [['name' => 'seo'], ['name' => 'cache']]], SQLiteJsonB::decode($distinctJsonb->bytes));
        $t->same(
            '["siteurl",null,[{"name":"seo"},{"name":"cache"}]]',
            SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunctionArguments('JSON_GROUP_ARRAY', ['siteurl', 'siteurl', null, null, $jsonRules, $jsonRules]),
        );
        $distinctVectorJsonb = SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunctionArguments('JSONB_GROUP_ARRAY', ['siteurl', 'siteurl', null, null, $jsonRules, $jsonRules]);
        $t->true($distinctVectorJsonb instanceof SQLiteBlobValue);
        $t->same(['siteurl', null, [['name' => 'seo'], ['name' => 'cache']]], SQLiteJsonB::decode($distinctVectorJsonb->bytes));
        $t->same('[]', SQLiteJsonAggregate::jsonGroupArrayDistinct([]));
        $t->same('[]', SQLiteJsonAggregate::jsonGroupArrayOrderBy([]));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArray([new SQLiteBlobValue("\xab\xcd")]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinct([new SQLiteBlobValue("\xab\xcd")]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayOrderBy([[new SQLiteBlobValue("\xab\xcd"), 1]]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunction('json_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunctionArguments('json_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayOrderBySqlFunction('json_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArrayOrderBy([['missing-order-key']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObject([[null, 5]]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObject([['a']]));
    },
    'dispatches sqlite json aggregate functions to text or jsonb results' => static function (TestRunner $t): void {
        $jsonRules = new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]');
        $jsonbSummary = new SQLiteBlobValue(SQLiteJsonB::encode(['count' => 2, 'autoload' => true]));

        $textArray = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', ['siteurl', null, $jsonRules, $jsonbSummary]);
        $jsonbArray = SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', ['siteurl', null, $jsonRules, $jsonbSummary]);
        $jsonbVectorArray = SQLiteJsonAggregate::jsonGroupArraySqlFunctionArguments('JSONB_GROUP_ARRAY', ['siteurl', null, $jsonRules, $jsonbSummary]);
        $textObject = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('json_group_object', [
            ['siteurl', 'https://example.test'],
            ['rules', $jsonRules],
            ['summary', $jsonbSummary],
        ]);
        $textVectorObject = SQLiteJsonAggregate::jsonGroupObjectSqlFunctionArguments('JSON_GROUP_OBJECT', [
            ['siteurl', 'https://example.test'],
            ['rules', $jsonRules],
            ['summary', $jsonbSummary],
        ]);
        $jsonbObject = SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', [
            ['siteurl', 'https://example.test'],
            ['rules', $jsonRules],
            ['summary', $jsonbSummary],
        ]);
        $jsonbVectorObject = SQLiteJsonAggregate::jsonGroupObjectSqlFunctionArguments('JSONB_GROUP_OBJECT', [
            ['siteurl', 'https://example.test'],
            ['rules', $jsonRules],
            ['summary', $jsonbSummary],
        ]);

        $t->same('["siteurl",null,[{"name":"seo"},{"name":"cache"}],{"count":2,"autoload":true}]', $textArray);
        $t->same('["siteurl",null,[{"name":"seo"},{"name":"cache"}],{"count":2,"autoload":true}]', SQLiteJsonAggregate::jsonGroupArraySqlFunction('JSON_GROUP_ARRAY', ['siteurl', null, $jsonRules, $jsonbSummary]));
        $t->same('["siteurl",null,[{"name":"seo"},{"name":"cache"}],{"count":2,"autoload":true}]', SQLiteJsonAggregate::jsonGroupArraySqlFunctionArguments('JSON_GROUP_ARRAY', ['siteurl', null, $jsonRules, $jsonbSummary]));
        $t->true($jsonbArray instanceof SQLiteBlobValue);
        $t->true($jsonbVectorArray instanceof SQLiteBlobValue);
        $t->same(['siteurl', null, [['name' => 'seo'], ['name' => 'cache']], ['count' => 2, 'autoload' => true]], SQLiteJsonB::decode($jsonbArray->bytes));
        $t->same(['siteurl', null, [['name' => 'seo'], ['name' => 'cache']], ['count' => 2, 'autoload' => true]], SQLiteJsonB::decode($jsonbVectorArray->bytes));
        $t->same('{"siteurl":"https://example.test","rules":[{"name":"seo"},{"name":"cache"}],"summary":{"count":2,"autoload":true}}', $textObject);
        $t->same('{"siteurl":"https://example.test","rules":[{"name":"seo"},{"name":"cache"}],"summary":{"count":2,"autoload":true}}', $textVectorObject);
        $t->true($jsonbObject instanceof SQLiteBlobValue);
        $t->true($jsonbVectorObject instanceof SQLiteBlobValue);
        $t->same([
            'siteurl' => 'https://example.test',
            'rules' => [['name' => 'seo'], ['name' => 'cache']],
            'summary' => ['count' => 2, 'autoload' => true],
        ], SQLiteJsonB::decode($jsonbObject->bytes));
        $t->same([
            'siteurl' => 'https://example.test',
            'rules' => [['name' => 'seo'], ['name' => 'cache']],
            'summary' => ['count' => 2, 'autoload' => true],
        ], SQLiteJsonB::decode($jsonbVectorObject->bytes));
        $t->true(SQLiteJsonAggregate::jsonGroupArraySqlFunction('jsonb_group_array', []) instanceof SQLiteBlobValue);
        $t->same([], SQLiteJsonB::decode(SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group_object', [])->bytes));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupArraySqlFunctionArguments('json_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectSqlFunction('jsonb_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectSqlFunctionArguments('jsonb_group', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonAggregate::jsonGroupObjectSqlFunctionArguments('json_group_object', [['missing-value']]));
    },
    'finalizes sqlite json aggregate state after ordered step rows' => static function (TestRunner $t): void {
        $state = new SQLiteJsonAggregateState();
        $state->stepArray('siteurl');
        $state->stepArray(null);
        $state->stepArray(new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'));
        $state->stepArray(new SQLiteBlobValue(SQLiteJsonB::encode(['count' => 2, 'autoload' => true])));
        $state->stepArrayDistinct('siteurl');
        $state->stepArrayDistinct('siteurl');
        $state->stepArrayDistinct(null);
        $state->stepArrayDistinct(null);
        $state->stepArrayDistinct(true);
        $state->stepArrayDistinct(1);
        $state->stepArrayDistinct(new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'));
        $state->stepArrayDistinct(new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'));
        $state->stepArrayOrderBy('siteurl', 20);
        $state->stepArrayOrderBy(new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 30);
        $state->stepArrayOrderBy(null, null);
        $state->stepArrayOrderBy('blogname', 10);
        $state->stepObject('siteurl', 'https://example.test');
        $state->stepObject('rules', new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'));
        $state->stepObject('summary', new SQLiteBlobValue(SQLiteJsonB::encode(['count' => 2, 'autoload' => true])));

        $t->same(['arrayRows' => 4, 'distinctArrayRows' => 8, 'orderedArrayRows' => 4, 'objectRows' => 3], $state->summary());
        $t->same(
            '["siteurl",null,[{"name":"seo"},{"name":"cache"}],{"count":2,"autoload":true}]',
            $state->finalizeArray(),
        );
        $t->same(
            '["siteurl",null,1,[{"name":"seo"},{"name":"cache"}]]',
            $state->finalizeDistinctArray(),
        );
        $t->same(
            '[null,"blogname","siteurl",[{"name":"seo"},{"name":"cache"}]]',
            $state->finalizeOrderedArray('JSON_GROUP_ARRAY'),
        );
        $t->same(
            '{"siteurl":"https://example.test","rules":[{"name":"seo"},{"name":"cache"}],"summary":{"count":2,"autoload":true}}',
            $state->finalizeObject('JSON_GROUP_OBJECT'),
        );

        $jsonbArray = $state->finalizeArray('JSONB_GROUP_ARRAY');
        $jsonbDistinctArray = $state->finalizeDistinctArray('JSONB_GROUP_ARRAY');
        $jsonbOrderedArray = $state->finalizeOrderedArray('JSONB_GROUP_ARRAY');
        $jsonbObject = $state->finalizeObject('jsonb_group_object');
        $t->true($jsonbArray instanceof SQLiteBlobValue);
        $t->true($jsonbDistinctArray instanceof SQLiteBlobValue);
        $t->true($jsonbOrderedArray instanceof SQLiteBlobValue);
        $t->true($jsonbObject instanceof SQLiteBlobValue);
        $t->same(['siteurl', null, [['name' => 'seo'], ['name' => 'cache']], ['count' => 2, 'autoload' => true]], SQLiteJsonB::decode($jsonbArray->bytes));
        $t->same(['siteurl', null, 1, [['name' => 'seo'], ['name' => 'cache']]], SQLiteJsonB::decode($jsonbDistinctArray->bytes));
        $t->same([null, 'blogname', 'siteurl', [['name' => 'seo'], ['name' => 'cache']]], SQLiteJsonB::decode($jsonbOrderedArray->bytes));
        $t->same([
            'siteurl' => 'https://example.test',
            'rules' => [['name' => 'seo'], ['name' => 'cache']],
            'summary' => ['count' => 2, 'autoload' => true],
        ], SQLiteJsonB::decode($jsonbObject->bytes));

        $empty = new SQLiteJsonAggregateState();
        $t->same('[]', $empty->finalizeArray());
        $t->same('[]', $empty->finalizeDistinctArray());
        $t->same('[]', $empty->finalizeOrderedArray());
        $t->same('{}', $empty->finalizeObject());
        $t->throws(InvalidArgumentException::class, static fn () => $state->finalizeArray('json_group'));
        $t->throws(InvalidArgumentException::class, static fn () => $state->finalizeDistinctArray('json_group'));
        $t->throws(InvalidArgumentException::class, static fn () => $state->finalizeOrderedArray('json_group'));
        $t->throws(InvalidArgumentException::class, static fn () => $state->finalizeObject('jsonb_group'));
    },
    'canonicalizes sqlite json text json5 blob and null option values' => static function (TestRunner $t): void {
        $jsonb = SQLiteJsonB::encode(['a' => 35, 'b' => [1, 2]]);
        $controlCharacterSettings = '{label:"abc' . chr(1) . 'xyz"}';

        $t->same('{"this":"is","a":["test"]}', SQLiteJsonCanonical::json(' { "this" : "is", "a": [ "test" ] } '));
        $t->same('{"a":5,"b":6}', SQLiteJsonCanonical::json('{a:5,b:6,}'));
        $t->same('[5,6]', SQLiteJsonCanonical::json('[5,6,]'));
        $t->same('{"x":4.0}', SQLiteJsonCanonical::json('{x: 4.}'));
        $t->same('{"x":4.0e1}', SQLiteJsonCanonical::json('{x: +4.e1}'));
        $t->same('{"x":-0.5e-1}', SQLiteJsonCanonical::json('{x: -.5e-1}'));
        $t->same('{"x":9e999}', SQLiteJsonCanonical::json('{x: +Infinity}'));
        $t->same('{"x":null}', SQLiteJsonCanonical::json('{x: NaN}'));
        $t->same('{"x":11259375}', SQLiteJsonCanonical::json('{x: 0xabcdef}'));
        $t->same('{"x":"a \\"b\\" c"}', SQLiteJsonCanonical::json('{x:\'a "b" c\'}'));
        $t->same('7b226c6162656c223a226162635c753030303178797a227d', bin2hex(SQLiteJsonCanonical::json($controlCharacterSettings) ?? ''));
        $t->same('{"u":"\\u0062","slash":"a/b","nl":"a\\nb"}', SQLiteJsonCanonical::json('{"u":"\\u0062","slash":"a/b","nl":"a\\nb"}'));
        $t->same('{"a":35}', SQLiteJsonCanonical::json(new SQLiteBlobValue('{"a":35}')));
        $t->same('{"a":35,"b":[1,2]}', SQLiteJsonCanonical::json(new SQLiteBlobValue($jsonb)));
        $t->same(null, SQLiteJsonCanonical::json(null));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::json('{ MNO_123/xyz : 789 }'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::json('{enabled:true,,}'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::json(new SQLiteBlobValue("\x8b\xff" . str_repeat("\0", 7))));
    },
    'dispatches sqlite json and jsonb canonical sql function names' => static function (TestRunner $t): void {
        $json5 = "{plugin:{enabled:true,modes:['cache','seo',],},}";
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true, 'count' => 2]]));

        $t->same('{"plugin":{"enabled":true,"modes":["cache","seo"]}}', SQLiteJsonCanonical::jsonSqlFunction('json', $json5));
        $t->same('{"plugin":{"enabled":true,"modes":["cache","seo"]}}', SQLiteJsonCanonical::jsonSqlFunction('JSON', $json5));
        $t->same('{"plugin":{"enabled":true,"modes":["cache","seo"]}}', SQLiteJsonCanonical::jsonSqlFunctionArguments('JSON', [$json5]));
        $t->same('{"plugin":{"enabled":true,"count":2}}', SQLiteJsonCanonical::jsonSqlFunction('json', $jsonb));
        $t->same(null, SQLiteJsonCanonical::jsonSqlFunction('json', null));
        $t->same(null, SQLiteJsonCanonical::jsonSqlFunctionArguments('JSON', [null]));

        $jsonbFromText = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $json5);
        $jsonbFromBlob = SQLiteJsonCanonical::jsonSqlFunction('jsonb', $jsonb);
        $jsonbFromArguments = SQLiteJsonCanonical::jsonSqlFunctionArguments('JSONB', [$json5]);
        $t->true($jsonbFromText instanceof SQLiteBlobValue);
        $t->true($jsonbFromBlob instanceof SQLiteBlobValue);
        $t->true($jsonbFromArguments instanceof SQLiteBlobValue);
        $t->same(['plugin' => ['enabled' => true, 'modes' => ['cache', 'seo']]], SQLiteJsonB::decode($jsonbFromText->bytes));
        $t->same(['plugin' => ['enabled' => true, 'count' => 2]], SQLiteJsonB::decode($jsonbFromBlob->bytes));
        $t->same(['plugin' => ['enabled' => true, 'modes' => ['cache', 'seo']]], SQLiteJsonB::decode($jsonbFromArguments->bytes));
        $t->same(null, SQLiteJsonCanonical::jsonSqlFunction('jsonb', null));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunction('json_pretty', $json5));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunctionArguments('json_pretty', [$json5]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunctionArguments('json', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunctionArguments('json', [$json5, '$']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunction('jsonb', '{enabled:true,,}'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunctionArguments('jsonb', ['{enabled:true,,}']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonCanonical::jsonSqlFunction('jsonb', new SQLiteBlobValue("\xab\xcd")));
    },
    'pretty prints sqlite json text json5 blob and null option values' => static function (TestRunner $t): void {
        $settings = '{"a":1,"b":[2,3],"c":{"d":4}}';
        $json5Settings = "{a:1,b:[2,3,],/* copied option */c:'hi'}";
        $jsonb = SQLiteJsonB::encode(['a' => 1, 'b' => [2, 3]]);

        $t->same(
            "{\n"
                . '    "a": 1,' . "\n"
                . '    "b": [' . "\n"
                . '        2,' . "\n"
                . '        3' . "\n"
                . '    ],' . "\n"
                . '    "c": {' . "\n"
                . '        "d": 4' . "\n"
                . '    }' . "\n"
                . '}',
            SQLiteJsonPretty::jsonPretty($settings),
        );
        $t->same(
            "{\n"
                . '    "a": 1,' . "\n"
                . '    "b": [' . "\n"
                . '        2,' . "\n"
                . '        3' . "\n"
                . '    ],' . "\n"
                . '    "c": "hi"' . "\n"
                . '}',
            SQLiteJsonPretty::jsonPretty($json5Settings),
        );
        $t->same("{\n\"a\": 1,\n\"b\": [\n2,\n3\n]\n}", SQLiteJsonPretty::jsonPretty('{"a":1,"b":[2,3]}', ''));
        $t->same("{\n\t\"a\": 1,\n\t\"b\": [\n\t\t2,\n\t\t3\n\t]\n}", SQLiteJsonPretty::jsonPretty('{"a":1,"b":[2,3]}', "\t"));
        $t->same("{\n--\"a\": 1,\n--\"b\": [\n----2,\n----3\n--]\n}", SQLiteJsonPretty::jsonPretty('{"a":1,"b":[2,3]}', '--'));
        $t->same(
            "{\n"
                . '    "empty": [],' . "\n"
                . '    "obj": {},' . "\n"
                . '    "x": 1' . "\n"
                . '}',
            SQLiteJsonPretty::jsonPretty('{"empty":[],"obj":{},"x":1}'),
        );
        $t->same(
            "{\n"
                . '    "x": "a\nb",' . "\n"
                . '    "y": "a/b",' . "\n"
                . '    "u": "\u0062"' . "\n"
                . '}',
            SQLiteJsonPretty::jsonPretty('{"x":"a\nb","y":"a/b","u":"\u0062"}'),
        );
        $t->same(
            "{\n"
                . '    "x": 9e999,' . "\n"
                . '    "y": null,' . "\n"
                . '    "z": 4.0,' . "\n"
                . '    "h": 16' . "\n"
                . '}',
            SQLiteJsonPretty::jsonPretty('{x:+Infinity,y:NaN,z:4.,h:0x10}'),
        );
        $t->same(
            "{\n"
                . '    "a": 1,' . "\n"
                . '    "b": [' . "\n"
                . '        2,' . "\n"
                . '        3' . "\n"
                . '    ]' . "\n"
                . '}',
            SQLiteJsonPretty::jsonPretty(new SQLiteBlobValue($jsonb)),
        );
        $t->same(null, SQLiteJsonPretty::jsonPretty(null));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}'));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('JSON_PRETTY', '{"a":1}'));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', new SQLiteJsonSubtypeValue('{"a":1}')));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', new SQLiteBlobValue('{"a":1}')));
        $t->same("{\n    \"a\": 1,\n    \"b\": [\n        2,\n        3\n    ]\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', new SQLiteBlobValue($jsonb)));
        $t->same("[\n--1,\n--2\n]", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', new SQLiteBlobValue(SQLiteJsonB::encode([1, 2])), '--'));
        $t->same("{\n--\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}', '--'));
        $t->same("{\n..\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', new SQLiteBlobValue('{"a":1}'), '..'));
        $t->same("{\n  \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', new SQLiteJsonSubtypeValue('{"a":1}'), '  '));
        $t->same("{\n**\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}', new SQLiteBlobValue('**')));
        $t->same("{\n::\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}', new SQLiteJsonSubtypeValue('::')));
        $t->same("{\n1\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}', true));
        $t->same("[\n01,\n02\n]", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '[1,2]', false));
        $t->same("{\n2.5\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}', 2.5));
        $t->same("{\n3.0\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{"a":1}', 3.0));
        $t->same('42', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', 42));
        $t->same('-7', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', -7));
        $t->same('3.5', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', 3.5));
        $t->same('0.125', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', 0.125));
        $t->same('3.0', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', 3.0));
        $t->same('1', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', true));
        $t->same('0', SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', false));
        $t->same("[\n01,\n02\n]", SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '[1,2]', 0));
        $t->same(null, SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', null));
        $t->same(null, SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', null, '--'));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}']));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [new SQLiteJsonSubtypeValue('{"a":1}')]));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [new SQLiteBlobValue('{"a":1}')]));
        $t->same("{\n    \"a\": 1,\n    \"b\": [\n        2,\n        3\n    ]\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [new SQLiteBlobValue($jsonb)]));
        $t->same("[\n--1,\n--2\n]", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [new SQLiteBlobValue(SQLiteJsonB::encode([1, 2])), '--']));
        $t->same("{\n  \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('JSON_PRETTY', [new SQLiteJsonSubtypeValue('{"a":1}'), '  ']));
        $t->same("{\n..\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [new SQLiteBlobValue('{"a":1}'), '..']));
        $t->same("{\n..\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', '..']));
        $t->same("{\n    \"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', null]));
        $t->same("{\n**\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', new SQLiteBlobValue('**')]));
        $t->same("{\n::\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', new SQLiteJsonSubtypeValue('::')]));
        $t->same("{\n1\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', true]));
        $t->same("{\n2.5\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', 2.5]));
        $t->same("{\n3.0\"a\": 1\n}", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', 3.0]));
        $t->same('42', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [42]));
        $t->same('-7', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [-7]));
        $t->same('3.5', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [3.5]));
        $t->same('0.125', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [0.125]));
        $t->same('3.0', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [3.0]));
        $t->same('1', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [true]));
        $t->same('0', SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [false]));
        $t->same("[\n01,\n02\n]", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['[1,2]', 0]));
        $t->same("[\n01,\n02\n]", SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['[1,2]', false]));
        $t->same(null, SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [null]));
        $t->same(null, SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', [null, '--']));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunction('jsonb_pretty', '{"a":1}'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunctionArguments('jsonb_pretty', ['{"a":1}']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{"a":1}', '  ', 'extra']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunction('json_pretty', '{a:true,,}'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPrettySqlFunctionArguments('json_pretty', ['{a:true,,}']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPretty('{a:true,,}'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPretty::jsonPretty(new SQLiteBlobValue("\x8b\xff" . str_repeat("\0", 7))));
    },
    'reports sqlite json_error_position for text json5 blob and null option values' => static function (TestRunner $t): void {
        $validJsonb = SQLiteJsonB::encode(['enabled' => true, 'modes' => ['dark']]);
        $superficialOnlyJsonb = "\x8b\xff" . str_repeat("\0", 7);

        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition('{"a":55,"b":72,}'));
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition('["a",55,"b",72,]'));
        $t->same(16, SQLiteJsonErrorPosition::jsonErrorPosition('{"a":55,"b":72,,}'));
        $t->same(16, SQLiteJsonErrorPosition::jsonErrorPosition('["a",55,"b",72,,]'));
        $t->same(9, SQLiteJsonErrorPosition::jsonErrorPosition('{a:null,{"h":[1,[1,2,3]],"j":"abc"}:true}'));
        $t->same(15, SQLiteJsonErrorPosition::jsonErrorPosition('{enabled:true,,}'));
        $t->same(1, SQLiteJsonErrorPosition::jsonErrorPosition('not json'));
        $t->same(1, SQLiteJsonErrorPosition::jsonErrorPosition('"ok" trailing'));
        $t->same(7, SQLiteJsonErrorPosition::jsonErrorPosition('{"x":01.5}'));
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition('{"x":+5,"y":.5,"z":1.}'));
        $t->same(null, SQLiteJsonErrorPosition::jsonErrorPosition(null));

        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition(new SQLiteBlobValue('{"a":35}')));
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPosition(new SQLiteBlobValue($validJsonb)));
        $t->same(2, SQLiteJsonErrorPosition::jsonErrorPosition(new SQLiteBlobValue($superficialOnlyJsonb)));
        $t->same(1, SQLiteJsonErrorPosition::jsonErrorPosition(new SQLiteBlobValue("\x10\0")));

        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', '{"a":55,"b":72,}'));
        $t->same(16, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', '{"a":55,"b":72,,}'));
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', new SQLiteBlobValue($validJsonb)));
        $t->same(2, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', new SQLiteBlobValue($superficialOnlyJsonb)));
        $t->same(null, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_error_position', null));
        $t->same(15, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('JSON_ERROR_POSITION', '{enabled:true,,}'));
        $t->same(15, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('JSON_ERROR_POSITION', ['{enabled:true,,}']));
        $t->same(0, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('json_error_position', [new SQLiteBlobValue($validJsonb)]));
        $t->same(null, SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('json_error_position', [null]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonErrorPosition::jsonErrorPositionSqlFunction('json_valid', '{"a":1}'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('json_error_position', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonErrorPosition::jsonErrorPositionSqlFunctionArguments('json_error_position', ['{"a":1}', '{"b":2}']));
    },
    'inspects sqlite json_type and json_array_length for text json5 blob and null option values' => static function (TestRunner $t): void {
        $settings = '{"a":[2,3.5,true,false,null,"x"],"mode":"dark","empty":[]}';
        $json5Settings = "{plugin:{modes:['dark','light',],enabled:true},threshold:.5}";
        $jsonb = SQLiteJsonB::encode(['a' => [2, 3.5, true, false, null, 'x']]);
        $castTextBlob = new SQLiteBlobValue('{"one":[1,2,3],"mode":"dark"}');

        $t->same('object', SQLiteJsonInspection::jsonType($settings));
        $t->same('array', SQLiteJsonInspection::jsonType($settings, '$.a'));
        $t->same('integer', SQLiteJsonInspection::jsonType($settings, '$.a[0]'));
        $t->same('real', SQLiteJsonInspection::jsonType($settings, '$.a[1]'));
        $t->same('true', SQLiteJsonInspection::jsonType($settings, '$.a[2]'));
        $t->same('false', SQLiteJsonInspection::jsonType($settings, '$.a[3]'));
        $t->same('null', SQLiteJsonInspection::jsonType($settings, '$.a[4]'));
        $t->same('text', SQLiteJsonInspection::jsonType($settings, '$.a[5]'));
        $t->same(null, SQLiteJsonInspection::jsonType($settings, '$.a[6]'));
        $t->same(null, SQLiteJsonInspection::jsonType($settings, null));

        $t->same(6, SQLiteJsonInspection::jsonArrayLength($settings, '$.a'));
        $t->same(0, SQLiteJsonInspection::jsonArrayLength($settings, '$.mode'));
        $t->same(0, SQLiteJsonInspection::jsonArrayLength($settings, '$.empty'));
        $t->same(null, SQLiteJsonInspection::jsonArrayLength($settings, '$.missing'));
        $t->same(null, SQLiteJsonInspection::jsonArrayLength(null));
        $t->same(null, SQLiteJsonInspection::jsonArrayLength($settings, null));

        $t->same('object', SQLiteJsonInspection::jsonType($json5Settings));
        $t->same('array', SQLiteJsonInspection::jsonType($json5Settings, '$.plugin.modes'));
        $t->same('real', SQLiteJsonInspection::jsonType($json5Settings, '$.threshold'));
        $t->same(2, SQLiteJsonInspection::jsonArrayLength($json5Settings, '$.plugin.modes'));

        $t->same('object', SQLiteJsonInspection::jsonType(new SQLiteBlobValue($jsonb)));
        $t->same('array', SQLiteJsonInspection::jsonType(new SQLiteBlobValue($jsonb), '$.a'));
        $t->same(6, SQLiteJsonInspection::jsonArrayLength(new SQLiteBlobValue($jsonb), '$.a'));
        $t->same('object', SQLiteJsonInspection::jsonType($castTextBlob));
        $t->same(3, SQLiteJsonInspection::jsonArrayLength($castTextBlob, '$.one'));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::jsonType($settings, '$.a[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::jsonArrayLength('{enabled:true,,}', '$'));
    },
    'dispatches sqlite json inspection sql functions with scalar result typing' => static function (TestRunner $t): void {
        $settings = '{"plugin":{"modes":["dark","light"],"enabled":true,"empty":null},"title":"Cache"}';
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'modes' => ['dark', 'light'],
                'enabled' => true,
                'empty' => null,
            ],
            'title' => 'Cache',
        ]));

        $t->same('object', SQLiteJsonInspection::inspectionSqlFunction('json_type', $jsonb));
        $t->same('object', SQLiteJsonInspection::inspectionSqlFunction('JSON_TYPE', $jsonb));
        $t->same('array', SQLiteJsonInspection::inspectionSqlFunction('json_type', $settings, '$.plugin.modes'));
        $t->same('true', SQLiteJsonInspection::inspectionSqlFunction('json_type', $jsonb, '$.plugin.enabled'));
        $t->same('null', SQLiteJsonInspection::inspectionSqlFunction('json_type', $settings, '$.plugin.empty'));
        $t->same(null, SQLiteJsonInspection::inspectionSqlFunction('json_type', $settings, '$.plugin.missing'));

        $t->same(2, SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $jsonb, '$.plugin.modes'));
        $t->same(2, SQLiteJsonInspection::inspectionSqlFunction('JSON_ARRAY_LENGTH', $jsonb, '$.plugin.modes'));
        $t->same(0, SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $settings, '$.title'));
        $t->same(null, SQLiteJsonInspection::inspectionSqlFunction('json_array_length', $settings, '$.plugin.missing'));
        $t->same(null, SQLiteJsonInspection::inspectionSqlFunction('json_array_length', null, '$.plugin.modes'));
        $t->same(null, SQLiteJsonInspection::inspectionSqlFunction('json_type', $settings, null));

        $t->same('object', SQLiteJsonInspection::inspectionSqlFunctionArguments('JSON_TYPE', [$settings, '$.plugin']));
        $t->same('object', SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', [$jsonb]));
        $t->same(2, SQLiteJsonInspection::inspectionSqlFunctionArguments('JSON_ARRAY_LENGTH', [$settings, '$.plugin.modes']));
        $t->same(null, SQLiteJsonInspection::inspectionSqlFunctionArguments('json_array_length', [null, '$.plugin.modes']));
        $t->same(null, SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', [$settings, null]));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunction('json_valid', $settings));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunction('json_type', $settings, '$.plugin[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunction('json_array_length', '{"plugin":,}', '$.plugin'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', [$settings, '$.plugin', '$.title']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::inspectionSqlFunctionArguments('json_type', [$settings, 7]));
    },
    'iterates sqlite json_each table rows for text json5 and jsonb inputs' => static function (TestRunner $t): void {
        $settings = '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}],"empty":null},"priority":7}';
        $json5 = "{plugin:{enabled:false,title:'Cache',rules:['seo','cache',],},priority:+7}";
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'title' => 'Cache',
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
                'dotted.key' => 'quoted',
            ],
        ]));

        $rootRows = SQLiteJsonEach::jsonEach($settings);
        $t->same(['plugin', 'priority'], array_column($rootRows, 'key'));
        $t->same(['object', 'integer'], array_column($rootRows, 'type'));
        $t->same('{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}],"empty":null}', $rootRows[0]['value']);
        $t->same(null, $rootRows[0]['atom']);
        $t->same(7, $rootRows[1]['atom']);
        $t->same('$.plugin', $rootRows[0]['fullkey']);
        $t->same('$', $rootRows[0]['path']);
        $t->same($settings, $rootRows[0]['json']);
        $t->same('$', $rootRows[0]['root']);

        $pluginRows = SQLiteJsonEach::jsonEachSqlFunction('Json_EaCh', $json5, '$.plugin');
        $t->same(['enabled', 'title', 'rules'], array_column($pluginRows, 'key'));
        $t->same(['false', 'text', 'array'], array_column($pluginRows, 'type'));
        $t->same(0, $pluginRows[0]['value']);
        $t->same(0, $pluginRows[0]['atom']);
        $t->same('Cache', $pluginRows[1]['atom']);
        $t->same('["seo","cache"]', $pluginRows[2]['value']);
        $t->same('$.plugin.rules', $pluginRows[2]['fullkey']);
        $t->same('$.plugin', $pluginRows[2]['path']);
        $t->same([$json5, $json5, $json5], array_column($pluginRows, 'json'));
        $t->same(['$.plugin', '$.plugin', '$.plugin'], array_column($pluginRows, 'root'));

        $argumentRows = SQLiteJsonEach::jsonEachSqlFunctionArguments('JSON_EACH', [$json5, '$.plugin.rules']);
        $t->same([0, 1], array_column($argumentRows, 'key'));
        $t->same(['seo', 'cache'], array_column($argumentRows, 'atom'));
        $t->same(['$.plugin.rules', '$.plugin.rules'], array_column($argumentRows, 'root'));
        $t->same(['plugin', 'priority'], array_column(SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$settings]), 'key'));
        $t->same([], SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [null, '$.plugin']));

        $rulesRows = SQLiteJsonEach::jsonEach($jsonb, '$.plugin.rules');
        $t->same([0, 1], array_column($rulesRows, 'key'));
        $t->same(['object', 'object'], array_column($rulesRows, 'type'));
        $t->same('{"name":"seo"}', $rulesRows[0]['value']);
        $t->same('$.plugin.rules[1]', $rulesRows[1]['fullkey']);
        $t->same([$jsonb, $jsonb], array_column($rulesRows, 'json'));
        $t->same(['$.plugin.rules', '$.plugin.rules'], array_column($rulesRows, 'root'));

        $quotedRows = SQLiteJsonEach::jsonEach($jsonb, '$.plugin');
        $t->same('$.plugin."dotted.key"', $quotedRows[3]['fullkey']);

        $scalarRows = SQLiteJsonEach::jsonEach($settings, '$.plugin.title');
        $t->same([[null, 'Cache', 'text', 'Cache', 1, null, '$.plugin.title', '$.plugin.title']], array_map(
            static fn (array $row): array => [
                $row['key'],
                $row['value'],
                $row['type'],
                $row['atom'],
                $row['id'],
                $row['parent'],
                $row['fullkey'],
                $row['path'],
            ],
            $scalarRows,
        ));

        $t->same([], SQLiteJsonEach::jsonEach(null));
        $t->same([], SQLiteJsonEach::jsonEach($settings, '$.plugin.missing'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEachSqlFunction('json_tree', $settings));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$settings, '$', '$.extra']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [7]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEachSqlFunctionArguments('json_each', [$settings, 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEach($settings, '$.plugin[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonEach::jsonEach('{"plugin":,}', '$.plugin'));
    },
    'recursively iterates sqlite json_tree table rows for text json5 and jsonb inputs' => static function (TestRunner $t): void {
        $settings = '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}],"empty":null},"priority":7}';
        $json5 = "{plugin:{enabled:false,title:'Cache',rules:['seo','cache',],},priority:+7}";
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
                'dotted.key' => 'quoted',
            ],
        ]));

        $rootRows = SQLiteJsonTree::jsonTree($settings);
        $t->same([null, 'plugin', 'enabled', 'title', 'rules', 0, 'name', 1, 'name', 'empty', 'priority'], array_column($rootRows, 'key'));
        $t->same(['object', 'object', 'true', 'text', 'array', 'object', 'text', 'object', 'text', 'null', 'integer'], array_column($rootRows, 'type'));
        $t->same([null, 0, 1, 1, 1, 4, 5, 4, 7, 1, 0], array_column($rootRows, 'parent'));
        $t->same(['$', '$', '$.plugin', '$.plugin', '$.plugin', '$.plugin.rules', '$.plugin.rules[0]', '$.plugin.rules', '$.plugin.rules[1]', '$.plugin', '$'], array_column($rootRows, 'path'));
        $t->same('$.plugin.rules[1].name', $rootRows[8]['fullkey']);
        $t->same('cache', $rootRows[8]['atom']);
        $t->same(7, $rootRows[10]['value']);
        $t->same($settings, $rootRows[8]['json']);
        $t->same('$', $rootRows[8]['root']);

        $pluginRows = SQLiteJsonTree::jsonTreeSqlFunction('Json_TrEe', $json5, '$.plugin');
        $t->same([null, 'enabled', 'title', 'rules', 0, 1], array_column($pluginRows, 'key'));
        $t->same(['object', 'false', 'text', 'array', 'text', 'text'], array_column($pluginRows, 'type'));
        $t->same([null, 0, 0, 0, 3, 3], array_column($pluginRows, 'parent'));
        $t->same('$.plugin.rules[1]', $pluginRows[5]['fullkey']);
        $t->same('cache', $pluginRows[5]['atom']);
        $t->same([$json5, $json5, $json5, $json5, $json5, $json5], array_column($pluginRows, 'json'));
        $t->same(['$.plugin', '$.plugin', '$.plugin', '$.plugin', '$.plugin', '$.plugin'], array_column($pluginRows, 'root'));

        $argumentRows = SQLiteJsonTree::jsonTreeSqlFunctionArguments('JSON_TREE', [$json5, '$.plugin.rules']);
        $t->same([null, 0, 1], array_column($argumentRows, 'key'));
        $t->same(['array', 'text', 'text'], array_column($argumentRows, 'type'));
        $t->same(['$.plugin.rules', '$.plugin.rules', '$.plugin.rules'], array_column($argumentRows, 'root'));
        $t->same([null, 'plugin', 'enabled'], array_slice(array_column(SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$settings]), 'key'), 0, 3));
        $t->same([], SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [null, '$.plugin']));

        $rulesRows = SQLiteJsonTree::jsonTree($jsonb, '$.plugin.rules');
        $t->same([null, 0, 'name', 1, 'name'], array_column($rulesRows, 'key'));
        $t->same(['array', 'object', 'text', 'object', 'text'], array_column($rulesRows, 'type'));
        $t->same('$.plugin.rules[1].name', $rulesRows[4]['fullkey']);
        $t->same([$jsonb, $jsonb, $jsonb, $jsonb, $jsonb], array_column($rulesRows, 'json'));
        $t->same(['$.plugin.rules', '$.plugin.rules', '$.plugin.rules', '$.plugin.rules', '$.plugin.rules'], array_column($rulesRows, 'root'));

        $quotedRows = SQLiteJsonTree::jsonTree($jsonb, '$.plugin');
        $t->same('$.plugin."dotted.key"', $quotedRows[7]['fullkey']);

        $scalarRows = SQLiteJsonTree::jsonTree($settings, '$.plugin.title');
        $t->same([[null, 'Cache', 'text', 'Cache', 0, null, '$.plugin.title', '$.plugin.title']], array_map(
            static fn (array $row): array => [
                $row['key'],
                $row['value'],
                $row['type'],
                $row['atom'],
                $row['id'],
                $row['parent'],
                $row['fullkey'],
                $row['path'],
            ],
            $scalarRows,
        ));

        $t->same([], SQLiteJsonTree::jsonTree(null));
        $t->same([], SQLiteJsonTree::jsonTree($settings, '$.plugin.missing'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTreeSqlFunction('json_each', $settings));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$settings, '$', '$.extra']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [7]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTreeSqlFunctionArguments('json_tree', [$settings, 1]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTree($settings, '$.plugin[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTree::jsonTree('{"plugin":,}', '$.plugin'));
    },
    'extracts sqlite json values with SQL result typing for text json5 and jsonb' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":true,"title":"Cache","priority":7,"ratio":1.5,"rules":[{"name":"seo"},{"name":"cache"}],"empty":null}}';
        $json5 = "{plugin:{enabled:false,title:'Cache',priority:+7,ratio:.5,rules:[{name:'seo'},],},}";
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'title' => 'Cache',
                'priority' => 7,
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
            ],
        ]));

        $t->same(1, SQLiteJsonExtract::extract($json, '$.plugin.enabled'));
        $t->same(0, SQLiteJsonExtract::extract($json5, '$.plugin.enabled'));
        $t->same('Cache', SQLiteJsonExtract::extract($json, '$.plugin.title'));
        $t->same(7, SQLiteJsonExtract::extract($json, '$.plugin.priority'));
        $t->same(1.5, SQLiteJsonExtract::extract($json, '$.plugin.ratio'));
        $t->same(null, SQLiteJsonExtract::extract($json, '$.plugin.empty'));
        $t->same(null, SQLiteJsonExtract::extract($json, '$.plugin.missing'));
        $t->same('{"name":"cache"}', SQLiteJsonExtract::extract($json, '$.plugin.rules[#-1]'));
        $t->same('["Cache",7,null,true]', SQLiteJsonExtract::extract($json, '$.plugin.title', '$.plugin.priority', '$.plugin.missing', '$.plugin.enabled'));
        $t->same('{"name":"seo"}', SQLiteJsonExtract::extract($jsonb, '$.plugin.rules[0]'));
        $t->same('["Cache",true]', SQLiteJsonExtract::extract($jsonb, '$.plugin.title', '$.plugin.enabled'));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract($json));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract($json, '$.plugin[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extract('{"plugin":,}', '$.plugin'));
    },
    'dispatches sqlite json_extract and jsonb_extract sql function names' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":true,"title":"Cache","priority":7,"rules":[{"name":"seo"},{"name":"cache"}],"empty":null}}';
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => false,
                'title' => 'Cache',
                'priority' => 7,
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
                'empty' => null,
            ],
        ]));

        $t->same(1, SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$.plugin.enabled'));
        $t->same('{"name":"cache"}', SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$.plugin.rules[#-1]'));
        $t->same('["Cache",7,null,true]', SQLiteJsonExtract::extractSqlFunction('json_extract', $json, '$.plugin.title', '$.plugin.priority', '$.plugin.missing', '$.plugin.enabled'));
        $t->same(0, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, '$.plugin.enabled'));
        $t->same('Cache', SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, '$.plugin.title'));
        $t->same(null, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, '$.plugin.empty'));
        $t->same(null, SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, '$.plugin.missing'));

        $jsonbObject = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, '$.plugin.rules[#-1]');
        $jsonbSummary = SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $jsonb, '$.plugin.title', '$.plugin.priority', '$.plugin.missing', '$.plugin.enabled');
        $t->true($jsonbObject instanceof SQLiteBlobValue);
        $t->same(['name' => 'cache'], SQLiteJsonB::decode($jsonbObject->bytes));
        $t->true($jsonbSummary instanceof SQLiteBlobValue);
        $t->same(['Cache', 7, null, false], SQLiteJsonB::decode($jsonbSummary->bytes));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractSqlFunction('json_type', $json, '$.plugin'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractSqlFunction('jsonb_extract', $json));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractSqlFunction('jsonb_extract', '{"plugin":,}', '$.plugin'));
    },
    'propagates sqlite json_extract JSON subtype values into constructors' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":true,"title":"Cache","rules":[{"name":"seo"},{"name":"cache"}],"empty":null}}';
        $json5 = "{plugin:{enabled:false,title:'Cache',rules:[{name:'seo'},],},}";
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'title' => 'Cache',
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
            ],
        ]));

        $t->same(
            '[{"name":"cache"}]',
            SQLiteJsonConstructor::jsonArray(SQLiteJsonExtract::extractJsonArgument($json, '$.plugin.rules[#-1]')),
        );
        $t->same(
            '[[{"name":"seo"}]]',
            SQLiteJsonConstructor::jsonArray(SQLiteJsonExtract::extractJsonArgument($json5, '$.plugin.rules')),
        );
        $t->same(
            '[["Cache",true]]',
            SQLiteJsonConstructor::jsonArray(SQLiteJsonExtract::extractJsonArgument($jsonb, '$.plugin.title', '$.plugin.enabled')),
        );
        $t->same(
            '["Cache"]',
            SQLiteJsonConstructor::jsonArray(SQLiteJsonExtract::extractJsonArgument($json, '$.plugin.title')),
        );
        $t->same(
            '[1,0,null]',
            SQLiteJsonConstructor::jsonArray(
                SQLiteJsonExtract::extractJsonArgument($json, '$.plugin.enabled'),
                SQLiteJsonExtract::extractJsonArgument($json5, '$.plugin.enabled'),
                SQLiteJsonExtract::extractJsonArgument($json, '$.plugin.missing'),
            ),
        );
        $t->same(
            '{"rules":[{"name":"seo"},{"name":"cache"}],"summary":["Cache",true]}',
            SQLiteJsonConstructor::jsonObject(
                'rules',
                SQLiteJsonExtract::extractJsonArgument($jsonb, '$.plugin.rules'),
                'summary',
                SQLiteJsonExtract::extractJsonArgument($json, '$.plugin.title', '$.plugin.enabled'),
            ),
        );
        $t->same(
            '[{"name":"cache"}]',
            SQLiteJsonConstructor::jsonArray(SQLiteJsonExtract::extractJsonArgumentSqlFunction('json_extract', $json, '$.plugin.rules[#-1]')),
        );
        $jsonbRule = SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $jsonb, '$.plugin.rules[#-1]');
        $t->true($jsonbRule instanceof SQLiteBlobValue);
        $t->same([['name' => 'cache']], [
            SQLiteJsonB::decode($jsonbRule instanceof SQLiteBlobValue ? $jsonbRule->bytes : ''),
        ]);
        $jsonbSummary = SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $json, '$.plugin.title', '$.plugin.enabled', '$.plugin.missing');
        $t->true($jsonbSummary instanceof SQLiteBlobValue);
        $t->same(
            '[["Cache",true,null]]',
            SQLiteJsonConstructor::jsonArray($jsonbSummary),
        );
        $t->same(
            '["Cache",1,null]',
            SQLiteJsonConstructor::jsonArray(
                SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $json, '$.plugin.title'),
                SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $json, '$.plugin.enabled'),
                SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $json, '$.plugin.missing'),
            ),
        );

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractJsonArgument($json));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractJsonArgument($json, '$.plugin[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractJsonArgumentSqlFunction('json_type', $json, '$.plugin'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonExtract::extractJsonArgumentSqlFunction('jsonb_extract', $json));
    },
    'removes sqlite json text paths with canonical text result typing' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":true,"legacyToken":"secret","rules":[{"name":"seo"},{"name":"cache"}],"empty":null},"keep":1}';
        $json5 = "{plugin:{enabled:true,legacyToken:'secret',rules:[{name:'seo'},{name:'cache'},],},keep:1,}";
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'legacyToken' => 'secret',
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
            ],
            'keep' => 1,
        ]));

        $t->same(
            '{"plugin":{"enabled":true,"rules":[{"name":"seo"},{"name":"cache"}],"empty":null},"keep":1}',
            SQLiteJsonRemove::remove($json, '$.plugin.legacyToken'),
        );
        $t->same(
            '{"plugin":{"enabled":true,"rules":[{"name":"cache"}],"empty":null},"keep":1}',
            SQLiteJsonRemove::remove($json, '$.plugin.legacyToken', '$.plugin.rules[0]'),
        );
        $t->same(
            '{"plugin":{"enabled":true,"legacyToken":"secret","rules":[{"name":"seo"}]},"keep":1}',
            SQLiteJsonRemove::remove($json5, '$.plugin.rules[#-1]'),
        );
        $t->same(
            '{"plugin":{"enabled":true,"rules":[{"name":"seo"},{"name":"cache"}]},"keep":1}',
            SQLiteJsonRemove::remove($jsonb, '$.plugin.legacyToken'),
        );
        $t->same('{"plugin":{"enabled":true,"legacyToken":"secret","rules":[{"name":"seo"},{"name":"cache"}],"empty":null},"keep":1}', SQLiteJsonRemove::remove($json));
        $t->same(null, SQLiteJsonRemove::remove($json, '$'));
        $t->same(null, SQLiteJsonRemove::remove(null, '$.plugin'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::remove($json, '$.plugin[#-]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::remove('{"plugin":,}', '$.plugin'));
    },
    'dispatches sqlite json remove sql functions with text or jsonb result typing' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":true,"legacyToken":"secret","rules":[{"name":"seo"},{"name":"cache"}]},"keep":1}';
        $jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => true,
                'legacyToken' => 'secret',
                'rules' => [
                    ['name' => 'seo'],
                    ['name' => 'cache'],
                ],
            ],
            'keep' => 1,
        ]));

        $textResult = SQLiteJsonRemove::removeSqlFunction('JSON_REMOVE', $jsonb, '$.plugin.legacyToken');
        $t->same('{"plugin":{"enabled":true,"rules":[{"name":"seo"},{"name":"cache"}]},"keep":1}', $textResult);
        $t->same(
            '{"plugin":{"enabled":true,"rules":[{"name":"seo"},{"name":"cache"}]},"keep":1}',
            SQLiteJsonRemove::removeSqlFunctionArguments('JSON_REMOVE', [$jsonb, '$.plugin.legacyToken']),
        );

        $blobResult = SQLiteJsonRemove::removeSqlFunction('JSONB_REMOVE', $json, '$.plugin.legacyToken', '$.plugin.rules[0]');
        $t->true($blobResult instanceof SQLiteBlobValue);
        $t->same(
            ['plugin' => ['enabled' => true, 'rules' => [['name' => 'cache']]], 'keep' => 1],
            SQLiteJsonB::decode($blobResult->bytes),
        );
        $argumentBlobResult = SQLiteJsonRemove::removeSqlFunctionArguments(
            'JSONB_REMOVE',
            [$json, '$.plugin.legacyToken', '$.plugin.rules[0]'],
        );
        $t->true($argumentBlobResult instanceof SQLiteBlobValue);
        $t->same(
            ['plugin' => ['enabled' => true, 'rules' => [['name' => 'cache']]], 'keep' => 1],
            SQLiteJsonB::decode($argumentBlobResult->bytes),
        );

        $unchangedBlob = SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json);
        $t->true($unchangedBlob instanceof SQLiteBlobValue);
        $t->same(['plugin' => ['enabled' => true, 'legacyToken' => 'secret', 'rules' => [['name' => 'seo'], ['name' => 'cache']]], 'keep' => 1], SQLiteJsonB::decode($unchangedBlob->bytes));
        $t->same(null, SQLiteJsonRemove::removeSqlFunction('jsonb_remove', $json, '$'));
        $t->same(null, SQLiteJsonRemove::removeSqlFunctionArguments('JSONB_REMOVE', [$json, '$']));
        $t->same(null, SQLiteJsonRemove::removeSqlFunction('json_remove', null, '$.plugin'));
        $t->same(null, SQLiteJsonRemove::removeSqlFunctionArguments('JSON_REMOVE', [null, '$.plugin']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunction('json_patch', $json, '$.plugin'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_patch', [$json, '$.plugin']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [7, '$.plugin']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonRemove::removeSqlFunctionArguments('json_remove', [$json, 7]));
    },
    'dispatches sqlite json patch sql functions with text or jsonb result typing' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":false,"legacyToken":"secret","rules":["seo","cache"],"nested":{"old":1,"keep":2}},"keep":1}';
        $patch = '{"plugin":{"enabled":true,"legacyToken":null,"rules":["cache"],"nested":{"old":null,"new":3}}}';
        $json5Patch = "{plugin:{enabled:true,legacyToken:null,rules:['cache',],nested:{old:null,new:3,},},}";
        $jsonbTarget = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => false,
                'legacyToken' => 'secret',
                'rules' => ['seo', 'cache'],
                'nested' => ['old' => 1, 'keep' => 2],
            ],
            'keep' => 1,
        ]));

        $t->same(
            '{"plugin":{"enabled":true,"rules":["cache"],"nested":{"keep":2,"new":3}},"keep":1}',
            SQLiteJsonPatch::patch($json, $patch),
        );
        $t->same(
            '{"plugin":{"enabled":true,"rules":["cache"],"nested":{"keep":2,"new":3}},"keep":1}',
            SQLiteJsonPatch::patch($jsonbTarget, $json5Patch),
        );

        $textResult = SQLiteJsonPatch::patchSqlFunction('JSON_PATCH', $jsonbTarget, $patch);
        $t->same('{"plugin":{"enabled":true,"rules":["cache"],"nested":{"keep":2,"new":3}},"keep":1}', $textResult);

        $blobResult = SQLiteJsonPatch::patchSqlFunctionArguments('JSONB_PATCH', [$json, $json5Patch]);
        $t->true($blobResult instanceof SQLiteBlobValue);
        $t->same(
            ['plugin' => ['enabled' => true, 'rules' => ['cache'], 'nested' => ['keep' => 2, 'new' => 3]], 'keep' => 1],
            SQLiteJsonB::decode($blobResult->bytes),
        );
        $t->same(
            '{"plugin":{"enabled":true,"rules":["cache"],"nested":{"keep":2,"new":3}},"keep":1}',
            SQLiteJsonPatch::patchSqlFunctionArguments('json_patch', [$jsonbTarget, $patch]),
        );

        $t->same('["replacement"]', SQLiteJsonPatch::patch('{"plugin":1}', '["replacement"]'));
        $t->same('{"created":1}', SQLiteJsonPatch::patch('[1,2]', '{"created":1}'));
        $t->same(null, SQLiteJsonPatch::patch(null, $patch));
        $t->same(null, SQLiteJsonPatch::patch($json, null));
        $t->same(null, SQLiteJsonPatch::patchSqlFunction('jsonb_patch', null, $patch));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPatch::patchSqlFunction('json_remove', $json, $patch));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('json_patch', [$json]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPatch::patchSqlFunctionArguments('json_patch', [$json, $patch, '{}']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPatch::patch('{"plugin":,}', $patch));
    },
    'dispatches sqlite json insert set and replace sql functions with text or jsonb result typing' => static function (TestRunner $t): void {
        $json = '{"plugin":{"enabled":false,"rules":["seo"],"nested":{"old":1}},"keep":1}';
        $jsonbInput = new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'enabled' => false,
                'rules' => ['seo'],
                'nested' => ['old' => 1],
            ],
            'keep' => 1,
        ]));
        $jsonFragment = new SQLiteJsonSubtypeValue('{"source":"native","strict":true}');
        $jsonbFragment = new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'cache']));

        $textSet = SQLiteJsonMutation::mutateSqlFunction(
            'json_set',
            $jsonbInput,
            '$.plugin.enabled',
            true,
            '$.plugin.settings',
            $jsonFragment,
            '$.plugin.literal',
            '[1,2]',
        );
        $t->same(
            '{"plugin":{"enabled":true,"rules":["seo"],"nested":{"old":1},"settings":{"source":"native","strict":true},"literal":"[1,2]"},"keep":1}',
            $textSet,
        );

        $blobInsert = SQLiteJsonMutation::mutateSqlFunction(
            'jsonb_insert',
            $json,
            '$.plugin.rules[#]',
            $jsonbFragment,
            '$.plugin.enabled',
            true,
            '$.plugin.newFlag',
            1,
        );
        $t->true($blobInsert instanceof SQLiteBlobValue);
        $t->same(
            [
                'plugin' => [
                    'enabled' => false,
                    'rules' => ['seo', ['name' => 'cache']],
                    'nested' => ['old' => 1],
                    'newFlag' => 1,
                ],
                'keep' => 1,
            ],
            SQLiteJsonB::decode($blobInsert->bytes),
        );

        $textReplace = SQLiteJsonMutation::mutateSqlFunction(
            'json_replace',
            $json,
            '$.plugin.nested.old',
            null,
            '$.plugin.missing',
            'ignored',
        );
        $t->same('{"plugin":{"enabled":false,"rules":["seo"],"nested":{"old":null}},"keep":1}', $textReplace);

        $vectorSet = SQLiteJsonMutation::mutateSqlFunctionArguments('JSON_SET', [
            $jsonbInput,
            '$.plugin.enabled',
            true,
            '$.plugin.settings',
            $jsonFragment,
        ]);
        $t->same(
            '{"plugin":{"enabled":true,"rules":["seo"],"nested":{"old":1},"settings":{"source":"native","strict":true}},"keep":1}',
            $vectorSet,
        );

        $vectorInsert = SQLiteJsonMutation::mutateSqlFunctionArguments('JSONB_INSERT', [
            $json,
            '$.plugin.rules[#]',
            $jsonbFragment,
            '$.plugin.newFlag',
            1,
        ]);
        $t->true($vectorInsert instanceof SQLiteBlobValue);
        $t->same(
            [
                'plugin' => [
                    'enabled' => false,
                    'rules' => ['seo', ['name' => 'cache']],
                    'nested' => ['old' => 1],
                    'newFlag' => 1,
                ],
                'keep' => 1,
            ],
            SQLiteJsonB::decode($vectorInsert->bytes),
        );

        $vectorReplace = SQLiteJsonMutation::mutateSqlFunctionArguments('JSON_REPLACE', [
            $json,
            '$.plugin.nested.old',
            null,
        ]);
        $t->same('{"plugin":{"enabled":false,"rules":["seo"],"nested":{"old":null}},"keep":1}', $vectorReplace);
        $t->same(null, SQLiteJsonMutation::mutateSqlFunction('jsonb_set', null, '$.plugin.enabled', true));
        $t->same(null, SQLiteJsonMutation::mutateSqlFunctionArguments('JSONB_SET', [null, '$.plugin.enabled', true]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunction('json_remove', $json, '$.plugin', true));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_remove', [$json, '$.plugin', true]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json, '$.plugin']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json, '$.plugin', true, '$.x']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [$json, 1, true]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunctionArguments('json_set', [1, '$.plugin', true]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.plugin', true, '$.x'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', $json, '$.plugin.raw', new SQLiteBlobValue("not-jsonb")));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonMutation::mutateSqlFunction('json_set', '{"plugin":,}', '$.plugin.enabled', true));
    },
    'dispatches sqlite json array insert sql functions with text or jsonb result typing' => static function (TestRunner $t): void {
        $json = '{"queue":["scan","rewrite"],"plugin":{"rules":["seo"]}}';
        $jsonbInput = new SQLiteBlobValue(SQLiteJsonB::encode([
            'queue' => ['scan', 'rewrite'],
            'plugin' => ['rules' => ['seo']],
        ]));
        $jsonFragment = new SQLiteJsonSubtypeValue('{"task":"cache","priority":2}');
        $jsonbFragment = new SQLiteBlobValue(SQLiteJsonB::encode(['rule' => 'cdn']));

        $textInsert = SQLiteJsonArrayInsert::arrayInsertSqlFunction(
            'JSON_ARRAY_INSERT',
            $jsonbInput,
            '$.queue[1]',
            $jsonFragment,
            '$.plugin.rules[#]',
            'literal-rule',
        );
        $t->same(
            '{"queue":["scan",{"task":"cache","priority":2},"rewrite"],"plugin":{"rules":["seo","literal-rule"]}}',
            $textInsert,
        );
        $t->same(
            '{"queue":["scan",{"task":"cache","priority":2},"rewrite"],"plugin":{"rules":["seo","literal-rule"]}}',
            SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments(
                'JSON_ARRAY_INSERT',
                [$jsonbInput, '$.queue[1]', $jsonFragment, '$.plugin.rules[#]', 'literal-rule'],
            ),
        );

        $blobInsert = SQLiteJsonArrayInsert::arrayInsertSqlFunction(
            'JSONB_ARRAY_INSERT',
            $json,
            '$.plugin.rules[#-0]',
            $jsonbFragment,
            '$.missing[0]',
            7,
        );
        $t->true($blobInsert instanceof SQLiteBlobValue);
        $t->same(
            [
                'queue' => ['scan', 'rewrite'],
                'plugin' => ['rules' => ['seo', ['rule' => 'cdn']]],
                'missing' => [7],
            ],
            SQLiteJsonB::decode($blobInsert->bytes),
        );
        $argumentBlobInsert = SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments(
            'JSONB_ARRAY_INSERT',
            [$json, '$.plugin.rules[#-0]', $jsonbFragment, '$.missing[0]', 7],
        );
        $t->true($argumentBlobInsert instanceof SQLiteBlobValue);
        $t->same(
            [
                'queue' => ['scan', 'rewrite'],
                'plugin' => ['rules' => ['seo', ['rule' => 'cdn']]],
                'missing' => [7],
            ],
            SQLiteJsonB::decode($argumentBlobInsert->bytes),
        );

        $t->same(null, SQLiteJsonArrayInsert::arrayInsertSqlFunction('jsonb_array_insert', null, '$.queue[0]', 'scan'));
        $t->same(null, SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('JSONB_ARRAY_INSERT', [null, '$.queue[0]', 'scan']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_insert', $json, '$.queue[0]', 'scan'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_insert', [$json, '$.queue[0]', 'scan']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', [$json, '$.queue[0]']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunctionArguments('json_array_insert', [$json, 7, 'scan']));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.queue[0]', 'scan', '$.queue[1]'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.queue[0]', 'scan', 7, 'rewrite'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.plugin.raw', new SQLiteBlobValue("not-jsonb")));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', '{"queue":,}', '$.queue[0]', 'scan'));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonArrayInsert::arrayInsertSqlFunction('json_array_insert', $json, '$.plugin.rules', 'scan'));
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
        $allColumns = SQLiteCreateTable::automaticIndexColumnMetadata(
            'CREATE TABLE wp_options(option_id integer primary key, autoload text, option_name text, UNIQUE(autoload, option_name COLLATE nocase DESC))',
        );

        $t->same(['option_name', 'slug'], array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $columns));
        $t->same(['NOCASE', 'RTRIM'], array_map(static fn (SQLiteIndexColumn $column): string => $column->collation, $columns));
        $t->same([false, true], array_map(static fn (SQLiteIndexColumn $column): bool => $column->descending, $columns));
        $t->same([['autoload', 'option_name']], array_map(
            static fn (array $indexColumns): array => array_map(static fn (SQLiteIndexColumn $column): string => $column->columnName, $indexColumns),
            $allColumns,
        ));
        $t->same([['BINARY', 'NOCASE']], array_map(
            static fn (array $indexColumns): array => array_map(static fn (SQLiteIndexColumn $column): string => $column->collation, $indexColumns),
            $allColumns,
        ));
        $t->same([[false, true]], array_map(
            static fn (array $indexColumns): array => array_map(static fn (SQLiteIndexColumn $column): bool => $column->descending, $indexColumns),
            $allColumns,
        ));
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
    'reads wordpress overflow values from reusable pages with reserved bytes' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $recordPayload): void {
        $largeValue = str_repeat('X', 1600);
        $payload = $recordPayload([null, 'freelist_cache', $largeValue, 'yes']);
        $usableSize = 500;
        $localPayloadLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $usableSize);
        $cell = SQLiteTableLeafCell::encode(1, $payload, $usableSize, 5);
        $overflowPages = SQLiteOverflowPage::encodeChainAtPages(
            substr($payload, $localPayloadLength),
            [5, 3, 7],
            512,
            $usableSize,
        );

        $firstPage = $makeFirstPage(512, 7);
        $firstPage[20] = "\x0c";
        $page1 = SQLiteTableLeafPage::assemble([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $firstPage, $usableSize);
        $page2 = SQLiteTableLeafPage::assemble([$cell], 512, 0, null, $usableSize);
        $emptyPage = str_repeat("\0", 512);

        $database = SQLiteDatabase::fromBytes(
            $page1
            . $page2
            . $overflowPages[3]
            . $emptyPage
            . $overflowPages[5]
            . $emptyPage
            . $overflowPages[7],
        );
        $options = $database->wordpressOptions();

        $t->same(500, $database->usablePageSize());
        $t->same(3, count($overflowPages));
        $t->same(3, unpack('N', substr($overflowPages[5], 0, 4))[1]);
        $t->same(7, unpack('N', substr($overflowPages[3], 0, 4))[1]);
        $t->same(0, unpack('N', substr($overflowPages[7], 0, 4))[1]);
        $t->same(str_repeat("\0", 12), substr($overflowPages[5], 500, 12));
        $t->same(1, count($options));
        $t->same('freelist_cache', $options[0]->optionName);
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
    'plans wordpress overflow reuse from sqlite freelist trunk metadata' => static function (TestRunner $t) use ($makeFirstPage, $schemaCell, $recordPayload): void {
        $usableSize = 500;
        $preFirstPage = $makeFirstPage(512, 7);
        $preFirstPage[20] = "\x0c";
        $preFirstPage = substr_replace($preFirstPage, pack('N', 5), 32, 4);
        $preFirstPage = substr_replace($preFirstPage, pack('N', 4), 36, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        ], 512, 100, $preFirstPage, $usableSize);
        $emptyPage = str_repeat("\0", 512);
        $preDatabase = SQLiteDatabase::fromBytes(
            $schemaPage
            . $emptyPage
            . $emptyPage
            . $emptyPage
            . SQLiteFreelistTrunkPage::assemble(null, [3, 7, 4], 512, $usableSize)
            . $emptyPage
            . $emptyPage,
        );

        $largeValue = str_repeat('reused-page:', 80);
        $payload = $recordPayload([null, 'planned_freelist_cache', $largeValue, 'yes']);
        $localPayloadLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $usableSize);
        $overflowPayload = substr($payload, $localPayloadLength);
        $requiredOverflowPages = SQLiteOverflowPage::requiredPageCount(strlen($overflowPayload), 512, $usableSize);
        $allocationPlan = $preDatabase->planPageAllocation($requiredOverflowPages, false);
        $reusablePages = $allocationPlan->allocatedPageNumbers;
        $cell = SQLiteTableLeafCell::encode(1, $payload, $usableSize, $reusablePages[0]);
        $overflowPages = SQLiteOverflowPage::encodeChainAtPages($overflowPayload, $reusablePages, 512, $usableSize);

        $postTablePage = SQLiteTableLeafPage::assemble([$cell], 512, 0, null, $usableSize);
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= 7; $pageNumber++) {
            $postPages[$pageNumber] = $emptyPage;
        }
        $postPages[1] = $allocationPlan->firstPage;
        $postPages[2] = $postTablePage;
        foreach ($allocationPlan->updatedFreelistPages as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        foreach ($overflowPages as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();

        $t->same([
            'allocated_page_numbers' => [3, 4],
            'appended_page_numbers' => [],
            'database_page_count' => 7,
            'first_freelist_trunk_page' => 5,
            'freelist_page_count' => 2,
            'updated_freelist_page_numbers' => [5],
        ], $allocationPlan->toArray());
        $t->same(2, $requiredOverflowPages);
        $t->same([3, 4], $reusablePages);
        $t->same(4, unpack('N', substr($overflowPages[3], 0, 4))[1]);
        $t->same(0, unpack('N', substr($overflowPages[4], 0, 4))[1]);
        $t->same([7, 5], $postDatabase->freelistAllocationOrder());
        $t->same(1, count($options));
        $t->same('planned_freelist_cache', $options[0]->optionName);
        $t->same($largeValue, $options[0]->optionValue);
    },
    'plans sqlite freePage2 leaf insertion into non-full freelist trunks' => static function (TestRunner $t) use ($makeFirstPage): void {
        $emptyPage = str_repeat("\0", 512);
        $firstPage = $makeFirstPage(512, 6);
        $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
        $database = SQLiteDatabase::fromBytes(
            $firstPage
            . $emptyPage
            . $emptyPage
            . $emptyPage
            . SQLiteFreelistTrunkPage::assemble(null, [3])
            . $emptyPage,
        );

        $plan = $database->planPageFree(4);
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= 6; $pageNumber++) {
            $postPages[$pageNumber] = $emptyPage;
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

        $t->same([
            'freed_page_numbers' => [4],
            'leaf_page_numbers' => [4],
            'new_trunk_page_numbers' => [],
            'database_page_count' => 6,
            'first_freelist_trunk_page' => 5,
            'freelist_page_count' => 3,
            'updated_freelist_page_numbers' => [5],
        ], $plan->toArray());
        $t->same([5, 3, 4], $postDatabase->freelistPageNumbers());
        $t->same([3, 4, 5], $postDatabase->freelistAllocationOrder());
    },
    'plans sqlite secure-delete freePage2 leaf clearing in non-full freelist trunks' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $oldLeafPage = str_repeat('X', $pageSize);
        $firstPage = $makeFirstPage($pageSize, 6);
        $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
        $database = SQLiteDatabase::fromBytes(
            $firstPage
            . $emptyPage
            . $emptyPage
            . $oldLeafPage
            . SQLiteFreelistTrunkPage::assemble(null, [3], $pageSize)
            . $emptyPage,
        );

        $nonSecurePlan = $database->planPageFree(4);
        $securePlan = $database->planPageFree(4, true);
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
            4 => $database->page(4),
            5 => $database->page(5),
            6 => $database->page(6),
        ];
        foreach ($securePlan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

        $t->same([1, 5], array_keys($nonSecurePlan->pageImages()));
        $t->same([1, 4, 5], array_keys($securePlan->pageImages()));
        $t->same([
            'freed_page_numbers' => [4],
            'leaf_page_numbers' => [4],
            'new_trunk_page_numbers' => [],
            'database_page_count' => 6,
            'first_freelist_trunk_page' => 5,
            'freelist_page_count' => 3,
            'updated_freelist_page_numbers' => [5],
            'cleared_page_numbers' => [4],
        ], $securePlan->toArray());
        $t->same(str_repeat("\0", $pageSize), $securePlan->clearedPageImages[4]);
        $t->same(str_repeat("\0", $pageSize), $postDatabase->page(4));
        $t->same([5, 3, 4], $postDatabase->freelistPageNumbers());
        $t->same([3, 4, 5], $postDatabase->freelistAllocationOrder());
    },
    'plans sqlite freePage2 new trunk pages for empty freelists' => static function (TestRunner $t) use ($makeFirstPage): void {
        $emptyPage = str_repeat("\0", 512);
        $database = SQLiteDatabase::fromBytes($makeFirstPage(512, 4) . $emptyPage . $emptyPage . $emptyPage);

        $plan = $database->planPageFree(4);
        $postPages = [1 => $emptyPage, 2 => $emptyPage, 3 => $emptyPage, 4 => $emptyPage];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

        $t->same([
            'freed_page_numbers' => [4],
            'leaf_page_numbers' => [],
            'new_trunk_page_numbers' => [4],
            'database_page_count' => 4,
            'first_freelist_trunk_page' => 4,
            'freelist_page_count' => 1,
            'updated_freelist_page_numbers' => [4],
        ], $plan->toArray());
        $t->same(0, unpack('N', substr($plan->updatedFreelistPages[4], 0, 4))[1]);
        $t->same(0, unpack('N', substr($plan->updatedFreelistPages[4], 4, 4))[1]);
        $t->same([4], $postDatabase->freelistPageNumbers());
        $t->same([4], $postDatabase->freelistAllocationOrder());
    },
    'plans sqlite freePage2 new trunk pages when the first trunk is compatibility-full' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $usableSize = 500;
        $emptyPage = str_repeat("\0", $pageSize);
        $leafCount = intdiv($usableSize, 4) - 8;
        $leafPages = range(3, 2 + $leafCount);
        $firstPage = $makeFirstPage($pageSize, 130);
        $firstPage[20] = "\x0c";
        $firstPage = substr_replace($firstPage, pack('N', 2), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 1 + $leafCount), 36, 4);
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= 130; $pageNumber++) {
            $pages[$pageNumber] = $emptyPage;
        }
        $pages[1] = $firstPage;
        $pages[2] = SQLiteFreelistTrunkPage::assemble(null, $leafPages, $pageSize, $usableSize);
        $database = SQLiteDatabase::fromBytes(implode('', $pages));

        $plan = $database->planPageFree(120);
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $pages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

        $t->same([
            'freed_page_numbers' => [120],
            'leaf_page_numbers' => [],
            'new_trunk_page_numbers' => [120],
            'database_page_count' => 130,
            'first_freelist_trunk_page' => 120,
            'freelist_page_count' => 119,
            'updated_freelist_page_numbers' => [120],
        ], $plan->toArray());
        $t->same(2, unpack('N', substr($plan->updatedFreelistPages[120], 0, 4))[1]);
        $t->same(0, unpack('N', substr($plan->updatedFreelistPages[120], 4, 4))[1]);
        $t->same([120, 2, 3, 4], array_slice($postDatabase->freelistPageNumbers(), 0, 4));
        $t->same([120, 3], $postDatabase->freelistAllocationOrder(2));
    },
    'plans wordpress wp_options leaf insert page images with appended overflow pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 2));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);

        $largeValue = str_repeat('rewrite-cache-fragment:', 58) . 'done';
        $plan = $database->planWordPressOptionInsert(3, 'generated_large_cache', $largeValue, 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
        ];
        for ($pageNumber = 3; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $emptyPage;
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();

        $t->same(SQLiteWordPressOptionWritePlan::class, get_class($plan));
        $t->same(range(3, 2 + count($plan->overflowPageNumbers)), $plan->overflowPageNumbers);
        $t->same(array_merge([1, 2], $plan->overflowPageNumbers), array_keys($plan->pageImages()));
        $t->same(2 + count($plan->overflowPageNumbers), $plan->databasePageCount);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 3,
            'option_name' => 'generated_large_cache',
            'autoload' => 'no',
            'overflow_page_numbers' => $plan->overflowPageNumbers,
            'local_payload_length' => SQLiteTableLeafCell::localPayloadLength(strlen(SQLiteRecord::encode([null, 'generated_large_cache', $largeValue, 'no'])), 512),
            'database_page_count' => $plan->databasePageCount,
            'updated_page_numbers' => array_merge([1, 2], $plan->overflowPageNumbers),
        ], $plan->toArray());
        $t->same(2, $postDatabase->pageHeader(2)->cellCount);
        $t->same(['siteurl', 'generated_large_cache'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same($largeValue, $options[1]->optionValue);
        $t->same('no', $options[1]->autoload);
        $t->same(3, $options[1]->rowId);
    },
    'plans auto-vacuum pointer-map entries for inserted wordpress overflow option chains' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $firstPage = $makeFirstPage($pageSize, 3);
        $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                3,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $firstPage);
        $pointerMapPage = substr_replace(
            str_repeat("\0", $pageSize),
            chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0),
            0,
            5,
        );
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $pointerMapPage . $tablePage);

        $largeValue = str_repeat('autoloaded-cache-fragment:', 64) . 'done';
        $plan = $database->planWordPressOptionInsert(2, 'theme_mods_twentyfive', $largeValue, 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : $emptyPage;
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $overflowPages = $plan->overflowPageNumbers;

        $t->true(count($overflowPages) > 1);
        $t->same(array_merge([1, 2, 3], $overflowPages), array_keys($plan->pageImages()));
        $t->same('root-page', $postDatabase->pointerMapEntryForPage(3)->typeName());
        $t->same('first-overflow-page', $postDatabase->pointerMapEntryForPage($overflowPages[0])->typeName());
        $t->same(3, $postDatabase->pointerMapEntryForPage($overflowPages[0])->parentPageNumber);
        for ($index = 1; $index < count($overflowPages); $index++) {
            $entry = $postDatabase->pointerMapEntryForPage($overflowPages[$index]);
            $t->same('overflow-page', $entry->typeName());
            $t->same($overflowPages[$index - 1], $entry->parentPageNumber);
        }

        $option = $postDatabase->tableRowByRowIdByName('wp_options', 2);
        $t->true($option !== null);
        $t->same([null, 'theme_mods_twentyfive', $largeValue, 'yes'], $option?->values());
    },
    'plans wordpress wp_options leaf insert in a utf16le encoded database image' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $textEncoding = 2;
        $firstPage = substr_replace($makeFirstPage($pageSize, 2), pack('N', $textEncoding), 56, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ], $textEncoding)),
        ], $pageSize, 100, $firstPage);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'], $textEncoding)),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);
        $optionValue = 'Ported ' . "\u{1234}" . ' option';

        $plan = $database->planWordPressOptionInsert(2, 'blogdescription', $optionValue, 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();
        $newRow = $postDatabase->tableRowByRowIdByName('wp_options', 2);

        $t->same($textEncoding, $database->header->textEncoding);
        $t->same([2], array_keys($plan->pageImages()));
        $t->same(['siteurl', 'blogdescription'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same($optionValue, $options[1]->optionValue);
        $t->same([null, 'blogdescription', $optionValue, 'yes'], $newRow?->values());
        $t->contains(hex2bin('50006f0072007400650064002000341220006f007000740069006f006e00'), $plan->pageImages()[2]);
    },
    'plans wordpress wp_options leaf insert using reusable freelist overflow pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $firstPage = $makeFirstPage($pageSize, 5);
        $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
        $firstPage = substr_replace($firstPage, pack('N', 3), 36, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $firstPage);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $emptyPage
            . $emptyPage
            . SQLiteFreelistTrunkPage::assemble(null, [3, 4]),
        );

        $largeValue = str_repeat('freelist-backed-cache:', 46) . 'done';
        $plan = $database->planWordPressOptionInsert(2, 'generated_reused_cache', $largeValue, 'yes', false);
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->tableRowByRowIdByName('wp_options', 2);

        $t->same([3, 4], $plan->overflowPageNumbers);
        $t->same([1, 2, 3, 4, 5], array_keys($plan->pageImages()));
        $t->same(5, $plan->databasePageCount);
        $t->same(1, $postDatabase->header->freelistPageCount);
        $t->same([5], $postDatabase->freelistPageNumbers());
        $t->true($option !== null);
        $t->same([null, 'generated_reused_cache', $largeValue, 'yes'], $option?->values());
    },
    'plans wordpress wp_options leaf insert while maintaining an option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionInsert(2, 'home', 'https://example.test/blog', 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedName('home');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3], array_keys($plan->pageImages()));
        $t->same([
            ['home', 2],
            ['siteurl', 1],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(2, $option->rowId);
        $t->same('https://example.test/blog', $option->optionValue);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 2,
            'option_name' => 'home',
            'autoload' => 'yes',
            'overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
            'database_page_count' => 3,
            'updated_page_numbers' => [2, 3],
        ], $plan->toArray());
    },
    'plans wordpress wp_options leaf insert while maintaining an automatic unique option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text UNIQUE, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'sqlite_autoindex_wp_options_1',
                'wp_options',
                3,
                null,
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionInsert(2, 'home', 'https://example.test/blog', 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedName('home');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3], array_keys($plan->pageImages()));
        $t->same([
            ['home', 2],
            ['siteurl', 1],
        ], $indexRecords);
        $t->same(3, $postDatabase->indexRootPageForColumn('wp_options', 'option_name'));
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(2, $option->rowId);
        $t->same('https://example.test/blog', $option->optionValue);
    },
    'plans wordpress wp_options leaf insert while maintaining a safe partial option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name_not_null',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name_not_null ON wp_options(option_name) WHERE option_name IS NOT NULL',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionInsert(2, 'home', 'https://example.test/blog', 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedName('home');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3], array_keys($plan->pageImages()));
        $t->same([
            ['home', 2],
            ['siteurl', 1],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same('home', $option->optionName);
        $t->same('https://example.test/blog', $option->optionValue);
    },
    'plans wordpress wp_options leaf insert while maintaining a composite autoload option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name COLLATE NOCASE DESC)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 2])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionInsert(3, 'home', 'https://example.test/blog', 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('yes', 'HOME');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3], array_keys($plan->pageImages()));
        $t->same(3, $postDatabase->indexRootPageForPointLookupColumns('wp_options', [
            'autoload' => 'yes',
            'option_name' => 'HOME',
        ]));
        $t->same([
            ['no', 'cron_lock', 2],
            ['yes', 'siteurl', 1],
            ['yes', 'home', 3],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->rowId);
        $t->same('home', $option->optionName);
        $t->same('https://example.test/blog', $option->optionValue);
    },
    'plans wordpress wp_options leaf insert while maintaining a multi-page option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'blogname', 'Example Site', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 2]), $pageSize, null, 4),
        ], 5, $pageSize);
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['blogname', 1])),
        ], $pageSize);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 3])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $leftIndexLeafPage
            . $rightIndexLeafPage,
        );

        $plan = $database->planWordPressOptionInsert(4, 'stylesheet', 'twentytwentyfive', 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
            4 => $database->page(4),
            5 => $database->page(5),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedName('stylesheet');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 5], array_keys($plan->pageImages()));
        $t->same([
            ['blogname', 1],
            ['home', 2],
            ['siteurl', 3],
            ['stylesheet', 4],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(4, $option->rowId);
        $t->same('twentytwentyfive', $option->optionValue);
    },
    'plans wordpress indexed insert by splitting a same-depth multi-page index leaf' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('m', 70), 9]), $pageSize, null, 4),
        ], 5, $pageSize);
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['blogname', 8])),
        ], $pageSize);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('n', 70), 10])),
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('o', 70), 11])),
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('p', 70), 12])),
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('q', 70), 13])),
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('r', 70), 14])),
            SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat('s', 70), 15])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $leftIndexLeafPage
            . $rightIndexLeafPage,
        );

        $insertedName = str_repeat('z', 70);
        $plan = $database->planWordPressOptionInsert(2, $insertedName, 'value', 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $insertedOption = $postDatabase->wordpressOptionByIndexedName($insertedName);

        $t->same([1, 2, 3, 5, 6], array_keys($plan->pageImages()));
        $t->same(6, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(2, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(5)->cellCount);
        $t->same(3, $postDatabase->pageHeader(6)->cellCount);
        $t->same([
            ['blogname', 8],
            [str_repeat('m', 70), 9],
            [str_repeat('n', 70), 10],
            [str_repeat('o', 70), 11],
            [str_repeat('p', 70), 12],
            [str_repeat('q', 70), 13],
            [str_repeat('r', 70), 14],
            [str_repeat('s', 70), 15],
            [$insertedName, 2],
        ], $indexRecords);
        $t->true($insertedOption instanceof SQLiteWordPressOption);
        $t->same(2, $insertedOption->rowId);
        $t->same($insertedName, $insertedOption->optionName);
        $t->same('value', $insertedOption->optionValue);
    },
    'plans wordpress indexed insert by growing a full option_name index root leaf' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexEntries = [];
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $index => $prefix) {
            $indexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 10 + $index]));
        }
        $indexRootPage = SQLiteIndexLeafPage::assemble($indexEntries, $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexRootPage);

        $insertedName = str_repeat('z', 70);
        $plan = $database->planWordPressOptionInsert(2, $insertedName, 'root-grown-value', 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $insertedOption = $postDatabase->wordpressOptionByIndexedName($insertedName);

        $t->same([1, 2, 3, 4, 5], array_keys($plan->pageImages()));
        $t->same(5, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(4)->cellCount);
        $t->same(3, $postDatabase->pageHeader(5)->cellCount);
        $t->same([
            [str_repeat('a', 70), 10],
            [str_repeat('b', 70), 11],
            [str_repeat('c', 70), 12],
            [str_repeat('d', 70), 13],
            [str_repeat('e', 70), 14],
            [str_repeat('f', 70), 15],
            [$insertedName, 2],
        ], $indexRecords);
        $t->true($insertedOption instanceof SQLiteWordPressOption);
        $t->same(2, $insertedOption->rowId);
        $t->same($insertedName, $insertedOption->optionName);
        $t->same('root-grown-value', $insertedOption->optionValue);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionInsert(2, $insertedName, 'root-grown-value', 'yes', false),
        );
    },
    'plans wordpress indexed insert by splitting a leaf and growing a full index root interior' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 10));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);

        $indexRootCells = [];
        foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
            $indexRootCells[] = SQLiteIndexCell::encode(
                SQLiteRecord::encode([str_repeat($prefix, 70), 100 + $index]),
                $pageSize,
                null,
                4 + $index,
            );
        }
        $indexRootPage = SQLiteIndexInteriorPage::assemble($indexRootCells, 10, $pageSize);

        $leafPages = [];
        foreach (['a', 'h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
            $leafPages[] = SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 50 + $index])),
            ], $pageSize);
        }
        $rightLeafCells = [];
        foreach (['r', 's', 't', 'u', 'v', 'w'] as $index => $prefix) {
            $rightLeafCells[] = SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 200 + $index]));
        }
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . implode('', $leafPages)
            . SQLiteIndexLeafPage::assemble($rightLeafCells, $pageSize),
        );

        $insertedName = str_repeat('z', 70);
        $plan = $database->planWordPressOptionInsert(2, $insertedName, 'parent-grown-value', 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $insertedOption = $postDatabase->wordpressOptionByIndexedName($insertedName);

        $t->same([1, 2, 3, 10, 11, 12, 13], array_keys($plan->pageImages()));
        $t->same(13, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(12)->cellCount);
        $t->same(3, $postDatabase->pageHeader(13)->cellCount);
        $t->same(3, $postDatabase->pageHeader(10)->cellCount);
        $t->same(3, $postDatabase->pageHeader(11)->cellCount);
        $t->same(7, $postDatabase->pageHeader(12)->rightMostPointer);
        $t->same(11, $postDatabase->pageHeader(13)->rightMostPointer);
        $t->same([
            [str_repeat('a', 70), 50],
            [str_repeat('g', 70), 100],
            [str_repeat('h', 70), 51],
            [str_repeat('i', 70), 101],
            [str_repeat('j', 70), 52],
            [str_repeat('k', 70), 102],
            [str_repeat('l', 70), 53],
            [str_repeat('m', 70), 103],
            [str_repeat('n', 70), 54],
            [str_repeat('o', 70), 104],
            [str_repeat('p', 70), 55],
            [str_repeat('q', 70), 105],
            [str_repeat('r', 70), 200],
            [str_repeat('s', 70), 201],
            [str_repeat('t', 70), 202],
            [str_repeat('u', 70), 203],
            [str_repeat('v', 70), 204],
            [str_repeat('w', 70), 205],
            [$insertedName, 2],
        ], $indexRecords);
        $t->true($insertedOption instanceof SQLiteWordPressOption);
        $t->same(2, $insertedOption->rowId);
        $t->same($insertedName, $insertedOption->optionName);
        $t->same('parent-grown-value', $insertedOption->optionValue);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionInsert(2, $insertedName, 'parent-grown-value', 'yes', false),
        );
    },
    'plans wordpress automatic indexed insert by splitting a leaf and growing a full index root interior' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text UNIQUE, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'sqlite_autoindex_wp_options_1',
                'wp_options',
                3,
                null,
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 10));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);

        $indexRootCells = [];
        foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
            $indexRootCells[] = SQLiteIndexCell::encode(
                SQLiteRecord::encode([str_repeat($prefix, 70), 100 + $index]),
                $pageSize,
                null,
                4 + $index,
            );
        }
        $indexRootPage = SQLiteIndexInteriorPage::assemble($indexRootCells, 10, $pageSize);

        $leafPages = [];
        foreach (['a', 'h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
            $leafPages[] = SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 50 + $index])),
            ], $pageSize);
        }
        $rightLeafCells = [];
        foreach (['r', 's', 't', 'u', 'v', 'w'] as $index => $prefix) {
            $rightLeafCells[] = SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 200 + $index]));
        }
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . implode('', $leafPages)
            . SQLiteIndexLeafPage::assemble($rightLeafCells, $pageSize),
        );

        $insertedName = str_repeat('z', 70);
        $plan = $database->planWordPressOptionInsert(2, $insertedName, 'auto-parent-grown-value', 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $insertedOption = $postDatabase->wordpressOptionByIndexedName($insertedName);

        $t->same([1, 2, 3, 10, 11, 12, 13], array_keys($plan->pageImages()));
        $t->same(13, $plan->databasePageCount);
        $t->same(3, $postDatabase->indexRootPageForColumn('wp_options', 'option_name'));
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(12)->cellCount);
        $t->same(3, $postDatabase->pageHeader(13)->cellCount);
        $t->same(3, $postDatabase->pageHeader(10)->cellCount);
        $t->same(3, $postDatabase->pageHeader(11)->cellCount);
        $t->same(7, $postDatabase->pageHeader(12)->rightMostPointer);
        $t->same(11, $postDatabase->pageHeader(13)->rightMostPointer);
        $t->same([
            [str_repeat('a', 70), 50],
            [str_repeat('g', 70), 100],
            [str_repeat('h', 70), 51],
            [str_repeat('i', 70), 101],
            [str_repeat('j', 70), 52],
            [str_repeat('k', 70), 102],
            [str_repeat('l', 70), 53],
            [str_repeat('m', 70), 103],
            [str_repeat('n', 70), 54],
            [str_repeat('o', 70), 104],
            [str_repeat('p', 70), 55],
            [str_repeat('q', 70), 105],
            [str_repeat('r', 70), 200],
            [str_repeat('s', 70), 201],
            [str_repeat('t', 70), 202],
            [str_repeat('u', 70), 203],
            [str_repeat('v', 70), 204],
            [str_repeat('w', 70), 205],
            [$insertedName, 2],
        ], $indexRecords);
        $t->true($insertedOption instanceof SQLiteWordPressOption);
        $t->same(2, $insertedOption->rowId);
        $t->same($insertedName, $insertedOption->optionName);
        $t->same('auto-parent-grown-value', $insertedOption->optionValue);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionInsert(2, $insertedName, 'auto-parent-grown-value', 'yes', false),
        );
    },
    'plans wordpress composite indexed insert by splitting a leaf and growing a full index root interior' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 10));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);

        $indexRootCells = [];
        foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
            $indexRootCells[] = SQLiteIndexCell::encode(
                SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 100 + $index]),
                $pageSize,
                null,
                4 + $index,
            );
        }
        $indexRootPage = SQLiteIndexInteriorPage::assemble($indexRootCells, 10, $pageSize);

        $leafPages = [];
        foreach (['a', 'h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
            $leafPages[] = SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 50 + $index])),
            ], $pageSize);
        }
        $rightLeafCells = [];
        foreach (['r', 's', 't', 'u', 'v', 'w'] as $index => $prefix) {
            $rightLeafCells[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 200 + $index]));
        }
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . implode('', $leafPages)
            . SQLiteIndexLeafPage::assemble($rightLeafCells, $pageSize),
        );

        $insertedName = str_repeat('z', $nameLength);
        $plan = $database->planWordPressOptionInsert(2, $insertedName, 'composite-parent-grown-value', 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $insertedOption = $postDatabase->wordpressOptionByIndexedAutoloadAndName('yes', $insertedName);

        $t->same([1, 2, 3, 10, 11, 12, 13], array_keys($plan->pageImages()));
        $t->same(13, $plan->databasePageCount);
        $t->same(3, $postDatabase->indexRootPageForPointLookupColumns('wp_options', [
            'autoload' => 'yes',
            'option_name' => $insertedName,
        ]));
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(12)->cellCount);
        $t->same(3, $postDatabase->pageHeader(13)->cellCount);
        $t->same(3, $postDatabase->pageHeader(10)->cellCount);
        $t->same(3, $postDatabase->pageHeader(11)->cellCount);
        $t->same(7, $postDatabase->pageHeader(12)->rightMostPointer);
        $t->same(11, $postDatabase->pageHeader(13)->rightMostPointer);
        $t->same([
            ['yes', str_repeat('a', $nameLength), 50],
            ['yes', str_repeat('g', $nameLength), 100],
            ['yes', str_repeat('h', $nameLength), 51],
            ['yes', str_repeat('i', $nameLength), 101],
            ['yes', str_repeat('j', $nameLength), 52],
            ['yes', str_repeat('k', $nameLength), 102],
            ['yes', str_repeat('l', $nameLength), 53],
            ['yes', str_repeat('m', $nameLength), 103],
            ['yes', str_repeat('n', $nameLength), 54],
            ['yes', str_repeat('o', $nameLength), 104],
            ['yes', str_repeat('p', $nameLength), 55],
            ['yes', str_repeat('q', $nameLength), 105],
            ['yes', str_repeat('r', $nameLength), 200],
            ['yes', str_repeat('s', $nameLength), 201],
            ['yes', str_repeat('t', $nameLength), 202],
            ['yes', str_repeat('u', $nameLength), 203],
            ['yes', str_repeat('v', $nameLength), 204],
            ['yes', str_repeat('w', $nameLength), 205],
            ['yes', $insertedName, 2],
        ], $indexRecords);
        $t->true($insertedOption instanceof SQLiteWordPressOption);
        $t->same(2, $insertedOption->rowId);
        $t->same($insertedName, $insertedOption->optionName);
        $t->same('composite-parent-grown-value', $insertedOption->optionValue);
        $t->same('yes', $insertedOption->autoload);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionInsert(2, $insertedName, 'composite-parent-grown-value', 'yes', false),
        );
    },
    'plans wordpress indexed insert by splitting an overflowing non-root index parent' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 12));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);

        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(
                SQLiteRecord::encode(['yes', str_repeat('{', $nameLength), 900]),
                $pageSize,
                null,
                4,
            ),
        ], 12, $pageSize);

        $lowerParentCells = [];
        foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
            $lowerParentCells[] = SQLiteIndexCell::encode(
                SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 100 + $index]),
                $pageSize,
                null,
                5 + $index,
            );
        }
        $lowerParentPage = SQLiteIndexInteriorPage::assemble($lowerParentCells, 11, $pageSize);

        $leafPages = [];
        foreach (['a', 'h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
            $leafPages[] = SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 50 + $index])),
            ], $pageSize);
        }
        $targetLeafEntries = [];
        foreach (['r', 's', 't', 'u', 'v', 'w'] as $index => $prefix) {
            $targetLeafEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 200 + $index]));
        }
        $rightRootLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('~', $nameLength), 901])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $lowerParentPage
            . implode('', $leafPages)
            . SQLiteIndexLeafPage::assemble($targetLeafEntries, $pageSize)
            . $rightRootLeafPage,
        );

        $insertedName = str_repeat('z', $nameLength);
        $plan = $database->planWordPressOptionInsert(2, $insertedName, 'nonroot-parent-grown-value', 'yes');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $insertedOption = $postDatabase->wordpressOptionByIndexedAutoloadAndName('yes', $insertedName);

        $t->same([1, 2, 3, 4, 11, 13, 14], array_keys($plan->pageImages()));
        $t->same(14, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(2, $postDatabase->pageHeader(3)->cellCount);
        $t->same(12, $postDatabase->pageHeader(3)->rightMostPointer);
        $t->same('index-interior', $postDatabase->pageHeader(4)->pageType);
        $t->same(3, $postDatabase->pageHeader(4)->cellCount);
        $t->same(8, $postDatabase->pageHeader(4)->rightMostPointer);
        $t->same('index-interior', $postDatabase->pageHeader(14)->pageType);
        $t->same(3, $postDatabase->pageHeader(14)->cellCount);
        $t->same(13, $postDatabase->pageHeader(14)->rightMostPointer);
        $t->same(3, $postDatabase->pageHeader(11)->cellCount);
        $t->same(3, $postDatabase->pageHeader(13)->cellCount);
        $t->same([
            ['yes', str_repeat('a', $nameLength), 50],
            ['yes', str_repeat('g', $nameLength), 100],
            ['yes', str_repeat('h', $nameLength), 51],
            ['yes', str_repeat('i', $nameLength), 101],
            ['yes', str_repeat('j', $nameLength), 52],
            ['yes', str_repeat('k', $nameLength), 102],
            ['yes', str_repeat('l', $nameLength), 53],
            ['yes', str_repeat('m', $nameLength), 103],
            ['yes', str_repeat('n', $nameLength), 54],
            ['yes', str_repeat('o', $nameLength), 104],
            ['yes', str_repeat('p', $nameLength), 55],
            ['yes', str_repeat('q', $nameLength), 105],
            ['yes', str_repeat('r', $nameLength), 200],
            ['yes', str_repeat('s', $nameLength), 201],
            ['yes', str_repeat('t', $nameLength), 202],
            ['yes', str_repeat('u', $nameLength), 203],
            ['yes', str_repeat('v', $nameLength), 204],
            ['yes', str_repeat('w', $nameLength), 205],
            ['yes', $insertedName, 2],
            ['yes', str_repeat('{', $nameLength), 900],
            ['yes', str_repeat('~', $nameLength), 901],
        ], $indexRecords);
        $t->true($insertedOption instanceof SQLiteWordPressOption);
        $t->same(2, $insertedOption->rowId);
        $t->same($insertedName, $insertedOption->optionName);
        $t->same('nonroot-parent-grown-value', $insertedOption->optionValue);
        $t->same('yes', $insertedOption->autoload);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionInsert(2, $insertedName, 'nonroot-parent-grown-value', 'yes', false),
        );
    },
    'plans wordpress wp_options replacement while freeing obsolete overflow pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 4));

        $largeValue = str_repeat('obsolete-cache-fragment:', 56) . 'done';
        $largePayload = SQLiteRecord::encode([null, 'obsolete_large_cache', $largeValue, 'yes']);
        $largeLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($largePayload), $pageSize);
        $largeOverflowPayload = substr($largePayload, $largeLocalLength);
        $largeOverflowPages = SQLiteOverflowPage::encodeChainAtPages($largeOverflowPayload, [3, 4], $pageSize);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, $largePayload, $pageSize, 3),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $largeOverflowPages[3]
            . $largeOverflowPages[4],
        );

        $plan = $database->planWordPressOptionReplace('obsolete_large_cache', 'small-cache-value', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();

        $t->same(SQLiteWordPressOptionReplacementPlan::class, get_class($plan));
        $t->same([
            'table_root_page' => 2,
            'rowid' => 2,
            'option_name' => 'obsolete_large_cache',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [3, 4],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'obsolete_large_cache', 'small-cache-value', 'no'])),
            'database_page_count' => 4,
            'updated_page_numbers' => [1, 2, 3],
        ], $plan->toArray());
        $t->same([3, 4], $plan->obsoleteOverflowPageNumbers);
        $t->same([3, 4], $postDatabase->freelistPageNumbers());
        $t->same([4, 3], $postDatabase->freelistAllocationOrder());
        $t->same(['siteurl', 'obsolete_large_cache'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same('small-cache-value', $options[1]->optionValue);
        $t->same('no', $options[1]->autoload);
        $t->same(2, $options[1]->rowId);
    },
    'plans wordpress secure-delete replacement while clearing obsolete overflow leaves' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 4));

        $largeValue = str_repeat('obsolete-private-cache:', 58) . 'done';
        $largePayload = SQLiteRecord::encode([null, 'obsolete_large_cache', $largeValue, 'yes']);
        $largeLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($largePayload), $pageSize);
        $largeOverflowPayload = substr($largePayload, $largeLocalLength);
        $largeOverflowPages = SQLiteOverflowPage::encodeChainAtPages($largeOverflowPayload, [3, 4], $pageSize);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, $largePayload, $pageSize, 3),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $largeOverflowPages[3]
            . $largeOverflowPages[4],
        );

        $plan = $database->planWordPressOptionReplace('obsolete_large_cache', 'small-cache-value', 'no', true, true);
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();

        $t->same([1, 2, 3, 4], array_keys($plan->pageImages()));
        $t->same([3, 4], $postDatabase->freelistPageNumbers());
        $t->same(str_repeat("\0", $pageSize), $postDatabase->page(4));
        $t->same(['siteurl', 'obsolete_large_cache'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same('small-cache-value', $options[1]->optionValue);
        $t->same('no', $options[1]->autoload);
        $t->same(2, $options[1]->rowId);
    },
    'plans wordpress wp_options replacement while preserving an unchanged option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'SiteURL', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['SiteURL', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionReplace('SiteURL', 'https://fixed.example', 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedName('siteurl');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2], array_keys($plan->pageImages()));
        $t->same([['SiteURL', 1]], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same('SiteURL', $option->optionName);
        $t->same('https://fixed.example', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 1,
            'option_name' => 'SiteURL',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'SiteURL', 'https://fixed.example', 'no'])),
            'database_page_count' => 3,
            'updated_page_numbers' => [2],
        ], $plan->toArray());
    },
    'plans wordpress wp_options replacement while preserving a safe partial option_name index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_name_not_null',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_name_not_null ON wp_options(option_name) WHERE option_name IS NOT NULL',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionReplace('siteurl', 'https://fixed.example', 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedName('siteurl');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2], array_keys($plan->pageImages()));
        $t->same([['siteurl', 1]], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same('https://fixed.example', $option->optionValue);
        $t->same('no', $option->autoload);
    },
    'plans wordpress wp_options replacement while moving a composite autoload option_name index entry' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name COLLATE NOCASE DESC)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 2])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionReplace('siteurl', 'https://fixed.example', 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', 'SITEURL');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3], array_keys($plan->pageImages()));
        $t->same([
            ['no', 'siteurl', 1],
            ['no', 'cron_lock', 2],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->rowId);
        $t->same('https://fixed.example', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 1,
            'option_name' => 'siteurl',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'siteurl', 'https://fixed.example', 'no'])),
            'database_page_count' => 3,
            'updated_page_numbers' => [2, 3],
        ], $plan->toArray());
    },
    'plans wordpress wp_options replacement while moving an automatic composite index entry' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text, UNIQUE(autoload, option_name COLLATE NOCASE DESC))',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'sqlite_autoindex_wp_options_1',
                'wp_options',
                3,
                null,
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 3));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
        ], $pageSize);
        $indexPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 2])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 1])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);

        $plan = $database->planWordPressOptionReplace('siteurl', 'https://fixed.example', 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', 'SITEURL');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3], array_keys($plan->pageImages()));
        $t->same([
            ['no', 'siteurl', 1],
            ['no', 'cron_lock', 2],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(1, $option->rowId);
        $t->same('https://fixed.example', $option->optionValue);
        $t->same('no', $option->autoload);
    },
    'plans wordpress wp_options replacement while moving entries across a multi-page composite index' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'home', 2]), $pageSize, null, 4),
        ], 5, $pageSize);
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 1])),
        ], $pageSize);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 3])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'stylesheet', 4])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $leftIndexLeafPage
            . $rightIndexLeafPage,
        );

        $plan = $database->planWordPressOptionReplace('siteurl', 'https://fixed.example', 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
            4 => $database->page(4),
            5 => $database->page(5),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', 'siteurl');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 4, 5], array_keys($plan->pageImages()));
        $t->same([
            ['no', 'cron_lock', 1],
            ['no', 'siteurl', 3],
            ['yes', 'home', 2],
            ['yes', 'stylesheet', 4],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->rowId);
        $t->same('https://fixed.example', $option->optionValue);
        $t->same('no', $option->autoload);
    },
    'plans wordpress replacement by redistributing an underfilled multi-sibling composite index leaf' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $optionName = str_repeat('z', $nameLength);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 6));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('g', $nameLength), 100]), $pageSize, null, 4),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('m', $nameLength), 101]), $pageSize, null, 5),
        ], 6, $pageSize);

        $leftIndexEntries = [];
        foreach (['a', 'b', 'c'] as $index => $prefix) {
            $leftIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat($prefix, $nameLength), 50 + $index]));
        }
        $middleIndexEntries = [];
        foreach (['h', 'i', 'j', 'k', 'l'] as $index => $prefix) {
            $middleIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 60 + $index]));
        }
        $rightIndexEntries = [
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 2])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 3])),
        ];
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . SQLiteIndexLeafPage::assemble($leftIndexEntries, $pageSize)
            . SQLiteIndexLeafPage::assemble($middleIndexEntries, $pageSize)
            . SQLiteIndexLeafPage::assemble($rightIndexEntries, $pageSize),
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([2, 3, 4, 5, 6], array_keys($plan->pageImages()));
        $t->same(6, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(2, $postDatabase->pageHeader(3)->cellCount);
        $t->same(4, $postDatabase->pageHeader(4)->cellCount);
        $t->same(3, $postDatabase->pageHeader(5)->cellCount);
        $t->same(3, $postDatabase->pageHeader(6)->cellCount);
        $t->same([
            ['no', str_repeat('a', $nameLength), 50],
            ['no', str_repeat('b', $nameLength), 51],
            ['no', str_repeat('c', $nameLength), 52],
            ['no', $optionName, 3],
            ['yes', str_repeat('g', $nameLength), 100],
            ['yes', str_repeat('h', $nameLength), 60],
            ['yes', str_repeat('i', $nameLength), 61],
            ['yes', str_repeat('j', $nameLength), 62],
            ['yes', str_repeat('k', $nameLength), 63],
            ['yes', str_repeat('l', $nameLength), 64],
            ['yes', str_repeat('m', $nameLength), 101],
            ['yes', 'siteurl', 2],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 3,
            'option_name' => $optionName,
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, $optionName, 'fixed-cache', 'no'])),
            'database_page_count' => 6,
            'updated_page_numbers' => [2, 3, 4, 5, 6],
        ], $plan->toArray());
    },
    'plans wordpress replacement by merging an underfilled composite index leaf when redistribution cannot fit' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $optionName = str_repeat('z', $nameLength);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 6));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('g', $nameLength), 100]), $pageSize, null, 4),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('m', $nameLength), 101]), $pageSize, null, 5),
        ], 6, $pageSize);
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat('a', $nameLength), 50])),
        ], $pageSize);
        $middleIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('h', $nameLength), 60])),
        ], $pageSize);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 2])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 3])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $leftIndexLeafPage
            . $middleIndexLeafPage
            . $rightIndexLeafPage,
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $database->page($pageNumber);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $freelistTrunks = $postDatabase->freelistTrunkPages();

        $t->same([1, 2, 3, 4, 5, 6], array_keys($plan->pageImages()));
        $t->same(6, $plan->databasePageCount);
        $t->same(6, SQLiteHeader::parse($plan->pageImages()[1])->firstFreelistTrunkPage);
        $t->same(1, SQLiteHeader::parse($plan->pageImages()[1])->freelistPageCount);
        $t->same(1, count($freelistTrunks));
        $t->same(6, $freelistTrunks[0]->pageNumber);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same(2, $postDatabase->pageHeader(4)->cellCount);
        $t->same(3, $postDatabase->pageHeader(5)->cellCount);
        $t->same([
            ['no', str_repeat('a', $nameLength), 50],
            ['no', $optionName, 3],
            ['yes', str_repeat('g', $nameLength), 100],
            ['yes', str_repeat('h', $nameLength), 60],
            ['yes', str_repeat('m', $nameLength), 101],
            ['yes', 'siteurl', 2],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 3,
            'option_name' => $optionName,
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, $optionName, 'fixed-cache', 'no'])),
            'database_page_count' => 6,
            'updated_page_numbers' => [1, 2, 3, 4, 5, 6],
        ], $plan->toArray());
    },
    'plans wordpress replacement by merging an underfilled composite index leaf below a non-root parent' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $name = static fn (string $prefix): string => str_repeat($prefix, $nameLength);
        $optionName = $name('x');
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 12));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('y'), 900]), $pageSize, null, 4),
        ], 10, $pageSize);
        $lowerInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('g'), 100]), $pageSize, null, 5),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('m'), 101]), $pageSize, null, 6),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('p'), 102]), $pageSize, null, 7),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('s'), 103]), $pageSize, null, 8),
        ], 9, $pageSize);
        $rightInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 911]), $pageSize, null, 11),
        ], 12, $pageSize);

        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $lowerInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['no', $name('a'), 50])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('h'), 60])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('n'), 61])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('r'), 62])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('w'), 63])),
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 4])),
            ], $pageSize)
            . $rightInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 910])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 912])),
            ], $pageSize),
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([1, 2, 4, 5, 8, 9], array_keys($plan->pageImages()));
        $t->same(12, $plan->databasePageCount);
        $t->same(9, SQLiteHeader::parse($plan->pageImages()[1])->firstFreelistTrunkPage);
        $t->same(1, SQLiteHeader::parse($plan->pageImages()[1])->freelistPageCount);
        $t->same(3, $postDatabase->pageHeader(4)->cellCount);
        $t->same(8, $postDatabase->pageHeader(4)->rightMostPointer);
        $t->same(2, $postDatabase->pageHeader(5)->cellCount);
        $t->same(3, $postDatabase->pageHeader(8)->cellCount);
        $t->same([9], $postDatabase->freelistPageNumbers());
        $t->same([
            ['no', $name('a'), 50],
            ['no', $optionName, 4],
            ['yes', $name('g'), 100],
            ['yes', $name('h'), 60],
            ['yes', $name('m'), 101],
            ['yes', $name('n'), 61],
            ['yes', $name('p'), 102],
            ['yes', $name('r'), 62],
            ['yes', $name('s'), 103],
            ['yes', $name('w'), 63],
            ['yes', $name('y'), 900],
            ['yes', $name('z'), 910],
            ['yes', $name('z'), 911],
            ['yes', $name('z'), 912],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(4, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 4,
            'option_name' => $optionName,
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, $optionName, 'fixed-cache', 'no'])),
            'database_page_count' => 12,
            'updated_page_numbers' => [1, 2, 4, 5, 8, 9],
        ], $plan->toArray());
    },
    'plans wordpress replacement by collapsing a non-root composite index parent after leaf merge' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $name = static fn (string $prefix): string => str_repeat($prefix, $nameLength);
        $optionName = $name('x');
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 10));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('y'), 900]), $pageSize, null, 4),
        ], 8, $pageSize);
        $lowerInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('g'), 100]), $pageSize, null, 5),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('m'), 101]), $pageSize, null, 6),
        ], 7, $pageSize);
        $rightInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 911]), $pageSize, null, 9),
        ], 10, $pageSize);

        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $lowerInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['no', $name('a'), 50])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('h'), 60])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('w'), 63])),
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 4])),
            ], $pageSize)
            . $rightInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 910])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 912])),
            ], $pageSize),
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $rootCells = SQLiteIndexCell::parsePageCells(
            $postDatabase->page(3),
            $postDatabase->pageHeader(3),
            $pageSize,
        );

        $t->same([1, 2, 3, 5, 6, 7], array_keys($plan->pageImages()));
        $t->same(10, $plan->databasePageCount);
        $t->same(7, SQLiteHeader::parse($plan->pageImages()[1])->firstFreelistTrunkPage);
        $t->same(3, SQLiteHeader::parse($plan->pageImages()[1])->freelistPageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(3, $postDatabase->pageHeader(3)->cellCount);
        $t->same(10, $postDatabase->pageHeader(3)->rightMostPointer);
        $t->same([5, 6, 9], array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage, $rootCells));
        $t->same(2, $postDatabase->pageHeader(5)->cellCount);
        $t->same(3, $postDatabase->pageHeader(6)->cellCount);
        $t->same([7, 4, 8], $postDatabase->freelistPageNumbers());
        $t->same([
            ['no', $name('a'), 50],
            ['no', $optionName, 4],
            ['yes', $name('g'), 100],
            ['yes', $name('h'), 60],
            ['yes', $name('m'), 101],
            ['yes', $name('w'), 63],
            ['yes', $name('y'), 900],
            ['yes', $name('z'), 910],
            ['yes', $name('z'), 911],
            ['yes', $name('z'), 912],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(4, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 4,
            'option_name' => $optionName,
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, $optionName, 'fixed-cache', 'no'])),
            'database_page_count' => 10,
            'updated_page_numbers' => [1, 2, 3, 5, 6, 7],
        ], $plan->toArray());
    },
    'plans wordpress replacement by merging a non-root composite index parent under a multi-child root' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $name = static fn (string $prefix): string => str_repeat($prefix, $nameLength);
        $optionName = $name('x');
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 13));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('y'), 900]), $pageSize, null, 4),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('{'), 950]), $pageSize, null, 8),
        ], 11, $pageSize);
        $lowerInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('g'), 100]), $pageSize, null, 5),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('m'), 101]), $pageSize, null, 6),
        ], 7, $pageSize);
        $rightInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 911]), $pageSize, null, 9),
        ], 10, $pageSize);
        $farRightInteriorPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('~'), 961]), $pageSize, null, 12),
        ], 13, $pageSize);

        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $lowerInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['no', $name('a'), 50])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('h'), 60])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('w'), 63])),
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 4])),
            ], $pageSize)
            . $rightInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 910])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('z'), 912])),
            ], $pageSize)
            . $farRightInteriorPage
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('|'), 951])),
            ], $pageSize)
            . SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $name('~'), 962])),
            ], $pageSize),
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );
        $rootCells = SQLiteIndexCell::parsePageCells(
            $postDatabase->page(3),
            $postDatabase->pageHeader(3),
            $pageSize,
        );
        $mergedParentCells = SQLiteIndexCell::parsePageCells(
            $postDatabase->page(4),
            $postDatabase->pageHeader(4),
            $pageSize,
        );

        $t->same([1, 2, 3, 4, 5, 6, 7], array_keys($plan->pageImages()));
        $t->same(13, $plan->databasePageCount);
        $t->same(7, SQLiteHeader::parse($plan->pageImages()[1])->firstFreelistTrunkPage);
        $t->same(2, SQLiteHeader::parse($plan->pageImages()[1])->freelistPageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same([4], array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage, $rootCells));
        $t->same(11, $postDatabase->pageHeader(3)->rightMostPointer);
        $t->same('index-interior', $postDatabase->pageHeader(4)->pageType);
        $t->same(3, $postDatabase->pageHeader(4)->cellCount);
        $t->same([5, 6, 9], array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage, $mergedParentCells));
        $t->same(10, $postDatabase->pageHeader(4)->rightMostPointer);
        $t->same(3, $postDatabase->pageHeader(6)->cellCount);
        $t->same([7, 8], $postDatabase->freelistPageNumbers());
        $t->same([
            ['no', $name('a'), 50],
            ['no', $optionName, 4],
            ['yes', $name('g'), 100],
            ['yes', $name('h'), 60],
            ['yes', $name('m'), 101],
            ['yes', $name('w'), 63],
            ['yes', $name('y'), 900],
            ['yes', $name('z'), 910],
            ['yes', $name('z'), 911],
            ['yes', $name('z'), 912],
            ['yes', $name('{'), 950],
            ['yes', $name('|'), 951],
            ['yes', $name('~'), 961],
            ['yes', $name('~'), 962],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(4, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 4,
            'option_name' => $optionName,
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, $optionName, 'fixed-cache', 'no'])),
            'database_page_count' => 13,
            'updated_page_numbers' => [1, 2, 3, 4, 5, 6, 7],
        ], $plan->toArray());
    },
    'plans wordpress replacement by collapsing an emptied composite index root' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'home', 3]), $pageSize, null, 4),
        ], 5, $pageSize);
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 1])),
        ], $pageSize);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 2])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $leftIndexLeafPage
            . $rightIndexLeafPage,
        );

        $plan = $database->planWordPressOptionReplace('siteurl', 'https://fixed.example', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', 'siteurl');
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([1, 2, 3, 4], array_keys($plan->pageImages()));
        $t->same(5, $plan->databasePageCount);
        $t->same('index-leaf', $postDatabase->pageHeader(3)->pageType);
        $t->same(3, $postDatabase->pageHeader(3)->cellCount);
        $t->same([4, 5], $postDatabase->freelistPageNumbers());
        $t->same([5, 4], $postDatabase->freelistAllocationOrder());
        $t->same([
            ['no', 'cron_lock', 1],
            ['no', 'siteurl', 2],
            ['yes', 'home', 3],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(2, $option->rowId);
        $t->same('https://fixed.example', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 2,
            'option_name' => 'siteurl',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'siteurl', 'https://fixed.example', 'no'])),
            'database_page_count' => 5,
            'updated_page_numbers' => [1, 2, 3, 4],
        ], $plan->toArray());
    },
    'plans wordpress replacement by splitting a same-depth composite index leaf' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $optionName = str_repeat('z', 70);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 5));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        $indexRootPage = SQLiteIndexInteriorPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'home', 2]), $pageSize, null, 4),
        ], 5, $pageSize);
        $leftIndexEntries = [];
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $index => $prefix) {
            $leftIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat($prefix, 70), 10 + $index]));
        }
        $leftIndexLeafPage = SQLiteIndexLeafPage::assemble($leftIndexEntries, $pageSize);
        $rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'stylesheet', 4])),
            SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 3])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . $leftIndexLeafPage
            . $rightIndexLeafPage,
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([1, 2, 3, 4, 5, 6], array_keys($plan->pageImages()));
        $t->same(6, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(2, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(4)->cellCount);
        $t->same(3, $postDatabase->pageHeader(6)->cellCount);
        $t->same([
            ['no', str_repeat('a', 70), 10],
            ['no', str_repeat('b', 70), 11],
            ['no', str_repeat('c', 70), 12],
            ['no', str_repeat('d', 70), 13],
            ['no', str_repeat('e', 70), 14],
            ['no', str_repeat('f', 70), 15],
            ['no', $optionName, 3],
            ['yes', 'home', 2],
            ['yes', 'stylesheet', 4],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(3, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no', false),
        );
    },
    'plans wordpress replacement by splitting a composite index leaf and growing a full root interior' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $nameLength = 64;
        $optionName = str_repeat('w', $nameLength);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 10));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
        ], $pageSize);

        $indexRootCells = [];
        foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
            $indexRootCells[] = SQLiteIndexCell::encode(
                SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 100 + $index]),
                $pageSize,
                null,
                4 + $index,
            );
        }
        $indexRootPage = SQLiteIndexInteriorPage::assemble($indexRootCells, 10, $pageSize);

        $leftIndexEntries = [];
        foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $index => $prefix) {
            $leftIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat($prefix, $nameLength), 50 + $index]));
        }
        $middleLeafPages = [];
        foreach (['h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
            $middleLeafPages[] = SQLiteIndexLeafPage::assemble([
                SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 60 + $index])),
            ], $pageSize);
        }
        $rightIndexEntries = [];
        foreach (['r', 's', 't', 'u', 'v'] as $index => $prefix) {
            $rightIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 200 + $index]));
        }
        $rightIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 2]));

        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $indexRootPage
            . SQLiteIndexLeafPage::assemble($leftIndexEntries, $pageSize)
            . implode('', $middleLeafPages)
            . SQLiteIndexLeafPage::assemble($rightIndexEntries, $pageSize),
        );

        $plan = $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->wordpressOptionByIndexedAutoloadAndName('no', $optionName);
        $indexRecords = array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            $postDatabase->indexCells(3),
        );

        $t->same([1, 2, 3, 4, 10, 11, 12, 13], array_keys($plan->pageImages()));
        $t->same(13, $plan->databasePageCount);
        $t->same('index-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same(1, $postDatabase->pageHeader(3)->cellCount);
        $t->same(3, $postDatabase->pageHeader(12)->cellCount);
        $t->same(3, $postDatabase->pageHeader(13)->cellCount);
        $t->same(3, $postDatabase->pageHeader(4)->cellCount);
        $t->same(3, $postDatabase->pageHeader(11)->cellCount);
        $t->same(5, $postDatabase->pageHeader(10)->cellCount);
        $t->same(6, $postDatabase->pageHeader(12)->rightMostPointer);
        $t->same(10, $postDatabase->pageHeader(13)->rightMostPointer);
        $t->same([
            ['no', str_repeat('a', $nameLength), 50],
            ['no', str_repeat('b', $nameLength), 51],
            ['no', str_repeat('c', $nameLength), 52],
            ['no', str_repeat('d', $nameLength), 53],
            ['no', str_repeat('e', $nameLength), 54],
            ['no', str_repeat('f', $nameLength), 55],
            ['no', $optionName, 2],
            ['yes', str_repeat('g', $nameLength), 100],
            ['yes', str_repeat('h', $nameLength), 60],
            ['yes', str_repeat('i', $nameLength), 101],
            ['yes', str_repeat('j', $nameLength), 61],
            ['yes', str_repeat('k', $nameLength), 102],
            ['yes', str_repeat('l', $nameLength), 62],
            ['yes', str_repeat('m', $nameLength), 103],
            ['yes', str_repeat('n', $nameLength), 63],
            ['yes', str_repeat('o', $nameLength), 104],
            ['yes', str_repeat('p', $nameLength), 64],
            ['yes', str_repeat('q', $nameLength), 105],
            ['yes', str_repeat('r', $nameLength), 200],
            ['yes', str_repeat('s', $nameLength), 201],
            ['yes', str_repeat('t', $nameLength), 202],
            ['yes', str_repeat('u', $nameLength), 203],
            ['yes', str_repeat('v', $nameLength), 204],
        ], $indexRecords);
        $t->true($option instanceof SQLiteWordPressOption);
        $t->same(2, $option->rowId);
        $t->same($optionName, $option->optionName);
        $t->same('fixed-cache', $option->optionValue);
        $t->same('no', $option->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 2,
            'option_name' => $optionName,
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, $optionName, 'fixed-cache', 'no'])),
            'database_page_count' => 13,
            'updated_page_numbers' => [1, 2, 3, 4, 10, 11, 12, 13],
        ], $plan->toArray());
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionReplace($optionName, 'fixed-cache', 'no', false),
        );
    },
    'plans wordpress replacement inside a multi-page table btree leaf' => static function (TestRunner $t) use ($makeFirstPage, $tableInteriorPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 4));
        $tableRootPage = $tableInteriorPage([[3, 2]], 4, $pageSize);
        $leftTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
        ], $pageSize);
        $rightTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tableRootPage
            . $leftTableLeafPage
            . $rightTableLeafPage,
        );

        $plan = $database->planWordPressOptionReplace('blogname', 'Fixed Site', 'no');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
            3 => $database->page(3),
            4 => $database->page(4),
        ];
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();

        $t->same([4], array_keys($plan->pageImages()));
        $t->same(2, $plan->tableRootPage);
        $t->same(3, $plan->rowId);
        $t->same('table-interior', $postDatabase->pageHeader(2)->pageType);
        $t->same($database->page(2), $postDatabase->page(2));
        $t->same(['siteurl', 'home', 'blogname', 'template'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same('Fixed Site', $options[2]->optionValue);
        $t->same('no', $options[2]->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 3,
            'option_name' => 'blogname',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'blogname', 'Fixed Site', 'no'])),
            'database_page_count' => 4,
            'updated_page_numbers' => [4],
        ], $plan->toArray());

        $duplicateDatabase = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tableRootPage
            . $leftTableLeafPage
            . SQLiteTableLeafPage::assemble([
                SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'home', 'duplicate', 'no'])),
                SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
            ], $pageSize),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $duplicateDatabase->planWordPressOptionReplace('home', 'fixed', 'no'),
        );
    },
    'plans wordpress replacement by splitting a table leaf below an interior root' => static function (TestRunner $t) use ($makeFirstPage, $tableInteriorPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 4));
        $tableRootPage = $tableInteriorPage([[3, 3]], 4, $pageSize);
        $leftTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_migration_lock', 'old-lock', 'no'])),
        ], $pageSize);
        $rightTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tableRootPage
            . $leftTableLeafPage
            . $rightTableLeafPage,
        );

        $replacementValue = str_repeat('expanded-cache-', 28);
        $plan = $database->planWordPressOptionReplace('blogname', $replacementValue, 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();
        $parentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));
        $oldLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(3), $postDatabase->pageHeader(3), $pageSize);
        $newLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(5), $postDatabase->pageHeader(5), $pageSize);

        $t->same([1, 2, 3, 5], array_keys($plan->pageImages()));
        $t->same(5, $plan->databasePageCount);
        $t->same(2, $plan->tableRootPage);
        $t->same(2, $plan->rowId);
        $t->same(strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])), $plan->localPayloadLength);
        $t->same('table-interior', $postDatabase->pageHeader(2)->pageType);
        $t->same(2, $postDatabase->pageHeader(2)->cellCount);
        $t->same(4, $postDatabase->pageHeader(2)->rightMostPointer);
        $t->same([3, 5], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $parentCells));
        $t->same([1, 3], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $parentCells));
        $t->same([1], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $oldLeafCells));
        $t->same([2, 3], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $newLeafCells));
        $t->same(['siteurl', 'blogname', '_transient_migration_lock', 'template'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same($replacementValue, $options[1]->optionValue);
        $t->same('no', $options[1]->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 2,
            'option_name' => 'blogname',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])),
            'database_page_count' => 5,
            'updated_page_numbers' => [1, 2, 3, 5],
        ], $plan->toArray());

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionReplace('blogname', $replacementValue, 'no', false),
        );
    },
    'plans wordpress replacement by splitting a table leaf below a non-root interior parent' => static function (TestRunner $t) use ($makeFirstPage, $tableInteriorPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 6));
        $tableRootPage = $tableInteriorPage([[3, 4]], 6, $pageSize);
        $lowerInteriorPage = $tableInteriorPage([[4, 2]], 5, $pageSize);
        $leftTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
        ], $pageSize);
        $targetTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, '_transient_migration_lock', 'old-lock', 'no'])),
        ], $pageSize);
        $rightTableLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(5, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tableRootPage
            . $lowerInteriorPage
            . $leftTableLeafPage
            . $targetTableLeafPage
            . $rightTableLeafPage,
        );

        $replacementValue = str_repeat('x', 450);
        $plan = $database->planWordPressOptionReplace('blogname', $replacementValue, 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();
        $rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));
        $lowerCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $postDatabase->pageHeader(3));
        $oldLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(5), $postDatabase->pageHeader(5), $pageSize);
        $newLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(7), $postDatabase->pageHeader(7), $pageSize);

        $t->same([1, 3, 5, 7], array_keys($plan->pageImages()));
        $t->same(7, $plan->databasePageCount);
        $t->same($database->page(2), $postDatabase->page(2));
        $t->same('table-interior', $postDatabase->pageHeader(2)->pageType);
        $t->same([3], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $rootCells));
        $t->same([4], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $rootCells));
        $t->same(6, $postDatabase->pageHeader(2)->rightMostPointer);
        $t->same('table-interior', $postDatabase->pageHeader(3)->pageType);
        $t->same([4, 5], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $lowerCells));
        $t->same([2, 3], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $lowerCells));
        $t->same(7, $postDatabase->pageHeader(3)->rightMostPointer);
        $t->same([3], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $oldLeafCells));
        $t->same([4], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $newLeafCells));
        $t->same(['siteurl', 'home', 'blogname', '_transient_migration_lock', 'template'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same($replacementValue, $options[2]->optionValue);
        $t->same('no', $options[2]->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 3,
            'option_name' => 'blogname',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])),
            'database_page_count' => 7,
            'updated_page_numbers' => [1, 3, 5, 7],
        ], $plan->toArray());
    },
    'plans wordpress replacement by splitting an overflowing non-root table parent' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $largeRowIdBase = 72057594037927936;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 38));

        $lowerParentCells = [];
        $leafPages = [];
        for ($index = 0; $index < 33; $index++) {
            $rowId = $largeRowIdBase + ($index * 10);
            $pageNumber = 4 + $index;
            $lowerParentCells[] = SQLiteTableInteriorCell::encode($pageNumber, $rowId);
            $leafPages[$pageNumber] = SQLiteTableLeafPage::assemble([
                SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode([null, 'filler_' . $index, 'value_' . $index, 'no'])),
            ], $pageSize);
        }

        $targetRowId = $largeRowIdBase + 330;
        $nextRowId = $largeRowIdBase + 340;
        $rightRootRowId = $largeRowIdBase + 350;
        $targetLeafPage = 37;
        $rightRootLeafPage = 38;
        $tableRootPage = SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode(3, $nextRowId),
        ], $rightRootLeafPage, $pageSize);
        $lowerInteriorPage = SQLiteTableInteriorPage::assemble($lowerParentCells, $targetLeafPage, $pageSize);
        $leafPages[$targetLeafPage] = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode($targetRowId, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode($nextRowId, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        $leafPages[$rightRootLeafPage] = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode($rightRootRowId, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentysix', 'yes'])),
        ], $pageSize);
        ksort($leafPages);

        $database = SQLiteDatabase::fromBytes($schemaPage . $tableRootPage . $lowerInteriorPage . implode('', $leafPages));
        $replacementValue = str_repeat('x', 450);
        $plan = $database->planWordPressOptionReplace('blogname', $replacementValue, 'no');

        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));
        $leftParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $postDatabase->pageHeader(3));
        $rightParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(40), $postDatabase->pageHeader(40));
        $oldLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(37), $postDatabase->pageHeader(37), $pageSize);
        $newLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(39), $postDatabase->pageHeader(39), $pageSize);
        $targetOptions = $postDatabase->wordpressOptionsByRowIdRange($targetRowId, $targetRowId, 1, true);

        $t->same([1, 2, 3, 37, 39, 40], array_keys($plan->pageImages()));
        $t->same(40, $plan->databasePageCount);
        $t->same('table-interior', $postDatabase->pageHeader(2)->pageType);
        $t->same(2, $postDatabase->pageHeader(2)->cellCount);
        $t->same($rightRootLeafPage, $postDatabase->pageHeader(2)->rightMostPointer);
        $t->same([3, 40], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $rootCells));
        $t->same([$largeRowIdBase + 160, $nextRowId], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $rootCells));
        $t->same(16, $postDatabase->pageHeader(3)->cellCount);
        $t->same(17, $postDatabase->pageHeader(40)->cellCount);
        $t->same(20, $postDatabase->pageHeader(3)->rightMostPointer);
        $t->same(39, $postDatabase->pageHeader(40)->rightMostPointer);
        $t->same(4, $leftParentCells[0]->leftChildPage);
        $t->same($largeRowIdBase, $leftParentCells[0]->key);
        $t->same(21, $rightParentCells[0]->leftChildPage);
        $t->same($largeRowIdBase + 170, $rightParentCells[0]->key);
        $t->same([$targetRowId], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $oldLeafCells));
        $t->same([$nextRowId], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $newLeafCells));
        $t->same(1, count($targetOptions));
        $t->same('blogname', $targetOptions[0]->optionName);
        $t->same($replacementValue, $targetOptions[0]->optionValue);
        $t->same('no', $targetOptions[0]->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => $targetRowId,
            'option_name' => 'blogname',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])),
            'database_page_count' => 40,
            'updated_page_numbers' => [1, 2, 3, 37, 39, 40],
        ], $plan->toArray());
    },
    'plans wordpress replacement by splitting a table leaf and growing a full table root parent' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $largeRowIdBase = 72057594037927936;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 36));

        $rootCells = [];
        $leafPages = [];
        for ($index = 0; $index < 33; $index++) {
            $rowId = $largeRowIdBase + ($index * 10);
            $pageNumber = 3 + $index;
            $rootCells[] = SQLiteTableInteriorCell::encode($pageNumber, $rowId);
            $leafPages[$pageNumber] = SQLiteTableLeafPage::assemble([
                SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode([null, 'filler_' . $index, 'value_' . $index, 'no'])),
            ], $pageSize);
        }

        $targetRowId = $largeRowIdBase + 330;
        $nextRowId = $largeRowIdBase + 340;
        $targetLeafPage = 36;
        $tableRootPage = SQLiteTableInteriorPage::assemble($rootCells, $targetLeafPage, $pageSize);
        $leafPages[$targetLeafPage] = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode($targetRowId, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode($nextRowId, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
        ], $pageSize);
        ksort($leafPages);

        $database = SQLiteDatabase::fromBytes($schemaPage . $tableRootPage . implode('', $leafPages));
        $replacementValue = str_repeat('root-parent-split-', 24);
        $plan = $database->planWordPressOptionReplace('blogname', $replacementValue, 'no');

        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $rootCellsAfter = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));
        $leftParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(38), $postDatabase->pageHeader(38));
        $rightParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(39), $postDatabase->pageHeader(39));
        $oldLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(36), $postDatabase->pageHeader(36), $pageSize);
        $newLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(37), $postDatabase->pageHeader(37), $pageSize);
        $targetOptions = $postDatabase->wordpressOptionsByRowIdRange($targetRowId, $targetRowId, 1, true);

        $t->same([1, 2, 36, 37, 38, 39], array_keys($plan->pageImages()));
        $t->same(39, $plan->databasePageCount);
        $t->same('table-interior', $postDatabase->pageHeader(2)->pageType);
        $t->same(1, $postDatabase->pageHeader(2)->cellCount);
        $t->same(39, $postDatabase->pageHeader(2)->rightMostPointer);
        $t->same(38, $rootCellsAfter[0]->leftChildPage);
        $t->same($largeRowIdBase + 160, $rootCellsAfter[0]->key);
        $t->same(16, $postDatabase->pageHeader(38)->cellCount);
        $t->same(17, $postDatabase->pageHeader(39)->cellCount);
        $t->same(19, $postDatabase->pageHeader(38)->rightMostPointer);
        $t->same(37, $postDatabase->pageHeader(39)->rightMostPointer);
        $t->same(3, $leftParentCells[0]->leftChildPage);
        $t->same($largeRowIdBase, $leftParentCells[0]->key);
        $t->same(20, $rightParentCells[0]->leftChildPage);
        $t->same($largeRowIdBase + 170, $rightParentCells[0]->key);
        $t->same([$targetRowId], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $oldLeafCells));
        $t->same([$nextRowId], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $newLeafCells));
        $t->same(1, count($targetOptions));
        $t->same('blogname', $targetOptions[0]->optionName);
        $t->same($replacementValue, $targetOptions[0]->optionValue);
        $t->same('no', $targetOptions[0]->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => $targetRowId,
            'option_name' => 'blogname',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])),
            'database_page_count' => 39,
            'updated_page_numbers' => [1, 2, 36, 37, 38, 39],
        ], $plan->toArray());
    },
    'plans wordpress replacement by growing a table leaf root' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 2));
        $tableRootLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_migration_lock', 'old-lock', 'no'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tableRootLeafPage);

        $replacementValue = str_repeat('expanded-cache-', 28);
        $plan = $database->planWordPressOptionReplace('blogname', $replacementValue, 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $options = $postDatabase->wordpressOptions();
        $rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));
        $leftLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(3), $postDatabase->pageHeader(3), $pageSize);
        $rightLeafCells = SQLiteTableLeafCell::parsePageCells($postDatabase->page(4), $postDatabase->pageHeader(4), $pageSize);

        $t->same([1, 2, 3, 4], array_keys($plan->pageImages()));
        $t->same(4, $plan->databasePageCount);
        $t->same(2, $plan->tableRootPage);
        $t->same(2, $plan->rowId);
        $t->same(strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])), $plan->localPayloadLength);
        $t->same('table-interior', $postDatabase->pageHeader(2)->pageType);
        $t->same(1, $postDatabase->pageHeader(2)->cellCount);
        $t->same(4, $postDatabase->pageHeader(2)->rightMostPointer);
        $t->same([3], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $rootCells));
        $t->same([1], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $rootCells));
        $t->same([1], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $leftLeafCells));
        $t->same([2, 3], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $rightLeafCells));
        $t->same(['siteurl', 'blogname', '_transient_migration_lock'], array_map(static fn (SQLiteWordPressOption $option): string => $option->optionName, $options));
        $t->same($replacementValue, $options[1]->optionValue);
        $t->same('no', $options[1]->autoload);
        $t->same([
            'table_root_page' => 2,
            'rowid' => 2,
            'option_name' => 'blogname',
            'autoload' => 'no',
            'overflow_page_numbers' => [],
            'obsolete_overflow_page_numbers' => [],
            'local_payload_length' => strlen(SQLiteRecord::encode([null, 'blogname', $replacementValue, 'no'])),
            'database_page_count' => 4,
            'updated_page_numbers' => [1, 2, 3, 4],
        ], $plan->toArray());

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionReplace('blogname', $replacementValue, 'no', false),
        );
    },
    'plans auto-vacuum pointer-map entries for table-root split btree children' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $firstPage = $makeFirstPage($pageSize, 3);
        $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                3,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $firstPage);
        $pointerMapPage = substr_replace(
            str_repeat("\0", $pageSize),
            chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0),
            0,
            5,
        );
        $tableRootLeafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
            SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_migration_lock', 'old-lock', 'no'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $pointerMapPage . $tableRootLeafPage);

        $replacementValue = str_repeat('expanded-cache-', 28);
        $plan = $database->planWordPressOptionReplace('blogname', $replacementValue, 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount()
                ? $database->page($pageNumber)
                : str_repeat("\0", $pageSize);
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $postDatabase->pageHeader(3));
        $leftChild = $rootCells[0]->leftChildPage;
        $rightChild = $postDatabase->pageHeader(3)->rightMostPointer;

        $t->same([1, 2, 3, 4, 5], array_keys($plan->pageImages()));
        $t->same(5, $plan->databasePageCount);
        $t->same('root-page', $postDatabase->pointerMapEntryForPage(3)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(3)->parentPageNumber);
        $t->same('btree-page', $postDatabase->pointerMapEntryForPage($leftChild)->typeName());
        $t->same(3, $postDatabase->pointerMapEntryForPage($leftChild)->parentPageNumber);
        $t->same('btree-page', $postDatabase->pointerMapEntryForPage($rightChild)->typeName());
        $t->same(3, $postDatabase->pointerMapEntryForPage($rightChild)->parentPageNumber);
        $t->same($replacementValue, $postDatabase->tableRowByRowIdByName('wp_options', 2)?->values()[2] ?? null);
    },
    'plans wordpress wp_options replacement with appended overflow pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 2));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'theme_mods_twentyfive', 'inline-cache', 'no'])),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);

        $largeValue = str_repeat('theme-mod-fragment:', 70) . 'done';
        $payload = SQLiteRecord::encode([null, 'theme_mods_twentyfive', $largeValue, 'yes']);
        $localLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $pageSize);
        $overflowPayload = substr($payload, $localLength);
        $expectedOverflowPages = range(
            3,
            2 + SQLiteOverflowPage::requiredPageCount(strlen($overflowPayload), $pageSize),
        );

        $plan = $database->planWordPressOptionReplace('theme_mods_twentyfive', $largeValue, 'yes');
        $postPages = [
            1 => $database->page(1),
            2 => $database->page(2),
        ];
        for ($pageNumber = 3; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $emptyPage;
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->tableRowByRowIdByName('wp_options', 2);

        $t->same($expectedOverflowPages, $plan->overflowPageNumbers);
        $t->same([], $plan->obsoleteOverflowPageNumbers);
        $t->same(array_merge([1, 2], $expectedOverflowPages), array_keys($plan->pageImages()));
        $t->same(2 + count($expectedOverflowPages), $plan->databasePageCount);
        $t->same($localLength, $plan->localPayloadLength);
        $t->true($option !== null);
        $t->same([null, 'theme_mods_twentyfive', $largeValue, 'yes'], $option?->values());
    },
    'plans wordpress wp_options replacement allocation before freeing old overflow pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $makeFirstPage($pageSize, 4));

        $oldValue = str_repeat('old-cache-fragment:', 70) . 'done';
        $oldPayload = SQLiteRecord::encode([null, 'rewrite_large_cache', $oldValue, 'yes']);
        $oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
        $oldOverflowPayload = substr($oldPayload, $oldLocalLength);
        $oldOverflowPages = SQLiteOverflowPage::encodeChainAtPages($oldOverflowPayload, [3, 4], $pageSize);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, $oldPayload, $pageSize, 3),
        ], $pageSize);
        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $tablePage
            . $oldOverflowPages[3]
            . $oldOverflowPages[4],
        );

        $newValue = str_repeat('new-cache-fragment:', 78) . 'done';
        $newPayload = SQLiteRecord::encode([null, 'rewrite_large_cache', $newValue, 'no']);
        $newLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($newPayload), $pageSize);
        $newOverflowPayload = substr($newPayload, $newLocalLength);
        $expectedOverflowPages = range(
            5,
            4 + SQLiteOverflowPage::requiredPageCount(strlen($newOverflowPayload), $pageSize),
        );

        $plan = $database->planWordPressOptionReplace('rewrite_large_cache', $newValue, 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount() ? $database->page($pageNumber) : $emptyPage;
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->tableRowByRowIdByName('wp_options', 2);

        $t->same($expectedOverflowPages, $plan->overflowPageNumbers);
        $t->same([3, 4], $plan->obsoleteOverflowPageNumbers);
        $t->same(array_merge([1, 2, 3], $expectedOverflowPages), array_keys($plan->pageImages()));
        $t->same($newLocalLength, $plan->localPayloadLength);
        $t->same(4 + count($expectedOverflowPages), $plan->databasePageCount);
        $t->same([3, 4], $postDatabase->freelistPageNumbers());
        $t->same([4, 3], $postDatabase->freelistAllocationOrder());
        $t->true($option !== null);
        $t->same([null, 'rewrite_large_cache', $newValue, 'no'], $option?->values());

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionReplace('rewrite_large_cache', $newValue, 'no', false),
        );
    },
    'plans auto-vacuum pointer-map entries for replacement-created wordpress overflow chains' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $emptyPage = str_repeat("\0", $pageSize);
        $firstPage = $makeFirstPage($pageSize, 5);
        $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                3,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], $pageSize, 100, $firstPage);

        $oldValue = str_repeat('old-theme-mod-fragment:', 48) . 'done';
        $oldPayload = SQLiteRecord::encode([null, 'theme_mods_twentyfive', $oldValue, 'yes']);
        $oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
        $oldOverflowPayload = substr($oldPayload, $oldLocalLength);
        $oldOverflowPages = SQLiteOverflowPage::encodeChainAtPages($oldOverflowPayload, [4, 5], $pageSize);
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, $oldPayload, $pageSize, 4),
        ], $pageSize);

        $pointerMapPage = str_repeat("\0", $pageSize);
        foreach ([
            3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
            4 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
            5 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 4],
        ] as $pageNumber => [$type, $parentPageNumber]) {
            $pointerMapPage = substr_replace(
                $pointerMapPage,
                chr($type) . pack('N', $parentPageNumber),
                ($pageNumber - 3) * 5,
                5,
            );
        }

        $database = SQLiteDatabase::fromBytes(
            $schemaPage
            . $pointerMapPage
            . $tablePage
            . $oldOverflowPages[4]
            . $oldOverflowPages[5],
        );

        $newValue = str_repeat('new-theme-mod-fragment:', 86) . 'done';
        $newPayload = SQLiteRecord::encode([null, 'theme_mods_twentyfive', $newValue, 'no']);
        $newLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($newPayload), $pageSize);
        $newOverflowPayload = substr($newPayload, $newLocalLength);
        $expectedOverflowPages = range(
            6,
            5 + SQLiteOverflowPage::requiredPageCount(strlen($newOverflowPayload), $pageSize),
        );

        $plan = $database->planWordPressOptionReplace('theme_mods_twentyfive', $newValue, 'no');
        $postPages = [];
        for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
            $postPages[$pageNumber] = $pageNumber <= $database->pageCount() ? $database->page($pageNumber) : $emptyPage;
        }
        foreach ($plan->pageImages() as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $option = $postDatabase->tableRowByRowIdByName('wp_options', 2);

        $t->same($expectedOverflowPages, $plan->overflowPageNumbers);
        $t->same([4, 5], $plan->obsoleteOverflowPageNumbers);
        $t->same(array_merge([1, 2, 3, 4], $expectedOverflowPages), array_keys($plan->pageImages()));
        $t->same(5 + count($expectedOverflowPages), $plan->databasePageCount);
        $t->same($newLocalLength, $plan->localPayloadLength);
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(4)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(4)->parentPageNumber);
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(5)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(5)->parentPageNumber);
        $t->same('first-overflow-page', $postDatabase->pointerMapEntryForPage($expectedOverflowPages[0])->typeName());
        $t->same(3, $postDatabase->pointerMapEntryForPage($expectedOverflowPages[0])->parentPageNumber);
        for ($index = 1; $index < count($expectedOverflowPages); $index++) {
            $entry = $postDatabase->pointerMapEntryForPage($expectedOverflowPages[$index]);
            $t->same('overflow-page', $entry->typeName());
            $t->same($expectedOverflowPages[$index - 1], $entry->parentPageNumber);
        }
        $t->same([4, 5], $postDatabase->freelistPageNumbers());
        $t->true($option !== null);
        $t->same([null, 'theme_mods_twentyfive', $newValue, 'no'], $option?->values());
    },
    'rejects bounded wordpress replacement plans that would be ambiguous or leave indexes stale' => static function (TestRunner $t) use ($makeFirstPage): void {
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], 512, 100, $makeFirstPage(512, 2));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ]);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);

        $t->throws(InvalidArgumentException::class, static fn () => $database->planWordPressOptionReplace('home', 'https://example.test/blog'));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $database->planWordPressOptionReplace('siteurl', str_repeat('too-large:', 80), null, false),
        );

        $duplicateTablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'siteurl', 'https://duplicate.example', 'no'])),
        ]);
        $duplicateDatabase = SQLiteDatabase::fromBytes($schemaPage . $duplicateTablePage);
        $t->throws(InvalidArgumentException::class, static fn () => $duplicateDatabase->planWordPressOptionReplace('siteurl', 'https://fixed.example'));

        $indexedSchemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_option_value',
                'wp_options',
                3,
                'CREATE INDEX wp_options_option_value ON wp_options(option_value)',
            ])),
        ], 512, 100, $makeFirstPage(512, 3));
        $indexedDatabase = SQLiteDatabase::fromBytes($indexedSchemaPage . $tablePage . SQLiteIndexLeafPage::assemble([]));

        $t->throws(InvalidArgumentException::class, static fn () => $indexedDatabase->planWordPressOptionReplace('siteurl', 'https://fixed.example'));

        $unsafePartialSchemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoloaded_name',
                'wp_options',
                3,
                "CREATE INDEX wp_options_autoloaded_name ON wp_options(option_name) WHERE autoload = 'yes'",
            ])),
        ], 512, 100, $makeFirstPage(512, 3));
        $unsafePartialDatabase = SQLiteDatabase::fromBytes($unsafePartialSchemaPage . $tablePage . SQLiteIndexLeafPage::assemble([
            SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 1])),
        ]));

        $t->throws(InvalidArgumentException::class, static fn () => $unsafePartialDatabase->planWordPressOptionReplace('siteurl', 'https://fixed.example'));
    },
    'rejects bounded wordpress insert plans that would leave indexes or duplicate rows stale' => static function (TestRunner $t) use ($makeFirstPage): void {
        $schemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], 512, 100, $makeFirstPage(512, 2));
        $tablePage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        ]);
        $database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);

        $t->throws(InvalidArgumentException::class, static fn () => $database->planWordPressOptionInsert(1, 'home', 'https://example.test/blog'));
        $t->throws(InvalidArgumentException::class, static fn () => $database->planWordPressOptionInsert(2, 'siteurl', 'https://duplicate.example'));

        $indexedSchemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoload_name',
                'wp_options',
                3,
                'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_value)',
            ])),
        ], 512, 100, $makeFirstPage(512, 3));
        $indexedDatabase = SQLiteDatabase::fromBytes($indexedSchemaPage . $tablePage . SQLiteIndexLeafPage::assemble([]));

        $t->throws(InvalidArgumentException::class, static fn () => $indexedDatabase->planWordPressOptionInsert(2, 'home', 'https://example.test/blog'));

        $unsafePartialSchemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                2,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
            SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
                'index',
                'wp_options_autoloaded_name',
                'wp_options',
                3,
                "CREATE INDEX wp_options_autoloaded_name ON wp_options(option_name) WHERE autoload = 'yes'",
            ])),
        ], 512, 100, $makeFirstPage(512, 3));
        $unsafePartialDatabase = SQLiteDatabase::fromBytes($unsafePartialSchemaPage . $tablePage . SQLiteIndexLeafPage::assemble([]));

        $t->throws(InvalidArgumentException::class, static fn () => $unsafePartialDatabase->planWordPressOptionInsert(2, 'home', 'https://example.test/blog'));

        $rootOneSchemaPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
                'table',
                'wp_options',
                'wp_options',
                1,
                'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
            ])),
        ], 512, 100, $makeFirstPage(512, 1));
        $rootOneDatabase = SQLiteDatabase::fromBytes($rootOneSchemaPage);

        $t->throws(InvalidArgumentException::class, static fn () => $rootOneDatabase->planWordPressOptionInsert(2, 'home', 'https://example.test/blog'));
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
    'parses sqlite wal headers and committed frame page images' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $salt1 = 0x01020304;
        $salt2 = 0x05060708;
        $walHeader = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 7, $salt1, $salt2, 0x11111111, 0x22222222);
        $pageOne = $makeFirstPage($pageSize, 2);
        $pageTwo = str_repeat('W', $pageSize);
        $pageThree = str_repeat('U', $pageSize);
        $walBytes = $walHeader
            . pack('N*', 1, 0, $salt1, $salt2, 0xaaaa0001, 0xbbbb0001) . $pageOne
            . pack('N*', 2, 2, $salt1, $salt2, 0xaaaa0002, 0xbbbb0002) . $pageTwo
            . pack('N*', 3, 0, $salt1, $salt2, 0xaaaa0003, 0xbbbb0003) . $pageThree;

        $wal = SQLiteWal::parse($walBytes);

        $t->same(SQLiteWal::class, get_class($wal));
        $t->same(SQLiteWalHeader::class, get_class($wal->header));
        $t->same(3, $wal->frameCount());
        $t->same('big-endian', $wal->header->byteOrder());
        $t->same([
            'magic' => SQLiteWalHeader::MAGIC_BIG_ENDIAN,
            'format_version' => 3007000,
            'page_size' => 512,
            'checkpoint_sequence' => 7,
            'salt1' => $salt1,
            'salt2' => $salt2,
            'checksum1' => 0x11111111,
            'checksum2' => 0x22222222,
            'byte_order' => 'big-endian',
        ], $wal->header->toArray());
        $lastCommitFrame = $wal->lastCommitFrame();
        $t->same(SQLiteWalFrame::class, get_class($lastCommitFrame));
        $t->same(2, $lastCommitFrame->index);
        $t->same(2, $lastCommitFrame->pageNumber);
        $t->same(true, $lastCommitFrame->isCommitFrame());
        $t->same([1, 2], array_keys($wal->pageImagesThroughLastCommit()));
        $t->same($pageOne, $wal->pageImagesThroughLastCommit()[1]);
        $t->same($pageTwo, $wal->pageImagesThroughLastCommit()[2]);
        $t->same([
            'header' => $wal->header->toArray(),
            'frame_count' => 3,
            'committed_page_numbers' => [1, 2],
            'last_commit_frame' => $lastCommitFrame->toArray(),
        ], $wal->toArray());
    },
    'rejects malformed sqlite wal files' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalHeader::parse(str_repeat("\0", 31)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalHeader::parse(pack('N*', 0, 3007000, 512, 0, 1, 2, 3, 4)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalHeader::parse(pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, 500, 0, 1, 2, 3, 4)));

        $header = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, 512, 0, 1, 2, 3, 4);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::parse(substr($header, 0, 32) . str_repeat("\0", 16)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::parse(pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, 0, 0, 1, 2, 3, 4)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::parse($header . pack('N*', 1, 1, 9, 2, 3, 4) . str_repeat("\0", 512)));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWal::parse($header . pack('N*', 0, 1, 1, 2, 3, 4) . str_repeat("\0", 512)));
    },
];
