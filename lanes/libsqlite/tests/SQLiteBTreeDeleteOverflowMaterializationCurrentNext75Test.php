<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteOverflowMaterializationPlan;
use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafBatchFreePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
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

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize): void {
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
    $pageCount = 220;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 4, 185);
    $pages[2] = str_repeat("\0", $pageSize);

    $fullExistingLeaves = range(20, 203);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(null, $fullExistingLeaves, $pageSize, $pageSize - 255);
    foreach ($fullExistingLeaves as $pageNumber) {
        $pages[$pageNumber] = str_repeat(chr($pageNumber % 251), $pageSize);
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }

    $pages[3] = SQLiteTableLeafPage::assemble([], $pageSize);
    $pages[5] = SQLiteIndexLeafPage::assemble([], $pageSize);
    foreach ([6, 9, 10] as $pageNumber) {
        $pages[$pageNumber] = str_repeat(chr(64 + $pageNumber), $pageSize);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
        4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        5 => [SQLitePointerMapEntry::BTREE_PAGE, 8],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        10 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 5],
        7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        8 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber, $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $batch = SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
        [
            'leaf_page' => 3,
            'leaf_page_type' => 'table-leaf',
            'delete_result' => [
                'page' => $pages[3],
                'rowids' => [301, 302],
                'obsolete_overflow_page_numbers' => [6, 9],
            ],
        ],
        [
            'leaf_page' => 5,
            'leaf_page_type' => 'index-leaf',
            'delete_result' => [
                'page' => $pages[5],
                'record_values' => [['_transient_timeout_btree75', 301]],
                'obsolete_overflow_page_numbers' => [10],
            ],
        ],
    ], true);
    $materialization = SQLiteBTreeDeleteOverflowMaterializationPlan::fromEmptyLeafBatchPlan($database, $batch);

    return [$database, $batch, $materialization];
};

