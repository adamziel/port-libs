<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage122 = static function (int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 5), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeFragmentedLeaf122 = static function (): string {
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

$putPointerMapEntry122 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$fixture122 = static function (int $maxTruncatedPages = 3, bool $secureDelete = true) use ($makeFirstPage122, $makeFragmentedLeaf122, $putPointerMapEntry122): SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan {
    $pages = array_fill(1, 14, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage122(14);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $makeFragmentedLeaf122();
    $pages[5] = "\n" . str_repeat("\0", 511);
    $pages[10] = substr(str_pad(str_repeat('live-option-row-next122', 24), 512, 'x'), 0, 512);

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
        $putPointerMapEntry122($pages, $pageNumber, $type, $parent);
    }

    foreach ([6 => 7, 7 => 8, 8 => 0, 12 => 13, 13 => 14, 14 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + $pageNumber), 508);
    }

    return SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan::coalescedOverflowFreeblockFromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        3,
        [
            [
                'source' => 'wp_options-table-leaf-defrag-delete',
                'leaf_page' => 3,
                'rowids' => [12201, 12202],
                'obsolete_overflow_page_numbers' => [6, 7, 8],
            ],
            [
                'source' => 'wp_options-autoload-index-delete',
                'leaf_page' => 3,
                'record_values' => [['autoload', 'yes', 12201]],
                'obsolete_overflow_page_numbers' => [12, 13, 14],
            ],
        ],
        $maxTruncatedPages,
        $secureDelete,
    );
};

$rows122 = static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): array => $plan->overflowFreeblockVacuumRows();

$cases = [
    'action names next122 behavior' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'coalesced fragment bytes come from adjacent freeblocks' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->coalescePlan->coalescedFragmentBytes,
    'fragmented bytes decrease after coalesce' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => [$plan->coalescePlan->fragmentedBytesBefore, $plan->coalescePlan->fragmentedBytesAfter],
    'freeblock count collapses to one block' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => [count($plan->coalescePlan->beforeFreeblocks), count($plan->coalescePlan->afterFreeblocks)],
    'coalesced freeblock spans current and next blocks' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->coalescePlan->afterFreeblocks[0]['size'],
    'released overflow pages preserve source order' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPages(),
    'surviving freed pages remain before live tail' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->survivingFreedPointerMapPages(),
    'truncated freed pages remove tail overflow chain' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->truncatedFreedPointerMapPages(),
    'rows include every obsolete overflow page' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'page_number'),
    'rows retain source labels' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'source'),
    'chain positions reset per deleted chain' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'chain_position'),
    'current next pointers preserve chain topology' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'current_overflow_next_page'),
    'current pointer-map types distinguish first overflow pages' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'current_pointer_map_type'),
    'current pointer-map parents retain owner chain' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'current_pointer_map_parent'),
    'row fragmented byte columns mirror coalesce plan' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_values(array_unique(array_column($rows122($plan), 'fragmented_bytes_before'))),
    'row fragmented after columns mirror coalesce plan' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_values(array_unique(array_column($rows122($plan), 'fragmented_bytes_after'))),
    'row freeblock counts mirror coalesce plan' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => [array_values(array_unique(array_column($rows122($plan), 'freeblock_count_before'))), array_values(array_unique(array_column($rows122($plan), 'freeblock_count_after')))],
    'vacuum statuses split lower survivors and tail truncation' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'vacuum_status'),
    'survivor rows expose free-page pointer-map type' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'next_pointer_map_type'),
    'truncated rows preserve truncated pointer-map type' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'truncated_pointer_map_type'),
    'materialized flags only mark surviving freed pages' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'materialized'),
    'truncated flags only mark tail pages' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($rows122($plan), 'truncated'),
    'updated pages include leaf header and freelist changes' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->updatedPageNumbers(),
    'final database stops before deleted tail chain' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
    'final freelist count keeps surviving freed pages' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_freelist_page_count'],
    'materialized byte length follows final page count' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->materializedApplySummary()['byte_length'],
    'materialized omitted pages are tail chain only' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized database preserves coalesced fragment header' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => ord($plan->materializedDatabase()->page(3)[7]),
    'materialized database marks survivor pointer map free' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => [
        $plan->materializedDatabase()->pointerMapEntryForPage(6)->typeName(),
        $plan->materializedDatabase()->pointerMapEntryForPage(8)->typeName(),
    ],
    'materialized database keeps live page ten' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => strlen($plan->materializedDatabase()->page(10)),
    'materialized database rejects page twelve after truncation' => static function (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): string {
        try {
            $plan->materializedDatabase()->page(12);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'summary embeds next122 rows' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['overflow_freeblock_vacuum_current_source_next122'], 'page_number'),
    'summary embeds pointer-map statuses' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['pointer_map_vacuum_transitions'], 'status'),
    'summary embeds materialized apply page count' => static fn (SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['materialized_apply']['database_page_count'],
    'low truncation keeps page twelve materialized' => static fn (): mixed => $fixture122(2)->survivingFreedPointerMapPages(),
    'low truncation removes only pages thirteen and fourteen' => static fn (): mixed => $fixture122(2)->truncatedFreedPointerMapPages(),
    'wide truncation still stops before deleted tail chain' => static fn (): mixed => $fixture122(9)->toArray()['final_database_page_count'],
    'wide truncation keeps lower free pages only' => static fn (): mixed => $fixture122(9)->survivingFreedPointerMapPages(),
    'wide truncation removes upper free pages' => static fn (): mixed => $fixture122(9)->truncatedFreedPointerMapPages(),
];

