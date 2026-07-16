<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage132 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
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

$putPointerMapEntry132 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage132 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database132 = static function () use ($makeFirstPage132, $putPointerMapEntry132, $overflowPage132): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage132(12, 10, 2);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $overflowPage132(7, 'A');
    $pages[7] = $overflowPage132(0, 'B');
    $pages[8] = $overflowPage132(9, 'C');
    $pages[9] = $overflowPage132(0, 'D');
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
        $putPointerMapEntry132($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$chains132 = static fn (): array => [
    [
        'source' => 'wp-options-delete-large-transient-table-cell',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [13201],
    ],
    [
        'source' => 'wp-options-delete-autoload-index-cell',
        'first_page' => 8,
        'overflow_payload_bytes' => 700,
        'record_values' => [['autoload', 'option_name']],
    ],
];

$plan132 = static fn (bool $secureDelete = false): SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan => SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan::fromOverflowChains(
    $database132(),
    $chains132(),
    str_repeat('R', 1600),
    4,
    $secureDelete,
);

$throwMessage132 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$currentRows132 = static fn (): array => $plan132()->currentSourceRows();
$reuseRows132 = static fn (): array => $plan132()->reuseRows();

$cases132 = [
    'action label' => static fn (): mixed => $plan132()->toArray()['action'],
    'current source row pages' => static fn (): mixed => array_column($currentRows132(), 'page_number'),
    'current source next pointers' => static fn (): mixed => array_column($currentRows132(), 'current_next_page'),
    'current source terminal flags' => static fn (): mixed => array_column($currentRows132(), 'current_terminal'),
    'current source pointer types' => static fn (): mixed => array_column($currentRows132(), 'current_pointer_map_type'),
    'current source pointer parents' => static fn (): mixed => array_column($currentRows132(), 'current_pointer_map_parent'),
    'current source names' => static fn (): mixed => array_column($currentRows132(), 'source'),
    'released overflow pages' => static fn (): mixed => $plan132()->releasedOverflowPages(),
    'release source counts' => static fn (): mixed => array_column($plan132()->releasePlan->sources, 'count'),
    'release source names' => static fn (): mixed => array_column($plan132()->releasePlan->sources, 'source'),
    'free plan freed pages' => static fn (): mixed => $plan132()->releasePlan->freePlan->freedPageNumbers,
    'free plan leaf pages' => static fn (): mixed => $plan132()->releasePlan->freePlan->leafPageNumbers,
    'free plan updated freelist pages' => static fn (): mixed => array_keys($plan132()->releasePlan->freePlan->updatedFreelistPages),
    'free plan updated pointer-map pages' => static fn (): mixed => array_keys($plan132()->releasePlan->freePlan->updatedPointerMapPages),
    'free pointer-map types' => static fn (): mixed => array_column($plan132()->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'after release freelist pages' => static fn (): mixed => $plan132()->databaseAfterRelease->freelistPageNumbers(),
    'after release pointer parents' => static fn (): mixed => [
        $plan132()->databaseAfterRelease->pointerMapEntryForPage(6)->parentPageNumber,
        $plan132()->databaseAfterRelease->pointerMapEntryForPage(7)->parentPageNumber,
        $plan132()->databaseAfterRelease->pointerMapEntryForPage(8)->parentPageNumber,
        $plan132()->databaseAfterRelease->pointerMapEntryForPage(9)->parentPageNumber,
    ],
    'allocated overflow pages' => static fn (): mixed => $plan132()->allocatedOverflowPages(),
    'reused released pages' => static fn (): mixed => $plan132()->reusedReleasedOverflowPages(),
    'allocation step sources' => static fn (): mixed => array_column($plan132()->allocationPlan->allocationSteps(), 'source'),
    'allocation leaf count before' => static fn (): mixed => array_column($plan132()->allocationPlan->allocationSteps(), 'leaf_count_before'),
    'allocation leaf count after' => static fn (): mixed => array_column($plan132()->allocationPlan->allocationSteps(), 'leaf_count_after'),
    'allocation freelist count after' => static fn (): mixed => array_column($plan132()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'allocation pointer types' => static fn (): mixed => array_column($plan132()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan132()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'reuse row pages' => static fn (): mixed => array_column($reuseRows132(), 'page_number'),
    'reuse row origins' => static fn (): mixed => array_column($reuseRows132(), 'page_origin'),
    'reuse row release sources' => static fn (): mixed => array_column($reuseRows132(), 'release_source'),
    'reuse row next pointer types' => static fn (): mixed => array_column($reuseRows132(), 'next_pointer_map_type'),
    'reuse row next pointer parents' => static fn (): mixed => array_column($reuseRows132(), 'next_pointer_map_parent'),
    'reuse row next overflow pointers' => static fn (): mixed => array_column($reuseRows132(), 'next_overflow_next_page'),
    'reuse row payload prefixes' => static fn (): mixed => array_column($reuseRows132(), 'payload_prefix'),
    'final freelist pages' => static fn (): mixed => $plan132()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan132()->databaseAfterAllocation->header->freelistPageCount,
    'final first freelist trunk' => static fn (): mixed => $plan132()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final page twelve becomes first overflow' => static fn (): mixed => $plan132()->databaseAfterAllocation->pointerMapEntryForPage(12)->typeName(),
    'final page twelve parent is target btree' => static fn (): mixed => $plan132()->databaseAfterAllocation->pointerMapEntryForPage(12)->parentPageNumber,
    'overflow page image keys' => static fn (): mixed => array_keys($plan132()->overflowPageImages()),
    'page image keys' => static fn (): mixed => array_keys($plan132()->pageImages()),
    'summary current rows' => static fn (): mixed => array_column($plan132()->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary reuse rows' => static fn (): mixed => array_column($plan132()->toArray()['btree_freelist_overflow_pointermap_current_source_next132'], 'page_number'),
    'summary final freelist pages' => static fn (): mixed => $plan132()->toArray()['final_freelist_page_numbers'],
    'secure delete clears all released pages' => static fn (): mixed => $plan132(true)->releasePlan->freePlan->clearedPageNumbers,
    'empty replacement rejected' => static fn (): mixed => $throwMessage132(static fn () => SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan::fromOverflowChains($database132(), $chains132(), '', 4)),
    'bad parent rejected' => static fn (): mixed => $throwMessage132(static fn () => SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan::fromOverflowChains($database132(), $chains132(), 'abc', 1)),
    'trailing current chain rejected' => static function () use ($database132, $chains132, $throwMessage132): mixed {
        $chains = $chains132();
        $chains[0]['overflow_payload_bytes'] = 508;

        return $throwMessage132(static fn () => SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan::fromOverflowChains($database132(), $chains, str_repeat('R', 508), 4));
    },
];

$expected132 = [
    'action label' => 'btree-freelist-overflow-pointermap-current-source-next132',
    'current source row pages' => [6, 7, 8, 9],
    'current source next pointers' => [7, 0, 9, 0],
    'current source terminal flags' => [false, true, false, true],
    'current source pointer types' => ['first-overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'current source pointer parents' => [3, 6, 4, 8],
    'current source names' => [
        'wp-options-delete-large-transient-table-cell',
        'wp-options-delete-large-transient-table-cell',
        'wp-options-delete-autoload-index-cell',
        'wp-options-delete-autoload-index-cell',
    ],
    'released overflow pages' => [6, 7, 8, 9],
    'release source counts' => [2, 2],
    'release source names' => ['wp-options-delete-large-transient-table-cell', 'wp-options-delete-autoload-index-cell'],
    'free plan freed pages' => [6, 7, 8, 9],
    'free plan leaf pages' => [6, 7, 8, 9],
    'free plan updated freelist pages' => [10],
    'free plan updated pointer-map pages' => [2],
    'free pointer-map types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'after release freelist pages' => [10, 12, 6, 7, 8, 9],
    'after release pointer parents' => [0, 0, 0, 0],
    'allocated overflow pages' => [12, 9, 8, 7],
    'reused released pages' => [9, 8, 7],
    'allocation step sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'allocation leaf count before' => [5, 4, 3, 2],
    'allocation leaf count after' => [4, 3, 2, 1],
    'allocation freelist count after' => [5, 4, 3, 2],
    'allocation pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer parents' => [4, 12, 9, 8],
    'reuse row pages' => [12, 9, 8, 7],
    'reuse row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page'],
    'reuse row release sources' => [
        null,
        'wp-options-delete-autoload-index-cell',
        'wp-options-delete-autoload-index-cell',
        'wp-options-delete-large-transient-table-cell',
    ],
    'reuse row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'reuse row next pointer parents' => [4, 12, 9, 8],
    'reuse row next overflow pointers' => [9, 8, 7, 0],
    'reuse row payload prefixes' => [str_repeat('R', 16), str_repeat('R', 16), str_repeat('R', 16), str_repeat('R', 16)],
    'final freelist pages' => [10, 6],
    'final freelist count' => 2,
    'final first freelist trunk' => 10,
    'final page twelve becomes first overflow' => 'first-overflow-page',
    'final page twelve parent is target btree' => 4,
    'overflow page image keys' => [12, 9, 8, 7],
    'page image keys' => [1, 2, 7, 8, 9, 10, 12],
    'summary current rows' => [6, 7, 8, 9],
    'summary reuse rows' => [12, 9, 8, 7],
    'summary final freelist pages' => [10, 6],
    'secure delete clears all released pages' => [6, 7, 8, 9],
    'empty replacement rejected' => 'SQLite b-tree freelist overflow pointer-map next132 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree freelist overflow pointer-map next132 parent b-tree page must be at page 2 or later',
    'trailing current chain rejected' => 'SQLite overflow chain has trailing pages beyond the expected payload length',
];

$tests = [];

foreach ($cases132 as $name => $callback) {
    $tests['btree freelist overflow pointermap current source next132 ' . $name] = static function (TestRunner $t) use ($callback, $expected132, $name): void {
        $t->same($expected132[$name], $callback());
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree freelist overflow pointermap current source next132 invariant ' . $index] = static function (TestRunner $t) use ($plan132): void {
        $plan = $plan132();

        $t->same([6, 7, 8, 9], $plan->releasedOverflowPages());
        $t->same([12, 9, 8, 7], $plan->allocatedOverflowPages());
        $t->same([7, 0, 9, 0], array_column($plan->currentSourceRows(), 'current_next_page'));
        $t->same(['free-page', 'free-page', 'free-page', 'free-page'], array_column($plan->releasePlan->freePlan->freedPointerMapEntries, 'type_name'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->reuseRows(), 'next_pointer_map_type'));
        $t->same([4, 12, 9, 8], array_column($plan->reuseRows(), 'next_pointer_map_parent'));
        $t->same([9, 8, 7, 0], array_column($plan->reuseRows(), 'next_overflow_next_page'));
        $t->same([10, 6], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
