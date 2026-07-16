<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage123 = static function (int $pageCount): string {
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

$putPointerMapEntry123 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$fixture123 = static function (int $maxTruncatedPages = 4, bool $secureDelete = true) use ($makeFirstPage123, $putPointerMapEntry123): SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage123(12);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = "\r" . str_repeat("\0", 511);
    $pages[4] = "\n" . str_repeat("\0", 511);
    $pages[10] = substr(str_pad(str_repeat('live-tail-page-next123', 30), 512, 'x'), 0, 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        10 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
        11 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 11],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry123($pages, $pageNumber, $type, $parent);
    }

    foreach ([6 => 7, 7 => 0, 11 => 12, 12 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + $pageNumber), 508);
    }

    return SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-table-delete',
                'leaf_page' => 3,
                'rowids' => [12301],
                'obsolete_overflow_page_numbers' => [6, 7],
            ],
            [
                'source' => 'wp_options-option-name-index-merge',
                'leaf_page' => 4,
                'record_values' => [['_transient_next123', 12301]],
                'obsolete_overflow_page_numbers' => [11, 12],
            ],
        ],
        $maxTruncatedPages,
        $secureDelete,
    );
};

$rows123 = static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): array => $plan->pointerMapOverflowVacuumMergeRows();

$cases = [
    'action names next123 merge behavior' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'released overflow pages preserve source order' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPages(),
    'surviving freelist pages stop before live tail' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->survivingFreelistPages(),
    'truncated freelist pages are tail overflow pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->truncatedFreelistPages(),
    'merge rows preserve release indexes' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'release_index'),
    'merge rows preserve source labels' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'source'),
    'merge rows preserve leaf pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'leaf_page'),
    'merge rows reset chain positions per source' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'chain_position'),
    'merge rows preserve page numbers' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'page_number'),
    'merge rows expose current overflow next pointers' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'current_next_page'),
    'merge rows expose current pointer-map types' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'current_pointer_map_type'),
    'merge rows expose next pointer-map types for survivors' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'next_pointer_map_type'),
    'merge rows expose truncated pointer-map types for tail pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'truncated_pointer_map_type'),
    'merge rows all live on pointer-map page two' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'pointer_map_page'),
    'merge rows classify freelist roles' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'freelist_role'),
    'merge rows classify merge status' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'merge_status'),
    'merge rows classify materialization' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'materialized'),
    'merge rows classify truncation' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($rows123($plan), 'truncated'),
    'summary embeds merge row pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['merge_rows'], 'page_number'),
    'summary final database page count' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
    'summary final first freelist trunk page' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_first_freelist_trunk_page'],
    'summary final freelist count' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_freelist_page_count'],
    'materialized byte length is ten pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->materializedApplySummary()['byte_length'],
    'materialized updated pages include header and pointer map' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_values(array_intersect([1, 2, 6, 7], $plan->materializedApplySummary()['updated_page_numbers'])),
    'materialized omitted pages are tail overflow pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized page images stop at final page count' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => array_key_last($plan->materializedPageImages()),
    'materialized page image count matches final count' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => count($plan->materializedPageImages()),
    'surviving page six becomes free pointer-map entry' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->materializedDatabase()->pointerMapEntryForPage(6)->typeName(),
    'surviving page seven becomes free pointer-map entry' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->materializedDatabase()->pointerMapEntryForPage(7)->typeName(),
    'surviving page six parent is cleared' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->materializedDatabase()->pointerMapEntryForPage(6)->parentPageNumber,
    'surviving page seven parent is cleared' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->materializedDatabase()->pointerMapEntryForPage(7)->parentPageNumber,
    'freelist numbers include surviving overflow pages' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->materializedDatabase()->freelistPageNumbers(),
    'materialized database keeps live tail page' => static fn (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): mixed => substr($plan->vacuumPlan->materializedDatabase()->page(10), 0, 14),
    'truncated page eleven is unavailable' => static function (SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan $plan): string {
        try {
            $plan->vacuumPlan->materializedDatabase()->page(11);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'no truncation limit keeps page eleven materialized' => static fn (): mixed => $fixture123(1)->survivingFreelistPages(),
    'no truncation limit removes only page twelve' => static fn (): mixed => $fixture123(1)->truncatedFreelistPages(),
    'full truncation still stops at live tail' => static fn (): mixed => $fixture123(8)->toArray()['final_database_page_count'],
    'full truncation still preserves lower pages' => static fn (): mixed => $fixture123(8)->survivingFreelistPages(),
    'full truncation still removes upper pages' => static fn (): mixed => $fixture123(8)->truncatedFreelistPages(),
    'non-secure delete leaves survivor page body bytes' => static fn (): mixed => substr($fixture123(4, false)->vacuumPlan->materializedDatabase()->page(7), 4, 4),
    'secure delete clears survivor page body bytes' => static fn (): mixed => substr($fixture123(4, true)->vacuumPlan->materializedDatabase()->page(7), 0, 8),
];

$expected = [
    'action names next123 merge behavior' => 'btree-pointermap-overflow-vacuum-merge-current-source-next123',
    'released overflow pages preserve source order' => [6, 7, 11, 12],
    'surviving freelist pages stop before live tail' => [6, 7],
    'truncated freelist pages are tail overflow pages' => [11, 12],
    'merge rows preserve release indexes' => [0, 1, 2, 3],
    'merge rows preserve source labels' => ['wp_options-autoload-table-delete', 'wp_options-autoload-table-delete', 'wp_options-option-name-index-merge', 'wp_options-option-name-index-merge'],
    'merge rows preserve leaf pages' => [3, 3, 4, 4],
    'merge rows reset chain positions per source' => [0, 1, 0, 1],
    'merge rows preserve page numbers' => [6, 7, 11, 12],
    'merge rows expose current overflow next pointers' => [7, 0, 12, 0],
    'merge rows expose current pointer-map types' => ['first-overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'merge rows expose next pointer-map types for survivors' => ['free-page', 'free-page', null, null],
    'merge rows expose truncated pointer-map types for tail pages' => [null, null, 'free-page', 'free-page'],
    'merge rows all live on pointer-map page two' => [2, 2, 2, 2],
    'merge rows classify freelist roles' => ['trunk', 'leaf', null, null],
    'merge rows classify merge status' => ['merged-into-freelist', 'merged-into-freelist', 'truncated-after-merge', 'truncated-after-merge'],
    'merge rows classify materialization' => [true, true, false, false],
    'merge rows classify truncation' => [false, false, true, true],
    'summary embeds merge row pages' => [6, 7, 11, 12],
    'summary final database page count' => 10,
    'summary final first freelist trunk page' => 6,
    'summary final freelist count' => 2,
    'materialized byte length is ten pages' => 5120,
    'materialized updated pages include header and pointer map' => [1, 2, 6, 7],
    'materialized omitted pages are tail overflow pages' => [12, 11],
    'materialized page images stop at final page count' => 10,
    'materialized page image count matches final count' => 10,
    'surviving page six becomes free pointer-map entry' => 'free-page',
    'surviving page seven becomes free pointer-map entry' => 'free-page',
    'surviving page six parent is cleared' => 0,
    'surviving page seven parent is cleared' => 0,
    'freelist numbers include surviving overflow pages' => [6, 7],
    'materialized database keeps live tail page' => 'live-tail-page',
    'truncated page eleven is unavailable' => 'unavailable',
    'no truncation limit keeps page eleven materialized' => [6, 7, 11],
    'no truncation limit removes only page twelve' => [12],
    'full truncation still stops at live tail' => 10,
    'full truncation still preserves lower pages' => [6, 7],
    'full truncation still removes upper pages' => [11, 12],
    'non-secure delete leaves survivor page body bytes' => 'HHHH',
    'secure delete clears survivor page body bytes' => "\0\0\0\0\0\0\0\0",
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree pointermap overflow vacuum merge current source next123 ' . $name] = static function (TestRunner $t) use ($fixture123, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture123()));
    };
}

foreach (range(1, 22) as $index) {
    $tests['btree pointermap overflow vacuum merge current source next123 invariant ' . $index] = static function (TestRunner $t) use ($fixture123, $index, $rows123): void {
        $limit = ($index % 4) + 1;
        $plan = $fixture123($limit, $index % 3 !== 0);
        $rows = $rows123($plan);

        $t->same([6, 7, 11, 12], array_column($rows, 'page_number'));
        $t->same($plan->releasedOverflowPages(), array_column($rows, 'page_number'));
        $t->same($plan->toArray()['merge_rows'], $rows);
        $t->same(array_column($rows, 'truncated'), array_map(static fn (int $pageNumber): bool => in_array($pageNumber, $plan->truncatedFreelistPages(), true), array_column($rows, 'page_number')));
        $t->same([], array_values(array_filter($plan->truncatedFreelistPages(), static fn (int $pageNumber): bool => $pageNumber <= $plan->toArray()['final_database_page_count'])));
    };
}

return $tests;
