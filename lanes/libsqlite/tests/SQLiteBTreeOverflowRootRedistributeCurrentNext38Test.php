<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowRootRedistributePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageSize, int $databaseSizePages, bool $autoVacuum): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', $autoVacuum ? 3 : 1), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$payload = static fn (string $name, string $value, string $autoload = 'yes'): string => SQLiteRecord::encode([null, $name, $value, $autoload]);

$pointerMapPage = static function (array $entries, int $pageSize = 512): string {
    $page = str_repeat("\0", $pageSize);
    foreach ($entries as $pageNumber => $entry) {
        $offset = 5 * ($pageNumber - 3);
        $page = substr_replace($page, chr($entry[0]) . pack('N', $entry[1]), $offset, 5);
    }

    return $page;
};

$makeDatabase = static function (int $base = 0, bool $autoVacuum = true) use ($makeFirstPage, $payload, $pointerMapPage): array {
    $pageSize = 512;
    $largePayload = SQLiteRecord::encode([
        null,
        '_transient_root_redistribute_' . $base,
        str_repeat('root-current-next38-' . $base . ':', 66),
        'no',
    ]);
    $large = SQLiteTableLeafCell::encodeWithOverflowPages(10 + $base, $largePayload, 7, $pageSize);
    $currentLeaf = SQLiteTableLeafPage::assemble([
        $large['cell'],
        SQLiteTableLeafCell::encode(20 + $base, $payload('_transient_keep_' . $base, 'keep'), $pageSize),
    ], $pageSize);
    $nextLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(30 + $base, $payload('autoload_next_' . $base . '_30', 'yes'), $pageSize),
        SQLiteTableLeafCell::encode(40 + $base, $payload('autoload_next_' . $base . '_40', 'yes'), $pageSize),
        SQLiteTableLeafCell::encode(50 + $base, $payload('autoload_next_' . $base . '_50', 'yes'), $pageSize),
        SQLiteTableLeafCell::encode(60 + $base, $payload('autoload_next_' . $base . '_60', 'yes'), $pageSize),
    ], $pageSize);
    $tailLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(9000 + $base, $payload('tail_' . $base, 'tail'), $pageSize),
    ], $pageSize);
    $root = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(4, 20 + $base),
        SQLiteTableInteriorCell::encode(5, 8999 + $base),
    ], 6, $pageSize);
    $overflowPages = array_combine(range(7, 6 + count($large['overflowPages'])), $large['overflowPages']);

    $pointerMapEntries = [
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    ];
    for ($pageNumber = 8; $pageNumber <= 6 + count($large['overflowPages']); $pageNumber++) {
        $pointerMapEntries[$pageNumber] = [SQLitePointerMapEntry::OVERFLOW_PAGE, $pageNumber - 1];
    }

    $pages = [
        1 => $makeFirstPage($pageSize, 6 + count($large['overflowPages']), $autoVacuum),
        2 => $autoVacuum ? $pointerMapPage($pointerMapEntries, $pageSize) : str_repeat("\0", $pageSize),
        3 => $root,
        4 => $currentLeaf,
        5 => $nextLeaf,
        6 => $tailLeaf,
    ];
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    ksort($pages);

    return [SQLiteDatabase::fromBytes(implode('', $pages)), $overflowPages];
};

$withPageImages = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$rowIdsFor = static function (SQLiteDatabase $database, int $pageNumber): array {
    $page = $database->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize, $pageNumber === 1 ? 100 : 0);

    return array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($page, $header, $database->usablePageSize(), static fn (int $_firstOverflowPage, int $byteCount): string => str_repeat("\0", $byteCount)),
    );
};

$parentKeysFor = static function (SQLiteDatabase $database): array {
    $header = SQLiteBTreePageHeader::parsePage($database->page(3), $database->header->pageSize);

    return array_map(
        static fn (SQLiteTableInteriorCell $cell): array => ['leftChild' => $cell->leftChildPage, 'key' => $cell->key],
        SQLiteTableInteriorCell::parsePageCells($database->page(3), $header),
    );
};

