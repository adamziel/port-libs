<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreepagePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage102 = static function (int $pageCount): string {
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

$putPointerMapEntry102 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$fixture102 = static function (bool $secureDelete = true, int $allocationCount = 5, ?int $parentPage = 3) use ($makeFirstPage102, $putPointerMapEntry102): SQLiteBTreeOverflowVacuumFreepagePlan {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage102(12);
    $pages[2] = str_repeat("\0", 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry102($pages, $pageNumber, $type, $parent);
    }

    foreach ([6 => 7, 7 => 0, 8 => 9, 9 => 10, 10 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + $pageNumber), 508);
    }

    return SQLiteBTreeOverflowVacuumFreepagePlan::fromOverflowChains(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-value-vacuum-rebuild',
                'first_page' => 6,
                'overflow_payload_bytes' => 700,
                'rowids' => [102],
            ],
            [
                'source' => 'wp_options-name-index-vacuum-rebuild',
                'first_page' => 8,
                'overflow_payload_bytes' => 1300,
                'record_values' => [['_transient_overflow freepage vacuum', 102]],
            ],
        ],
        $secureDelete,
        $allocationCount,
    );
};

$rows102 = static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan, int $count = 5, ?int $parent = 3): array => $plan->overflowFreepageVacuumRows($count, $parent);

$cases = [
    'summary embeds overflow freepage vacuum rows' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($plan->toArray()['overflow_freepage_vacuum_rows'], 'page_number'),
    'vacuum allocation order consumes freelist leaves before trunk' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'page_number'),
    'source labels follow allocated obsolete overflow pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'source'),
    'current status starts from free pointer-map pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_values(array_unique(array_column($rows102($plan), 'current_status'))),
    'next status marks btree reuse' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_values(array_unique(array_column($rows102($plan), 'next_status'))),
    'freelist roles preserve release roles' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'freelist_role'),
    'freelist positions preserve traversal' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'freelist_position'),
    'allocation positions are current-source next order' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'allocation_position'),
    'allocation sources show leaf reuse before trunk reuse' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'allocation_source'),
    'allocation trunk page remains released first page' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_values(array_unique(array_column($rows102($plan), 'allocation_trunk_page'))),
    'current pointer-map types are free pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'current_pointer_map_type'),
    'current pointer-map parents are zero' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'current_pointer_map_parent'),
    'next pointer-map types become btree pages' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'next_pointer_map_type'),
    'next pointer-map parents target rebuilt parent' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'next_pointer_map_parent'),
    'pointer-map page is preserved' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_values(array_unique(array_column($rows102($plan), 'pointer_map_page'))),
    'secure delete state survives until reuse' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan), 'secure_deleted_before_reuse'),
    'partial vacuum exposes nullable unallocated pages through rows only' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan, 2), 'page_number'),
    'partial vacuum allocation sources' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan, 2), 'allocation_source'),
    'custom parent rewrites next pointer-map parent' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => array_column($rows102($plan, 3, 4), 'next_pointer_map_parent'),
    'non secure delete reports no cleared pages before reuse' => static fn (): mixed => array_column($rows102($fixture102(false), 5), 'secure_deleted_before_reuse'),
    'zero allocation returns empty rows' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $rows102($plan, 0),
    'negative allocation is rejected' => static function (SQLiteBTreeOverflowVacuumFreepagePlan $plan) use ($rows102): string {
        try {
            $rows102($plan, -1);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'bad parent is rejected' => static function (SQLiteBTreeOverflowVacuumFreepagePlan $plan) use ($rows102): string {
        try {
            $rows102($plan, 1, 1);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'over allocation without append is rejected' => static function (SQLiteBTreeOverflowVacuumFreepagePlan $plan) use ($rows102): string {
        try {
            $rows102($plan, 6);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'current freelist remains released page traversal' => static fn (SQLiteBTreeOverflowVacuumFreepagePlan $plan): mixed => $plan->currentFreelistPages,
];

$expected = [
    'summary embeds overflow freepage vacuum rows' => [7, 10, 9, 8, 6],
    'vacuum allocation order consumes freelist leaves before trunk' => [7, 10, 9, 8, 6],
    'source labels follow allocated obsolete overflow pages' => [
        'wp_options-value-vacuum-rebuild',
        'wp_options-name-index-vacuum-rebuild',
        'wp_options-name-index-vacuum-rebuild',
        'wp_options-name-index-vacuum-rebuild',
        'wp_options-value-vacuum-rebuild',
    ],
    'current status starts from free pointer-map pages' => ['current-pointer-map-free-page'],
    'next status marks btree reuse' => ['allocated-btree-page'],
    'freelist roles preserve release roles' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'freelist positions preserve traversal' => [1, 4, 3, 2, 0],
    'allocation positions are current-source next order' => [0, 1, 2, 3, 4],
    'allocation sources show leaf reuse before trunk reuse' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'],
    'allocation trunk page remains released first page' => [6],
    'current pointer-map types are free pages' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'current pointer-map parents are zero' => [0, 0, 0, 0, 0],
    'next pointer-map types become btree pages' => ['btree-page', 'btree-page', 'btree-page', 'btree-page', 'btree-page'],
    'next pointer-map parents target rebuilt parent' => [3, 3, 3, 3, 3],
    'pointer-map page is preserved' => [2],
    'secure delete state survives until reuse' => [true, true, true, true, false],
    'partial vacuum exposes nullable unallocated pages through rows only' => [7, 10],
    'partial vacuum allocation sources' => ['freelist-leaf', 'freelist-leaf'],
    'custom parent rewrites next pointer-map parent' => [4, 4, 4],
    'non secure delete reports no cleared pages before reuse' => [false, false, false, false, false],
    'zero allocation returns empty rows' => [],
    'negative allocation is rejected' => 'SQLite overflow freepage vacuum current-source overflow freepage vacuum allocation count cannot be negative',
    'bad parent is rejected' => 'SQLite overflow freepage vacuum current-source overflow freepage vacuum parent page must be null or at page 2 or later',
    'over allocation without append is rejected' => 'SQLite freelist does not contain enough pages for this allocation',
    'current freelist remains released page traversal' => [6, 7, 8, 9, 10],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree overflow freepage vacuum current source overflow freepage vacuum ' . $name] = static function (TestRunner $t) use ($fixture102, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture102()));
    };
}

foreach (range(1, 35) as $index) {
    $tests['btree overflow freepage vacuum current source overflow freepage vacuum invariant ' . $index] = static function (TestRunner $t) use ($fixture102, $rows102, $index): void {
        $secureDelete = $index % 4 !== 0;
        $count = ($index % 5) + 1;
        $parent = $index % 3 === 0 ? 4 : 3;
        $plan = $fixture102($secureDelete, $count, $parent);
        $rows = $rows102($plan, $count, $parent);

        $t->same($count, count($rows));
        $t->same(range(0, $count - 1), array_column($rows, 'allocation_position'));
        $t->same(array_slice([7, 10, 9, 8, 6], 0, $count), array_column($rows, 'page_number'));
        $t->same(array_fill(0, $count, 'free-page'), array_column($rows, 'current_pointer_map_type'));
        $t->same(array_fill(0, $count, 'btree-page'), array_column($rows, 'next_pointer_map_type'));
        $t->same(array_fill(0, $count, $parent), array_column($rows, 'next_pointer_map_parent'));
        $t->same([2], array_values(array_unique(array_column($rows, 'pointer_map_page'))));
        if (!$secureDelete) {
            $t->same(array_fill(0, $count, false), array_column($rows, 'secure_deleted_before_reuse'));
        }
    };
}

return $tests;
