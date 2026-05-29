<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage129 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
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

$putPointerMapEntry129 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage129 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database129 = static function () use ($makeFirstPage129, $putPointerMapEntry129, $overflowPage129): SQLiteDatabase {
    $pages = array_fill(1, 9, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage129(9, 8, 2);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $overflowPage129(7, 'O');
    $pages[7] = $overflowPage129(0, 'P');
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, [9], 512);
    $pages[9] = str_repeat("\0", 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        9 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry129($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan129 = static fn (bool $secureDelete = false): SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next129FromOverflowChains(
    $database129(),
    [[
        'source' => 'wp-option-delete-stale-cache-overflow-chain',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [12901],
    ]],
    1300,
    3,
    str_repeat('N', 1300),
    $secureDelete,
);

$throwMessage129 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows129 = static fn (): array => $plan129()->rows;

$cases129 = [
    'action label' => static fn (): mixed => $plan129()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan129()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $plan129()->allocatedOverflowPages(),
    'reused released overflow pages' => static fn (): mixed => $plan129()->reusedReleasedOverflowPages(),
    'allocated existing freelist pages' => static fn (): mixed => $plan129()->allocatedExistingFreelistPages(),
    'final freelist pages' => static fn (): mixed => $plan129()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan129()->toArray()['final_freelist_page_count'],
    'updated page numbers' => static fn (): mixed => $plan129()->toArray()['updated_page_numbers'],
    'row page numbers' => static fn (): mixed => array_column($rows129(), 'page_number'),
    'row origins' => static fn (): mixed => array_column($rows129(), 'page_origin'),
    'row release sources' => static fn (): mixed => array_column($rows129(), 'release_source'),
    'row allocation sources' => static fn (): mixed => array_column($rows129(), 'allocation_source'),
    'row before pointer types' => static fn (): mixed => array_column($rows129(), 'before_pointer_map_type'),
    'row before pointer parents' => static fn (): mixed => array_column($rows129(), 'before_pointer_map_parent'),
    'row free pointer types' => static fn (): mixed => array_column($rows129(), 'free_pointer_map_type'),
    'row free pointer parents' => static fn (): mixed => array_column($rows129(), 'free_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($rows129(), 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($rows129(), 'next_pointer_map_parent'),
    'row expected pointer types' => static fn (): mixed => array_column($rows129(), 'expected_next_pointer_map_type'),
    'row expected pointer parents' => static fn (): mixed => array_column($rows129(), 'expected_next_pointer_map_parent'),
    'row next overflow next pages' => static fn (): mixed => array_column($rows129(), 'next_overflow_next_page'),
    'row tail flags' => static fn (): mixed => array_column($rows129(), 'next_overflow_is_tail'),
    'row payload prefixes' => static fn (): mixed => array_column($rows129(), 'payload_prefix'),
    'allocated images pages' => static fn (): mixed => array_keys($plan129()->allocatedPageImages()),
    'page image pages' => static fn (): mixed => array_keys($plan129()->pageImages()),
    'summary row count' => static fn (): mixed => count($plan129()->toArray()['btree_pointermap_freelist_overflow_current_source_next129']),
    'summary allocation entries' => static fn (): mixed => array_column($plan129()->toArray()['allocation']['allocated_pointer_map_entries'], 'type_name'),
    'summary allocation parents' => static fn (): mixed => array_column($plan129()->toArray()['allocation']['allocated_pointer_map_entries'], 'parent_page_number'),
    'secure delete clears released pages' => static fn (): mixed => $plan129(true)->releasePlan->freePlan->clearedPageNumbers,
    'bad payload count rejected' => static fn (): mixed => $throwMessage129(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next129FromOverflowChains($database129(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 0, 3, '')),
    'bad parent rejected' => static fn (): mixed => $throwMessage129(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next129FromOverflowChains($database129(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 4, 1, 'abcd')),
    'short payload rejected' => static fn (): mixed => $throwMessage129(static fn () => SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next129FromOverflowChains($database129(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 4, 3, 'abc')),
];

$expected129 = [
    'action label' => 'btree-pointermap-freelist-overflow-current-source-next129',
    'released overflow pages' => [6, 7],
    'allocated overflow pages' => [9, 7, 6],
    'reused released overflow pages' => [6, 7],
    'allocated existing freelist pages' => [9],
    'final freelist pages' => [8],
    'final freelist count' => 1,
    'updated page numbers' => [1, 2, 6, 7, 8, 9],
    'row page numbers' => [9, 7, 6],
    'row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page'],
    'row release sources' => [null, 'wp-option-delete-stale-cache-overflow-chain', 'wp-option-delete-stale-cache-overflow-chain'],
    'row allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'row before pointer types' => ['free-page', 'overflow-page', 'first-overflow-page'],
    'row before pointer parents' => [0, 6, 3],
    'row free pointer types' => ['free-page', 'free-page', 'free-page'],
    'row free pointer parents' => [0, 0, 0],
    'row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'row next pointer parents' => [3, 9, 7],
    'row expected pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'row expected pointer parents' => [3, 9, 7],
    'row next overflow next pages' => [7, 6, 0],
    'row tail flags' => [false, false, true],
    'row payload prefixes' => [str_repeat('N', 12), str_repeat('N', 12), str_repeat('N', 12)],
    'allocated images pages' => [9, 7, 6],
    'page image pages' => [1, 2, 6, 7, 8, 9],
    'summary row count' => 3,
    'summary allocation entries' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'summary allocation parents' => [3, 9, 7],
    'secure delete clears released pages' => [6, 7],
    'bad payload count rejected' => 'SQLite b-tree pointer-map freelist overflow next129 payload byte count must be positive',
    'bad parent rejected' => 'SQLite b-tree pointer-map freelist overflow next129 parent b-tree page must be at page 2 or later',
    'short payload rejected' => 'SQLite b-tree pointer-map freelist overflow next129 payload image is shorter than requested bytes',
];

$tests = [];

foreach ($cases129 as $name => $callback) {
    $tests['btree pointermap freelist overflow current source next129 ' . $name] = static function (TestRunner $t) use ($callback, $expected129, $name): void {
        $t->same($expected129[$name], $callback());
    };
}

foreach (range(1, 34) as $index) {
    $tests['btree pointermap freelist overflow current source next129 invariant ' . $index] = static function (TestRunner $t) use ($plan129): void {
        $plan = $plan129();
        $rows = $plan->rows;

        $t->same([6, 7], $plan->releasedOverflowPages());
        $t->same([9, 7, 6], $plan->allocatedOverflowPages());
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], array_column($rows, 'next_pointer_map_type'));
        $t->same([3, 9, 7], array_column($rows, 'next_pointer_map_parent'));
        $t->same([7, 6, 0], array_column($rows, 'next_overflow_next_page'));
        $t->same([8], $plan->databaseAfterAllocation->freelistPageNumbers());
        $t->same(str_repeat('N', 12), $rows[0]['payload_prefix']);
    };
}

return $tests;
