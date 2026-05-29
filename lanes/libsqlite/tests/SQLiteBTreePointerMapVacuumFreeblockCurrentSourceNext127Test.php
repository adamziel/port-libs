<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage127 = static function (int $pageCount, int $firstFreelistTrunkPage = 0, int $freelistPageCount = 0): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry127 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$overflowPage127 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$tableDatabase127 = static function () use ($makeFirstPage127, $putPointerMapEntry127, $overflowPage127): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage127(12);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(11, SQLiteRecord::encode([null, '_transient_timeout_next127', str_repeat('t', 120)])),
        SQLiteTableLeafCell::encode(12, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 30)])),
    ]);
    $pages[5] = "\r" . str_repeat("\0", 511);
    $pages[8] = $overflowPage127(9, 'H');
    $pages[9] = $overflowPage127(10, 'I');
    $pages[10] = $overflowPage127(11, 'J');
    $pages[11] = $overflowPage127(12, 'K');
    $pages[12] = $overflowPage127(0, 'L');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
        11 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 10],
        12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 11],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry127($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$indexDatabase127 = static function () use ($makeFirstPage127, $putPointerMapEntry127, $overflowPage127): SQLiteDatabase {
    $pages = array_fill(1, 10, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage127(10);
    $pages[2] = str_repeat("\0", 512);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['active_plugins', 10])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_next127', 11, str_repeat('i', 70)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['rewrite_rules', 12])),
    ]);
    $pages[5] = "\r" . str_repeat("\0", 511);
    $pages[7] = $overflowPage127(8, 'M');
    $pages[8] = $overflowPage127(9, 'N');
    $pages[9] = $overflowPage127(10, 'O');
    $pages[10] = $overflowPage127(0, 'P');

    foreach ([
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry127($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tablePlan127 = static function (int $maxTruncatedPages = 5) use ($tableDatabase127): SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan {
    $database = $tableDatabase127();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 11, secureDelete: true);

    return SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next127TableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 11,
            'obsolete_overflow_page_numbers' => [8, 9, 10, 11, 12],
        ],
        $maxTruncatedPages,
        true,
    );
};

$indexPlan127 = static function (int $maxTruncatedPages = 4) use ($indexDatabase127): SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan {
    $database = $indexDatabase127();
    $deletedPage = SQLiteIndexLeafPage::deleteCellByRecordValues(
        $database->page(4),
        ['_transient_timeout_next127', 11, str_repeat('i', 70)],
        secureDelete: true,
    );

    return SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next127IndexLeafFromDeleteResult(
        $database,
        4,
        [
            'page' => $deletedPage,
            'record_values' => ['_transient_timeout_next127', 11, str_repeat('i', 70)],
            'obsolete_overflow_page_numbers' => [7, 8, 9, 10],
        ],
        $maxTruncatedPages,
        true,
    );
};

$throwMessage127 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$tableRows127 = static fn (): array => $tablePlan127()->rows;
$indexRows127 = static fn (): array => $indexPlan127()->rows;