$cases = [
    'source action preserves empty leaf batch free' => static fn (array $fx): mixed => $fx[2]->sourceAction,
    'materialization action names current next surface' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'current page count remains original count' => static fn (array $fx): mixed => $fx[2]->currentDatabase->pageCount(),
    'next page count remains original count' => static fn (array $fx): mixed => $fx[2]->nextDatabase->pageCount(),
    'current header first trunk is old full trunk' => static fn (array $fx): mixed => $fx[2]->currentDatabase->header->firstFreelistTrunkPage,
    'next header first trunk is promoted table leaf' => static fn (array $fx): mixed => $fx[2]->nextDatabase->header->firstFreelistTrunkPage,
    'current header freelist count includes old trunk leaves' => static fn (array $fx): mixed => $fx[2]->currentDatabase->header->freelistPageCount,
    'next header freelist count includes five newly freed pages' => static fn (array $fx): mixed => $fx[2]->nextDatabase->header->freelistPageCount,
    'updated page numbers include header pointermap trunk and freed pages' => static fn (array $fx): mixed => $fx[2]->updatedPageNumbers(),
    'page transition count matches materialized images' => static fn (array $fx): mixed => count($fx[2]->pageTransitions),
    'page transition pages are stable sorted' => static fn (array $fx): mixed => array_column($fx[2]->pageTransitions, 'page_number'),
    'all page transitions changed' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[2]->pageTransitions, 'changed'))),
    'header transition next digest matches next database' => static fn (array $fx): mixed => $fx[2]->pageTransitions[0]['next_sha1'],
    'header transition current digest matches current database' => static fn (array $fx): mixed => $fx[2]->pageTransitions[0]['current_sha1'],
    'promoted trunk transition is not zeroed next' => static fn (array $fx): mixed => $fx[2]->pageTransitions[2]['next_zeroed'],
    'freed overflow page transition is secure zeroed' => static fn (array $fx): mixed => $fx[2]->pageTransitions[3]['next_zeroed'],
    'freed index leaf transition is secure zeroed' => static fn (array $fx): mixed => $fx[2]->pageTransitions[5]['next_zeroed'],
    'freed overflow tail transition is secure zeroed' => static fn (array $fx): mixed => $fx[2]->pageTransitions[6]['next_zeroed'],
    'pointer map transition count covers freed non pointer-map pages' => static fn (array $fx): mixed => count($fx[2]->pointerMapTransitions),
    'pointer map transition pages' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'page_number'),
    'pointer map current types include btree and overflow pages' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'current_type_name'),
    'pointer map next types are all free page' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[2]->pointerMapTransitions, 'next_type_name'))),
    'pointer map current parents preserve btree and overflow chain parents' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'current_parent_page_number'),
    'pointer map next parents are zero' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[2]->pointerMapTransitions, 'next_parent_page_number'))),
    'summary current first trunk' => static fn (array $fx): mixed => $fx[2]->toArray()['current_first_freelist_trunk_page'],
    'summary next first trunk' => static fn (array $fx): mixed => $fx[2]->toArray()['next_first_freelist_trunk_page'],
    'summary current freelist count' => static fn (array $fx): mixed => $fx[2]->toArray()['current_freelist_page_count'],
    'summary next freelist count' => static fn (array $fx): mixed => $fx[2]->toArray()['next_freelist_page_count'],
    'summary includes updated page numbers' => static fn (array $fx): mixed => $fx[2]->toArray()['updated_page_numbers'],
    'batch freed page numbers retain delete then overflow order' => static fn (array $fx): mixed => $fx[1]->freedPageNumbers,
    'batch new trunk is table leaf page' => static fn (array $fx): mixed => $fx[1]->freePlan->newTrunkPageNumbers,
    'batch freelist leaves include overflow and index pages' => static fn (array $fx): mixed => $fx[1]->freePlan->leafPageNumbers,
    'batch pointer-map entries include every freed page' => static fn (array $fx): mixed => array_column($fx[1]->freePlan->freedPointerMapEntries, 'page_number'),
    'next freelist starts at promoted page' => static fn (array $fx): mixed => array_slice($fx[2]->nextDatabase->freelistPageNumbers(), 0, 7),
    'next allocation order starts with newest secure-cleared overflow page' => static fn (array $fx): mixed => array_slice($fx[2]->nextDatabase->freelistAllocationOrder(), 0, 6),
    'next promoted trunk points to previous full trunk' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->nextDatabase->page(3), 0, 4))[1],
    'next promoted trunk leaf count' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->nextDatabase->page(3), 4, 4))[1],
    'next promoted trunk first leaf entry' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->nextDatabase->page(3), 8, 4))[1],
    'next promoted trunk last leaf entry' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->nextDatabase->page(3), 20, 4))[1],
    'next overflow page 6 is zeroed' => static fn (array $fx): mixed => $fx[2]->nextDatabase->page(6),
    'next overflow page 9 is zeroed' => static fn (array $fx): mixed => $fx[2]->nextDatabase->page(9),
    'next overflow page 10 is zeroed' => static fn (array $fx): mixed => $fx[2]->nextDatabase->page(10),
    'next table leaf page became freelist trunk not zeroed' => static fn (array $fx): mixed => $fx[2]->nextDatabase->page(3) === str_repeat("\0", 1024),
    'next index leaf page is zeroed' => static fn (array $fx): mixed => $fx[2]->nextDatabase->page(5),
    'next pointer map page image matches materialized database page' => static fn (array $fx): mixed => $fx[2]->pageImages[2] === $fx[2]->nextDatabase->page(2),
    'next header image parses promoted first trunk' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[2]->pageImages[1])->firstFreelistTrunkPage,
    'next header image parses expanded freelist count' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[2]->pageImages[1])->freelistPageCount,
    'next page 3 is freelist trunk shape' => static fn (array $fx): mixed => unpack('N', substr($fx[2]->nextDatabase->page(3), 4, 4))[1],
    'current table leaf pointer-map type' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(3)->typeName(),
    'next table leaf pointer-map type' => static fn (array $fx): mixed => $fx[2]->nextDatabase->pointerMapEntryForPage(3)->typeName(),
    'current first overflow pointer-map type' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(6)->typeName(),
    'next first overflow pointer-map type' => static fn (array $fx): mixed => $fx[2]->nextDatabase->pointerMapEntryForPage(6)->typeName(),
    'current chained overflow pointer-map parent' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(9)->parentPageNumber,
    'next chained overflow pointer-map parent' => static fn (array $fx): mixed => $fx[2]->nextDatabase->pointerMapEntryForPage(9)->parentPageNumber,
    'summary page transition first page is header' => static fn (array $fx): mixed => $fx[2]->toArray()['page_transitions'][0]['page_number'],
    'summary pointer transition first page is promoted trunk' => static fn (array $fx): mixed => $fx[2]->toArray()['pointer_map_transitions'][0]['page_number'],
];

