<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage136 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
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

$putPointerMapEntry136 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$overflowPage136 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database136 = static function () use ($makeFirstPage136, $putPointerMapEntry136, $overflowPage136): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage136(12, 10, 2);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $overflowPage136(7, 'A');
    $pages[7] = $overflowPage136(0, 'B');
    $pages[8] = $overflowPage136(9, 'C');
    $pages[9] = $overflowPage136(0, 'D');
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [12], 512);
    $pages[12] = str_repeat("\0", 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry136($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$chains136 = static fn (): array => [
    [
        'source' => 'wp-options-transient-table-overflow-delete-next136',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [13601],
    ],
    [
        'source' => 'wp-options-autoload-index-overflow-delete-next136',
        'first_page' => 8,
        'overflow_payload_bytes' => 700,
        'record_values' => [['autoload', 'option_name']],
    ],
];

$payload136 = static fn (): string => str_repeat('R', 3000);

$plan136 = static fn (bool $secureDelete = false): SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan => SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan::fromOverflowChains(
    $database136(),
    $chains136(),
    $payload136(),
    4,
    $secureDelete,
);

$throwsMessage136 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$trunkRows136 = static fn (): array => $plan136()->trunkOverflowRows();
$chainRows136 = static fn (): array => $plan136()->chainRows();

$cases136 = [
    'action label' => static fn (): mixed => $plan136()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan136()->basePlan->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $plan136()->basePlan->allocatedOverflowPages(),
    'reused released overflow pages' => static fn (): mixed => $plan136()->basePlan->reusedReleasedOverflowPages(),
    'release source names' => static fn (): mixed => array_column($plan136()->basePlan->releasePlan->sources, 'source'),
    'release source counts' => static fn (): mixed => array_column($plan136()->basePlan->releasePlan->sources, 'count'),
    'release new trunk pages' => static fn (): mixed => $plan136()->basePlan->releasePlan->freePlan->newTrunkPageNumbers,
    'release leaf pages' => static fn (): mixed => $plan136()->basePlan->releasePlan->freePlan->leafPageNumbers,
    'release first trunk page' => static fn (): mixed => $plan136()->basePlan->databaseAfterRelease->header->firstFreelistTrunkPage,
    'release freelist count' => static fn (): mixed => $plan136()->basePlan->databaseAfterRelease->header->freelistPageCount,
    'release freelist page numbers' => static fn (): mixed => $plan136()->basePlan->databaseAfterRelease->freelistPageNumbers(),
    'release pointer-map page six' => static fn (): mixed => $plan136()->basePlan->databaseAfterRelease->pointerMapEntryForPage(6)->typeName(),
    'release pointer-map page ten' => static fn (): mixed => $plan136()->basePlan->databaseAfterRelease->pointerMapEntryForPage(10)->typeName(),
    'allocation sources' => static fn (): mixed => array_column($plan136()->basePlan->allocationPlan->allocationSteps(), 'source'),
    'allocation trunk pages' => static fn (): mixed => array_column($plan136()->basePlan->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation pointer types' => static fn (): mixed => array_column($plan136()->basePlan->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan136()->basePlan->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'allocation final first trunk' => static fn (): mixed => $plan136()->basePlan->allocationPlan->firstFreelistTrunkPage,
    'allocation final freelist count' => static fn (): mixed => $plan136()->basePlan->allocationPlan->freelistPageCount,
    'allocation updated freelist pages' => static fn (): mixed => array_keys($plan136()->basePlan->allocationPlan->updatedFreelistPages),
    'allocation updated pointer map pages' => static fn (): mixed => array_keys($plan136()->basePlan->allocationPlan->updatedPointerMapPages),
    'trunk row pages' => static fn (): mixed => array_column($trunkRows136(), 'page_number'),
    'trunk row roles' => static fn (): mixed => array_column($trunkRows136(), 'release_trunk_role'),
    'trunk row allocation sources' => static fn (): mixed => array_column($trunkRows136(), 'allocation_source'),
    'trunk row positions' => static fn (): mixed => array_column($trunkRows136(), 'replacement_chain_position'),
    'trunk before next trunk' => static fn (): mixed => array_column($trunkRows136(), 'before_freelist_next_trunk_page'),
    'trunk before leaf count' => static fn (): mixed => array_column($trunkRows136(), 'before_freelist_leaf_count'),
    'trunk after overflow next page' => static fn (): mixed => array_column($trunkRows136(), 'after_overflow_next_page'),
    'trunk after stale leaf count bytes' => static fn (): mixed => array_column($trunkRows136(), 'after_stale_leaf_count_bytes'),
    'trunk before pointer types' => static fn (): mixed => array_column($trunkRows136(), 'before_pointer_map_type'),
    'trunk after pointer types' => static fn (): mixed => array_column($trunkRows136(), 'after_pointer_map_type'),
    'trunk after pointer parents' => static fn (): mixed => array_column($trunkRows136(), 'after_pointer_map_parent'),
    'trunk payload prefixes' => static fn (): mixed => array_column($trunkRows136(), 'payload_prefix'),
    'trunk freelist empty flags' => static fn (): mixed => array_column($trunkRows136(), 'freelist_empty_after_allocation'),
    'chain row pages' => static fn (): mixed => array_column($chainRows136(), 'page_number'),
    'chain row origins' => static fn (): mixed => array_column($chainRows136(), 'page_origin'),
    'chain row allocation sources' => static fn (): mixed => array_column($chainRows136(), 'allocation_source'),
    'chain row next overflow pointers' => static fn (): mixed => array_column($chainRows136(), 'next_overflow_next_page'),
    'chain row next pointer types' => static fn (): mixed => array_column($chainRows136(), 'next_pointer_map_type'),
    'chain row next pointer parents' => static fn (): mixed => array_column($chainRows136(), 'next_pointer_map_parent'),
    'final freelist pages' => static fn (): mixed => $plan136()->basePlan->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan136()->basePlan->databaseAfterAllocation->header->freelistPageCount,
    'final first freelist trunk' => static fn (): mixed => $plan136()->basePlan->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final page ten pointer type' => static fn (): mixed => $plan136()->basePlan->databaseAfterAllocation->pointerMapEntryForPage(10)->typeName(),
    'final page ten pointer parent' => static fn (): mixed => $plan136()->basePlan->databaseAfterAllocation->pointerMapEntryForPage(10)->parentPageNumber,
    'final page ten next pointer' => static fn (): mixed => unpack('N', substr($plan136()->basePlan->databaseAfterAllocation->page(10), 0, 4))[1],
    'summary trunk rows' => static fn (): mixed => array_column($plan136()->toArray()['trunk_overflow_rows'], 'page_number'),
    'summary replacement pages' => static fn (): mixed => array_column($plan136()->toArray()['replacement_chain_rows'], 'page_number'),
    'summary final freelist count' => static fn (): mixed => $plan136()->toArray()['final_freelist_page_count'],
    'secure delete clears released pages' => static fn (): mixed => $plan136(true)->basePlan->releasePlan->freePlan->clearedPageNumbers,
    'short replacement rejected' => static fn (): mixed => $throwsMessage136(static fn () => SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan::fromOverflowChains($database136(), $chains136(), str_repeat('x', 1600), 4)),
    'empty replacement rejected' => static fn (): mixed => $throwsMessage136(static fn () => SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan::fromOverflowChains($database136(), $chains136(), '', 4)),
    'bad parent rejected' => static fn (): mixed => $throwsMessage136(static fn () => SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan::fromOverflowChains($database136(), $chains136(), $payload136(), 1)),
];

$expected136 = [
    'action label' => 'btree-freelist-pointermap-overflow-current-source-next136',
    'released overflow pages' => [6, 7, 8, 9],
    'allocated overflow pages' => [12, 9, 8, 7, 6, 10],
    'reused released overflow pages' => [9, 8, 7, 6],
    'release source names' => ['wp-options-transient-table-overflow-delete-next136', 'wp-options-autoload-index-overflow-delete-next136'],
    'release source counts' => [2, 2],
    'release new trunk pages' => [],
    'release leaf pages' => [6, 7, 8, 9],
    'release first trunk page' => 10,
    'release freelist count' => 6,
    'release freelist page numbers' => [10, 12, 6, 7, 8, 9],
    'release pointer-map page six' => 'free-page',
    'release pointer-map page ten' => 'free-page',
    'allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'allocation trunk pages' => [10, 10, 10, 10, 10, 10],
    'allocation pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer parents' => [4, 12, 9, 8, 7, 6],
    'allocation final first trunk' => 0,
    'allocation final freelist count' => 0,
    'allocation updated freelist pages' => [],
    'allocation updated pointer map pages' => [2],
    'trunk row pages' => [10],
    'trunk row roles' => ['existing-trunk'],
    'trunk row allocation sources' => ['freelist-trunk'],
    'trunk row positions' => [5],
    'trunk before next trunk' => [0],
    'trunk before leaf count' => [5],
    'trunk after overflow next page' => [0],
    'trunk after stale leaf count bytes' => [1381126738],
    'trunk before pointer types' => ['free-page'],
    'trunk after pointer types' => ['overflow-page'],
    'trunk after pointer parents' => [6],
    'trunk payload prefixes' => [str_repeat('R', 16)],
    'trunk freelist empty flags' => [true],
    'chain row pages' => [12, 9, 8, 7, 6, 10],
    'chain row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'existing-freelist-page'],
    'chain row allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'chain row next overflow pointers' => [9, 8, 7, 6, 10, 0],
    'chain row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'chain row next pointer parents' => [4, 12, 9, 8, 7, 6],
    'final freelist pages' => [],
    'final freelist count' => 0,
    'final first freelist trunk' => 0,
    'final page ten pointer type' => 'overflow-page',
    'final page ten pointer parent' => 6,
    'final page ten next pointer' => 0,
    'summary trunk rows' => [10],
    'summary replacement pages' => [12, 9, 8, 7, 6, 10],
    'summary final freelist count' => 0,
    'secure delete clears released pages' => [6, 7, 8, 9],
    'short replacement rejected' => 'SQLite b-tree freelist pointer-map overflow next136 requires replacement allocation to consume the freelist trunk',
    'empty replacement rejected' => 'SQLite b-tree freelist overflow pointer-map next132 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree freelist overflow pointer-map next132 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases136 as $name => $callback) {
    $tests['btree freelist pointermap overflow current source next136 ' . $name] = static function (TestRunner $t) use ($callback, $expected136, $name): void {
        $t->same($expected136[$name], $callback());
    };
}

foreach (range(1, 20) as $index) {
    $tests['btree freelist pointermap overflow current source next136 invariant ' . $index] = static function (TestRunner $t) use ($plan136): void {
        $plan = $plan136();
        $trunkRows = $plan->trunkOverflowRows();
        $chainRows = $plan->chainRows();

        $t->same([12, 9, 8, 7, 6, 10], $plan->basePlan->allocatedOverflowPages());
        $t->same([10], array_column($trunkRows, 'page_number'));
        $t->same(['freelist-trunk'], array_column($trunkRows, 'allocation_source'));
        $t->same(['overflow-page'], array_column($trunkRows, 'after_pointer_map_type'));
        $t->same([6], array_column($trunkRows, 'after_pointer_map_parent'));
        $t->same([0], array_column($trunkRows, 'after_overflow_next_page'));
        $t->same([], $plan->basePlan->databaseAfterAllocation->freelistPageNumbers());
        $t->same([9, 8, 7, 6, 10, 0], array_column($chainRows, 'next_overflow_next_page'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($chainRows, 'next_pointer_map_type'));
    };
}

return $tests;
