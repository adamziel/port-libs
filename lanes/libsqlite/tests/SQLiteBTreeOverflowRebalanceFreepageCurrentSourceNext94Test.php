<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 10), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$databaseFixture = static function () use ($firstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 10, str_repeat("\0", 512));
    $pages[1] = $firstPage();
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(20, SQLiteRecord::encode([null, '_transient_timeout_feed', str_repeat('a', 96)])),
    ]);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_feed', 20, str_repeat('i', 40)])),
    ]);
    foreach ([5 => 's', 6 => 'a', 7 => 'b', 8 => 'c', 9 => 'd', 10 => 'e'] as $pageNumber => $fill) {
        $pages[$pageNumber] = str_repeat($fill, 512);
    }

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 5);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 5);
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$throwsMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$fixture = static function () use ($databaseFixture): array {
    $database = $databaseFixture();
    $table = SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseTableLeaf($database, 3, [
        ['rowid' => 20, 'obsolete_overflow_page_numbers' => [6, 7]],
    ], true);
    $index = SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseIndexLeaf($database, 4, [
        ['record_values' => ['_transient_timeout_feed', 20, str_repeat('i', 40)], 'obsolete_overflow_page_numbers' => [8, 9, 10]],
    ], true);

    return [$database, $table, $index];
};

$cases = [
    'table action' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'table leaf type' => static fn (array $fx): mixed => $fx[1]->leafPageType,
    'table step type' => static fn (array $fx): mixed => $fx[1]->events[0]['step_type'],
    'table deleted rowid' => static fn (array $fx): mixed => $fx[1]->events[0]['deleted_rowids'],
    'table freed leaf first' => static fn (array $fx): mixed => $fx[1]->events[0]['freed_pages'][0],
    'table freed pages' => static fn (array $fx): mixed => $fx[1]->events[0]['freed_pages'],
    'table released pages' => static fn (array $fx): mixed => $fx[1]->releasedPageNumbers(),
    'table materialized pages' => static fn (array $fx): mixed => $fx[1]->materializedPageNumbers(),
    'table first freelist trunk page' => static fn (array $fx): mixed => $fx[1]->steps[0]->freePlan->firstFreelistTrunkPage,
    'table final freelist count' => static fn (array $fx): mixed => $fx[1]->finalFreelistPageCount(),
    'table page count before' => static fn (array $fx): mixed => $fx[1]->toArray()['database_page_count_before'],
    'table page count after' => static fn (array $fx): mixed => $fx[1]->toArray()['database_page_count_after'],
    'table header first trunk' => static fn (array $fx): mixed => $fx[1]->databaseAfter->header->firstFreelistTrunkPage,
    'table header freelist count' => static fn (array $fx): mixed => $fx[1]->databaseAfter->header->freelistPageCount,
    'table freelist page numbers' => static fn (array $fx): mixed => $fx[1]->databaseAfter->freelistPageNumbers(),
    'table leaf pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(3)->typeName(),
    'table overflow head pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(6)->typeName(),
    'table overflow tail pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(7)->typeName(),
    'table leaf pointer parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(3)->parentPageNumber,
    'table overflow parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(7)->parentPageNumber,
    'table secure delete cleared pages' => static fn (array $fx): mixed => $fx[1]->steps[0]->freePlan->clearedPageNumbers,
    'table freed pointer entries count' => static fn (array $fx): mixed => count($fx[1]->steps[0]->freePlan->freedPointerMapEntries),
    'table freed pointer entry pages' => static fn (array $fx): mixed => array_column($fx[1]->steps[0]->freePlan->freedPointerMapEntries, 'page_number'),
    'table freed pointer entry types' => static fn (array $fx): mixed => array_unique(array_column($fx[1]->steps[0]->freePlan->freedPointerMapEntries, 'type_name')),
    'table freed page image zeroed' => static fn (array $fx): mixed => $fx[1]->databaseAfter->page(6) === str_repeat("\0", 512),
    'table toArray released pages' => static fn (array $fx): mixed => $fx[1]->toArray()['released_pages'],
    'table toArray event step type' => static fn (array $fx): mixed => $fx[1]->toArray()['events'][0]['step_type'],
    'index action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'index leaf type' => static fn (array $fx): mixed => $fx[2]->leafPageType,
    'index step type' => static fn (array $fx): mixed => $fx[2]->events[0]['step_type'],
    'index deleted record name' => static fn (array $fx): mixed => $fx[2]->events[0]['deleted_record_values'][0][0],
    'index freed pages' => static fn (array $fx): mixed => $fx[2]->events[0]['freed_pages'],
    'index released pages' => static fn (array $fx): mixed => $fx[2]->releasedPageNumbers(),
    'index materialized pages' => static fn (array $fx): mixed => $fx[2]->materializedPageNumbers(),
    'index final freelist count' => static fn (array $fx): mixed => $fx[2]->finalFreelistPageCount(),
    'index header first trunk' => static fn (array $fx): mixed => $fx[2]->databaseAfter->header->firstFreelistTrunkPage,
    'index freelist page numbers' => static fn (array $fx): mixed => $fx[2]->databaseAfter->freelistPageNumbers(),
    'index leaf pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter->pointerMapEntryForPage(4)->typeName(),
    'index overflow tail pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter->pointerMapEntryForPage(10)->typeName(),
    'index pointer parents zero' => static fn (array $fx): mixed => array_map(static fn (int $page): int => $fx[2]->databaseAfter->pointerMapEntryForPage($page)->parentPageNumber, [4, 8, 9, 10]),
    'index secure delete cleared pages' => static fn (array $fx): mixed => $fx[2]->steps[0]->freePlan->clearedPageNumbers,
    'index freed pointer entries count' => static fn (array $fx): mixed => count($fx[2]->steps[0]->freePlan->freedPointerMapEntries),
    'index freed page image zeroed' => static fn (array $fx): mixed => $fx[2]->databaseAfter->page(9) === str_repeat("\0", 512),
    'rejects empty table sequence' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseTableLeaf($fx[0], 3, [])),
    'rejects empty index sequence' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseIndexLeaf($fx[0], 4, [])),
    'rejects noninteger rowid' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseTableLeaf($fx[0], 3, [['rowid' => '20', 'obsolete_overflow_page_numbers' => []]])),
    'rejects missing overflow list' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseTableLeaf($fx[0], 3, [['rowid' => 20]])),
    'rejects noninteger overflow page' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseTableLeaf($fx[0], 3, [['rowid' => 20, 'obsolete_overflow_page_numbers' => ['6']]])),
    'rejects missing index record array' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseIndexLeaf($fx[0], 4, [['obsolete_overflow_page_numbers' => []]])),
    'rejects stale second current-source delete' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::baseTableLeaf($fx[0], 3, [
        ['rowid' => 20, 'obsolete_overflow_page_numbers' => [6]],
        ['rowid' => 20, 'obsolete_overflow_page_numbers' => [7]],
    ])),
];

