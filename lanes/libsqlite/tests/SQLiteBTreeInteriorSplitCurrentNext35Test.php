<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorSplitCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 14): string {
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

$makeDatabase = static function (int $base = 100, int $currentPage = 4, int $nextPage = 12) use ($makeFirstPage, $pointerMapPage): SQLiteDatabase {
    $pageSize = 512;
    $parents = [
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        7 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        8 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        9 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        10 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        11 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        13 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        14 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    ];
    $parent = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(4, $base + 900),
    ], 5, $pageSize);
    $current = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(6, $base + 10),
        SQLiteTableInteriorCell::encode(7, $base + 20),
        SQLiteTableInteriorCell::encode(8, $base + 30),
        SQLiteTableInteriorCell::encode(9, $base + 40),
    ], 10, $pageSize);
    $right = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(11, $base + 1000),
    ], 14, $pageSize);
    $pages = [
        1 => $makeFirstPage($pageSize, 14),
        2 => $pointerMapPage($parents, $pageSize),
        3 => $parent,
        4 => $currentPage === 4 ? $current : $right,
        5 => $currentPage === 5 ? $current : $right,
        6 => str_repeat("\x06", $pageSize),
        7 => str_repeat("\x07", $pageSize),
        8 => str_repeat("\x08", $pageSize),
        9 => str_repeat("\x09", $pageSize),
        10 => str_repeat("\x0a", $pageSize),
        11 => str_repeat("\x0b", $pageSize),
        12 => str_repeat("\0", $pageSize),
        13 => str_repeat("\0", $pageSize),
        14 => str_repeat("\x0e", $pageSize),
    ];
    if ($currentPage === 5) {
        $pages[3] = SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode(4, $base + 100),
        ], 5, $pageSize);
        $parents[5] = [SQLitePointerMapEntry::BTREE_PAGE, 3];
        $pages[2] = $pointerMapPage($parents, $pageSize);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$withPageImages = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    $pageCount = max($database->pageCount(), max(array_keys($pageImages)));
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? ($pageNumber <= $database->pageCount() ? $database->page($pageNumber) : str_repeat("\0", $database->header->pageSize));
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
    'btree interior split current next35 splits current interior page and inserts parent divider' => static function (TestRunner $t) use ($makeDatabase, $withPageImages, $tableInteriorKeys, $tableInteriorChildren): void {
        $database = $makeDatabase();
        $plan = SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 3, 4, 12);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(SQLiteBTreeInteriorSplitCurrentNextPlan::class, get_class($plan));
        $t->same('table-interior-current-next-split', $summary['action']);
        $t->same(3, $summary['parent_page']);
        $t->same(4, $summary['current_page']);
        $t->same(12, $summary['next_page']);
        $t->same(130, $summary['inserted_divider_key']);
        $t->same(0, $summary['parent_divider_cell_index']);
        $t->same([110, 120], $tableInteriorKeys($postDatabase, 4));
        $t->same([6, 7, 8], $tableInteriorChildren($postDatabase, 4));
        $t->same([140], $tableInteriorKeys($postDatabase, 12));
        $t->same([9, 10], $tableInteriorChildren($postDatabase, 12));
        $t->same([130, 1000], $tableInteriorKeys($postDatabase, 3));
        $t->same([4, 12, 5], $tableInteriorChildren($postDatabase, 3));
        $t->same('btree-page', $postDatabase->pointerMapEntryForPage(12)->typeName());
        $t->same(3, $postDatabase->pointerMapEntryForPage(12)->parentPageNumber);
        $t->same(12, $postDatabase->pointerMapEntryForPage(9)->parentPageNumber);
        $t->same(12, $postDatabase->pointerMapEntryForPage(10)->parentPageNumber);
        $t->same(4, $postDatabase->pointerMapEntryForPage(8)->parentPageNumber);
        $t->same([3, 4, 12], array_values(array_filter($summary['updated_page_numbers'], static fn (int $page): bool => $page !== 2)));
    },
    'btree interior split current next35 can split parent rightmost current child' => static function (TestRunner $t) use ($makeDatabase, $withPageImages, $tableInteriorKeys, $tableInteriorChildren): void {
        $database = $makeDatabase(500, 5, 13);
        $plan = SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 3, 5, 13);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same(530, $summary['inserted_divider_key']);
        $t->same(1, $summary['parent_divider_cell_index']);
        $t->same([600, 530], $tableInteriorKeys($postDatabase, 3));
        $t->same([4, 5, 13], $tableInteriorChildren($postDatabase, 3));
        $t->same([510, 520], $tableInteriorKeys($postDatabase, 5));
        $t->same([540], $tableInteriorKeys($postDatabase, 13));
        $t->same(3, $postDatabase->pointerMapEntryForPage(13)->parentPageNumber);
        $t->same(13, $postDatabase->pointerMapEntryForPage(9)->parentPageNumber);
        $t->same(13, $postDatabase->pointerMapEntryForPage(10)->parentPageNumber);
    },
    'btree interior split current next35 rejects invalid parent current and next pages' => static function (TestRunner $t) use ($makeDatabase): void {
        $database = $makeDatabase();

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 3, 99, 12));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 3, 4, 5));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 4, 4, 12));
    },
];

for ($index = 0; $index < 50; $index++) {
    $base = 1000 + ($index * 10);
    $nextPage = $index % 2 === 0 ? 12 : 13;
    $tests['btree interior split current next35 generated split case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($base, $nextPage, $makeDatabase, $withPageImages, $tableInteriorKeys, $tableInteriorChildren): void {
        $database = $makeDatabase($base, 4, $nextPage);
        $plan = SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 3, 4, $nextPage);
        $postDatabase = $withPageImages($database, $plan->pageImages);
        $summary = $plan->toArray();

        $t->same($base + 30, $summary['inserted_divider_key']);
        $t->same([$base + 10, $base + 20], $tableInteriorKeys($postDatabase, 4));
        $t->same([$base + 40], $tableInteriorKeys($postDatabase, $nextPage));
        $t->same([4, $nextPage, 5], $tableInteriorChildren($postDatabase, 3));
        $t->same($nextPage, $postDatabase->pointerMapEntryForPage(9)->parentPageNumber);
    };
}

return $tests;