$cases127 = [
    'table action label' => static fn (): mixed => $tablePlan127()->toArray()['action'],
    'table leaf page type' => static fn (): mixed => $tablePlan127()->deletePlan->leafPageType,
    'table released pages' => static fn (): mixed => $tablePlan127()->releasedOverflowPages(),
    'table truncated pages' => static fn (): mixed => $tablePlan127()->truncatedReleasedOverflowPages(),
    'table surviving pages' => static fn (): mixed => $tablePlan127()->survivingReleasedOverflowPages(),
    'table final page count' => static fn (): mixed => $tablePlan127()->toArray()['final_database_page_count'],
    'table final freelist count' => static fn (): mixed => $tablePlan127()->toArray()['final_freelist_page_count'],
    'table final freelist pages' => static fn (): mixed => $tablePlan127()->nextDatabase->freelistPageNumbers(),
    'table updated pages' => static fn (): mixed => $tablePlan127()->updatedPageNumbers(),
    'table materialized byte length' => static fn (): mixed => $tablePlan127()->materializedApplySummary()['byte_length'],
    'table omitted tail pages' => static fn (): mixed => $tablePlan127()->materializedApplySummary()['omitted_truncated_page_numbers'],
    'table rows pages' => static fn (): mixed => array_column($tableRows127(), 'page_number'),
    'table row current next pointers' => static fn (): mixed => array_column($tableRows127(), 'current_overflow_next_page'),
    'table row current pointer types' => static fn (): mixed => array_column($tableRows127(), 'current_pointer_map_type'),
    'table row current pointer parents' => static fn (): mixed => array_column($tableRows127(), 'current_pointer_map_parent'),
    'table row freed pointer types' => static fn (): mixed => array_column($tableRows127(), 'freed_pointer_map_type'),
    'table row next pointer types' => static fn (): mixed => array_column($tableRows127(), 'next_pointer_map_type'),
    'table row truncated pointer types' => static fn (): mixed => array_column($tableRows127(), 'truncated_pointer_map_type'),
    'table row statuses' => static fn (): mixed => array_column($tableRows127(), 'vacuum_status'),
    'table row materialized flags' => static fn (): mixed => array_column($tableRows127(), 'materialized'),
    'table row truncated flags' => static fn (): mixed => array_column($tableRows127(), 'truncated'),
    'table freeblock bytes before positive' => static fn (): mixed => $tablePlan127()->deletePlan->freeblockBytesBefore > 0,
    'table freeblock bytes after zero' => static fn (): mixed => $tablePlan127()->deletePlan->freeblockBytesAfter,
    'table freeblock status ok' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($tablePlan127()->deletePlan->leafPageImage, 512)->freeblockIntegrityReport($tablePlan127()->deletePlan->leafPageImage)['status'],
    'table next rowids' => static fn (): mixed => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($tablePlan127()->deletePlan->leafPageImage, SQLiteBTreePageHeader::parsePage($tablePlan127()->deletePlan->leafPageImage, 512))),
    'table page twelve unavailable' => static function () use ($tablePlan127): string {
        try {
            $tablePlan127()->nextDatabase->page(12);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'table summary rows pages' => static fn (): mixed => array_column($tablePlan127()->toArray()['rows'], 'page_number'),
    'table low truncation survivors' => static fn (): mixed => $tablePlan127(2)->survivingReleasedOverflowPages(),
    'table low truncation truncated' => static fn (): mixed => $tablePlan127(2)->truncatedReleasedOverflowPages(),
    'index leaf page type' => static fn (): mixed => $indexPlan127()->deletePlan->leafPageType,
    'index released pages' => static fn (): mixed => $indexPlan127()->releasedOverflowPages(),
    'index truncated pages' => static fn (): mixed => $indexPlan127()->truncatedReleasedOverflowPages(),
    'index surviving pages' => static fn (): mixed => $indexPlan127()->survivingReleasedOverflowPages(),
    'index final page count' => static fn (): mixed => $indexPlan127()->toArray()['final_database_page_count'],
    'index final freelist pages' => static fn (): mixed => $indexPlan127()->nextDatabase->freelistPageNumbers(),
    'index updated pages' => static fn (): mixed => $indexPlan127()->updatedPageNumbers(),
    'index materialized byte length' => static fn (): mixed => $indexPlan127()->materializedApplySummary()['byte_length'],
    'index omitted tail pages' => static fn (): mixed => $indexPlan127()->materializedApplySummary()['omitted_truncated_page_numbers'],
    'index rows pages' => static fn (): mixed => array_column($indexRows127(), 'page_number'),
    'index row current next pointers' => static fn (): mixed => array_column($indexRows127(), 'current_overflow_next_page'),
    'index row current pointer types' => static fn (): mixed => array_column($indexRows127(), 'current_pointer_map_type'),
    'index row current pointer parents' => static fn (): mixed => array_column($indexRows127(), 'current_pointer_map_parent'),
    'index row statuses' => static fn (): mixed => array_column($indexRows127(), 'vacuum_status'),
    'index row materialized flags' => static fn (): mixed => array_column($indexRows127(), 'materialized'),
    'index row truncated flags' => static fn (): mixed => array_column($indexRows127(), 'truncated'),
    'index next names' => static fn (): mixed => array_map(static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0], SQLiteIndexCell::parsePageCells($indexPlan127()->deletePlan->leafPageImage, SQLiteBTreePageHeader::parsePage($indexPlan127()->deletePlan->leafPageImage, 512))),
    'index low truncation survivors' => static fn (): mixed => $indexPlan127(2)->survivingReleasedOverflowPages(),
    'index low truncation truncated' => static fn (): mixed => $indexPlan127(2)->truncatedReleasedOverflowPages(),
    'invalid table truncation rejected' => static fn (): mixed => $throwMessage127(static fn () => $tablePlan127(0)),
    'invalid from plan truncation rejected' => static function () use ($tablePlan127, $throwMessage127): mixed {
        $plan = $tablePlan127();

        return $throwMessage127(static fn () => SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next127FromDeletePlan($plan->sourceDatabase, $plan->deletePlan, 0));
    },
];

