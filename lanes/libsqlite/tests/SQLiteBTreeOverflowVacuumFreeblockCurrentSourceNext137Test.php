<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage137 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 5), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeFragmentedLeaf137 = static function (): string {
    $page = "\r" . str_repeat("\0", 511);
    $page = substr_replace($page, pack('n', 200), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 190), 5, 2);
    $page[7] = chr(2);
    $page = substr_replace($page, pack('n', 222) . pack('n', 20), 200, 4);
    $page = substr_replace($page, 'aaaaaaaaaaaaaaaa', 204, 16);
    $page = substr_replace($page, 'xy', 220, 2);
    $page = substr_replace($page, pack('n', 0) . pack('n', 30), 222, 4);
    $page = substr_replace($page, 'bbbbbbbbbbbbbbbbbbbbbbbbbb', 226, 26);
    $page = substr_replace($page, pack('n', 450), 8, 2);
    $page = substr_replace($page, str_repeat('C', 40), 450, 40);

    return $page;
};

$putPointerMapEntry137 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$database137 = static function () use ($makeFirstPage137, $makeFragmentedLeaf137, $putPointerMapEntry137): SQLiteDatabase {
    $pages = array_fill(1, 14, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage137(14);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $makeFragmentedLeaf137();
    $pages[5] = "\n" . str_repeat("\0", 511);
    $pages[10] = substr(str_pad(str_repeat('live-option-row-next137', 24), 512, 'x'), 0, 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
        10 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        12 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        13 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 12],
        14 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 13],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry137($pages, $pageNumber, $type, $parent);
    }

    foreach ([6 => 7, 7 => 8, 8 => 0, 12 => 13, 13 => 14, 14 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + $pageNumber), 508);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$deleteResults137 = [[
    'source' => 'wp_options-table-leaf-freeblock-vacuum-next137',
    'leaf_page' => 3,
    'rowids' => [13701, 13702],
    'obsolete_overflow_page_numbers' => [6, 7, 8],
], [
    'source' => 'wp_options-autoload-index-freeblock-vacuum-next137',
    'leaf_page' => 3,
    'record_values' => [['autoload', 'yes', 13701]],
    'obsolete_overflow_page_numbers' => [12, 13, 14],
]];

$payload137 = str_repeat('next137-application-overflow-vacuum-freeblock-', 24);

$plan137 = static fn (int $limit = 3, string $payload = null): SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan => SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan::fromDeleteResults(
    $database137(),
    3,
    $deleteResults137,
    $limit,
    5,
    $payload ?? $payload137,
);

$throwMessage137 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows137 = static fn (): array => $plan137()->rows;

