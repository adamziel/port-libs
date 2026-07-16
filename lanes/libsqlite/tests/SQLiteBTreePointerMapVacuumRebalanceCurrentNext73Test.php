<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafBatchFreePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\xff";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 7), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    $pointerMapPage = 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$fixture = static function () use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 1024;
    $pageCount = 210;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 4, 185);
    $pages[2] = str_repeat("\0", $pageSize);

    $fullExistingLeaves = range(20, 203);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(null, $fullExistingLeaves, $pageSize, $pageSize - 255);
    foreach ($fullExistingLeaves as $pageNumber) {
        $pages[$pageNumber] = str_repeat(chr($pageNumber % 251), $pageSize);
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }

    $tablePayload = SQLiteRecord::encode([null, '_transient_rebalance_batch', 'expired', 'no']);
    $tablePage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(41, $tablePayload, $pageSize),
    ], $pageSize);
    $tableDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
        $tablePage,
        41,
        static fn (): array => [],
        $pageSize,
        secureDelete: true,
    );
    $pages[3] = $tablePage;

    $indexRecord = ['_transient_rebalance_batch', 41];
    $indexPage = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode($indexRecord)),
    ], $pageSize);
    $indexDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
        $indexPage,
        $indexRecord,
        static fn (): array => [],
        $pageSize,
        secureDelete: true,
    );
    $pages[5] = $indexPage;

    foreach ([
        3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
        4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 8],
        7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        8 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber, $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
        ['leaf_page' => 3, 'leaf_page_type' => 'table-leaf', 'delete_result' => $tableDelete],
        ['leaf_page' => 5, 'leaf_page_type' => 'index-leaf', 'delete_result' => $indexDelete],
    ], true);

    $postPages = [];
    for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
        $postPages[$pageNumber] = $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
    }
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

    return [$database, $plan, $postDatabase, $tableDelete, $indexDelete];
};

