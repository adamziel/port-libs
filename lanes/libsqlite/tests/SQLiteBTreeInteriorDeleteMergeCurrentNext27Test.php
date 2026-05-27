<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 12): string {
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
    $page = substr_replace($page, pack('N', 3), 52, 4);

    return $page;
};

$pointerMapPage = static function (array $parents, int $pageSize = 512): string {
    $page = str_repeat("\0", $pageSize);
    foreach ($parents as $pageNumber => [$type, $parentPageNumber]) {
        $page = substr_replace($page, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
    }

    return $page;
};

$makeDatabase = static function (int $base = 100) use ($makeFirstPage, $pointerMapPage): SQLiteDatabase {
    $pageSize = 512;
    $parents = [
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        7 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        8 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        9 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        10 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        11 => [SQLitePointerMapEntry::BTREE_PAGE, 10],
        12 => [SQLitePointerMapEntry::BTREE_PAGE, 10],
    ];
    $parent = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(4, $base + 100),
        SQLiteTableInteriorCell::encode(5, $base + 300),
    ], 10, $pageSize);
    $current = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(6, $base + 10),
    ], 7, $pageSize);
    $next = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(8, $base + 200),
    ], 9, $pageSize);
    $tail = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(11, $base + 500),
    ], 12, $pageSize);

    return SQLiteDatabase::fromBytes(
        $makeFirstPage($pageSize, 12)
        . $pointerMapPage($parents, $pageSize)
        . $parent
        . $current
        . $next
        . str_repeat("\0", $pageSize * 4)
        . $tail
        . str_repeat("\0", $pageSize * 2),
    );
};

$withPageImages = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tableInteriorKeys = static function (SQLiteDatabase $database, int $pageNumber): array {
    $header = SQLiteBTreePageHeader::parsePage($database->page($pageNumber), $database->header->pageSize, $pageNumber === 1 ? 100 : 0);

    return array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, SQLiteTableInteriorCell::parsePageCells($database->page($pageNumber), $header));
};

$tableInteriorChildren = static function (SQLiteDatabase $database, int $pageNumber): array {
    $header = SQLiteBTreePageHeader::parsePage($database->page($pageNumber), $database->header->pageSize, $pageNumber === 1 ? 100 : 0);
    $children = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, SQLiteTableInteriorCell::parsePageCells($database->page($pageNumber), $header));
    $children[] = $header->rightMostPointer;

    return $children;
};

$tests = [
    'btree interior delete merge current next27 rewrites parent divider and frees next sibling' => static function (TestRunner $t) use ($makeDatabase, $withPageImages, $tableInteriorKeys, $tableInteriorChildren): void {
        $database = $makeDatabase();
        $plan = SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 3, 4, true);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(SQLiteBTreeInteriorMergeApplicationPlan::class, get_class($plan));
        $t->same('table-interior-sibling-merge-apply', $summary['action']);
        $t->same(4, $summary['merged_page']);
        $t->same(5, $summary['obsolete_page']);
        $t->same(3, $summary['parent_page']);
        $t->same(0, $summary['parent_divider_cell_index']);
        $t->same(true, $summary['parent_page_updated']);
        $t->same([6, 7, 8, 9], $summary['merged_child_page_numbers']);
        $t->same([5], $summary['freed_pages']);
        $t->same([1, 2, 3, 4, 5], $summary['updated_page_numbers']);
        $t->same([110, 200, 300], $tableInteriorKeys($postDatabase, 4));
        $t->same([6, 7, 8, 9], $tableInteriorChildren($postDatabase, 4));
        $t->same([400], $tableInteriorKeys($postDatabase, 3));
        $t->same([4, 10], $tableInteriorChildren($postDatabase, 3));
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(5)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(5)->parentPageNumber);
        $t->same(4, $postDatabase->pointerMapEntryForPage(8)->parentPageNumber);
        $t->same(4, $postDatabase->pointerMapEntryForPage(9)->parentPageNumber);
        $t->same(3, $postDatabase->pointerMapEntryForPage(10)->parentPageNumber);
    },
    'btree interior delete merge current next27 can merge the middle child with the parent rightmost next sibling' => static function (TestRunner $t) use ($makeDatabase, $withPageImages, $tableInteriorKeys, $tableInteriorChildren): void {
        $database = $makeDatabase(1000);
        $plan = SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 3, 5, true);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(5, $summary['merged_page']);
        $t->same(10, $summary['obsolete_page']);
        $t->same(1, $summary['parent_divider_cell_index']);
        $t->same([8, 9, 11, 12], $summary['merged_child_page_numbers']);
        $t->same([1100], $tableInteriorKeys($postDatabase, 3));
        $t->same([4, 5], $tableInteriorChildren($postDatabase, 3));
        $t->same([1200, 1300, 1500], $tableInteriorKeys($postDatabase, 5));
        $t->same([8, 9, 11, 12], $tableInteriorChildren($postDatabase, 5));
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(10)->typeName());
        $t->same(5, $postDatabase->pointerMapEntryForPage(11)->parentPageNumber);
        $t->same(5, $postDatabase->pointerMapEntryForPage(12)->parentPageNumber);
    },
    'btree interior delete merge current next27 rejects missing current child and missing next sibling' => static function (TestRunner $t) use ($makeDatabase): void {
        $database = $makeDatabase();

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 3, 99));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 3, 10));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 4, 6));
    },
];

for ($index = 0; $index < 48; $index++) {
    $base = 2000 + ($index * 20);
    $currentPage = $index % 2 === 0 ? 4 : 5;
    $tests['btree interior delete merge current next27 generated parent rewrite case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($base, $currentPage, $makeDatabase, $withPageImages, $tableInteriorKeys, $tableInteriorChildren): void {
        $database = $makeDatabase($base);
        $plan = SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 3, $currentPage, true);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(true, $summary['parent_page_updated']);
        if ($currentPage === 4) {
            $t->same([$base + 300], $tableInteriorKeys($postDatabase, 3));
            $t->same([4, 10], $tableInteriorChildren($postDatabase, 3));
            $t->same([$base + 10, $base + 100, $base + 200], $tableInteriorKeys($postDatabase, 4));
        } else {
            $t->same([$base + 100], $tableInteriorKeys($postDatabase, 3));
            $t->same([4, 5], $tableInteriorChildren($postDatabase, 3));
            $t->same([$base + 200, $base + 300, $base + 500], $tableInteriorKeys($postDatabase, 5));
        }
    };
}

return $tests;
