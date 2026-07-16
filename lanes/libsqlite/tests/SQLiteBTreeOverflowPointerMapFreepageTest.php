<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreepagePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage91 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry91 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$fixture91 = static function (bool $secureDelete = true, ?int $allocationLimit = null) use ($makeFirstPage91, $putPointerMapEntry91): SQLiteBTreeOverflowVacuumFreepagePlan {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage91(12);
    $pages[2] = str_repeat("\0", 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
    ] as $pageNumber => [$type, $parentPage]) {
        $putPointerMapEntry91($pages, $pageNumber, $type, $parentPage);
    }

    foreach ([6 => 7, 7 => 0, 8 => 9, 9 => 10, 10 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + $pageNumber), 508);
    }

    return SQLiteBTreeOverflowVacuumFreepagePlan::fromOverflowChains(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-transient-value-table-delete',
                'first_page' => 6,
                'overflow_payload_bytes' => 700,
                'rowids' => [91],
            ],
            [
                'source' => 'wp_options-option-name-index-delete',
                'first_page' => 8,
                'overflow_payload_bytes' => 1300,
                'record_values' => [['_transient_timeout_pointer-map freepage', 91]],
            ],
        ],
        $secureDelete,
        $allocationLimit,
    );
};

$rows91 = static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): array => $plan->overflowPointerMapFreepageRows();

$cases = [
    'action includes current source pointer-map freepage summary' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->toArray()['action'],
    'source labels follow chain order' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'source'),
    'released page numbers follow source chain order' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'page_number'),
    'current pointer map types preserve overflow ownership' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'current_type_name'),
    'current pointer map parents preserve chain ownership' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'current_parent_page_number'),
    'next pointer map types become free pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'next_type_name'),
    'next pointer map parents are zero' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'next_parent_page_number'),
    'freelist roles distinguish trunk and leaves' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'freelist_role'),
    'freelist positions match traversal' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'freelist_position'),
    'allocation positions match SQLite freelist order' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'next_allocation_position'),
    'all released pages point at pointer map page two' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_values(array_unique(array_column($rows91($plan), 'pointer_map_page'))),
    'secure delete clears all released pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows91($plan), 'secure_deleted'),
    'current freelist starts with released trunk' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->currentFirstFreelistTrunkPage(),
    'current freelist count covers released overflow pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->currentFreelistPageCount(),
    'current freelist pages match released pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->currentFreelistPages,
    'limited next allocation order is exposed' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->nextAllocationOrder,
    'sources retain table chain count' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->sources[0]['count'],
    'sources retain index chain count' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->sources[1]['count'],
    'summary embeds pointer-map freepage rows' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($plan->toArray()['overflow_pointer_map_freepage_rows'], 'page_number'),
    'updated pointer map page numbers are exposed' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->toArray()['updated_pointer_map_page_numbers'],
    'current page numbers include header pointer map and freelist pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->toArray()['current_page_numbers'],
    'secure delete page list is exposed' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->toArray()['secure_delete_cleared_pages'],
    'release plan records released overflow pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->releasePlan->releasedOverflowPages,
    'free plan records new trunk page' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->releasePlan->freePlan->newTrunkPageNumbers,
    'free plan records leaf pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->releasePlan->freePlan->leafPageNumbers,
];

$expected = [
    'action includes current source pointer-map freepage summary' => 'btree-overflow-vacuum-freepage-current-next',
    'source labels follow chain order' => [
        'wp_options-transient-value-table-delete',
        'wp_options-transient-value-table-delete',
        'wp_options-option-name-index-delete',
        'wp_options-option-name-index-delete',
        'wp_options-option-name-index-delete',
    ],
    'released page numbers follow source chain order' => [6, 7, 8, 9, 10],
    'current pointer map types preserve overflow ownership' => ['first-overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page', 'overflow-page'],
    'current pointer map parents preserve chain ownership' => [3, 6, 4, 8, 9],
    'next pointer map types become free pages' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'next pointer map parents are zero' => [0, 0, 0, 0, 0],
    'freelist roles distinguish trunk and leaves' => ['freelist-trunk', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'freelist positions match traversal' => [0, 1, 2, 3, 4],
    'allocation positions match SQLite freelist order' => [4, 0, 3, 2, 1],
    'all released pages point at pointer map page two' => [2],
    'secure delete clears all released pages' => [false, true, true, true, true],
    'current freelist starts with released trunk' => 6,
    'current freelist count covers released overflow pages' => 5,
    'current freelist pages match released pages' => [6, 7, 8, 9, 10],
    'limited next allocation order is exposed' => [7, 10, 9, 8, 6],
    'sources retain table chain count' => 2,
    'sources retain index chain count' => 3,
    'summary embeds pointer-map freepage rows' => [6, 7, 8, 9, 10],
    'updated pointer map page numbers are exposed' => [2],
    'current page numbers include header pointer map and freelist pages' => [1, 2, 6, 7, 8, 9, 10],
    'secure delete page list is exposed' => [7, 8, 9, 10],
    'release plan records released overflow pages' => [6, 7, 8, 9, 10],
    'free plan records new trunk page' => [6],
    'free plan records leaf pages' => [7, 8, 9, 10],
];

$tests = [];

foreach ($cases as $name => $callback) {
    $tests['btree overflow pointermap freepage current source pointer-map freepage ' . $name] = static function (TestRunner $t) use ($fixture91, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture91(true, 5)));
    };
}

foreach (range(1, 35) as $index) {
    $tests['btree overflow pointermap freepage current source pointer-map freepage invariant ' . $index] = static function (TestRunner $t) use ($fixture91, $rows91, $index): void {
        $limit = $index % 4;
        $secureDelete = $index % 5 !== 0;
        $plan = $fixture91($secureDelete, $limit === 0 ? null : $limit);
        $rows = $rows91($plan);

        $t->same($plan->releasePlan->releasedOverflowPages, array_column($rows, 'page_number'));
        $t->same(array_fill(0, count($rows), 'free-page'), array_column($rows, 'next_type_name'));
        $t->same(array_fill(0, count($rows), 0), array_column($rows, 'next_parent_page_number'));
        $t->same($plan->currentFreelistPages, array_column($rows, 'page_number'));
        $t->same(range(0, count($rows) - 1), array_column($rows, 'freelist_position'));
        $t->same([2], array_values(array_unique(array_column($rows, 'pointer_map_page'))));
        if ($secureDelete) {
            $t->same([false, true, true, true, true], array_column($rows, 'secure_deleted'));
        } else {
            $t->same([false, false, false, false, false], array_column($rows, 'secure_deleted'));
        }
    };
}

$tests['btree overflow pointermap freepage current source pointer-map freepage limited allocation keeps positions nullable'] = static function (TestRunner $t) use ($fixture91, $rows91): void {
    $rows = $rows91($fixture91(true, 2));
    $t->same([null, 0, null, null, 1], array_column($rows, 'next_allocation_position'));
};

$tests['btree overflow pointermap freepage current source pointer-map freepage rejects duplicate chains'] = static function (TestRunner $t) use ($fixture91): void {
    try {
        $plan = $fixture91();
        SQLiteBTreeOverflowVacuumFreepagePlan::fromOverflowChains(
            $plan->sourceDatabase,
            [
                ['first_page' => 6, 'overflow_payload_bytes' => 700],
                ['first_page' => 6, 'overflow_payload_bytes' => 700],
            ],
        );
    } catch (Throwable $exception) {
        $t->same('SQLite overflow freelist release page 6 appears more than once', $exception->getMessage());
        return;
    }

    $t->fail('duplicate overflow chain was not rejected');
};

return $tests;