$cases = [
    'current first trunk is existing full trunk' => static fn (array $fx): mixed => $fx[0]->header->firstFreelistTrunkPage,
    'current freelist count includes trunk plus full leaves' => static fn (array $fx): mixed => $fx[0]->header->freelistPageCount,
    'current allocation order starts with first existing full leaf' => static fn (array $fx): mixed => $fx[0]->freelistAllocationOrder()[0],
    'current allocation order ends with existing trunk' => static fn (array $fx): mixed => $fx[0]->freelistAllocationOrder()[184],
    'current table leaf pointer-map is btree page' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(3)->typeName(),
    'current table leaf pointer-map parent is table root' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(3)->parentPageNumber,
    'current index leaf pointer-map is btree page' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(5)->typeName(),
    'current index leaf pointer-map parent is index root' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(5)->parentPageNumber,
    'delete result table page is empty leaf' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[3]['page'], 1024)->cellCount,
    'delete result index page is empty leaf' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[4]['page'], 1024)->cellCount,
    'plan action is batch free' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'plan class is empty leaf batch free plan' => static fn (array $fx): mixed => get_class($fx[1]),
    'plan leaf delete count includes table and index leaves' => static fn (array $fx): mixed => $fx[1]->toArray()['leaf_delete_count'],
    'plan frees table leaf before index leaf' => static fn (array $fx): mixed => $fx[1]->freedPageNumbers,
    'plan promotes first deleted page into new trunk' => static fn (array $fx): mixed => $fx[1]->freePlan->newTrunkPageNumbers,
    'plan inserts second deleted page as freelist leaf' => static fn (array $fx): mixed => $fx[1]->freePlan->leafPageNumbers,
    'plan first freelist trunk becomes promoted table leaf' => static fn (array $fx): mixed => $fx[1]->freePlan->firstFreelistTrunkPage,
    'plan freelist count grows by two' => static fn (array $fx): mixed => $fx[1]->freePlan->freelistPageCount,
    'plan updated page numbers include header pointermap trunk and cleared leaf' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'plan page images include promoted trunk' => static fn (array $fx): mixed => array_key_exists(3, $fx[1]->pageImages),
    'plan page images include secure-cleared index leaf' => static fn (array $fx): mixed => array_key_exists(5, $fx[1]->pageImages),
    'summary new trunk page numbers expose promoted deleted leaf' => static fn (array $fx): mixed => $fx[1]->toArray()['new_trunk_page_numbers'],
    'summary leaf page numbers expose appended deleted leaf' => static fn (array $fx): mixed => $fx[1]->toArray()['leaf_page_numbers'],
    'summary updated freelist pages include new trunk only' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_freelist_page_numbers'],
    'summary pointer-map pages include first pointer-map page' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'summary secure delete clears only freelist leaf' => static fn (array $fx): mixed => $fx[1]->toArray()['secure_delete_cleared_pages'],
    'summary freed pointer-map entry count covers both leaves' => static fn (array $fx): mixed => count($fx[1]->toArray()['freed_pointer_map_entries']),
    'summary first freed pointer-map entry page is table leaf' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pointer_map_entries'][0]['page_number'],
    'summary first freed pointer-map type is free page' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pointer_map_entries'][0]['type_name'],
    'summary second freed pointer-map entry page is index leaf' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pointer_map_entries'][1]['page_number'],
    'summary second freed pointer-map parent is zero' => static fn (array $fx): mixed => $fx[1]->toArray()['freed_pointer_map_entries'][1]['parent_page_number'],
    'post header first trunk is promoted deleted leaf' => static fn (array $fx): mixed => $fx[2]->header->firstFreelistTrunkPage,
    'post header freelist count includes batch frees' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'post freelist pages start at promoted trunk' => static fn (array $fx): mixed => array_slice($fx[2]->freelistPageNumbers(), 0, 3),
    'post freelist pages retain old full trunk leaf count' => static fn (array $fx): mixed => count($fx[2]->freelistPageNumbers()),
    'post allocation order starts with newest freelist leaf' => static fn (array $fx): mixed => $fx[2]->freelistAllocationOrder()[0],
    'post allocation order then reuses promoted trunk' => static fn (array $fx): mixed => $fx[2]->freelistAllocationOrder()[1],
    'post allocation order then reaches old trunk leaves' => static fn (array $fx): mixed => $fx[2]->freelistAllocationOrder()[2],
    'post promoted trunk points to old full trunk' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->page(3), 0, 4))[1],
    'post promoted trunk has one leaf entry' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->page(3), 4, 4))[1],
    'post promoted trunk leaf entry is index leaf' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->page(3), 8, 4))[1],
    'post index leaf page is secure-cleared' => static fn (array $fx): mixed => $fx[2]->page(5),
    'post promoted trunk is not secure-cleared' => static fn (array $fx): mixed => $fx[2]->page(3) === str_repeat("\0", 1024),
    'post table leaf pointer-map is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(3)->typeName(),
    'post table leaf pointer-map parent is zero' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(3)->parentPageNumber,
    'post index leaf pointer-map is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(5)->typeName(),
    'post index leaf pointer-map parent is zero' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(5)->parentPageNumber,
    'post old trunk remains first map free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(4)->typeName(),
    'post table root pointer-map remains root page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(7)->typeName(),
    'post index root pointer-map remains root page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->typeName(),
    'header image first trunk is promoted leaf' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->firstFreelistTrunkPage,
    'header image freelist count includes promoted trunk and leaf' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->freelistPageCount,
];

