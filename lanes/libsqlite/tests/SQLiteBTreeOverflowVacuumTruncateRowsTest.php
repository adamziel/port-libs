<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
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

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$fixture = static function (int $maxTruncatedPages = 5, bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): SQLiteOverflowVacuumTruncatePlan {
    $pageSize = 512;
    $pageCount = 416;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 55 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 56 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }

    $releasedPages = [412, 413, 415, 416];
    foreach ($releasedPages as $index => $pageNumber) {
        $first = $index === 0 || $index === 2;
        $parent = $first ? ($index === 0 ? 55 : 56) : $releasedPages[$index - 1];
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [412, 415], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(88 + $index), $pageSize - 4);
    }

    return SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-table-overflow-vacuum-tail',
                'obsolete_overflow_page_numbers' => [412, 413],
                'rowids' => [9201],
            ],
            [
                'source' => 'wp_options-option-name-index-overflow-vacuum-tail',
                'obsolete_overflow_page_numbers' => [415, 416],
                'record_values' => [['_transient_vacuum_rows', 9201]],
            ],
        ],
        $maxTruncatedPages,
        $secureDelete,
    );
};

$tests = [];

$cases = [
    'truncated window includes pointer-map page between overflow tails' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'page_number'),
    'pointer-map page has no delete source' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'source'),
    'current statuses distinguish pointer-map page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'current_status'),
    'every row is omitted from next image' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_values(array_unique(array_column($plan->overflowVacuumTruncateRows(), 'next_status'))),
    'released overflow flags skip pointer-map page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'released_overflow'),
    'pointer-map flag marks only page 414' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'pointer_map_page'),
    'current next pointers are omitted for pointer-map page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'current_next_page'),
    'freelist roles record released pages only' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'freelist_role'),
    'current pointer-map types keep original overflow ownership' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'current_pointer_map_type'),
    'truncated pointer-map types only exist for non pointer-map pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowVacuumTruncateRows(), 'truncated_pointer_map_type'),
    'materialized flags are false across omitted tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_values(array_unique(array_column($plan->overflowVacuumTruncateRows(), 'materialized'))),
    'truncated flags are true across omitted tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_values(array_unique(array_column($plan->overflowVacuumTruncateRows(), 'truncated'))),
    'released pages preserve source order' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->releasedOverflowPages(),
    'truncation order descends from database tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatedPageNumbers(),
    'final database page count stops before pointer-map page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalDatabasePageCount(),
    'final freelist is empty after all freed tail pages are truncated' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalFreelistPageCount(),
    'first freelist trunk is cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalFirstFreelistTrunkPage(),
    'next freelist page list is empty' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->nextFreelistPageNumbers(),
    'current freelist contains released overflow pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->currentFreelistPageNumbers(),
    'materialized bytes stop at final page count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => strlen($plan->materializedBytes()),
    'materialized apply omits pointer-map page too' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'materialized apply byte length matches next image' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['byte_length'],
    'materialized apply first trunk cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['first_freelist_trunk_page'],
    'materialized apply freelist count cleared' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['freelist_page_count'],
    'materialized apply updated pages omit truncated pointer-map page image' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['updated_page_numbers'],
    'summary exposes vacuum rows rows' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->toArray()['overflow_vacuum_truncate_rows'], 'page_number'),
    'summary exposes pointer-map marker' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->toArray()['overflow_vacuum_truncate_rows'], 'pointer_map_page'),
    'truncate plan records no pointer-map entry for pointer-map page itself' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->truncatePlan->truncatedPointerMapEntries, 'page_number'),
    'boundary pointer-map entry records final database page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatePlan->boundaryPointerMapEntry['page_number'] ?? null,
    'boundary pointer-map entry keeps page type before final tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatePlan->boundaryPointerMapEntry['type_name'] ?? null,
    'materialized database header size is rewritten' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->header->databaseSizePages,
    'materialized database header freelist count is zero' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->header->freelistPageCount,
    'materialized database header first trunk is zero' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedDatabase()->header->firstFreelistTrunkPage,
    'materialized database cannot read truncated pointer-map page' => static function (SQLiteOverflowVacuumTruncatePlan $plan): string {
        try {
            $plan->materializedDatabase()->page(414);
        } catch (Throwable) {
            return 'unavailable';
        }

        return 'available';
    },
    'source database still reads pointer-map page' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => strlen($plan->sourceDatabase->page(414)),
    'current database still reads pointer-map page before truncate' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => strlen($plan->currentDatabase->page(414)),
];

