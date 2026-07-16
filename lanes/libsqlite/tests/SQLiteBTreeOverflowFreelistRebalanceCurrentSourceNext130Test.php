<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage130 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
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
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry130 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = (intdiv($pageNumber - 2, 103) * 103) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$makeOverflowPage130 = static fn (?int $nextPage, string $payload): string => str_pad(
    pack('N', $nextPage ?? 0) . $payload,
    512,
    "\0",
);

$databaseFixture130 = static function () use ($makeFirstPage130, $putPointerMapEntry130, $makeOverflowPage130): SQLiteDatabase {
    $pages = array_fill(1, 10, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage130(10, 8, 3);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[5] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $makeOverflowPage130(7, str_repeat('O', 508));
    $pages[7] = $makeOverflowPage130(null, str_repeat('P', 220));
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, [9, 10], 512);

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 4 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 5 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::FREE_PAGE, 0], 9 => [SQLitePointerMapEntry::FREE_PAGE, 0], 10 => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry130($pages, $pageNumber, $type, $parent);
    }
    $putPointerMapEntry130($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry130($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payload130 = static fn (): string => str_repeat('current-source-next130:', 22);

$fixture130 = static function (bool $secureDelete = true) use ($databaseFixture130, $payload130): SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan {
    return SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan::fromDeleteResults(
        $databaseFixture130(),
        [[
            'source' => 'wp-options-transient-overflow-shrink-next130',
            'obsolete_overflow_page_numbers' => [6, 7],
            'rowids' => [13001],
        ]],
        3,
        $payload130(),
        $secureDelete,
    );
};

$throwsMessage130 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases130 = [
    'action label' => static fn (): mixed => $fixture130()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $fixture130()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $fixture130()->allocatedOverflowPages(),
    'deferred released pages' => static fn (): mixed => $fixture130()->deferredReleasedOverflowPages(),
    'reused existing freelist pages' => static fn (): mixed => $fixture130()->reusedExistingFreelistPages(),
    'release source pages' => static fn (): mixed => $fixture130()->releasePlan->sources[0]['pages'],
    'release source count' => static fn (): mixed => $fixture130()->releasePlan->sources[0]['count'],
    'free plan freed pages' => static fn (): mixed => $fixture130()->releasePlan->freePlan->freedPageNumbers,
    'free plan existing trunk pages' => static fn (): mixed => $fixture130()->releasePlan->freePlan->existingTrunkPageNumbers(),
    'free plan leaf pages' => static fn (): mixed => $fixture130()->releasePlan->freePlan->leafPageNumbers,
    'free plan new trunks' => static fn (): mixed => $fixture130()->releasePlan->freePlan->newTrunkPageNumbers,
    'free plan pointer map pages' => static fn (): mixed => array_keys($fixture130()->releasePlan->freePlan->updatedPointerMapPages),
    'free plan pointer map types' => static fn (): mixed => array_column($fixture130()->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'after release freelist count' => static fn (): mixed => $fixture130()->databaseAfterRelease->header->freelistPageCount,
    'after release freelist pages' => static fn (): mixed => $fixture130()->databaseAfterRelease->freelistPageNumbers(),
    'after release allocation order' => static fn (): mixed => $fixture130()->databaseAfterRelease->freelistAllocationOrder(),
    'allocation step sources' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocationSteps(), 'source'),
    'allocation step pages' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocationSteps(), 'allocated_page'),
    'allocation step trunks' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation leaf count before' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocationSteps(), 'leaf_count_before'),
    'allocation leaf count after' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocationSteps(), 'leaf_count_after'),
    'allocation pointer map pages' => static fn (): mixed => array_keys($fixture130()->allocationPlan->updatedPointerMapPages),
    'allocation pointer map entry pages' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer map entry types' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer map parents' => static fn (): mixed => array_column($fixture130()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'final freelist count' => static fn (): mixed => $fixture130()->databaseAfterAllocation->header->freelistPageCount,
    'final freelist pages' => static fn (): mixed => $fixture130()->databaseAfterAllocation->freelistPageNumbers(),
    'final allocation order' => static fn (): mixed => $fixture130()->databaseAfterAllocation->freelistAllocationOrder(),
    'final page nine pointer type' => static fn (): mixed => $fixture130()->databaseAfterAllocation->pointerMapEntryForPage(9)->typeName(),
    'final page ten pointer type' => static fn (): mixed => $fixture130()->databaseAfterAllocation->pointerMapEntryForPage(10)->typeName(),
    'final page six pointer type' => static fn (): mixed => $fixture130()->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'final page seven pointer type' => static fn (): mixed => $fixture130()->databaseAfterAllocation->pointerMapEntryForPage(7)->typeName(),
    'final page nine next pointer' => static fn (): mixed => unpack('N', substr($fixture130()->databaseAfterAllocation->page(9), 0, 4))[1],
    'final page ten next pointer' => static fn (): mixed => unpack('N', substr($fixture130()->databaseAfterAllocation->page(10), 0, 4))[1],
    'final page nine payload prefix' => static fn (): mixed => substr($fixture130()->databaseAfterAllocation->page(9), 4, 16),
    'final page ten payload prefix' => static fn (): mixed => substr($fixture130()->databaseAfterAllocation->page(10), 4, 16),
    'released row pages' => static fn (): mixed => array_column($fixture130()->releasedRows(), 'page_number'),
    'released row before types' => static fn (): mixed => array_column($fixture130()->releasedRows(), 'before_pointer_map_type'),
    'released row free types' => static fn (): mixed => array_column($fixture130()->releasedRows(), 'free_pointer_map_type'),
    'released row final types' => static fn (): mixed => array_column($fixture130()->releasedRows(), 'final_pointer_map_type'),
    'released row deferred flags' => static fn (): mixed => array_column($fixture130()->releasedRows(), 'deferred_on_freelist'),
    'allocated row pages' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'page_number'),
    'allocated row before types' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'before_pointer_map_type'),
    'allocated row free types' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'free_pointer_map_type'),
    'allocated row final types' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'final_pointer_map_type'),
    'allocated row parents' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'final_pointer_map_parent'),
    'allocated row next pointers' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'next_overflow_next_page'),
    'allocated row payload prefixes' => static fn (): mixed => array_column($fixture130()->allocatedRows(), 'payload_prefix'),
    'page images keys' => static fn (): mixed => array_keys($fixture130()->pageImages()),
    'overflow page image keys' => static fn (): mixed => array_keys($fixture130()->overflowPageImages()),
    'summary updated pages' => static fn (): mixed => $fixture130()->toArray()['updated_page_numbers'],
    'summary final freelist pages' => static fn (): mixed => $fixture130()->toArray()['final_freelist_page_numbers'],
    'secure delete clears released pages before allocation' => static fn (): mixed => $fixture130(true)->releasePlan->freePlan->clearedPageNumbers,
    'without secure delete leaves cleared list empty' => static fn (): mixed => $fixture130(false)->releasePlan->freePlan->clearedPageNumbers,
    'final integrity ok' => static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $fixture130()->databaseAfterAllocation)['rows'],
    'empty payload rejected' => static fn () => $throwsMessage130(static fn () => SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan::fromDeleteResults($databaseFixture130(), [['obsolete_overflow_page_numbers' => [6, 7]]], 3, '')),
    'bad parent rejected' => static fn () => $throwsMessage130(static fn () => SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan::fromDeleteResults($databaseFixture130(), [['obsolete_overflow_page_numbers' => [6, 7]]], 1, 'x')),
];

