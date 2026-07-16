<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionApplyPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

$firstPage = static function (int $pageCount, int $pageSize = 512): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 12), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$tableDatabase = static function () use ($firstPage, $putPointerMapEntry): SQLiteDatabase {
    $pageSize = 512;
    $pages = array_fill(1, 18, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(18, $pageSize);
    $pages[3] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(7, 20),
        SQLiteTableInteriorCell::encode(8, 60),
    ], 9, $pageSize);
    $pages[7] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(10, 10),
    ], 11, $pageSize);
    $pages[8] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(12, 30),
        SQLiteTableInteriorCell::encode(13, 40),
        SQLiteTableInteriorCell::encode(14, 50),
    ], 15, $pageSize);
    $pages[9] = SQLiteTableInteriorPage::assemble([
        SQLiteTableInteriorCell::encode(16, 70),
    ], 17, $pageSize);

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 9 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 8, 15 => 8, 16 => 9, 17 => 9] as $pageNumber => $parent) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent, $pageSize);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payload = static fn (string $autoload, string $name, int $rowid): string => SQLiteRecord::encode([$autoload, $name, $rowid]);
$indexDatabase = static function () use ($firstPage, $putPointerMapEntry, $payload): SQLiteDatabase {
    $pageSize = 512;
    $pages = array_fill(1, 18, str_repeat("\0", $pageSize));
    $pages[1] = $firstPage(18, $pageSize);
    $dividerOne = $payload('no', '_transient_timeout_feed', 20);
    $dividerTwo = $payload('yes', 'template', 60);
    $pages[3] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($dividerOne, $pageSize, null, 7),
        SQLiteIndexCell::encode($dividerTwo, $pageSize, null, 8),
    ], 9, $pageSize);
    $pages[7] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('no', '_transient_feed', 10), $pageSize, null, 10),
    ], 11, $pageSize);
    $pages[8] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('yes', 'blogname', 30), $pageSize, null, 12),
        SQLiteIndexCell::encode($payload('yes', 'siteurl', 40), $pageSize, null, 13),
        SQLiteIndexCell::encode($payload('yes', 'stylesheet', 50), $pageSize, null, 14),
    ], 15, $pageSize);
    $pages[9] = SQLiteIndexInteriorPage::assemble([
        SQLiteIndexCell::encode($payload('yes', 'upload_path', 70), $pageSize, null, 16),
    ], 17, $pageSize);

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 9 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 8, 15 => 8, 16 => 9, 17 => 9] as $pageNumber => $parent) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent, $pageSize);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tablePlan = static fn (): SQLiteBTreeInteriorRedistributionApplyPlan => SQLiteBTreeInteriorRedistributionApplyPlan::tableCurrentAndNext($tableDatabase(), 3, 7);
$indexPlan = static fn (): SQLiteBTreeInteriorRedistributionApplyPlan => SQLiteBTreeInteriorRedistributionApplyPlan::indexCurrentAndNext($indexDatabase(), 3, 7);

$tableChildren = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, SQLiteTableInteriorCell::parsePageCells($page, $header));
    $children[] = $header->rightMostPointer;

    return $children;
};

$tableKeys = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, SQLiteTableInteriorCell::parsePageCells($page, $header));
};

$indexChildren = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    $children = array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage ?? 0, SQLiteIndexCell::parsePageCells($page, $header, 512));
    $children[] = $header->rightMostPointer;

    return $children;
};

$indexValues = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);

    return array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, SQLiteIndexCell::parsePageCells($page, $header, 512));
};

$exceptionMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return 'no exception';
};

