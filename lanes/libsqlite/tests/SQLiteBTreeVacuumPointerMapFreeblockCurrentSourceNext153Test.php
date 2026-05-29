<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage153 = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 13), 28, 4);
    $page = substr_replace($page, pack('N', 10), 32, 4);
    $page = substr_replace($page, pack('N', 4), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$fragmentedLeaf153 = static function (): string {
    $page = str_repeat("\xdd", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 390), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 376), 5, 2);
    $page[7] = chr(7);
    $page = substr_replace($page, pack('n', 496), 8, 2);
    $page = substr_replace($page, str_repeat('L', 12), 496, 12);
    $page = substr_replace($page, pack('n', 406) . pack('n', 12), 390, 4);
    $page = substr_replace($page, pack('n', 422) . pack('n', 14), 406, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 18), 422, 4);

    return $page;
};

$putPointerMapEntry153 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database153 = static function () use ($makeFirstPage153, $fragmentedLeaf153, $putPointerMapEntry153): SQLiteDatabase {
    $pages = array_fill(1, 13, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage153();
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $fragmentedLeaf153();
    $pages[5] = pack('N', 6) . str_repeat('T', 508);
    $pages[6] = pack('N', 0) . str_repeat('U', 508);
    $pages[8] = pack('N', 9) . str_repeat('I', 508);
    $pages[9] = pack('N', 0) . str_repeat('J', 508);
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [11, 12, 13], 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        13 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry153($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan153 = static fn (bool $secureDelete = true): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan => SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableAndIndexFromCurrentSourceDeleteResultsNext153(
    $database153(),
    3,
    [
        [
            'source' => 'wp_options-autoload-value-current-source-next153',
            'first_page' => 5,
            'overflow_payload_bytes' => 1016,
        ],
        [
            'source' => 'wp_options-option-name-index-current-source-next153',
            'first_page' => 8,
            'overflow_payload_bytes' => 1016,
        ],
    ],
    [
        [
            'source' => 'wp_options-autoload-value-current-source-next153',
            'rowid' => 15301,
            'obsolete_overflow_page_numbers' => [5, 6],
        ],
        [
            'source' => 'wp_options-option-name-index-current-source-next153',
            'record_values' => [['_transient_next153', 15301]],
            'obsolete_overflow_page_numbers' => [8, 9],
        ],
    ],
    3,
    str_repeat('N', 2540),
    $secureDelete,
);

$message153 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows153 = static fn (): array => $plan153()->rows;
$reuseRows153 = static fn (): array => $plan153()->reusedReleasedRows();
$freelistRows153 = static fn (): array => $plan153()->existingFreelistRows();
$leaf153 = static fn (): array => $plan153()->rows[0];

$cases153 = [
    'action label' => static fn (): mixed => $plan153()->toArray()['action'],
    'released pages' => static fn (): mixed => $plan153()->toArray()['released_overflow_pages'],
    'allocated pages' => static fn (): mixed => $plan153()->toArray()['allocated_overflow_pages'],
    'reused released pages' => static fn (): mixed => $plan153()->toArray()['reused_released_overflow_pages'],
    'existing freelist allocation' => static fn (): mixed => $plan153()->toArray()['allocated_existing_freelist_pages'],
    'row kinds' => static fn (): mixed => array_column($rows153(), 'kind'),
    'row pages' => static fn (): mixed => array_column($rows153(), 'page_number'),
    'row statuses' => static fn (): mixed => array_column($rows153(), 'vacuum_reuse_status'),
    'reuse row pages' => static fn (): mixed => array_column($reuseRows153(), 'page_number'),
    'freelist row pages' => static fn (): mixed => array_column($freelistRows153(), 'page_number'),
    'release sources' => static fn (): mixed => array_column($reuseRows153(), 'release_source'),
    'current sources' => static fn (): mixed => array_column($reuseRows153(), 'current_source'),
    'current chain positions' => static fn (): mixed => array_column($reuseRows153(), 'current_chain_position'),
    'current next pages' => static fn (): mixed => array_column($reuseRows153(), 'current_next_page'),
    'before pointer types' => static fn (): mixed => array_column($reuseRows153(), 'before_pointer_map_type'),
    'free pointer types' => static fn (): mixed => array_column($reuseRows153(), 'free_pointer_map_type'),
    'next pointer types' => static fn (): mixed => array_column($reuseRows153(), 'next_pointer_map_type'),
    'next pointer parents' => static fn (): mixed => array_column($reuseRows153(), 'next_pointer_map_parent'),
    'next overflow next pages' => static fn (): mixed => array_column($rows153(), 'next_overflow_next_page'),
    'next tail flags' => static fn (): mixed => array_column($rows153(), 'next_overflow_is_tail'),
    'payload prefixes' => static fn (): mixed => array_column($reuseRows153(), 'payload_prefix'),
    'leaf freeblock status' => static fn (): mixed => $leaf153()['freeblock_status'],
    'leaf freeblock count' => static fn (): mixed => $leaf153()['freeblock_count'],
    'leaf freeblock bytes' => static fn (): mixed => $leaf153()['freeblock_bytes'],
    'leaf coalesced bytes' => static fn (): mixed => $leaf153()['coalesced_fragment_bytes'],
    'leaf fragment before after' => static fn (): mixed => [$leaf153()['fragmented_bytes_before'], $leaf153()['fragmented_bytes_after']],
    'leaf secure delete' => static fn (): mixed => $leaf153()['secure_delete_zeroed'],
    'final freelist pages' => static fn (): mixed => $plan153()->toArray()['final_freelist_page_numbers'],
    'updated pages' => static fn (): mixed => $plan153()->toArray()['updated_page_numbers'],
    'base action' => static fn (): mixed => $plan153()->toArray()['base_plan']['action'],
    'without secure delete preserves released table byte before reuse' => static fn (): mixed => substr($plan153(false)->basePlan->databaseAfterRelease->page(6), 4, 1),
    'without secure delete preserves released index byte before reuse' => static fn (): mixed => substr($plan153(false)->basePlan->databaseAfterRelease->page(9), 4, 1),
    'no autovacuum rejected' => static fn (): mixed => $message153(static fn () => SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableAndIndexFromCurrentSourceDeleteResultsNext153(SQLiteDatabase::fromBytes(substr_replace($database153()->toBytes(), pack('N', 0), 52, 4)), 3, [['first_page' => 5, 'overflow_payload_bytes' => 1016]], [['obsolete_overflow_page_numbers' => [5, 6]], ['obsolete_overflow_page_numbers' => [8, 9]]], 3, 'x')),
    'empty replacement rejected' => static fn (): mixed => $message153(static fn () => SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableAndIndexFromCurrentSourceDeleteResultsNext153($database153(), 3, [['first_page' => 5, 'overflow_payload_bytes' => 1016]], [['obsolete_overflow_page_numbers' => [5, 6]], ['obsolete_overflow_page_numbers' => [8, 9]]], 3, '')),
];

$expected153 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next153',
    'released pages' => [5, 6, 8, 9],
    'allocated pages' => [11, 9, 8, 6, 5],
    'reused released pages' => [5, 6, 8, 9],
    'existing freelist allocation' => [11],
    'row kinds' => ['deleted-leaf-freeblock', 'overflow-page-vacuum-reuse', 'overflow-page-vacuum-reuse', 'overflow-page-vacuum-reuse', 'overflow-page-vacuum-reuse', 'overflow-page-vacuum-reuse'],
    'row pages' => [3, 11, 9, 8, 6, 5],
    'row statuses' => ['leaf-freeblock-materialized', 'existing-freelist-reused', 'released-overflow-reused', 'released-overflow-reused', 'released-overflow-reused', 'released-overflow-reused'],
    'reuse row pages' => [9, 8, 6, 5],
    'freelist row pages' => [11],
    'release sources' => ['wp_options-option-name-index-current-source-next153', 'wp_options-option-name-index-current-source-next153', 'wp_options-autoload-value-current-source-next153', 'wp_options-autoload-value-current-source-next153'],
    'current sources' => ['wp_options-option-name-index-current-source-next153', 'wp_options-option-name-index-current-source-next153', 'wp_options-autoload-value-current-source-next153', 'wp_options-autoload-value-current-source-next153'],
    'current chain positions' => [1, 0, 1, 0],
    'current next pages' => [0, 9, 0, 6],
    'before pointer types' => ['overflow-page', 'first-overflow-page', 'overflow-page', 'first-overflow-page'],
    'free pointer types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'next pointer types' => ['overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'next pointer parents' => [11, 9, 8, 6],
    'next overflow next pages' => [9, 8, 6, 5, 0],
    'next tail flags' => [false, false, false, false, true],
    'payload prefixes' => [str_repeat('N', 12), str_repeat('N', 12), str_repeat('N', 12), str_repeat('N', 12)],
    'leaf freeblock status' => 'ok',
    'leaf freeblock count' => 2,
    'leaf freeblock bytes' => 46,
    'leaf coalesced bytes' => 2,
    'leaf fragment before after' => [7, 5],
    'leaf secure delete' => true,
    'final freelist pages' => [10, 13, 12],
    'updated pages' => [1, 2, 3, 5, 6, 8, 9, 10, 11],
    'base action' => 'btree-overflow-freeblock-pointermap-current-source-next147',
    'without secure delete preserves released table byte before reuse' => 'U',
    'without secure delete preserves released index byte before reuse' => 'J',
    'no autovacuum rejected' => 'SQLite b-tree overflow freeblock pointer-map next147 requires an auto-vacuum database',
    'empty replacement rejected' => 'SQLite b-tree overflow freeblock pointer-map next147 requires replacement overflow payload bytes',
];

foreach ($cases153 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next153 ' . $name] = static function (TestRunner $t) use ($callback, $expected153, $name): void {
        $t->same($expected153[$name], $callback());
    };
}

foreach (range(1, 40) as $index) {
    $tests['btree vacuum pointermap freeblock current source next153 invariant ' . $index] = static function (TestRunner $t) use ($plan153): void {
        $plan = $plan153();
        $leaf = $plan->rows[0];

        $t->same('ok', $leaf['freeblock_status']);
        $t->same([9, 8, 6, 5], array_column($plan->reusedReleasedRows(), 'page_number'));
        $t->same(['free-page', 'free-page', 'free-page', 'free-page'], array_column($plan->reusedReleasedRows(), 'free_pointer_map_type'));
        $t->same(['overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->reusedReleasedRows(), 'next_pointer_map_type'));
        $t->same([11, 9, 8, 6], array_column($plan->reusedReleasedRows(), 'next_pointer_map_parent'));
        $t->same([10, 13, 12], $plan->basePlan->databaseAfterAllocation->freelistPageNumbers());
        $t->same('ok', SQLiteBTreePageHeader::parsePage($plan->basePlan->databaseAfterAllocation->page(3), 512)->freeblockIntegrityReport($plan->basePlan->databaseAfterAllocation->page(3))['status']);
    };
}

return $tests;