$expected = [
    'action names next122 behavior' => 'btree-overflow-freeblock-vacuum-current-source-next122',
    'coalesced fragment bytes come from adjacent freeblocks' => 2,
    'fragmented bytes decrease after coalesce' => [2, 0],
    'freeblock count collapses to one block' => [2, 1],
    'coalesced freeblock spans current and next blocks' => 52,
    'released overflow pages preserve source order' => [6, 7, 8, 12, 13, 14],
    'surviving freed pages remain before live tail' => [6, 7, 8],
    'truncated freed pages remove tail overflow chain' => [12, 13, 14],
    'rows include every obsolete overflow page' => [6, 7, 8, 12, 13, 14],
    'rows retain source labels' => ['wp_options-table-leaf-defrag-delete', 'wp_options-table-leaf-defrag-delete', 'wp_options-table-leaf-defrag-delete', 'wp_options-autoload-index-delete', 'wp_options-autoload-index-delete', 'wp_options-autoload-index-delete'],
    'chain positions reset per deleted chain' => [0, 1, 2, 0, 1, 2],
    'current next pointers preserve chain topology' => [7, 8, 0, 13, 14, 0],
    'current pointer-map types distinguish first overflow pages' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page', 'overflow-page'],
    'current pointer-map parents retain owner chain' => [3, 6, 7, 3, 12, 13],
    'row fragmented byte columns mirror coalesce plan' => [2],
    'row fragmented after columns mirror coalesce plan' => [0],
    'row freeblock counts mirror coalesce plan' => [[2], [1]],
    'vacuum statuses split lower survivors and tail truncation' => ['survives-as-free-page', 'survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'survivor rows expose free-page pointer-map type' => ['free-page', 'free-page', 'free-page', null, null, null],
    'truncated rows preserve truncated pointer-map type' => [null, null, null, 'free-page', 'free-page', 'free-page'],
    'materialized flags only mark surviving freed pages' => [true, true, true, false, false, false],
    'truncated flags only mark tail pages' => [false, false, false, true, true, true],
    'updated pages include leaf header and freelist changes' => [1, 2, 3, 6, 7, 8],
    'final database stops before deleted tail chain' => 11,
    'final freelist count keeps surviving freed pages' => 3,
    'materialized byte length follows final page count' => 5632,
    'materialized omitted pages are tail chain only' => [14, 13, 12],
    'materialized database preserves coalesced fragment header' => 0,
    'materialized database marks survivor pointer map free' => ['free-page', 'free-page'],
    'materialized database keeps live page ten' => 512,
    'materialized database rejects page twelve after truncation' => 'unavailable',
    'summary embeds next122 rows' => [6, 7, 8, 12, 13, 14],
    'summary embeds pointer-map statuses' => ['survives-as-free-page', 'survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'summary embeds materialized apply page count' => 11,
    'low truncation keeps page twelve materialized' => [6, 7, 8, 12],
    'low truncation removes only pages thirteen and fourteen' => [13, 14],
    'wide truncation still stops before deleted tail chain' => 11,
    'wide truncation keeps lower free pages only' => [6, 7, 8],
    'wide truncation removes upper free pages' => [12, 13, 14],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree overflow freeblock vacuum current source next122 ' . $name] = static function (TestRunner $t) use ($fixture122, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture122()));
    };
}

foreach (range(1, 27) as $index) {
    $tests['btree overflow freeblock vacuum current source next122 invariant ' . $index] = static function (TestRunner $t) use ($fixture122, $rows122, $index): void {
        $limit = ($index % 4) + 1;
        $plan = $fixture122($limit, $index % 5 !== 0);
        $rows = $rows122($plan);

        $t->same($plan->releasedOverflowPages(), array_column($rows, 'page_number'));
        $t->same(array_fill(0, 6, 2), array_column($rows, 'coalesced_fragment_bytes'));
        $t->same(array_fill(0, 6, 3), array_column($rows, 'leaf_page'));
        $t->same($plan->toArray()['overflow_freeblock_vacuum_current_source_next122'], $rows);
        $t->same([], array_values(array_filter($plan->truncatedFreedPointerMapPages(), static fn (int $pageNumber): bool => $pageNumber <= $plan->toArray()['final_database_page_count'])));
        $t->same([], array_values(array_filter($plan->survivingFreedPointerMapPages(), static fn (int $pageNumber): bool => $pageNumber > $plan->toArray()['final_database_page_count'])));
    };
}

return $tests;