$readers = [
    'table action' => static fn () => $tablePlan()->toArray()['action'],
    'table current page selected' => static fn () => $tablePlan()->redistributionPlan->leftPageNumber,
    'table next page selected' => static fn () => $tablePlan()->redistributionPlan->rightPageNumber,
    'table divider selected from parent' => static fn () => $tablePlan()->redistributionPlan->oldDividerKey,
    'table divider rewritten' => static fn () => $tablePlan()->redistributionPlan->newDividerKey,
    'table updated pages' => static fn () => $tablePlan()->updatedPageNumbers(),
    'table pointer map pages' => static fn () => $tablePlan()->toArray()['updated_pointer_map_page_numbers'],
    'table moved child' => static fn () => $tablePlan()->toArray()['moved_child_page_numbers'],
    'table parent divider update old' => static fn () => $tablePlan()->parentDividerUpdate['old_separator'],
    'table parent divider update new' => static fn () => $tablePlan()->parentDividerUpdate['new_separator'],
    'table parent keys after apply' => static fn () => $tableKeys($tablePlan()->postDatabase->page(3)),
    'table parent children after apply' => static fn () => $tableChildren($tablePlan()->postDatabase->page(3)),
    'table current children after apply' => static fn () => $tableChildren($tablePlan()->postDatabase->page(7)),
    'table next children after apply' => static fn () => $tableChildren($tablePlan()->postDatabase->page(8)),
    'table untouched next-next children' => static fn () => $tableChildren($tablePlan()->postDatabase->page(9)),
    'table current keys after apply' => static fn () => $tableKeys($tablePlan()->postDatabase->page(7)),
    'table next keys after apply' => static fn () => $tableKeys($tablePlan()->postDatabase->page(8)),
    'table untouched next-next keys' => static fn () => $tableKeys($tablePlan()->postDatabase->page(9)),
    'table moved child parent' => static fn () => $tablePlan()->postDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'table unmoved child parent' => static fn () => $tablePlan()->postDatabase->pointerMapEntryForPage(15)->parentPageNumber,
    'table next-next parent unchanged' => static fn () => $tablePlan()->postDatabase->pointerMapEntryForPage(16)->parentPageNumber,
    'table pointer entry pages' => static fn () => array_column($tablePlan()->pointerMapEntries, 'page_number'),
    'table pointer entry type names' => static fn () => array_values(array_unique(array_column($tablePlan()->pointerMapEntries, 'type_name'))),
    'table page count' => static fn () => $tablePlan()->postDatabase->pageCount(),
    'table header page count' => static fn () => $tablePlan()->postDatabase->header->databaseSizePages,
    'table summary left children' => static fn () => $tablePlan()->toArray()['left_child_page_numbers'],
    'table summary right children' => static fn () => $tablePlan()->toArray()['right_child_page_numbers'],
    'table current cell count' => static fn () => SQLiteBTreePageHeader::parsePage($tablePlan()->postDatabase->page(7), 512)->cellCount,
    'table next cell count' => static fn () => SQLiteBTreePageHeader::parsePage($tablePlan()->postDatabase->page(8), 512)->cellCount,
    'table page images count' => static fn () => count($tablePlan()->pageImages),
    'index action' => static fn () => $indexPlan()->toArray()['action'],
    'index current page selected' => static fn () => $indexPlan()->redistributionPlan->leftPageNumber,
    'index next page selected' => static fn () => $indexPlan()->redistributionPlan->rightPageNumber,
    'index old divider values' => static fn () => $indexPlan()->redistributionPlan->oldDividerValues,
    'index new divider values' => static fn () => $indexPlan()->redistributionPlan->newDividerValues,
    'index updated pages' => static fn () => $indexPlan()->updatedPageNumbers(),
    'index pointer map pages' => static fn () => $indexPlan()->toArray()['updated_pointer_map_page_numbers'],
    'index moved child' => static fn () => $indexPlan()->toArray()['moved_child_page_numbers'],
    'index parent divider update old' => static fn () => $indexPlan()->parentDividerUpdate['old_separator'],
    'index parent divider update new' => static fn () => $indexPlan()->parentDividerUpdate['new_separator'],
    'index parent values after apply' => static fn () => $indexValues($indexPlan()->postDatabase->page(3)),
    'index parent children after apply' => static fn () => $indexChildren($indexPlan()->postDatabase->page(3)),
    'index current children after apply' => static fn () => $indexChildren($indexPlan()->postDatabase->page(7)),
    'index next children after apply' => static fn () => $indexChildren($indexPlan()->postDatabase->page(8)),
    'index untouched next-next children' => static fn () => $indexChildren($indexPlan()->postDatabase->page(9)),
    'index current values after apply' => static fn () => $indexValues($indexPlan()->postDatabase->page(7)),
    'index next values after apply' => static fn () => $indexValues($indexPlan()->postDatabase->page(8)),
    'index untouched next-next values' => static fn () => $indexValues($indexPlan()->postDatabase->page(9)),
    'index moved child parent' => static fn () => $indexPlan()->postDatabase->pointerMapEntryForPage(12)->parentPageNumber,
    'index unmoved child parent' => static fn () => $indexPlan()->postDatabase->pointerMapEntryForPage(15)->parentPageNumber,
    'index next-next parent unchanged' => static fn () => $indexPlan()->postDatabase->pointerMapEntryForPage(16)->parentPageNumber,
    'index pointer entry pages' => static fn () => array_column($indexPlan()->pointerMapEntries, 'page_number'),
    'index pointer entry type names' => static fn () => array_values(array_unique(array_column($indexPlan()->pointerMapEntries, 'type_name'))),
    'index page count' => static fn () => $indexPlan()->postDatabase->pageCount(),
    'index header page count' => static fn () => $indexPlan()->postDatabase->header->databaseSizePages,
    'index summary left children' => static fn () => $indexPlan()->toArray()['left_child_page_numbers'],
    'index summary right children' => static fn () => $indexPlan()->toArray()['right_child_page_numbers'],
    'index current cell count' => static fn () => SQLiteBTreePageHeader::parsePage($indexPlan()->postDatabase->page(7), 512)->cellCount,
    'index next cell count' => static fn () => SQLiteBTreePageHeader::parsePage($indexPlan()->postDatabase->page(8), 512)->cellCount,
    'index page images count' => static fn () => count($indexPlan()->pageImages),
    'rejects missing table current' => static fn () => $exceptionMessage(static fn () => SQLiteBTreeInteriorRedistributionApplyPlan::tableCurrentAndNext($tableDatabase(), 3, 99)),
    'rejects table rightmost current' => static fn () => $exceptionMessage(static fn () => SQLiteBTreeInteriorRedistributionApplyPlan::tableCurrentAndNext($tableDatabase(), 3, 9)),
    'rejects wrong table parent type' => static fn () => $exceptionMessage(static fn () => SQLiteBTreeInteriorRedistributionApplyPlan::tableCurrentAndNext($indexDatabase(), 3, 7)),
    'rejects missing index current' => static fn () => $exceptionMessage(static fn () => SQLiteBTreeInteriorRedistributionApplyPlan::indexCurrentAndNext($indexDatabase(), 3, 99)),
    'rejects index rightmost current' => static fn () => $exceptionMessage(static fn () => SQLiteBTreeInteriorRedistributionApplyPlan::indexCurrentAndNext($indexDatabase(), 3, 9)),
    'rejects wrong index parent type' => static fn () => $exceptionMessage(static fn () => SQLiteBTreeInteriorRedistributionApplyPlan::indexCurrentAndNext($tableDatabase(), 3, 7)),
];

