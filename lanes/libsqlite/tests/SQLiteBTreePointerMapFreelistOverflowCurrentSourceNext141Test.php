<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage141 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
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

$putPointerMapEntry141 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$overflowPage141 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database141 = static function (bool $current) use ($makeFirstPage141, $putPointerMapEntry141, $overflowPage141): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage141(12, 10, 2);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $overflowPage141(7, $current ? 's' : 'P');
    $pages[7] = $overflowPage141(0, $current ? 't' : 'Q');
    $pages[8] = $overflowPage141(9, $current ? 'C' : 'u');
    $pages[9] = $overflowPage141(0, $current ? 'D' : 'v');
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [12], 512);
    $pages[12] = str_repeat("\0", 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => $current ? [SQLitePointerMapEntry::BTREE_PAGE, 3] : [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => $current ? [SQLitePointerMapEntry::BTREE_PAGE, 3] : [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => $current ? [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4] : [SQLitePointerMapEntry::BTREE_PAGE, 3],
        9 => $current ? [SQLitePointerMapEntry::OVERFLOW_PAGE, 8] : [SQLitePointerMapEntry::BTREE_PAGE, 3],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry141($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$preparedChains141 = static fn (): array => [[
    'source' => 'prepared-wp-options-large-transient-row',
    'first_page' => 6,
    'overflow_payload_bytes' => 700,
    'rowids' => [14100],
]];

$currentChains141 = static fn (): array => [[
    'source' => 'current-wp-options-autoload-index-entry',
    'first_page' => 8,
    'overflow_payload_bytes' => 700,
    'record_values' => [['autoload', 'option_name']],
]];

$plan141 = static fn (bool $secureDelete = false): SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next141FromPreparedAndCurrentOverflowChains(
    $database141(false),
    $database141(true),
    $preparedChains141(),
    $currentChains141(),
    str_repeat('N', 1200),
    4,
    $secureDelete,
);

$throwMessage141 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$preparedRows141 = static fn (): array => $plan141()->preparedSourceRows();
$currentRows141 = static fn (): array => $plan141()->currentSourceRows();
$transitionRows141 = static fn (): array => $plan141()->transitionRows();

$cases141 = [
    'action label' => static fn (): mixed => $plan141()->toArray()['action'],
    'prepared source pages' => static fn (): mixed => array_column($preparedRows141(), 'page_number'),
    'prepared next pointers' => static fn (): mixed => array_column($preparedRows141(), 'next_page'),
    'prepared pointer types' => static fn (): mixed => array_column($preparedRows141(), 'pointer_map_type'),
    'prepared pointer parents' => static fn (): mixed => array_column($preparedRows141(), 'pointer_map_parent'),
    'current source pages' => static fn (): mixed => array_column($currentRows141(), 'page_number'),
    'current next pointers' => static fn (): mixed => array_column($currentRows141(), 'next_page'),
    'current pointer types' => static fn (): mixed => array_column($currentRows141(), 'pointer_map_type'),
    'current pointer parents' => static fn (): mixed => array_column($currentRows141(), 'pointer_map_parent'),
    'stale prepared pages' => static fn (): mixed => $plan141()->stalePreparedOverflowPages(),
    'released current pages' => static fn (): mixed => $plan141()->releasedOverflowPages(),
    'release source names' => static fn (): mixed => array_column($plan141()->releasePlan->sources, 'source'),
    'free plan freed pages' => static fn (): mixed => $plan141()->releasePlan->freePlan->freedPageNumbers,
    'free pointer-map types' => static fn (): mixed => array_column($plan141()->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'after release freelist pages' => static fn (): mixed => $plan141()->databaseAfterRelease->freelistPageNumbers(),
    'after release stale prepared type six' => static fn (): mixed => $plan141()->databaseAfterRelease->pointerMapEntryForPage(6)->typeName(),
    'after release current page eight free' => static fn (): mixed => $plan141()->databaseAfterRelease->pointerMapEntryForPage(8)->typeName(),
    'allocated pages' => static fn (): mixed => $plan141()->allocatedOverflowPages(),
    'reused current pages' => static fn (): mixed => $plan141()->reusedCurrentOverflowPages(),
    'allocation pointer types' => static fn (): mixed => array_column($plan141()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan141()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'transition pages' => static fn (): mixed => array_column($transitionRows141(), 'page_number'),
    'transition prepared chain flags' => static fn (): mixed => array_column($transitionRows141(), 'prepared_chain_page'),
    'transition current chain flags' => static fn (): mixed => array_column($transitionRows141(), 'current_chain_page'),
    'transition released flags' => static fn (): mixed => array_column($transitionRows141(), 'released_current_page'),
    'transition current pointer types' => static fn (): mixed => array_column($transitionRows141(), 'current_pointer_map_type'),
    'transition free pointer types' => static fn (): mixed => array_column($transitionRows141(), 'free_pointer_map_type'),
    'transition next pointer types' => static fn (): mixed => array_column($transitionRows141(), 'next_pointer_map_type'),
    'transition next parents' => static fn (): mixed => array_column($transitionRows141(), 'next_pointer_map_parent'),
    'transition overflow next pages' => static fn (): mixed => array_column($transitionRows141(), 'next_overflow_next_page'),
    'transition payload prefixes' => static fn (): mixed => array_column($transitionRows141(), 'payload_prefix'),
    'final freelist pages' => static fn (): mixed => $plan141()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan141()->databaseAfterAllocation->header->freelistPageCount,
    'final first freelist trunk' => static fn (): mixed => $plan141()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final stale page six remains btree' => static fn (): mixed => $plan141()->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'final current page eight reused' => static fn (): mixed => $plan141()->databaseAfterAllocation->pointerMapEntryForPage(8)->typeName(),
    'overflow page image keys' => static fn (): mixed => array_keys($plan141()->overflowPageImages()),
    'page image keys' => static fn (): mixed => array_keys($plan141()->pageImages()),
    'summary prepared pages' => static fn (): mixed => $plan141()->toArray()['prepared_source_overflow_pages'],
    'summary current pages' => static fn (): mixed => $plan141()->toArray()['current_source_overflow_pages'],
    'summary stale pages' => static fn (): mixed => $plan141()->toArray()['stale_prepared_overflow_pages'],
    'summary transition pages' => static fn (): mixed => array_column($plan141()->toArray()['btree_pointermap_freelist_overflow_current_source_next141'], 'page_number'),
    'dependency closure note' => static fn (): mixed => str_contains($plan141()->toArray()['dependency_closure'], 'no new support component needed'),
    'non overlap note' => static fn (): mixed => str_contains($plan141()->toArray()['non_overlap'], 'stale prepared overflow chain'),
    'secure delete clears current pages only' => static fn (): mixed => $plan141(true)->releasePlan->freePlan->clearedPageNumbers,
    'empty replacement rejected' => static fn (): mixed => $throwMessage141(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next141FromPreparedAndCurrentOverflowChains($database141(false), $database141(true), $preparedChains141(), $currentChains141(), '', 4)),
    'bad parent rejected' => static fn (): mixed => $throwMessage141(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next141FromPreparedAndCurrentOverflowChains($database141(false), $database141(true), $preparedChains141(), $currentChains141(), 'abc', 1)),
    'empty current chains rejected' => static fn (): mixed => $throwMessage141(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next141FromPreparedAndCurrentOverflowChains($database141(false), $database141(true), $preparedChains141(), [], 'abc', 4)),
    'trailing current chain rejected' => static function () use ($database141, $preparedChains141, $currentChains141, $throwMessage141): mixed {
        $chains = $currentChains141();
        $chains[0]['overflow_payload_bytes'] = 508;

        return $throwMessage141(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next141FromPreparedAndCurrentOverflowChains($database141(false), $database141(true), $preparedChains141(), $chains, 'abc', 4));
    },
];

$expected141 = [
    'action label' => 'btree-pointermap-freelist-overflow-current-source-next141',
    'prepared source pages' => [6, 7],
    'prepared next pointers' => [7, 0],
    'prepared pointer types' => ['first-overflow-page', 'overflow-page'],
    'prepared pointer parents' => [3, 6],
    'current source pages' => [8, 9],
    'current next pointers' => [9, 0],
    'current pointer types' => ['first-overflow-page', 'overflow-page'],
    'current pointer parents' => [4, 8],
    'stale prepared pages' => [6, 7],
    'released current pages' => [8, 9],
    'release source names' => ['current-wp-options-autoload-index-entry'],
    'free plan freed pages' => [8, 9],
    'free pointer-map types' => ['free-page', 'free-page'],
    'after release freelist pages' => [10, 12, 8, 9],
    'after release stale prepared type six' => 'btree-page',
    'after release current page eight free' => 'free-page',
    'allocated pages' => [12, 9, 8],
    'reused current pages' => [9, 8],
    'allocation pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer parents' => [4, 12, 9],
    'transition pages' => [12, 9, 8],
    'transition prepared chain flags' => [false, false, false],
    'transition current chain flags' => [false, true, true],
    'transition released flags' => [false, true, true],
    'transition current pointer types' => ['free-page', 'overflow-page', 'first-overflow-page'],
    'transition free pointer types' => ['free-page', 'free-page', 'free-page'],
    'transition next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'transition next parents' => [4, 12, 9],
    'transition overflow next pages' => [9, 8, 0],
    'transition payload prefixes' => [str_repeat('N', 16), str_repeat('N', 16), str_repeat('N', 16)],
    'final freelist pages' => [10],
    'final freelist count' => 1,
    'final first freelist trunk' => 10,
    'final stale page six remains btree' => 'btree-page',
    'final current page eight reused' => 'overflow-page',
    'overflow page image keys' => [12, 9, 8],
    'page image keys' => [1, 2, 8, 9, 10, 12],
    'summary prepared pages' => [6, 7],
    'summary current pages' => [8, 9],
    'summary stale pages' => [6, 7],
    'summary transition pages' => [12, 9, 8],
    'dependency closure note' => true,
    'non overlap note' => true,
    'secure delete clears current pages only' => [8, 9],
    'empty replacement rejected' => 'SQLite b-tree pointer-map freelist overflow next141 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree pointer-map freelist overflow next141 parent b-tree page must be at page 2 or later',
    'empty current chains rejected' => 'SQLite b-tree pointer-map freelist overflow next141 requires at least one current overflow page',
    'trailing current chain rejected' => 'SQLite overflow chain has trailing pages beyond the expected payload length',
];

$tests = [];

foreach ($cases141 as $name => $callback) {
    $tests['btree pointermap freelist overflow current source next141 ' . $name] = static function (TestRunner $t) use ($callback, $expected141, $name): void {
        $t->same($expected141[$name], $callback());
    };
}

foreach (range(1, 28) as $index) {
    $tests['btree pointermap freelist overflow current source next141 invariant ' . $index] = static function (TestRunner $t) use ($plan141): void {
        $plan = $plan141();

        $t->same([6, 7], $plan->stalePreparedOverflowPages());
        $t->same([8, 9], $plan->releasedOverflowPages());
        $t->same([12, 9, 8], $plan->allocatedOverflowPages());
        $t->same([9, 8], $plan->reusedCurrentOverflowPages());
        $t->same('btree-page', $plan->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName());
        $t->same('first-overflow-page', $plan->databaseAfterAllocation->pointerMapEntryForPage(12)->typeName());
        $t->same('overflow-page', $plan->databaseAfterAllocation->pointerMapEntryForPage(8)->typeName());
        $t->same([9, 8, 0], array_column($plan->transitionRows(), 'next_overflow_next_page'));
    };
}

return $tests;
