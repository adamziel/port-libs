<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage119 = static function (int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry119 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$fixture119 = static function (int $maxTruncatedPages = 2, bool $secureDelete = true) use ($makeFirstPage119, $putPointerMapEntry119): SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage119(12);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = "\r" . str_repeat("\0", 511);
    $pages[4] = "\n" . str_repeat("\0", 511);
    $pages[10] = substr(str_pad(str_repeat('live-tail-page', 39), 512, 'x'), 0, 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        10 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        11 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 11],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry119($pages, $pageNumber, $type, $parent);
    }

    foreach ([6 => 7, 7 => 0, 11 => 12, 12 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(80 + $pageNumber), 508);
    }

    return SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-table-delete',
                'leaf_page' => 3,
                'page' => "\r" . str_repeat("\0", 511),
                'rowids' => [11901],
                'obsolete_overflow_page_numbers' => [6, 7],
            ],
            [
                'source' => 'wp_options-option-name-index-delete',
                'leaf_page' => 4,
                'page' => "\n" . str_repeat("\0", 511),
                'record_values' => [['_transient_next119', 11901]],
                'obsolete_overflow_page_numbers' => [11, 12],
            ],
        ],
        $maxTruncatedPages,
        $secureDelete,
    );
};

$rows119 = static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): array => $plan->deleteOverflowVacuumPointerMapRows();