$expected = [
    'table action' => 'table-interior-sibling-redistribute-apply',
    'table current page selected' => 7,
    'table next page selected' => 8,
    'table divider selected from parent' => 20,
    'table divider rewritten' => 30,
    'table updated pages' => [2, 3, 7, 8],
    'table pointer map pages' => [2],
    'table moved child' => [12],
    'table parent divider update old' => 20,
    'table parent divider update new' => 30,
    'table parent keys after apply' => [30, 60],
    'table parent children after apply' => [7, 8, 9],
    'table current children after apply' => [10, 11, 12],
    'table next children after apply' => [13, 14, 15],
    'table untouched next-next children' => [16, 17],
    'table current keys after apply' => [10, 20],
    'table next keys after apply' => [40, 50],
    'table untouched next-next keys' => [70],
    'table moved child parent' => 7,
    'table unmoved child parent' => 8,
    'table next-next parent unchanged' => 9,
    'table pointer entry pages' => [10, 11, 12, 13, 14, 15],
    'table pointer entry type names' => ['btree-page'],
    'table page count' => 18,
    'table header page count' => 18,
    'table summary left children' => [10, 11, 12],
    'table summary right children' => [13, 14, 15],
    'table current cell count' => 2,
    'table next cell count' => 2,
    'table page images count' => 4,
    'index action' => 'index-interior-sibling-redistribute-apply',
    'index current page selected' => 7,
    'index next page selected' => 8,
    'index old divider values' => ['no', '_transient_timeout_feed', 20],
    'index new divider values' => ['yes', 'blogname', 30],
    'index updated pages' => [2, 3, 7, 8],
    'index pointer map pages' => [2],
    'index moved child' => [12],
    'index parent divider update old' => ['no', '_transient_timeout_feed', 20],
    'index parent divider update new' => ['yes', 'blogname', 30],
    'index parent values after apply' => [['yes', 'blogname', 30], ['yes', 'template', 60]],
    'index parent children after apply' => [7, 8, 9],
    'index current children after apply' => [10, 11, 12],
    'index next children after apply' => [13, 14, 15],
    'index untouched next-next children' => [16, 17],
    'index current values after apply' => [['no', '_transient_feed', 10], ['no', '_transient_timeout_feed', 20]],
    'index next values after apply' => [['yes', 'siteurl', 40], ['yes', 'stylesheet', 50]],
    'index untouched next-next values' => [['yes', 'upload_path', 70]],
    'index moved child parent' => 7,
    'index unmoved child parent' => 8,
    'index next-next parent unchanged' => 9,
    'index pointer entry pages' => [10, 11, 12, 13, 14, 15],
    'index pointer entry type names' => ['btree-page'],
    'index page count' => 18,
    'index header page count' => 18,
    'index summary left children' => [10, 11, 12],
    'index summary right children' => [13, 14, 15],
    'index current cell count' => 2,
    'index next cell count' => 2,
    'index page images count' => 4,
    'rejects missing table current' => 'SQLite b-tree current/next interior redistribution current child is not referenced by parent',
    'rejects table rightmost current' => 'SQLite b-tree current/next interior redistribution requires a next sibling to the right',
    'rejects wrong table parent type' => 'SQLite b-tree current/next interior redistribution requires a table interior parent',
    'rejects missing index current' => 'SQLite b-tree current/next interior redistribution current child is not referenced by parent',
    'rejects index rightmost current' => 'SQLite b-tree current/next interior redistribution requires a next sibling to the right',
    'rejects wrong index parent type' => 'SQLite b-tree current/next interior redistribution requires an index interior parent',
];

$tests = [];
foreach ($readers as $name => $reader) {
    $tests['btree interior redistribute pointermap current next72 ' . $name] = static function (TestRunner $t) use ($name, $reader, $expected): void {
        $t->same($expected[$name], $reader());
    };
}

return $tests;