$cases137 = [
    'action label' => static fn (): mixed => $plan137()->toArray()['action'],
    'vacuum action label' => static fn (): mixed => $plan137()->vacuumPlan->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan137()->releasedOverflowPages(),
    'surviving vacuum free pages' => static fn (): mixed => $plan137()->survivingFreedPointerMapPages(),
    'truncated vacuum free pages' => static fn (): mixed => $plan137()->truncatedFreedPointerMapPages(),
    'allocated overflow pages' => static fn (): mixed => $plan137()->allocatedOverflowPages(),
    'reused surviving freed pages' => static fn (): mixed => $plan137()->reusedSurvivingFreedPages(),
    'allocation sources' => static fn (): mixed => array_column($plan137()->allocationPlan->allocationSteps(), 'source'),
    'allocation trunks' => static fn (): mixed => array_column($plan137()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation pointer pages' => static fn (): mixed => array_column($plan137()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer types' => static fn (): mixed => array_column($plan137()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer parents' => static fn (): mixed => array_column($plan137()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'row page numbers' => static fn (): mixed => array_column($rows137(), 'page_number'),
    'row sources' => static fn (): mixed => array_column($rows137(), 'source'),
    'row vacuum statuses' => static fn (): mixed => array_column($rows137(), 'vacuum_status'),
    'row free pointer types' => static fn (): mixed => array_column($rows137(), 'free_pointer_map_type'),
    'row allocated flags' => static fn (): mixed => array_column($rows137(), 'allocated_after_vacuum'),
    'row next pointer types' => static fn (): mixed => array_column($rows137(), 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($rows137(), 'next_pointer_map_parent'),
    'row next overflow next pages' => static fn (): mixed => array_column($rows137(), 'next_overflow_next_page'),
    'row payload prefixes' => static fn (): mixed => array_column($rows137(), 'payload_prefix'),
    'row coalesced fragments' => static fn (): mixed => array_values(array_unique(array_column($rows137(), 'coalesced_fragment_bytes'))),
    'final database page count' => static fn (): mixed => $plan137()->databaseAfterAllocation->pageCount(),
    'final freelist pages' => static fn (): mixed => $plan137()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan137()->databaseAfterAllocation->header->freelistPageCount,
    'final page six pointer type' => static fn (): mixed => $plan137()->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'final page seven pointer parent' => static fn (): mixed => $plan137()->databaseAfterAllocation->pointerMapEntryForPage(7)->parentPageNumber,
    'final page eight pointer parent' => static fn (): mixed => $plan137()->databaseAfterAllocation->pointerMapEntryForPage(8)->parentPageNumber,
    'final page twelve unavailable' => static function () use ($plan137): string {
        try {
            $plan137()->databaseAfterAllocation->page(12);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'overflow image keys' => static fn (): mixed => array_keys($plan137()->overflowPageImages()),
    'page image keys' => static fn (): mixed => array_keys($plan137()->pageImages()),
    'summary rows' => static fn (): mixed => array_column($plan137()->toArray()['btree_overflow_vacuum_freeblock_current_source_next137'], 'page_number'),
    'summary final freelist pages' => static fn (): mixed => $plan137()->toArray()['final_freelist_page_numbers'],
    'empty payload rejected' => static fn (): mixed => $throwMessage137(static fn () => SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan::fromDeleteResults($database137(), 3, $deleteResults137, 3, 5, '')),
    'bad parent rejected' => static fn (): mixed => $throwMessage137(static fn () => SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan::fromDeleteResults($database137(), 3, $deleteResults137, 3, 1, 'abc')),
];

$expected137 = [
    'action label' => 'btree-overflow-vacuum-freeblock-current-source-next137',
    'vacuum action label' => 'btree-overflow-freeblock-vacuum-current-source-next122',
    'released overflow pages' => [6, 7, 8, 12, 13, 14],
    'surviving vacuum free pages' => [6, 7, 8],
    'truncated vacuum free pages' => [12, 13, 14],
    'allocated overflow pages' => [7, 8, 6],
    'reused surviving freed pages' => [6, 7, 8],
    'allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'allocation trunks' => [6, 6, 6],
    'allocation pointer pages' => [7, 8, 6],
    'allocation pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer parents' => [5, 7, 8],
    'row page numbers' => [6, 7, 8, 12, 13, 14],
    'row sources' => ['wp_options-table-leaf-freeblock-vacuum-next137', 'wp_options-table-leaf-freeblock-vacuum-next137', 'wp_options-table-leaf-freeblock-vacuum-next137', 'wp_options-autoload-index-freeblock-vacuum-next137', 'wp_options-autoload-index-freeblock-vacuum-next137', 'wp_options-autoload-index-freeblock-vacuum-next137'],
    'row vacuum statuses' => ['survives-as-free-page', 'survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'row free pointer types' => ['free-page', 'free-page', 'free-page', null, null, null],
    'row allocated flags' => [true, true, true, false, false, false],
    'row next pointer types' => ['overflow-page', 'first-overflow-page', 'overflow-page', null, null, null],
    'row next pointer parents' => [8, 5, 7, null, null, null],
    'row next overflow next pages' => [0, 8, 6, null, null, null],
    'row payload prefixes' => [substr($payload137, 1016, 12), substr($payload137, 0, 12), substr($payload137, 508, 12), null, null, null],
    'row coalesced fragments' => [2],
    'final database page count' => 11,
    'final freelist pages' => [],
    'final freelist count' => 0,
    'final page six pointer type' => 'overflow-page',
    'final page seven pointer parent' => 5,
    'final page eight pointer parent' => 7,
    'final page twelve unavailable' => 'unavailable',
    'overflow image keys' => [7, 8, 6],
    'page image keys' => [1, 2, 3, 6, 7, 8],
    'summary rows' => [6, 7, 8, 12, 13, 14],
    'summary final freelist pages' => [],
    'empty payload rejected' => 'SQLite overflow vacuum freeblock next137 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite overflow vacuum freeblock next137 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases137 as $name => $callback) {
    $tests['btree overflow vacuum freeblock current source next137 ' . $name] = static function (TestRunner $t) use ($callback, $expected137, $name): void {
        $t->same($expected137[$name], $callback());
    };
}

foreach (range(1, 27) as $index) {
    $tests['btree overflow vacuum freeblock current source next137 invariant ' . $index] = static function (TestRunner $t) use ($plan137): void {
        $plan = $plan137();

        $t->same([6, 7, 8, 12, 13, 14], $plan->releasedOverflowPages());
        $t->same([6, 7, 8], $plan->survivingFreedPointerMapPages());
        $t->same([7, 8, 6], $plan->allocatedOverflowPages());
        $t->same(['overflow-page', 'first-overflow-page', 'overflow-page', null, null, null], array_column($plan->rows, 'next_pointer_map_type'));
        $t->same([8, 5, 7, null, null, null], array_column($plan->rows, 'next_pointer_map_parent'));
        $t->same([], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
