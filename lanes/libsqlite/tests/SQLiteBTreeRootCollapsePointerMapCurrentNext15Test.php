<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeRootCollapsePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteTableRow;

$makeFirstPage = static function (int $pageSize, int $databaseSizePages): string {
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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize): void {
    $usableSize = $pageSize;
    $stride = intdiv($usableSize, 5) + 1;
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

$fixture = static function () use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 106;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount);
    $pages[3] = SQLiteTableInteriorPage::assemble([], 4, $pageSize);
    $pages[4] = SQLiteTableInteriorPage::assemble([], 106, $pageSize);
    $pages[106] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
    ], $pageSize);

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0, $pageSize);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3, $pageSize);
    $putPointerMapEntry($pages, 106, SQLitePointerMapEntry::BTREE_PAGE, 4, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeRootCollapsePlan::collapseOnlyChild($database, 3, true);
    foreach ($plan->pageImages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
    $rootHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);
    $rows = array_map(
        static fn (SQLiteTableLeafCell $cell): array => SQLiteTableRow::fromTableLeafCell($cell)->values(),
        SQLiteTableLeafCell::parsePageCells($postDatabase->page(106), SQLiteBTreePageHeader::parsePage($postDatabase->page(106), $pageSize)),
    );

    return [$database, $plan, $postDatabase, $rootHeader, $rows];
};

$cases = [
    'plan class is root collapse' => static fn (array $fx): string => get_class($fx[1]),
    'summary action is root collapse apply' => static fn (array $fx): string => $fx[1]->toArray()['action'],
    'root page is preserved' => static fn (array $fx): int => $fx[1]->rootPageNumber,
    'obsolete child is freed' => static fn (array $fx): int => $fx[1]->obsoleteChildPageNumber,
    'root started as table interior' => static fn (array $fx): string => $fx[1]->rootBeforeType,
    'root remains table interior after child copy' => static fn (array $fx): string => $fx[1]->rootAfterType,
    'root started empty' => static fn (array $fx): int => $fx[1]->rootBeforeCellCount,
    'root remains empty with one rightmost child' => static fn (array $fx): int => $fx[1]->rootAfterCellCount,
    'adopted grandchild is reported' => static fn (array $fx): array => $fx[1]->childPageNumbers,
    'pointer-map update targets grandchild' => static fn (array $fx): array => array_keys($fx[1]->pointerMapUpdates),
    'grandchild becomes child of root' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[106]['parent_page_number'],
    'grandchild pointer-map type remains btree page' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[106]['type'],
    'summary includes both pointer-map pages' => static fn (array $fx): array => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'plan exposes both pointer-map pages' => static fn (array $fx): array => $fx[1]->updatedPointerMapPageNumbers,
    'updated pages include first page' => static fn (array $fx): bool => array_key_exists(1, $fx[1]->pageImages),
    'updated pages include first pointer-map page' => static fn (array $fx): bool => array_key_exists(2, $fx[1]->pageImages),
    'updated pages include collapsed root' => static fn (array $fx): bool => array_key_exists(3, $fx[1]->pageImages),
    'updated pages include obsolete child trunk' => static fn (array $fx): bool => array_key_exists(4, $fx[1]->pageImages),
    'updated pages include second pointer-map page' => static fn (array $fx): bool => array_key_exists(105, $fx[1]->pageImages),
    'updated pages omit unchanged grandchild page' => static fn (array $fx): bool => array_key_exists(106, $fx[1]->pageImages),
    'updated page number list is sorted' => static fn (array $fx): array => $fx[1]->updatedPageNumbers(),
    'summary updated page list is sorted' => static fn (array $fx): array => $fx[1]->toArray()['updated_page_numbers'],
    'freed page list contains obsolete child' => static fn (array $fx): array => $fx[1]->freePlan->freedPageNumbers,
    'freelist count increments for child page' => static fn (array $fx): int => $fx[1]->freePlan->freelistPageCount,
    'first freelist trunk is obsolete child' => static fn (array $fx): int => $fx[1]->freePlan->firstFreelistTrunkPage,
    'free plan only reports first pointer-map page' => static fn (array $fx): array => array_keys($fx[1]->freePlan->updatedPointerMapPages),
    'root header rightmost points to adopted grandchild' => static fn (array $fx): int => $fx[3]->rightMostPointer,
    'post root header remains table interior' => static fn (array $fx): string => $fx[3]->pageType,
    'post root header remains empty' => static fn (array $fx): int => $fx[3]->cellCount,
    'post header first freelist trunk' => static fn (array $fx): int => $fx[2]->header->firstFreelistTrunkPage,
    'post header freelist page count' => static fn (array $fx): int => $fx[2]->header->freelistPageCount,
    'post freelist pages' => static fn (array $fx): array => $fx[2]->freelistPageNumbers(),
    'post freelist allocation order' => static fn (array $fx): array => $fx[2]->freelistAllocationOrder(),
    'obsolete child pointer-map type is free page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(4)->typeName(),
    'obsolete child pointer-map parent is cleared' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(4)->parentPageNumber,
    'grandchild pointer-map type is btree page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(106)->typeName(),
    'grandchild pointer-map parent is root' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(106)->parentPageNumber,
    'first pointer-map entry offset is low page' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(4)->pointerMapPageNumber,
    'second pointer-map entry offset is high page' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(106)->pointerMapPageNumber,
    'summary freed pages' => static fn (array $fx): array => $fx[1]->toArray()['freed_pages'],
    'summary first freelist trunk' => static fn (array $fx): int => $fx[1]->toArray()['first_freelist_trunk_page'],
    'summary secure-delete list remains empty for new trunk' => static fn (array $fx): array => $fx[1]->toArray()['secure_delete_cleared_pages'],
    'header parsed from updated first page has freelist count' => static fn (array $fx): int => SQLiteHeader::parse($fx[1]->pageImages[1])->freelistPageCount,
    'header parsed from updated first page has first trunk' => static fn (array $fx): int => SQLiteHeader::parse($fx[1]->pageImages[1])->firstFreelistTrunkPage,
    'grandchild table leaf still has two rows' => static fn (array $fx): int => count($fx[4]),
    'first grandchild row name survives' => static fn (array $fx): string => $fx[4][0][1],
    'second grandchild row name survives' => static fn (array $fx): string => $fx[4][1][1],
    'database page count is unchanged' => static fn (array $fx): int => $fx[2]->pageCount(),
    'database size header is unchanged' => static fn (array $fx): int => $fx[2]->header->databaseSizePages,
];

