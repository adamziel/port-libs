<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage147 = static function (): string {
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

$fragmentedLeaf147 = static function (): string {
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

$putPointerMapEntry147 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database147 = static function () use ($makeFirstPage147, $fragmentedLeaf147, $putPointerMapEntry147): SQLiteDatabase {
    $pages = array_fill(1, 13, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage147();
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $fragmentedLeaf147();
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
        $putPointerMapEntry147($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan147 = static fn (bool $secureDelete = true): SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::extendedTableAndIndexFromCurrentSourceDeleteResults(
    $database147(),
    3,
    [
        [
            'source' => 'app-settings-value-overflow',
            'first_page' => 5,
            'overflow_payload_bytes' => 1016,
        ],
        [
            'source' => 'app-settings-key-index-overflow',
            'first_page' => 8,
            'overflow_payload_bytes' => 1016,
        ],
    ],
    [
        [
            'source' => 'app-settings-value-overflow',
            'rowid' => 14701,
            'obsolete_overflow_page_numbers' => [5, 6],
        ],
        [
            'source' => 'app-settings-key-index-overflow',
            'record_values' => [['_transient_setting', 14701]],
            'obsolete_overflow_page_numbers' => [8, 9],
        ],
    ],
    3,
    str_repeat('R', 2540),
    $secureDelete,
);

$throws147 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$currentRows147 = static fn (): array => $plan147()->currentSourceRows();
$nextRows147 = static fn (): array => $plan147()->nextRows();
$leafHeader147 = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan147()->databaseAfterAllocation->page(3), 512);

$cases147 = [
    'action label' => static fn (): mixed => $plan147()->toArray()['action'],
    'leaf page' => static fn (): mixed => $plan147()->toArray()['leaf_page'],
    'coalesced fragment bytes' => static fn (): mixed => $plan147()->coalescePlan->coalescedFragmentBytes,
    'current source pages' => static fn (): mixed => array_column($currentRows147(), 'page_number'),
    'current source sources' => static fn (): mixed => array_column($currentRows147(), 'source'),
    'current source chain indexes' => static fn (): mixed => array_column($currentRows147(), 'chain_index'),
    'current source chain positions' => static fn (): mixed => array_column($currentRows147(), 'chain_position'),
    'current source next pages' => static fn (): mixed => array_column($currentRows147(), 'current_next_page'),
    'current source terminal flags' => static fn (): mixed => array_column($currentRows147(), 'current_terminal'),
    'current source pointer types' => static fn (): mixed => array_column($currentRows147(), 'current_pointer_map_type'),
    'current source pointer parents' => static fn (): mixed => array_column($currentRows147(), 'current_pointer_map_parent'),
    'released pages' => static fn (): mixed => $plan147()->releasedOverflowPages(),
    'allocated pages' => static fn (): mixed => $plan147()->allocatedOverflowPages(),
    'reused pages' => static fn (): mixed => $plan147()->reusedReleasedOverflowPages(),
    'existing freelist pages' => static fn (): mixed => $plan147()->allocatedExistingFreelistPages(),
    'next row pages' => static fn (): mixed => array_column($nextRows147(), 'page_number'),
    'next row origins' => static fn (): mixed => array_column($nextRows147(), 'page_origin'),
    'next row release sources' => static fn (): mixed => array_column($nextRows147(), 'release_source'),
    'next row current sources' => static fn (): mixed => array_column($nextRows147(), 'current_source'),
    'next row current chain indexes' => static fn (): mixed => array_column($nextRows147(), 'current_chain_index'),
    'next row current chain positions' => static fn (): mixed => array_column($nextRows147(), 'current_chain_position'),
    'next row current next pages' => static fn (): mixed => array_column($nextRows147(), 'current_next_page'),
    'next row before pointer types' => static fn (): mixed => array_column($nextRows147(), 'before_pointer_map_type'),
    'next row before pointer parents' => static fn (): mixed => array_column($nextRows147(), 'before_pointer_map_parent'),
    'next row free pointer types' => static fn (): mixed => array_column($nextRows147(), 'free_pointer_map_type'),
    'next row next pointer types' => static fn (): mixed => array_column($nextRows147(), 'next_pointer_map_type'),
    'next row next pointer parents' => static fn (): mixed => array_column($nextRows147(), 'next_pointer_map_parent'),
    'next row allocation sources' => static fn (): mixed => array_column($nextRows147(), 'allocation_source'),
    'next row allocation trunks' => static fn (): mixed => array_column($nextRows147(), 'allocation_trunk_page'),
    'next row overflow next pages' => static fn (): mixed => array_column($nextRows147(), 'next_overflow_next_page'),
    'next row tail flags' => static fn (): mixed => array_column($nextRows147(), 'next_overflow_is_tail'),
    'next row payload prefixes' => static fn (): mixed => array_column($nextRows147(), 'payload_prefix'),
    'final freelist pages' => static fn (): mixed => $plan147()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan147()->databaseAfterAllocation->header->freelistPageCount,
    'final first trunk' => static fn (): mixed => $plan147()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'updated pages' => static fn (): mixed => $plan147()->toArray()['updated_page_numbers'],
    'overflow image pages' => static fn (): mixed => array_keys($plan147()->overflowPageImages()),
    'header from image freelist count' => static fn (): mixed => SQLiteHeader::parse($plan147()->pageImages()[1])->freelistPageCount,
    'leaf fragment status' => static fn (): mixed => $leafHeader147()->freeblockFragmentReport($plan147()->databaseAfterAllocation->page(3))['status'],
    'leaf secure delete zeroed' => static fn (): mixed => $leafHeader147()->freeblockSecureDeleteReport($plan147()->databaseAfterAllocation->page(3))['secure_delete_payload_zeroed'],
    'without secure delete keeps released table payload before allocation overwrite' => static fn (): mixed => substr($plan147(false)->databaseAfterRelease->page(6), 4, 1),
    'without secure delete keeps released index payload before allocation overwrite' => static fn (): mixed => substr($plan147(false)->databaseAfterRelease->page(9), 4, 1),
    'summary current pages' => static fn (): mixed => array_column($plan147()->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary next pages' => static fn (): mixed => array_column($plan147()->toArray()['btree_overflow_freeblock_pointermap_current_source'], 'page_number'),
    'source mismatch rejected' => static fn (): mixed => $throws147(static fn () => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::extendedTableAndIndexFromCurrentSourceDeleteResults($database147(), 3, [['first_page' => 5, 'overflow_payload_bytes' => 1016]], [['obsolete_overflow_page_numbers' => [5, 6]], ['obsolete_overflow_page_numbers' => [8, 9]]], 3, 'x')),
    'single delete rejected' => static fn (): mixed => $throws147(static fn () => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::extendedTableAndIndexFromCurrentSourceDeleteResults($database147(), 3, [['first_page' => 5, 'overflow_payload_bytes' => 1016]], [['obsolete_overflow_page_numbers' => [5, 6]]], 3, 'x')),
    'empty payload rejected' => static fn (): mixed => $throws147(static fn () => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::extendedTableAndIndexFromCurrentSourceDeleteResults($database147(), 3, [['first_page' => 5, 'overflow_payload_bytes' => 1016]], [['obsolete_overflow_page_numbers' => [5, 6]], ['obsolete_overflow_page_numbers' => [8, 9]]], 3, '')),
];

$expected147 = [
    'action label' => 'btree-overflow-freeblock-pointermap-current-source',
    'leaf page' => 3,
    'coalesced fragment bytes' => 2,
    'current source pages' => [5, 6, 8, 9],
    'current source sources' => ['app-settings-value-overflow', 'app-settings-value-overflow', 'app-settings-key-index-overflow', 'app-settings-key-index-overflow'],
    'current source chain indexes' => [0, 0, 1, 1],
    'current source chain positions' => [0, 1, 0, 1],
    'current source next pages' => [6, 0, 9, 0],
    'current source terminal flags' => [false, true, false, true],
    'current source pointer types' => ['first-overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'current source pointer parents' => [3, 5, 4, 8],
    'released pages' => [5, 6, 8, 9],
    'allocated pages' => [11, 9, 8, 6, 5],
    'reused pages' => [5, 6, 8, 9],
    'existing freelist pages' => [11],
    'next row pages' => [11, 9, 8, 6, 5],
    'next row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page'],
    'next row release sources' => [null, 'app-settings-key-index-overflow', 'app-settings-key-index-overflow', 'app-settings-value-overflow', 'app-settings-value-overflow'],
    'next row current sources' => [null, 'app-settings-key-index-overflow', 'app-settings-key-index-overflow', 'app-settings-value-overflow', 'app-settings-value-overflow'],
    'next row current chain indexes' => [null, 1, 1, 0, 0],
    'next row current chain positions' => [null, 1, 0, 1, 0],
    'next row current next pages' => [null, 0, 9, 0, 6],
    'next row before pointer types' => ['free-page', 'overflow-page', 'first-overflow-page', 'overflow-page', 'first-overflow-page'],
    'next row before pointer parents' => [0, 8, 4, 5, 3],
    'next row free pointer types' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'next row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'next row next pointer parents' => [3, 11, 9, 8, 6],
    'next row allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'next row allocation trunks' => [10, 10, 10, 10, 10],
    'next row overflow next pages' => [9, 8, 6, 5, 0],
    'next row tail flags' => [false, false, false, false, true],
    'next row payload prefixes' => [str_repeat('R', 12), str_repeat('R', 12), str_repeat('R', 12), str_repeat('R', 12), str_repeat('R', 12)],
    'final freelist pages' => [10, 13, 12],
    'final freelist count' => 3,
    'final first trunk' => 10,
    'updated pages' => [1, 2, 3, 5, 6, 8, 9, 10, 11],
    'overflow image pages' => [11, 9, 8, 6, 5],
    'header from image freelist count' => 3,
    'leaf fragment status' => 'ok',
    'leaf secure delete zeroed' => true,
    'without secure delete keeps released table payload before allocation overwrite' => 'U',
    'without secure delete keeps released index payload before allocation overwrite' => 'J',
    'summary current pages' => [5, 6, 8, 9],
    'summary next pages' => [11, 9, 8, 6, 5],
    'source mismatch rejected' => 'SQLite b-tree overflow freeblock pointer-map current-source delete results must match current-source overflow pages',
    'single delete rejected' => 'SQLite b-tree overflow freeblock pointer-map current-source requires table and index delete results',
    'empty payload rejected' => 'SQLite b-tree overflow freeblock pointer-map current-source requires replacement overflow payload bytes',
];

$tests = [];

foreach ($cases147 as $name => $callback) {
    $tests['btree overflow freeblock pointermap current source extended ' . $name] = static function (TestRunner $t) use ($callback, $expected147, $name): void {
        $t->same($expected147[$name], $callback());
    };
}

foreach (range(1, 40) as $index) {
    $tests['btree overflow freeblock pointermap current source extended invariant ' . $index] = static function (TestRunner $t) use ($plan147): void {
        $plan = $plan147();

        $t->same([5, 6, 8, 9], $plan->releasedOverflowPages());
        $t->same([11, 9, 8, 6, 5], $plan->allocatedOverflowPages());
        $t->same([5, 6, 8, 9], $plan->reusedReleasedOverflowPages());
        $t->same([11], $plan->allocatedExistingFreelistPages());
        $t->same(['free-page', 'free-page', 'free-page', 'free-page', 'free-page'], array_column($plan->nextRows(), 'free_pointer_map_type'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->nextRows(), 'next_pointer_map_type'));
        $t->same([3, 11, 9, 8, 6], array_column($plan->nextRows(), 'next_pointer_map_parent'));
        $t->same([9, 8, 6, 5, 0], array_column($plan->nextRows(), 'next_overflow_next_page'));
        $t->same([10, 13, 12], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