$expected130 = [
    'action label' => 'btree-overflow-freelist-rebalance-current-source-next130',
    'released overflow pages' => [6, 7],
    'allocated overflow pages' => [9],
    'deferred released pages' => [6, 7],
    'reused existing freelist pages' => [9],
    'release source pages' => [6, 7],
    'release source count' => 2,
    'free plan freed pages' => [6, 7],
    'free plan existing trunk pages' => [8],
    'free plan leaf pages' => [6, 7],
    'free plan new trunks' => [],
    'free plan pointer map pages' => [2],
    'free plan pointer map types' => ['free-page', 'free-page'],
    'after release freelist count' => 5,
    'after release freelist pages' => [8, 9, 10, 6, 7],
    'after release allocation order' => [9, 7, 6, 10, 8],
    'allocation step sources' => ['freelist-leaf'],
    'allocation step pages' => [9],
    'allocation step trunks' => [8],
    'allocation leaf count before' => [4],
    'allocation leaf count after' => [3],
    'allocation pointer map pages' => [2],
    'allocation pointer map entry pages' => [9],
    'allocation pointer map entry types' => ['first-overflow-page'],
    'allocation pointer map parents' => [3],
    'final freelist count' => 4,
    'final freelist pages' => [8, 7, 10, 6],
    'final allocation order' => [7, 6, 10, 8],
    'final page nine pointer type' => 'first-overflow-page',
    'final page ten pointer type' => 'free-page',
    'final page six pointer type' => 'free-page',
    'final page seven pointer type' => 'free-page',
    'final page nine next pointer' => 0,
    'final page ten next pointer' => 0,
    'final page nine payload prefix' => 'current-source-n',
    'final page ten payload prefix' => "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0",
    'released row pages' => [6, 7],
    'released row before types' => ['first-overflow-page', 'overflow-page'],
    'released row free types' => ['free-page', 'free-page'],
    'released row final types' => ['free-page', 'free-page'],
    'released row deferred flags' => [true, true],
    'allocated row pages' => [9],
    'allocated row before types' => ['free-page'],
    'allocated row free types' => ['free-page'],
    'allocated row final types' => ['first-overflow-page'],
    'allocated row parents' => [3],
    'allocated row next pointers' => [0],
    'allocated row payload prefixes' => ['current-source-n'],
    'page images keys' => [1, 2, 6, 7, 8, 9],
    'overflow page image keys' => [9],
    'summary updated pages' => [1, 2, 6, 7, 8, 9],
    'summary final freelist pages' => [8, 7, 10, 6],
    'secure delete clears released pages before allocation' => [6, 7],
    'without secure delete leaves cleared list empty' => [],
    'final integrity ok' => [['integrity_check' => 'ok']],
    'empty payload rejected' => 'SQLite overflow freelist rebalance next130 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite overflow freelist rebalance next130 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases130 as $name => $callback) {
    $tests['btree overflow freelist rebalance current source next130 ' . $name] = static function (TestRunner $t) use ($callback, $expected130, $name): void {
        $t->same($expected130[$name], $callback());
    };
}

foreach (range(1, 16) as $index) {
    $tests['btree overflow freelist rebalance current source next130 repeated deferred freelist invariant ' . $index] = static function (TestRunner $t) use ($fixture130): void {
        $plan = $fixture130();

        $t->same([6, 7], $plan->deferredReleasedOverflowPages());
        $t->same([9], $plan->reusedExistingFreelistPages());
        $t->same(['free-page', 'free-page'], array_column($plan->releasedRows(), 'final_pointer_map_type'));
        $t->same(['first-overflow-page'], array_column($plan->allocatedRows(), 'final_pointer_map_type'));
        $t->same([8, 7, 10, 6], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