$expected = [
    'plan class is root collapse' => SQLiteBTreeRootCollapsePlan::class,
    'summary action is root collapse apply' => 'root-collapse-apply',
    'root page is preserved' => 3,
    'obsolete child is freed' => 4,
    'root started as table interior' => 'table-interior',
    'root remains table interior after child copy' => 'table-interior',
    'root started empty' => 0,
    'root remains empty with one rightmost child' => 0,
    'adopted grandchild is reported' => [106],
    'pointer-map update targets grandchild' => [106],
    'grandchild becomes child of root' => 3,
    'grandchild pointer-map type remains btree page' => SQLitePointerMapEntry::BTREE_PAGE,
    'summary includes both pointer-map pages' => [2, 105],
    'plan exposes both pointer-map pages' => [2, 105],
    'updated pages include first page' => true,
    'updated pages include first pointer-map page' => true,
    'updated pages include collapsed root' => true,
    'updated pages include obsolete child trunk' => true,
    'updated pages include second pointer-map page' => true,
    'updated pages omit unchanged grandchild page' => false,
    'updated page number list is sorted' => [1, 2, 3, 4, 105],
    'summary updated page list is sorted' => [1, 2, 3, 4, 105],
    'freed page list contains obsolete child' => [4],
    'freelist count increments for child page' => 1,
    'first freelist trunk is obsolete child' => 4,
    'free plan only reports first pointer-map page' => [2],
    'root header rightmost points to adopted grandchild' => 106,
    'post root header remains table interior' => 'table-interior',
    'post root header remains empty' => 0,
    'post header first freelist trunk' => 4,
    'post header freelist page count' => 1,
    'post freelist pages' => [4],
    'post freelist allocation order' => [4],
    'obsolete child pointer-map type is free page' => 'free-page',
    'obsolete child pointer-map parent is cleared' => 0,
    'grandchild pointer-map type is btree page' => 'btree-page',
    'grandchild pointer-map parent is root' => 3,
    'first pointer-map entry offset is low page' => 2,
    'second pointer-map entry offset is high page' => 105,
    'summary freed pages' => [4],
    'summary first freelist trunk' => 4,
    'summary secure-delete list remains empty for new trunk' => [],
    'header parsed from updated first page has freelist count' => 1,
    'header parsed from updated first page has first trunk' => 4,
    'grandchild table leaf still has two rows' => 2,
    'first grandchild row name survives' => 'siteurl',
    'second grandchild row name survives' => 'home',
    'database page count is unchanged' => 106,
    'database size header is unchanged' => 106,
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['root collapse current-next15 ' . $name] = static function (TestRunner $t) use ($fixture, $read, $expected, $name): void {
        $t->same($expected[$name], $read($fixture()));
    };
}

return $tests;
