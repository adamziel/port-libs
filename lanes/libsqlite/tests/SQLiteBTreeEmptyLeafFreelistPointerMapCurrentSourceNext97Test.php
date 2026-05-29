<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage97 = static function (int $pageCount): string {
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

$putPointerMapEntry97 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$fixture97 = static function (bool $secureDelete = true, ?int $allocationLimit = null) use ($makeFirstPage97, $putPointerMapEntry97): SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage97(12);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteIndexLeafPage::assemble([]);
    foreach ([6 => 't', 7 => 'u', 8 => 'i', 9 => 'j', 10 => 'k'] as $pageNumber => $byte) {
        $pages[$pageNumber] = str_repeat($byte, 512);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
    ] as $pageNumber => [$type, $parentPage]) {
        $putPointerMapEntry97($pages, $pageNumber, $type, $parentPage);
    }

    return SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'leaf_page' => 3,
                'leaf_page_type' => 'table-leaf',
                'delete_result' => [
                    'page' => $pages[3],
                    'rowids' => [97, 98],
                    'obsolete_overflow_page_numbers' => [6, 7],
                ],
            ],
            [
                'leaf_page' => 4,
                'leaf_page_type' => 'index-leaf',
                'delete_result' => [
                    'page' => $pages[4],
                    'record_values' => [
                        ['_transient_timeout_next97', 97],
                        ['_transient_feed_next97', 98],
                    ],
                    'obsolete_overflow_page_numbers' => [8, 9, 10],
                ],
            ],
        ],
        $secureDelete,
        $allocationLimit,
    );
};

$rows97 = static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): array => $plan->emptyLeafFreelistPointerMapRows();

$cases = [
    'action label' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'leaf delete count' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['leaf_delete_count'],
    'released pages include empty leaves then overflow' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->batchPlan->freedPageNumbers,
    'current freelist pages' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['current_freelist_pages'],
    'allocation order follows SQLite trunk leaf ordering' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['current_freelist_allocation_order'],
    'source labels separate leaves and overflow' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'source'),
    'leaf pages are carried only for deleted leaves' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'leaf_page'),
    'leaf types are carried only for deleted leaves' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'leaf_page_type'),
    'table rowids are preserved on table leaf row' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $rows97($plan)[0]['deleted_rowids'],
    'index records are preserved on index leaf row' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $rows97($plan)[3]['deleted_record_values'],
    'current pointer map types' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'current_type_name'),
    'current pointer map parents' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'current_parent_page_number'),
    'next pointer map types become free' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'next_type_name'),
    'next pointer map parents are zero' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'next_parent_page_number'),
    'freelist roles' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'freelist_role'),
    'freelist positions' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'freelist_position'),
    'next allocation positions' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'next_allocation_position'),
    'all rows use pointer map page two' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_values(array_unique(array_column($rows97($plan), 'pointer_map_page'))),
    'secure delete clears non trunk released pages' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'secure_deleted'),
    'materialized flags are exposed' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows97($plan), 'materialized'),
    'first trunk page is empty table leaf page' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['current_first_freelist_trunk_page'],
    'freelist count covers leaves and overflow' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['current_freelist_page_count'],
    'updated page numbers include header pointer map and freelist' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['updated_page_numbers'],
    'updated pointer map page numbers' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['updated_pointer_map_page_numbers'],
    'secure delete page list' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['secure_delete_cleared_pages'],
    'summary embeds rows' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['empty_leaf_freelist_pointermap_current_source_next97'], 'page_number'),
    'table leaf is now freelist trunk page shape' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->currentDatabase->page(3), 4, 4))[1],
    'index leaf was secure deleted as freelist leaf' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => $plan->currentDatabase->page(4) === str_repeat("\0", 512),
    'source table leaf was empty before release' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => SQLiteBTreePageHeader::parsePage($plan->sourceDatabase->page(3), 512)->cellCount,
    'source index leaf was empty before release' => static fn (SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan $plan): mixed => SQLiteBTreePageHeader::parsePage($plan->sourceDatabase->page(4), 512)->cellCount,
];