$expected = [
    'source action preserves empty leaf batch free' => 'btree-empty-leaf-batch-free',
    'materialization action names current next surface' => 'btree-delete-overflow-materialization-current-next',
    'current page count remains original count' => 220,
    'next page count remains original count' => 220,
    'current header first trunk is old full trunk' => 4,
    'next header first trunk is promoted table leaf' => 3,
    'current header freelist count includes old trunk leaves' => 185,
    'next header freelist count includes five newly freed pages' => 190,
    'updated page numbers include header pointermap trunk and freed pages' => [1, 2, 3, 5, 6, 9, 10],
    'page transition count matches materialized images' => 7,
    'page transition pages are stable sorted' => [1, 2, 3, 5, 6, 9, 10],
    'all page transitions changed' => [true],
    'promoted trunk transition is not zeroed next' => false,
    'freed overflow page transition is secure zeroed' => true,
    'freed index leaf transition is secure zeroed' => true,
    'freed overflow tail transition is secure zeroed' => true,
    'pointer map transition count covers freed non pointer-map pages' => 5,
    'pointer map transition pages' => [3, 5, 6, 9, 10],
    'pointer map current types include btree and overflow pages' => ['btree-page', 'btree-page', 'first-overflow-page', 'overflow-page', 'first-overflow-page'],
    'pointer map next types are all free page' => ['free-page'],
    'pointer map current parents preserve btree and overflow chain parents' => [7, 8, 3, 6, 5],
    'pointer map next parents are zero' => [0],
    'summary current first trunk' => 4,
    'summary next first trunk' => 3,
    'summary current freelist count' => 185,
    'summary next freelist count' => 190,
    'summary includes updated page numbers' => [1, 2, 3, 5, 6, 9, 10],
    'batch freed page numbers retain delete then overflow order' => [3, 6, 9, 5, 10],
    'batch new trunk is table leaf page' => [3],
    'batch freelist leaves include overflow and index pages' => [6, 9, 5, 10],
    'batch pointer-map entries include every freed page' => [3, 6, 9, 5, 10],
    'next freelist starts at promoted page' => [3, 6, 9, 5, 10, 4, 20],
    'next allocation order starts with newest secure-cleared overflow page' => [6, 10, 5, 9, 3, 20],
    'next promoted trunk points to previous full trunk' => 4,
    'next promoted trunk leaf count' => 4,
    'next promoted trunk first leaf entry' => 6,
    'next promoted trunk last leaf entry' => 10,
    'next overflow page 6 is zeroed' => str_repeat("\0", 1024),
    'next overflow page 9 is zeroed' => str_repeat("\0", 1024),
    'next overflow page 10 is zeroed' => str_repeat("\0", 1024),
    'next table leaf page became freelist trunk not zeroed' => false,
    'next index leaf page is zeroed' => str_repeat("\0", 1024),
    'next pointer map page image matches materialized database page' => true,
    'next header image parses promoted first trunk' => 3,
    'next header image parses expanded freelist count' => 190,
    'next page 3 is freelist trunk shape' => 4,
    'current table leaf pointer-map type' => 'btree-page',
    'next table leaf pointer-map type' => 'free-page',
    'current first overflow pointer-map type' => 'first-overflow-page',
    'next first overflow pointer-map type' => 'free-page',
    'current chained overflow pointer-map parent' => 6,
    'next chained overflow pointer-map parent' => 0,
    'summary page transition first page is header' => 1,
    'summary pointer transition first page is promoted trunk' => 3,
];

$tests = [];
foreach ($cases as $name => $callback) {
    if ($name === 'header transition next digest matches next database') {
        $tests['btree delete overflow materialization current next75 ' . $name] = static function (TestRunner $t) use ($fixture): void {
            [, , $materialization] = $fixture();
            $t->same(sha1($materialization->nextDatabase->page(1)), $materialization->pageTransitions[0]['next_sha1']);
        };
        continue;
    }
    if ($name === 'header transition current digest matches current database') {
        $tests['btree delete overflow materialization current next75 ' . $name] = static function (TestRunner $t) use ($fixture): void {
            [, , $materialization] = $fixture();
            $t->same(sha1($materialization->currentDatabase->page(1)), $materialization->pageTransitions[0]['current_sha1']);
        };
        continue;
    }

    $tests['btree delete overflow materialization current next75 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree delete overflow materialization current next75 rejects empty image map'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $ref = new ReflectionClass(SQLiteBTreeDeleteOverflowMaterializationPlan::class);
    $method = $ref->getMethod('fromPageImages');
    $method->setAccessible(true);
    $t->throws(InvalidArgumentException::class, static fn () => $method->invoke(null, $database, [], 'empty'));
};

$tests['btree delete overflow materialization current next75 rejects malformed page image length'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $ref = new ReflectionClass(SQLiteBTreeDeleteOverflowMaterializationPlan::class);
    $method = $ref->getMethod('fromPageImages');
    $method->setAccessible(true);
    $t->throws(InvalidArgumentException::class, static fn () => $method->invoke(null, $database, [1 => 'short'], 'short'));
};

return $tests;