$expected = [
    'truncated window includes pointer-map page between overflow tails' => [416, 415, 414, 413, 412],
    'pointer-map page has no delete source' => [
        'wp_options-option-name-index-overflow-vacuum-tail',
        'wp_options-option-name-index-overflow-vacuum-tail',
        null,
        'wp_options-table-overflow-vacuum-tail',
        'wp_options-table-overflow-vacuum-tail',
    ],
    'current statuses distinguish pointer-map page' => [
        'obsolete-overflow-free-page',
        'obsolete-overflow-free-page',
        'auto-vacuum-pointer-map-page',
        'obsolete-overflow-free-page',
        'obsolete-overflow-free-page',
    ],
    'every row is omitted from next image' => ['truncated-from-database'],
    'released overflow flags skip pointer-map page' => [true, true, false, true, true],
    'pointer-map flag marks only page 414' => [false, false, true, false, false],
    'current next pointers are omitted for pointer-map page' => [0, 416, null, 0, 413],
    'freelist roles record released pages only' => ['leaf', 'leaf', null, 'leaf', 'trunk'],
    'current pointer-map types keep original overflow ownership' => ['overflow-page', 'first-overflow-page', null, 'overflow-page', 'first-overflow-page'],
    'truncated pointer-map types only exist for non pointer-map pages' => ['free-page', 'free-page', null, 'free-page', 'free-page'],
    'materialized flags are false across omitted tail' => [false],
    'truncated flags are true across omitted tail' => [true],
    'released pages preserve source order' => [412, 413, 415, 416],
    'truncation order descends from database tail' => [416, 415, 414, 413, 412],
    'final database page count stops before pointer-map page' => 411,
    'final freelist is empty after all freed tail pages are truncated' => 0,
    'first freelist trunk is cleared' => 0,
    'next freelist page list is empty' => [],
    'current freelist contains released overflow pages' => [412, 413, 415, 416],
    'materialized bytes stop at final page count' => 411 * 512,
    'materialized apply omits pointer-map page too' => [416, 415, 414, 413, 412],
    'materialized apply byte length matches next image' => 411 * 512,
    'materialized apply first trunk cleared' => 0,
    'materialized apply freelist count cleared' => 0,
    'materialized apply updated pages omit truncated pointer-map page image' => [1, 311],
    'summary exposes vacuum rows rows' => [416, 415, 414, 413, 412],
    'summary exposes pointer-map marker' => [false, false, true, false, false],
    'truncate plan records no pointer-map entry for pointer-map page itself' => [416, 415, 413, 412],
    'boundary pointer-map entry records final database page' => null,
    'boundary pointer-map entry keeps page type before final tail' => null,
    'materialized database header size is rewritten' => 411,
    'materialized database header freelist count is zero' => 0,
    'materialized database header first trunk is zero' => 0,
    'materialized database cannot read truncated pointer-map page' => 'unavailable',
    'source database still reads pointer-map page' => 512,
    'current database still reads pointer-map page before truncate' => 512,
];

foreach ($cases as $name => $callback) {
    $tests['btree overflow vacuum truncate vacuum rows ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree overflow vacuum truncate vacuum rows invariant ' . $index] = static function (TestRunner $t) use ($fixture, $index): void {
        $plan = $fixture(5, $index % 4 !== 0);
        $rows = $plan->overflowVacuumTruncateRows();

        $t->same($plan->truncatedPageNumbers(), array_column($rows, 'page_number'));
        $t->same([414], array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['pointer_map_page']), 'page_number')));
        $t->same([], $plan->nextFreelistPageNumbers());
        $t->same(411, $plan->materializedDatabase()->pageCount());
        $t->same(411 * 512, strlen($plan->materializedBytes()));
        $t->same($plan->toArray()['overflow_vacuum_truncate_rows'], $rows);
    };
}

$tests['btree overflow vacuum truncate vacuum rows partial limit leaves pointer-map page materialized'] = static function (TestRunner $t) use ($fixture): void {
    $plan = $fixture(2);

    $t->same([416, 415], $plan->truncatedPageNumbers());
    $t->same([416, 415], array_column($plan->overflowVacuumTruncateRows(), 'page_number'));
    $t->same(412, $plan->finalFirstFreelistTrunkPage());
    $t->same(2, $plan->finalFreelistPageCount());
    $t->same(414 * 512, strlen($plan->materializedBytes()));
};

return $tests;