$overflowPageNumbers = static function (array $overflowPages, int $firstOverflowPage, int $byteCount): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $page = $overflowPages[$pageNumber] ?? null;
        if ($page === null) {
            throw new InvalidArgumentException('Fixture overflow page is missing');
        }
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};

$tests = [
    'applies overflow-backed current leaf delete redistribution under root and releases overflow freelist' => static function (TestRunner $t) use ($makeDatabase, $overflowPageNumbers, $withPageImages, $rowIdsFor, $parentKeysFor): void {
        [$database, $overflowPages] = $makeDatabase(1000);
        $plan = SQLiteBTreeOverflowRootRedistributePlan::deleteCurrentAndRedistributeNext(
            $database,
            3,
            4,
            5,
            0,
            1010,
            static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($overflowPages, $firstOverflowPage, $byteCount),
            true,
        );
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(SQLiteBTreeOverflowRootRedistributePlan::class, get_class($plan));
        $t->same('overflow-root-redistribute-current-next', $summary['action']);
        $t->same($summary['obsolete_overflow_pages'], $summary['freed_pages']);
        $t->same(count($summary['freed_pages']), $summary['freelist_page_count']);
        $t->same($summary['freed_pages'][0], $summary['first_freelist_trunk_page']);
        $t->same([$summary['freed_pages'][0]], $summary['updated_freelist_page_numbers']);
        $t->same([2], $summary['updated_pointer_map_page_numbers']);
        $t->same(array_slice($summary['freed_pages'], 1), $summary['secure_delete_cleared_pages']);
        $t->same([1, 2, 3, 4, 5, ...$summary['freed_pages']], $summary['updated_page_numbers']);
        $t->same([1020, 1030, 1040], $rowIdsFor($postDatabase, 4));
        $t->same([1050, 1060], $rowIdsFor($postDatabase, 5));
        $t->same([
            ['leftChild' => 4, 'key' => 1040],
            ['leftChild' => 5, 'key' => 9999],
        ], $parentKeysFor($postDatabase));
        foreach ($summary['freed_pages'] as $freedPageNumber) {
            $t->same('free-page', $postDatabase->pointerMapEntryForPage($freedPageNumber)->typeName());
        }
        $t->same(str_repeat("\0", 512), $postDatabase->page($summary['freed_pages'][1]));
    },
    'rejects root redistribution when the deleted row has no obsolete overflow pages' => static function (TestRunner $t) use ($makeDatabase): void {
        [$database] = $makeDatabase(2000);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowRootRedistributePlan::deleteCurrentAndRedistributeNext(
            $database,
            3,
            4,
            5,
            0,
            2020,
            static fn (): array => [],
        ));
    },
];

for ($index = 0; $index < 50; $index++) {
    $base = 1000 + ($index * 100);
    $tests['applies generated overflow root current next redistribution case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($base, $makeDatabase, $overflowPageNumbers, $withPageImages, $rowIdsFor): void {
        [$database, $overflowPages] = $makeDatabase($base);
        $plan = SQLiteBTreeOverflowRootRedistributePlan::deleteCurrentAndRedistributeNext(
            $database,
            3,
            4,
            5,
            0,
            $base + 10,
            static fn (int $firstOverflowPage, int $byteCount): array => $overflowPageNumbers($overflowPages, $firstOverflowPage, $byteCount),
            true,
        );
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same($base + 10, $summary['deleted_rowid']);
        $t->same([$base + 20, $base + 30, $base + 40], $rowIdsFor($postDatabase, 4));
        $t->same([$base + 50, $base + 60], $rowIdsFor($postDatabase, 5));
        $t->same($summary['obsolete_overflow_pages'], $summary['freed_pages']);
        $t->same(2, $summary['moved_cell_count']);
        $t->same('free-page', $postDatabase->pointerMapEntryForPage($summary['freed_pages'][0])->typeName());
        $t->same('free-page', $postDatabase->pointerMapEntryForPage($summary['freed_pages'][1])->typeName());
    };
}

return $tests;
