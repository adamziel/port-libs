<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage134 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
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

$putPointerMapEntry134 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$database134 = static function () use ($makeFirstPage134, $putPointerMapEntry134): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage134(12, 10, 2);
    $pages[2] = str_repeat("\0", 512);

    $firstPayload = SQLiteRecord::encode([null, '_transient_timeout_update_core', str_repeat('a', 1077)]);
    $secondPayload = SQLiteRecord::encode([null, '_site_transient_update_plugins', 'still-local']);
    $first = SQLiteTableLeafCell::encodeWithOverflowPages(13401, $firstPayload, 6, 512);
    $second = SQLiteTableLeafCell::encode(13402, $secondPayload);
    $pages[3] = SQLiteTableLeafPage::assemble([$first['cell'], $second]);
    foreach ($first['overflowPages'] as $offset => $overflowPage) {
        $pages[6 + $offset] = $overflowPage;
    }
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [12], 512);

    foreach ([
        3 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry134($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payload134 = str_repeat('application-option-replacement-next134-', 35);

$plan134 = static fn (bool $secureDelete = true): SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan => SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan::tableLeafReplacement(
    $database134(),
    3,
    [13401],
    $payload134,
    4,
    $secureDelete,
);

$throwMessage134 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows134 = static fn (): array => $plan134()->reuseRows();

$cases134 = [
    'action label' => static fn (): mixed => $plan134()->toArray()['action'],
    'delete action label' => static fn (): mixed => $plan134()->deletePlan->toArray()['action'],
    'delete leaf page type' => static fn (): mixed => $plan134()->deletePlan->leafPageType,
    'delete leaf page number' => static fn (): mixed => $plan134()->deletePlan->leafPageNumber,
    'delete step count' => static fn (): mixed => $plan134()->deletePlan->toArray()['step_count'],
    'delete event phases' => static fn (): mixed => array_column($plan134()->deletePlan->events, 'phase'),
    'delete event freed pages' => static fn (): mixed => array_column($plan134()->deletePlan->events, 'freed_pages'),
    'delete materialized pages' => static fn (): mixed => $plan134()->deletePlan->materializedPageNumbers(),
    'released overflow pages' => static fn (): mixed => $plan134()->releasedOverflowPages(),
    'delete database freelist pages' => static fn (): mixed => $plan134()->deletePlan->databaseAfter()->freelistPageNumbers(),
    'delete database freelist count' => static fn (): mixed => $plan134()->deletePlan->databaseAfter()->header->freelistPageCount,
    'delete pointer map page six' => static fn (): mixed => $plan134()->deletePlan->databaseAfter()->pointerMapEntryForPage(6)->typeName(),
    'delete pointer map page seven' => static fn (): mixed => $plan134()->deletePlan->databaseAfter()->pointerMapEntryForPage(7)->typeName(),
    'allocated overflow pages' => static fn (): mixed => $plan134()->allocatedOverflowPages(),
    'reused released overflow pages' => static fn (): mixed => $plan134()->reusedReleasedOverflowPages(),
    'allocation step sources' => static fn (): mixed => array_column($plan134()->allocationPlan->allocationSteps(), 'source'),
    'allocation step trunks' => static fn (): mixed => array_column($plan134()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation pointer map pages' => static fn (): mixed => array_column($plan134()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer types' => static fn (): mixed => array_column($plan134()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan134()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'reuse row pages' => static fn (): mixed => array_column($rows134(), 'page_number'),
    'reuse row origins' => static fn (): mixed => array_column($rows134(), 'page_origin'),
    'reuse delete phases' => static fn (): mixed => array_column($rows134(), 'delete_phase'),
    'reuse allocation sources' => static fn (): mixed => array_column($rows134(), 'allocation_source'),
    'reuse before pointer types' => static fn (): mixed => array_column($rows134(), 'before_pointer_map_type'),
    'reuse free pointer types' => static fn (): mixed => array_column($rows134(), 'free_pointer_map_type'),
    'reuse next pointer types' => static fn (): mixed => array_column($rows134(), 'next_pointer_map_type'),
    'reuse next pointer parents' => static fn (): mixed => array_column($rows134(), 'next_pointer_map_parent'),
    'reuse next overflow pointers' => static fn (): mixed => array_column($rows134(), 'next_overflow_next_page'),
    'reuse payload prefixes' => static fn (): mixed => array_column($rows134(), 'payload_prefix'),
    'final freelist pages' => static fn (): mixed => $plan134()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan134()->databaseAfterAllocation->header->freelistPageCount,
    'final first freelist trunk' => static fn (): mixed => $plan134()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final page twelve pointer type' => static fn (): mixed => $plan134()->databaseAfterAllocation->pointerMapEntryForPage(12)->typeName(),
    'final page twelve parent' => static fn (): mixed => $plan134()->databaseAfterAllocation->pointerMapEntryForPage(12)->parentPageNumber,
    'final page seven pointer type' => static fn (): mixed => $plan134()->databaseAfterAllocation->pointerMapEntryForPage(7)->typeName(),
    'final page six pointer type' => static fn (): mixed => $plan134()->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'overflow image keys' => static fn (): mixed => array_keys($plan134()->overflowPageImages()),
    'page image keys' => static fn (): mixed => array_keys($plan134()->pageImages()),
    'summary rows' => static fn (): mixed => array_column($plan134()->toArray()['btree_overflow_rebalance_freelist_current_source_next134'], 'page_number'),
    'summary final freelist pages' => static fn (): mixed => $plan134()->toArray()['final_freelist_page_numbers'],
    'secure delete cleared pages' => static fn (): mixed => $plan134(true)->deletePlan->steps[0]->freePlan->clearedPageNumbers,
    'without secure delete keeps old overflow byte before allocation remainder' => static fn (): mixed => strpos($plan134(false)->deletePlan->databaseAfter()->page(7), 'a') !== false,
    'empty payload rejected' => static fn (): mixed => $throwMessage134(static fn () => SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan::tableLeafReplacement($database134(), 3, [13401], '', 4)),
    'bad parent rejected' => static fn (): mixed => $throwMessage134(static fn () => SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan::tableLeafReplacement($database134(), 3, [13401], 'abc', 1)),
    'bad rowid rejected' => static fn (): mixed => $throwMessage134(static fn () => SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan::tableLeafReplacement($database134(), 3, [404], 'abc', 4)),
];

$expected134 = [
    'action label' => 'btree-overflow-rebalance-freelist-current-source-next134',
    'delete action label' => 'btree-freelist-overflow-rebalance-current-source-next120',
    'delete leaf page type' => 'table-leaf',
    'delete leaf page number' => 3,
    'delete step count' => 1,
    'delete event phases' => ['table-delete-current-source'],
    'delete event freed pages' => [[6, 7]],
    'delete materialized pages' => [1, 2, 3, 6, 7, 10],
    'released overflow pages' => [6, 7],
    'delete database freelist pages' => [10, 12, 6, 7],
    'delete database freelist count' => 4,
    'delete pointer map page six' => 'free-page',
    'delete pointer map page seven' => 'free-page',
    'allocated overflow pages' => [12, 7, 6],
    'reused released overflow pages' => [7, 6],
    'allocation step sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'allocation step trunks' => [10, 10, 10],
    'allocation pointer map pages' => [12, 7, 6],
    'allocation pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer parents' => [4, 12, 7],
    'reuse row pages' => [12, 7, 6],
    'reuse row origins' => ['existing-freelist-page', 'deleted-overflow-page', 'deleted-overflow-page'],
    'reuse delete phases' => [null, 'table-delete-current-source', 'table-delete-current-source'],
    'reuse allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'reuse before pointer types' => ['free-page', 'overflow-page', 'first-overflow-page'],
    'reuse free pointer types' => ['free-page', 'free-page', 'free-page'],
    'reuse next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'reuse next pointer parents' => [4, 12, 7],
    'reuse next overflow pointers' => [7, 6, 0],
    'reuse payload prefixes' => [substr($payload134, 0, 16), substr($payload134, 508, 16), substr($payload134, 1016, 16)],
    'final freelist pages' => [10],
    'final freelist count' => 1,
    'final first freelist trunk' => 10,
    'final page twelve pointer type' => 'first-overflow-page',
    'final page twelve parent' => 4,
    'final page seven pointer type' => 'overflow-page',
    'final page six pointer type' => 'overflow-page',
    'overflow image keys' => [12, 7, 6],
    'page image keys' => [1, 2, 3, 6, 7, 10, 12],
    'summary rows' => [12, 7, 6],
    'summary final freelist pages' => [10],
    'secure delete cleared pages' => [6, 7],
    'without secure delete keeps old overflow byte before allocation remainder' => true,
    'empty payload rejected' => 'SQLite overflow rebalance freelist current-source next134 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite overflow rebalance freelist current-source next134 parent b-tree page must be at page 2 or later',
    'bad rowid rejected' => 'SQLite table leaf rowid was not found',
];

$tests = [];

foreach ($cases134 as $name => $callback) {
    $tests['btree overflow rebalance freelist current source next134 ' . $name] = static function (TestRunner $t) use ($callback, $expected134, $name): void {
        $t->same($expected134[$name], $callback());
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree overflow rebalance freelist current source next134 invariant ' . $index] = static function (TestRunner $t) use ($plan134): void {
        $plan = $plan134();

        $t->same([6, 7], $plan->releasedOverflowPages());
        $t->same([12, 7, 6], $plan->allocatedOverflowPages());
        $t->same([7, 6], $plan->reusedReleasedOverflowPages());
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->reuseRows(), 'next_pointer_map_type'));
        $t->same([4, 12, 7], array_column($plan->reuseRows(), 'next_pointer_map_parent'));
        $t->same([7, 6, 0], array_column($plan->reuseRows(), 'next_overflow_next_page'));
        $t->same([10], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
