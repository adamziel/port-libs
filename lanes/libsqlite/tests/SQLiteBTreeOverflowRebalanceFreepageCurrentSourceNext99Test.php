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

$firstPage99 = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 14), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry99 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$databaseFixture99 = static function () use ($firstPage99, $putPointerMapEntry99): SQLiteDatabase {
    $pages = array_fill(1, 14, str_repeat("\0", 512));
    $pages[1] = $firstPage99();
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(41, SQLiteRecord::encode([null, '_transient_timeout_plugins', str_repeat('a', 92)])),
        SQLiteTableLeafCell::encode(42, SQLiteRecord::encode([null, '_transient_update_plugins', str_repeat('b', 94)])),
    ]);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_plugins', 41, str_repeat('i', 34)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_update_plugins', 42, str_repeat('j', 36)])),
    ]);
    foreach ([5 => 'r', 6 => 's', 7 => 't', 8 => 'u', 9 => 'v', 10 => 'w', 11 => 'x', 12 => 'y', 13 => 'z', 14 => 'q'] as $pageNumber => $fill) {
        $pages[$pageNumber] = str_repeat($fill, 512);
    }

    $putPointerMapEntry99($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 5);
    $putPointerMapEntry99($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 5);
    $putPointerMapEntry99($pages, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry99($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry99($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry99($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry99($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry99($pages, 10, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry99($pages, 11, SQLitePointerMapEntry::OVERFLOW_PAGE, 10);
    $putPointerMapEntry99($pages, 12, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry99($pages, 13, SQLitePointerMapEntry::OVERFLOW_PAGE, 12);
    $putPointerMapEntry99($pages, 14, SQLitePointerMapEntry::OVERFLOW_PAGE, 13);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tableRows99 = static fn (string $page): array => array_map(
    static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
    SQLiteTableLeafCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512)),
);

$indexNames99 = static fn (string $page): array => array_map(
    static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0],
    SQLiteIndexCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512)),
);

$throwsMessage99 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$fixture99 = static function () use ($databaseFixture99): array {
    $database = $databaseFixture99();
    $table = SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedTableLeaf($database, 3, [
        ['rowid' => 41, 'obsolete_overflow_page_numbers' => [6, 7]],
        ['rowid' => 42, 'obsolete_overflow_page_numbers' => [8, 9]],
    ], true);
    $index = SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedIndexLeaf($database, 4, [
        ['record_values' => ['_transient_timeout_plugins', 41, str_repeat('i', 34)], 'obsolete_overflow_page_numbers' => [10, 11]],
        ['record_values' => ['_transient_update_plugins', 42, str_repeat('j', 36)], 'obsolete_overflow_page_numbers' => [12, 13, 14]],
    ], true);

    return [$database, $table, $index];
};

$cases99 = [
    'table action' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'table leaf type' => static fn (array $fx): mixed => $fx[1]->plan->leafPageType,
    'table step count' => static fn (array $fx): mixed => count($fx[1]->plan->steps),
    'table transition step types' => static fn (array $fx): mixed => array_column($fx[1]->transitionRows(), 'step_type'),
    'table transition phases' => static fn (array $fx): mixed => array_column($fx[1]->transitionRows(), 'phase'),
    'table transition indexes' => static fn (array $fx): mixed => array_column($fx[1]->transitionRows(), 'step'),
    'table first step freed overflow pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[0]['freed_pages'],
    'table second step frees emptied leaf' => static fn (array $fx): mixed => $fx[1]->transitionRows()[1]['freed_pages'],
    'table released pages' => static fn (array $fx): mixed => $fx[1]->releasedPageNumbers(),
    'table materialized pages' => static fn (array $fx): mixed => $fx[1]->materializedPageNumbers(),
    'table first freelist count' => static fn (array $fx): mixed => $fx[1]->transitionRows()[0]['freelist_page_count'],
    'table final freelist count' => static fn (array $fx): mixed => $fx[1]->finalFreelistPageCount(),
    'table databaseAfter helper count' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->header->freelistPageCount,
    'table first trunk' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->header->firstFreelistTrunkPage,
    'table freelist traversal' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->freelistPageNumbers(),
    'table freed leaf image is not btree page' => static fn (array $fx): mixed => ord($fx[1]->databaseAfter()->page(3)[0]),
    'table freed leaf payload cleared' => static fn (array $fx): mixed => trim($fx[1]->databaseAfter()->page(3), "\0") === '',
    'table leaf pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(3)->typeName(),
    'table first overflow pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(6)->typeName(),
    'table last overflow pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(9)->typeName(),
    'table leaf parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(3)->parentPageNumber,
    'table overflow parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(9)->parentPageNumber,
    'table first step updated pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[0]['updated_page_numbers'],
    'table second step updated pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[1]['updated_page_numbers'],
    'table first chain becomes freelist trunk' => static fn (array $fx): mixed => unpack('Nnext', substr($fx[1]->databaseAfter()->page(6), 0, 4))['next'],
    'table secure delete second chain' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->page(8) === str_repeat("\0", 512),
    'table toArray transition rows count' => static fn (array $fx): mixed => count($fx[1]->toArray()['current_source_transition_rows']),
    'table toArray final count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_freelist_page_count'],
    'index action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'index leaf type' => static fn (array $fx): mixed => $fx[2]->plan->leafPageType,
    'index step count' => static fn (array $fx): mixed => count($fx[2]->plan->steps),
    'index transition step types' => static fn (array $fx): mixed => array_column($fx[2]->transitionRows(), 'step_type'),
    'index transition phases' => static fn (array $fx): mixed => array_column($fx[2]->transitionRows(), 'phase'),
    'index first deleted record' => static fn (array $fx): mixed => $fx[2]->plan->events[0]['deleted_record_values'][0][0],
    'index second deleted record' => static fn (array $fx): mixed => $fx[2]->plan->events[1]['deleted_record_values'][0][0],
    'index first step freed overflow pages' => static fn (array $fx): mixed => $fx[2]->transitionRows()[0]['freed_pages'],
    'index second step frees emptied leaf' => static fn (array $fx): mixed => $fx[2]->transitionRows()[1]['freed_pages'],
    'index released pages' => static fn (array $fx): mixed => $fx[2]->releasedPageNumbers(),
    'index materialized pages' => static fn (array $fx): mixed => $fx[2]->materializedPageNumbers(),
    'index final freelist count' => static fn (array $fx): mixed => $fx[2]->finalFreelistPageCount(),
    'index databaseAfter helper count' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->header->freelistPageCount,
    'index first trunk' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->header->firstFreelistTrunkPage,
    'index freelist traversal' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->freelistPageNumbers(),
    'index freed leaf image is not btree page' => static fn (array $fx): mixed => ord($fx[2]->databaseAfter()->page(4)[0]),
    'index freed leaf payload cleared' => static fn (array $fx): mixed => trim($fx[2]->databaseAfter()->page(4), "\0") === '',
    'index leaf pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->pointerMapEntryForPage(4)->typeName(),
    'index first overflow pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->pointerMapEntryForPage(10)->typeName(),
    'index last overflow pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->pointerMapEntryForPage(14)->typeName(),
    'index leaf parent zero' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->pointerMapEntryForPage(4)->parentPageNumber,
    'index overflow parent zero' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->pointerMapEntryForPage(14)->parentPageNumber,
    'index first step updated pages' => static fn (array $fx): mixed => $fx[2]->transitionRows()[0]['updated_page_numbers'],
    'index second step updated pages' => static fn (array $fx): mixed => $fx[2]->transitionRows()[1]['updated_page_numbers'],
    'index first chain becomes freelist trunk' => static fn (array $fx): mixed => unpack('Nnext', substr($fx[2]->databaseAfter()->page(10), 0, 4))['next'],
    'index secure delete second chain' => static fn (array $fx): mixed => $fx[2]->databaseAfter()->page(12) === str_repeat("\0", 512),
    'index toArray transition rows count' => static fn (array $fx): mixed => count($fx[2]->toArray()['current_source_transition_rows']),
    'index toArray final count' => static fn (array $fx): mixed => $fx[2]->toArray()['final_freelist_page_count'],
    'rejects empty table deletes' => static fn (array $fx): mixed => $throwsMessage99(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedTableLeaf($fx[0], 3, [])),
    'rejects bad table rowid' => static fn (array $fx): mixed => $throwsMessage99(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedTableLeaf($fx[0], 3, [['rowid' => '41', 'obsolete_overflow_page_numbers' => []]])),
    'rejects missing overflow list' => static fn (array $fx): mixed => $throwsMessage99(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedTableLeaf($fx[0], 3, [['rowid' => 41]])),
    'rejects bad overflow page' => static fn (array $fx): mixed => $throwsMessage99(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedTableLeaf($fx[0], 3, [['rowid' => 41, 'obsolete_overflow_page_numbers' => ['6']]])),
    'rejects empty index deletes' => static fn (array $fx): mixed => $throwsMessage99(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedIndexLeaf($fx[0], 4, [])),
    'rejects missing index record' => static fn (array $fx): mixed => $throwsMessage99(static fn () => SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::extendedIndexLeaf($fx[0], 4, [['obsolete_overflow_page_numbers' => []]])),
];

$expected99 = [
    'table action' => 'btree-overflow-rebalance-freepage-current-source-next99',
    'table leaf type' => 'table-leaf',
    'table step count' => 2,
    'table transition step types' => ['freeblock-rebalance', 'empty-leaf-free'],
    'table transition phases' => ['table-delete', 'table-delete'],
    'table transition indexes' => [0, 1],
    'table first step freed overflow pages' => [6, 7],
    'table second step frees emptied leaf' => [3, 8, 9],
    'table released pages' => [6, 7, 3, 8, 9],
    'table materialized pages' => [1, 2, 3, 6, 7, 8, 9],
    'table first freelist count' => 2,
    'table final freelist count' => 5,
    'table databaseAfter helper count' => 5,
    'table first trunk' => 6,
    'table freelist traversal' => [6, 7, 3, 8, 9],
    'table freed leaf image is not btree page' => 0,
    'table freed leaf payload cleared' => true,
    'table leaf pointer map free' => 'free-page',
    'table first overflow pointer map free' => 'free-page',
    'table last overflow pointer map free' => 'free-page',
    'table leaf parent zero' => 0,
    'table overflow parent zero' => 0,
    'table first step updated pages' => [1, 2, 3, 6, 7],
    'table second step updated pages' => [1, 2, 3, 6, 8, 9],
    'table first chain becomes freelist trunk' => 0,
    'table secure delete second chain' => true,
    'table toArray transition rows count' => 2,
    'table toArray final count' => 5,
    'index action' => 'btree-overflow-rebalance-freepage-current-source-next99',
    'index leaf type' => 'index-leaf',
    'index step count' => 2,
    'index transition step types' => ['freeblock-rebalance', 'empty-leaf-free'],
    'index transition phases' => ['index-delete', 'index-delete'],
    'index first deleted record' => '_transient_timeout_plugins',
    'index second deleted record' => '_transient_update_plugins',
    'index first step freed overflow pages' => [10, 11],
    'index second step frees emptied leaf' => [4, 12, 13, 14],
    'index released pages' => [10, 11, 4, 12, 13, 14],
    'index materialized pages' => [1, 2, 4, 10, 11, 12, 13, 14],
    'index final freelist count' => 6,
    'index databaseAfter helper count' => 6,
    'index first trunk' => 10,
    'index freelist traversal' => [10, 11, 4, 12, 13, 14],
    'index freed leaf image is not btree page' => 0,
    'index freed leaf payload cleared' => true,
    'index leaf pointer map free' => 'free-page',
    'index first overflow pointer map free' => 'free-page',
    'index last overflow pointer map free' => 'free-page',
    'index leaf parent zero' => 0,
    'index overflow parent zero' => 0,
    'index first step updated pages' => [1, 2, 4, 10, 11],
    'index second step updated pages' => [1, 2, 4, 10, 12, 13, 14],
    'index first chain becomes freelist trunk' => 0,
    'index secure delete second chain' => true,
    'index toArray transition rows count' => 2,
    'index toArray final count' => 6,
    'rejects empty table deletes' => 'SQLite overflow rebalance freepage current-source next94 requires at least one table delete',
    'rejects bad table rowid' => 'SQLite overflow rebalance freepage current-source next94 table rowid must be an integer',
    'rejects missing overflow list' => 'SQLite overflow rebalance freepage current-source next94 requires obsolete overflow page numbers',
    'rejects bad overflow page' => 'SQLite overflow rebalance freepage current-source next94 overflow page numbers must be integers',
    'rejects empty index deletes' => 'SQLite overflow rebalance freepage current-source next94 requires at least one index delete',
    'rejects missing index record' => 'SQLite overflow rebalance freepage current-source next94 index record values must be an array',
];

$tests = [];
foreach ($cases99 as $name => $case) {
    $tests['btree overflow rebalance freepage current source next99 ' . $name] = static function (TestRunner $t) use ($fixture99, $case, $expected99, $name): void {
        $t->same($expected99[$name], $case($fixture99()));
    };
}

return $tests;