$expected127 = [
    'table action label' => 'btree-pointermap-vacuum-freeblock-current-source-next127',
    'table leaf page type' => 'table-leaf',
    'table released pages' => [8, 9, 10, 11, 12],
    'table truncated pages' => [8, 9, 10, 11, 12],
    'table surviving pages' => [],
    'table final page count' => 7,
    'table final freelist count' => 0,
    'table final freelist pages' => [],
    'table updated pages' => [1, 2, 3],
    'table materialized byte length' => 3584,
    'table omitted tail pages' => [12, 11, 10, 9, 8],
    'table rows pages' => [8, 9, 10, 11, 12],
    'table row current next pointers' => [9, 10, 11, 12, 0],
    'table row current pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'table row current pointer parents' => [3, 8, 9, 10, 11],
    'table row freed pointer types' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'table row next pointer types' => [null, null, null, null, null],
    'table row truncated pointer types' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'table row statuses' => ['truncated-from-database', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'table row materialized flags' => [false, false, false, false, false],
    'table row truncated flags' => [true, true, true, true, true],
    'table freeblock bytes before positive' => true,
    'table freeblock bytes after zero' => 0,
    'table freeblock status ok' => 'ok',
    'table next rowids' => [10, 12],
    'table page twelve unavailable' => 'unavailable',
    'table summary rows pages' => [8, 9, 10, 11, 12],
    'table low truncation survivors' => [8, 9, 10],
    'table low truncation truncated' => [11, 12],
    'index leaf page type' => 'index-leaf',
    'index released pages' => [7, 8, 9, 10],
    'index truncated pages' => [7, 8, 9, 10],
    'index surviving pages' => [],
    'index final page count' => 6,
    'index final freelist pages' => [],
    'index updated pages' => [1, 2, 4],
    'index materialized byte length' => 3072,
    'index omitted tail pages' => [10, 9, 8, 7],
    'index rows pages' => [7, 8, 9, 10],
    'index row current next pointers' => [8, 9, 10, 0],
    'index row current pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'index row current pointer parents' => [4, 7, 8, 9],
    'index row statuses' => ['truncated-from-database', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'index row materialized flags' => [false, false, false, false],
    'index row truncated flags' => [true, true, true, true],
    'index next names' => ['active_plugins', 'rewrite_rules'],
    'index low truncation survivors' => [7, 8],
    'index low truncation truncated' => [9, 10],
    'invalid table truncation rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
    'invalid from plan truncation rejected' => 'SQLite pointer-map vacuum freeblock next127 requires a positive truncation limit',
];

$tests = [];

foreach ($cases127 as $name => $callback) {
    $tests['btree pointermap vacuum freeblock current source next127 ' . $name] = static function (TestRunner $t) use ($callback, $expected127, $name): void {
        $t->same($expected127[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree pointermap vacuum freeblock current source next127 repeated invariant ' . $index] = static function (TestRunner $t) use ($tablePlan127, $index): void {
        $limit = ($index % 4) + 1;
        $plan = $tablePlan127($limit);
        $rows = $plan->rows;

        $t->same($plan->releasedOverflowPages(), array_column($rows, 'page_number'));
        $t->same($plan->truncatedReleasedOverflowPages(), array_values(array_filter(array_column($rows, 'page_number'), static fn (int $pageNumber): bool => $pageNumber > $plan->toArray()['final_database_page_count'])));
        $t->same([], array_values(array_filter($plan->survivingReleasedOverflowPages(), static fn (int $pageNumber): bool => $pageNumber > $plan->toArray()['final_database_page_count'])));
        $t->same([], array_values(array_filter($plan->truncatedReleasedOverflowPages(), static fn (int $pageNumber): bool => $pageNumber <= $plan->toArray()['final_database_page_count'])));
        $t->same($plan->rows, $plan->toArray()['rows']);
    };
}

return $tests;