$expected = [
    'action label' => 'btree-empty-leaf-freelist-pointermap-current-source-next97',
    'leaf delete count' => 2,
    'released pages include empty leaves then overflow' => [3, 6, 7, 4, 8, 9, 10],
    'current freelist pages' => [3, 6, 7, 4, 8, 9, 10],
    'allocation order follows SQLite trunk leaf ordering' => [6, 10, 9, 8, 4, 7, 3],
    'source labels separate leaves and overflow' => ['table-leaf-empty-page', 'obsolete-overflow-page', 'obsolete-overflow-page', 'index-leaf-empty-page', 'obsolete-overflow-page', 'obsolete-overflow-page', 'obsolete-overflow-page'],
    'leaf pages are carried only for deleted leaves' => [3, null, null, 4, null, null, null],
    'leaf types are carried only for deleted leaves' => ['table-leaf', null, null, 'index-leaf', null, null, null],
    'table rowids are preserved on table leaf row' => [97, 98],
    'index records are preserved on index leaf row' => [['_transient_timeout_next97', 97], ['_transient_feed_next97', 98]],
    'current pointer map types' => ['btree-page', 'first-overflow-page', 'overflow-page', 'btree-page', 'first-overflow-page', 'overflow-page', 'overflow-page'],
    'current pointer map parents' => [5, 3, 6, 5, 4, 8, 9],
    'next pointer map types become free' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'next pointer map parents are zero' => [0, 0, 0, 0, 0, 0, 0],
    'freelist roles' => ['freelist-trunk', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'freelist positions' => [0, 1, 2, 3, 4, 5, 6],
    'next allocation positions' => [6, 0, 5, 4, 3, 2, 1],
    'all rows use pointer map page two' => [2],
    'secure delete clears non trunk released pages' => [false, true, true, true, true, true, true],
    'materialized flags are exposed' => [true, true, true, true, true, true, true],
    'first trunk page is empty table leaf page' => 3,
    'freelist count covers leaves and overflow' => 7,
    'updated page numbers include header pointer map and freelist' => [1, 2, 3, 4, 6, 7, 8, 9, 10],
    'updated pointer map page numbers' => [2],
    'secure delete page list' => [6, 7, 4, 8, 9, 10],
    'summary embeds rows' => [3, 6, 7, 4, 8, 9, 10],
    'table leaf is now freelist trunk page shape' => 6,
    'index leaf was secure deleted as freelist leaf' => true,
    'source table leaf was empty before release' => 0,
    'source index leaf was empty before release' => 0,
];

$tests = [];

foreach ($cases as $name => $callback) {
    $tests['btree empty leaf freelist pointermap current source next97 ' . $name] = static function (TestRunner $t) use ($fixture97, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture97(true)));
    };
}

foreach (range(1, 32) as $index) {
    $tests['btree empty leaf freelist pointermap current source next97 invariant ' . $index] = static function (TestRunner $t) use ($fixture97, $rows97, $index): void {
        $limit = $index % 5;
        $secureDelete = $index % 4 !== 0;
        $plan = $fixture97($secureDelete, $limit === 0 ? null : $limit);
        $rows = $rows97($plan);

        $t->same($plan->batchPlan->freedPageNumbers, array_column($rows, 'page_number'));
        $t->same(array_fill(0, count($rows), 'free-page'), array_column($rows, 'next_type_name'));
        $t->same(array_fill(0, count($rows), 0), array_column($rows, 'next_parent_page_number'));
        $t->same($plan->currentDatabase->freelistPageNumbers(), array_column($rows, 'page_number'));
        $t->same(range(0, count($rows) - 1), array_column($rows, 'freelist_position'));
        $t->same([2], array_values(array_unique(array_column($rows, 'pointer_map_page'))));
        if ($secureDelete) {
            $t->same([false, true, true, true, true, true, true], array_column($rows, 'secure_deleted'));
        } else {
            $t->same([false, false, false, false, false, false, false], array_column($rows, 'secure_deleted'));
        }
    };
}

$tests['btree empty leaf freelist pointermap current source next97 limited allocation positions'] = static function (TestRunner $t) use ($fixture97, $rows97): void {
    $rows = $rows97($fixture97(true, 3));
    $t->same([null, 0, null, null, null, 2, 1], array_column($rows, 'next_allocation_position'));
};

$tests['btree empty leaf freelist pointermap current source next97 rejects repeated released page'] = static function (TestRunner $t) use ($fixture97): void {
    try {
        $plan = $fixture97();
        SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan::fromDeleteResults(
            $plan->sourceDatabase,
            [
                [
                    'leaf_page' => 3,
                    'leaf_page_type' => 'table-leaf',
                    'delete_result' => [
                        'page' => $plan->sourceDatabase->page(3),
                        'rowids' => [97],
                        'obsolete_overflow_page_numbers' => [6],
                    ],
                ],
                [
                    'leaf_page' => 4,
                    'leaf_page_type' => 'index-leaf',
                    'delete_result' => [
                        'page' => $plan->sourceDatabase->page(4),
                        'record_values' => [['_transient_timeout_next97', 97]],
                        'obsolete_overflow_page_numbers' => [6],
                    ],
                ],
            ],
        );
    } catch (Throwable $exception) {
        $t->same('SQLite empty leaf batch page 6 is released more than once', $exception->getMessage());
        return;
    }

    $t->fail('repeated freed page was not rejected');
};

return $tests;