$expected = [
    'table action' => 'btree-overflow-rebalance-freepage-current-source-next94',
    'table leaf type' => 'table-leaf',
    'table step type' => 'empty-leaf-free',
    'table deleted rowid' => [20],
    'table freed leaf first' => 3,
    'table freed pages' => [3, 6, 7],
    'table released pages' => [3, 6, 7],
    'table materialized pages' => [1, 2, 3, 6, 7],
    'table first freelist trunk page' => 3,
    'table final freelist count' => 3,
    'table page count before' => 10,
    'table page count after' => 10,
    'table header first trunk' => 3,
    'table header freelist count' => 3,
    'table freelist page numbers' => [3, 6, 7],
    'table leaf pointer map free' => 'free-page',
    'table overflow head pointer map free' => 'free-page',
    'table overflow tail pointer map free' => 'free-page',
    'table leaf pointer parent zero' => 0,
    'table overflow parent zero' => 0,
    'table secure delete cleared pages' => [6, 7],
    'table freed pointer entries count' => 3,
    'table freed pointer entry pages' => [3, 6, 7],
    'table freed pointer entry types' => ['free-page'],
    'table freed page image zeroed' => true,
    'table toArray released pages' => [3, 6, 7],
    'table toArray event step type' => 'empty-leaf-free',
    'index action' => 'btree-overflow-rebalance-freepage-current-source-next94',
    'index leaf type' => 'index-leaf',
    'index step type' => 'empty-leaf-free',
    'index deleted record name' => '_transient_timeout_feed',
    'index freed pages' => [4, 8, 9, 10],
    'index released pages' => [4, 8, 9, 10],
    'index materialized pages' => [1, 2, 4, 8, 9, 10],
    'index final freelist count' => 4,
    'index header first trunk' => 4,
    'index freelist page numbers' => [4, 8, 9, 10],
    'index leaf pointer map free' => 'free-page',
    'index overflow tail pointer map free' => 'free-page',
    'index pointer parents zero' => [0, 0, 0, 0],
    'index secure delete cleared pages' => [8, 9, 10],
    'index freed pointer entries count' => 4,
    'index freed page image zeroed' => true,
    'rejects empty table sequence' => 'SQLite overflow rebalance freepage current-source next94 requires at least one table delete',
    'rejects empty index sequence' => 'SQLite overflow rebalance freepage current-source next94 requires at least one index delete',
    'rejects noninteger rowid' => 'SQLite overflow rebalance freepage current-source next94 table rowid must be an integer',
    'rejects missing overflow list' => 'SQLite overflow rebalance freepage current-source next94 requires obsolete overflow page numbers',
    'rejects noninteger overflow page' => 'SQLite overflow rebalance freepage current-source next94 overflow page numbers must be integers',
    'rejects missing index record array' => 'SQLite overflow rebalance freepage current-source next94 index record values must be an array',
    'rejects stale second current-source delete' => 'Invalid SQLite b-tree page type flag: 0x00',
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree overflow rebalance freepage current source next94 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

return $tests;