$cases = [
    'action names next119 behavior' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'released overflow pages preserve delete order' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPages(),
    'surviving freed pointer-map pages stay below live tail' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->survivingFreedPointerMapPages(),
    'truncated freed pointer-map pages consume tail chain' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->truncatedFreedPointerMapPages(),
    'row pages include both table and index overflow chains' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'page_number'),
    'row sources distinguish table and index deletes' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'source'),
    'leaf page numbers are threaded from delete results' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'leaf_page'),
    'chain positions reset per delete result' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'chain_position'),
    'current overflow next pages match source chains' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'current_overflow_next_page'),
    'current pointer-map types preserve overflow ownership' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'current_pointer_map_type'),
    'current pointer-map parents preserve owner pages' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'current_pointer_map_parent'),
    'vacuum statuses split survivors from truncated tail' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'vacuum_status'),
    'surviving rows expose next free pointer-map type' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'next_pointer_map_type'),
    'truncated rows preserve truncated free pointer-map type' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'truncated_pointer_map_type'),
    'materialized flags only mark surviving freed pages' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'materialized'),
    'truncated flags only mark omitted tail pages' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($rows119($plan), 'truncated'),
    'current leaf hashes are stable per source' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_values(array_unique(array_column($rows119($plan), 'current_leaf_page_hash'))),
    'deleted leaf hashes are stable per source' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_values(array_unique(array_column($rows119($plan), 'deleted_leaf_page_hash'))),
    'table current and deleted hashes match for fixture delete image' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $rows119($plan)[0]['current_leaf_page_hash'] === $rows119($plan)[0]['deleted_leaf_page_hash'],
    'index current and deleted hashes match for fixture delete image' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $rows119($plan)[2]['current_leaf_page_hash'] === $rows119($plan)[2]['deleted_leaf_page_hash'],
    'summary embeds next119 rows' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['delete_overflow_vacuum_pointermap_current_source_next119'], 'page_number'),
    'summary embeds vacuum transitions' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['pointer_map_vacuum_transitions'], 'status'),
    'final page count keeps live page ten' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
    'final freelist count keeps surviving pages only' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_freelist_page_count'],
    'materialized bytes are truncated after page ten' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->materializedApplySummary()['byte_length'],
    'materialized apply omits only tail overflow pages' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized database keeps surviving pointer-map entries free' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => [
        $plan->vacuumPlan->materializedDatabase()->pointerMapEntryForPage(6)->typeName(),
        $plan->vacuumPlan->materializedDatabase()->pointerMapEntryForPage(7)->typeName(),
    ],
    'materialized database preserves live tail page' => static fn (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => strlen($plan->vacuumPlan->materializedDatabase()->page(10)),
    'materialized database rejects truncated page eleven' => static function (SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan $plan): string {
        try {
            $plan->vacuumPlan->materializedDatabase()->page(11);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'full truncation stops at live tail page' => static fn (): mixed => $fixture119(8)->toArray()['final_database_page_count'],
    'full truncation still preserves lower freed pages behind live tail' => static fn (): mixed => $fixture119(8)->survivingFreedPointerMapPages(),
    'full truncation still removes tail freed pages' => static fn (): mixed => $fixture119(8)->truncatedFreedPointerMapPages(),
    'no truncation keeps every freed pointer-map page materialized' => static fn (): mixed => $fixture119(1)->survivingFreedPointerMapPages(),
    'no truncation removes only last page' => static fn (): mixed => $fixture119(1)->truncatedFreedPointerMapPages(),
];

$expected = [
    'action names next119 behavior' => 'btree-delete-overflow-vacuum-pointermap-current-source-next119',
    'released overflow pages preserve delete order' => [6, 7, 11, 12],
    'surviving freed pointer-map pages stay below live tail' => [6, 7],
    'truncated freed pointer-map pages consume tail chain' => [11, 12],
    'row pages include both table and index overflow chains' => [6, 7, 11, 12],
    'row sources distinguish table and index deletes' => ['wp_options-table-delete', 'wp_options-table-delete', 'wp_options-option-name-index-delete', 'wp_options-option-name-index-delete'],
    'leaf page numbers are threaded from delete results' => [3, 3, 4, 4],
    'chain positions reset per delete result' => [0, 1, 0, 1],
    'current overflow next pages match source chains' => [7, 0, 12, 0],
    'current pointer-map types preserve overflow ownership' => ['first-overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'current pointer-map parents preserve owner pages' => [3, 6, 4, 11],
    'vacuum statuses split survivors from truncated tail' => ['survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database'],
    'surviving rows expose next free pointer-map type' => ['free-page', 'free-page', null, null],
    'truncated rows preserve truncated free pointer-map type' => [null, null, 'free-page', 'free-page'],
    'materialized flags only mark surviving freed pages' => [true, true, false, false],
    'truncated flags only mark omitted tail pages' => [false, false, true, true],
    'current leaf hashes are stable per source' => [hash('sha256', "\r" . str_repeat("\0", 511)), hash('sha256', "\n" . str_repeat("\0", 511))],
    'deleted leaf hashes are stable per source' => [hash('sha256', "\r" . str_repeat("\0", 511)), hash('sha256', "\n" . str_repeat("\0", 511))],
    'table current and deleted hashes match for fixture delete image' => true,
    'index current and deleted hashes match for fixture delete image' => true,
    'summary embeds next119 rows' => [6, 7, 11, 12],
    'summary embeds vacuum transitions' => ['survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database'],
    'final page count keeps live page ten' => 10,
    'final freelist count keeps surviving pages only' => 2,
    'materialized bytes are truncated after page ten' => 10 * 512,
    'materialized apply omits only tail overflow pages' => [12, 11],
    'materialized database keeps surviving pointer-map entries free' => ['free-page', 'free-page'],
    'materialized database preserves live tail page' => 512,
    'materialized database rejects truncated page eleven' => 'unavailable',
    'full truncation stops at live tail page' => 10,
    'full truncation still preserves lower freed pages behind live tail' => [6, 7],
    'full truncation still removes tail freed pages' => [11, 12],
    'no truncation keeps every freed pointer-map page materialized' => [6, 7, 11],
    'no truncation removes only last page' => [12],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree delete overflow vacuum pointermap current source next119 ' . $name] = static function (TestRunner $t) use ($fixture119, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture119()));
    };
}

foreach (range(1, 25) as $index) {
    $tests['btree delete overflow vacuum pointermap current source next119 invariant ' . $index] = static function (TestRunner $t) use ($fixture119, $index, $rows119): void {
        $limit = ($index % 3) + 1;
        $plan = $fixture119($limit, $index % 4 !== 0);
        $rows = $rows119($plan);

        $t->same([6, 7, 11, 12], array_column($rows, 'page_number'));
        $t->same($plan->releasedOverflowPages(), array_column($rows, 'page_number'));
        $t->same($plan->toArray()['delete_overflow_vacuum_pointermap_current_source_next119'], $rows);
        $t->same(array_fill(0, 4, true), array_map(static fn (array $row): bool => $row['materialized'] !== $row['truncated'], $rows));
        $t->same([], array_values(array_filter($plan->truncatedFreedPointerMapPages(), static fn (int $pageNumber): bool => $pageNumber <= $plan->toArray()['final_database_page_count'])));
    };
}

return $tests;