$expected = [
    'current first trunk is existing full trunk' => 4,
    'current freelist count includes trunk plus full leaves' => 185,
    'current allocation order starts with first existing full leaf' => 20,
    'current allocation order ends with existing trunk' => 4,
    'current table leaf pointer-map is btree page' => 'btree-page',
    'current table leaf pointer-map parent is table root' => 7,
    'current index leaf pointer-map is btree page' => 'btree-page',
    'current index leaf pointer-map parent is index root' => 8,
    'delete result table page is empty leaf' => 0,
    'delete result index page is empty leaf' => 0,
    'plan action is batch free' => 'btree-empty-leaf-batch-free',
    'plan class is empty leaf batch free plan' => SQLiteBTreeEmptyLeafBatchFreePlan::class,
    'plan leaf delete count includes table and index leaves' => 2,
    'plan frees table leaf before index leaf' => [3, 5],
    'plan promotes first deleted page into new trunk' => [3],
    'plan inserts second deleted page as freelist leaf' => [5],
    'plan first freelist trunk becomes promoted table leaf' => 3,
    'plan freelist count grows by two' => 187,
    'plan updated page numbers include header pointermap trunk and cleared leaf' => [1, 2, 3, 5],
    'plan page images include promoted trunk' => true,
    'plan page images include secure-cleared index leaf' => true,
    'summary new trunk page numbers expose promoted deleted leaf' => [3],
    'summary leaf page numbers expose appended deleted leaf' => [5],
    'summary updated freelist pages include new trunk only' => [3],
    'summary pointer-map pages include first pointer-map page' => [2],
    'summary secure delete clears only freelist leaf' => [5],
    'summary freed pointer-map entry count covers both leaves' => 2,
    'summary first freed pointer-map entry page is table leaf' => 3,
    'summary first freed pointer-map type is free page' => 'free-page',
    'summary second freed pointer-map entry page is index leaf' => 5,
    'summary second freed pointer-map parent is zero' => 0,
    'post header first trunk is promoted deleted leaf' => 3,
    'post header freelist count includes batch frees' => 187,
    'post freelist pages start at promoted trunk' => [3, 5, 4],
    'post freelist pages retain old full trunk leaf count' => 187,
    'post allocation order starts with newest freelist leaf' => 5,
    'post allocation order then reuses promoted trunk' => 3,
    'post allocation order then reaches old trunk leaves' => 20,
    'post promoted trunk points to old full trunk' => 4,
    'post promoted trunk has one leaf entry' => 1,
    'post promoted trunk leaf entry is index leaf' => 5,
    'post index leaf page is secure-cleared' => str_repeat("\0", 1024),
    'post promoted trunk is not secure-cleared' => false,
    'post table leaf pointer-map is free page' => 'free-page',
    'post table leaf pointer-map parent is zero' => 0,
    'post index leaf pointer-map is free page' => 'free-page',
    'post index leaf pointer-map parent is zero' => 0,
    'post old trunk remains first map free page' => 'free-page',
    'post table root pointer-map remains root page' => 'root-page',
    'post index root pointer-map remains root page' => 'root-page',
    'header image first trunk is promoted leaf' => 3,
    'header image freelist count includes promoted trunk and leaf' => 187,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree pointermap vacuum rebalance current next73 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree pointermap vacuum rebalance current next73 rejects duplicate release inside batch'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , , $tableDelete, $indexDelete] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
        ['leaf_page' => 3, 'leaf_page_type' => 'table-leaf', 'delete_result' => ['page' => $tableDelete['page'], 'rowid' => 41, 'obsolete_overflow_page_numbers' => [6]]],
        ['leaf_page' => 5, 'leaf_page_type' => 'index-leaf', 'delete_result' => ['page' => $indexDelete['page'], 'record_values' => ['_transient_rebalance_batch', 41], 'obsolete_overflow_page_numbers' => [6]]],
    ], true));
};

$tests['btree pointermap vacuum rebalance current next73 rejects existing freelist duplicate'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , , $tableDelete] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
        ['leaf_page' => 20, 'leaf_page_type' => 'table-leaf', 'delete_result' => ['page' => $tableDelete['page'], 'rowid' => 41, 'obsolete_overflow_page_numbers' => []]],
    ], true));
};

$tests['btree pointermap vacuum rebalance current next73 rejects pointer-map page release'] = static function (TestRunner $t) use ($fixture): void {
    [$database, , , $tableDelete] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
        ['leaf_page' => 2, 'leaf_page_type' => 'table-leaf', 'delete_result' => ['page' => $tableDelete['page'], 'rowid' => 41, 'obsolete_overflow_page_numbers' => []]],
    ], true));
};

return $tests;
