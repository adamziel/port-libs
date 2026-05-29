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

$fixture = static function (int $maxTruncatedPages = 4, bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): SQLiteOverflowVacuumTruncatePlan {
    $pageSize = 512;
    $pageCount = 412;
    $releasedPages = [406, 407, 408, 409, 410, 411, 412];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 44 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 45 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }

    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 44 : 45) : $releasedPages[$index - 1];
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [406, 407, 409, 410, 411], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(80 + $index), $pageSize - 4);
    }

    return SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-table-overflow-freeblock-delete',
                'obsolete_overflow_page_numbers' => [406, 407, 408],
                'rowids' => [701, 702],
            ],
            [
                'source' => 'wp_options-option-name-index-overflow-freeblock-delete',
                'obsolete_overflow_page_numbers' => [409, 410, 411, 412],
                'record_values' => [['_transient_timeout_overflow_rows', 701]],
            ],
        ],
        $maxTruncatedPages,
        $secureDelete,
    );
};

$tests = [];

$cases = [
    'released source labels are preserved' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'source'),
    'released page numbers stay delete-result order' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'page_number'),
    'current statuses are obsolete overflow' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_values(array_unique(array_column($plan->overflowFreeblockTruncateRows(), 'current_status'))),
    'next statuses split survivor and truncated tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'next_status'),
    'current overflow next pointers follow both chains' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'current_next_page'),
    'next overflow next pointers are freelist shape for survivors' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'next_next_page'),
    'current pointer map types show first and chained overflow' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'current_pointer_map_type'),
    'next pointer map types remain only for survivors' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'next_pointer_map_type'),
    'truncated pointer map types capture omitted tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'truncated_pointer_map_type'),
    'materialized flags split at tail boundary' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'materialized'),
    'truncated flags split at tail boundary' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'truncated'),
    'secure delete marks surviving free pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->overflowFreeblockTruncateRows(), 'secure_deleted'),
    'summary is exposed through toArray' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => array_column($plan->toArray()['overflow_freeblock_truncate_rows'], 'page_number'),
    'materialized apply omits truncated pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'final database page count is survivor boundary' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalDatabasePageCount(),
    'final freelist contains surviving released pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->nextFreelistPageNumbers(),
    'surviving helper records free pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->survivingFreedPointerMapPages(),
    'truncated helper records removed pages' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->truncatedFreedPointerMapPages(),
    'first surviving page becomes freelist trunk' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalFirstFreelistTrunkPage(),
    'final freelist count excludes truncated tail' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->finalFreelistPageCount(),
    'first source count remains table chain length' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->releasePlan->sources[0]['count'],
    'second source count remains index chain length' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->releasePlan->sources[1]['count'],
    'updated page numbers omit truncated tail images' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => $plan->toArray()['updated_page_numbers'],
    'materialized byte length matches final page count' => static fn (SQLiteOverflowVacuumTruncatePlan $plan): mixed => strlen($plan->materializedBytes()),
    'tail page read is unavailable after truncate' => static function (SQLiteOverflowVacuumTruncatePlan $plan): string {
        try {
            $plan->materializedDatabase()->page(412);
        } catch (Throwable $exception) {
            return 'unavailable';
        }

        return 'available';
    },
];

$expected = [
    'released source labels are preserved' => [
        'wp_options-table-overflow-freeblock-delete',
        'wp_options-table-overflow-freeblock-delete',
        'wp_options-table-overflow-freeblock-delete',
        'wp_options-option-name-index-overflow-freeblock-delete',
        'wp_options-option-name-index-overflow-freeblock-delete',
        'wp_options-option-name-index-overflow-freeblock-delete',
        'wp_options-option-name-index-overflow-freeblock-delete',
    ],
    'released page numbers stay delete-result order' => [406, 407, 408, 409, 410, 411, 412],
    'current statuses are obsolete overflow' => ['obsolete-overflow'],
    'next statuses split survivor and truncated tail' => [
        'survives-as-freelist-page',
        'survives-as-freelist-page',
        'survives-as-freelist-page',
        'truncated-from-database',
        'truncated-from-database',
        'truncated-from-database',
        'truncated-from-database',
    ],
    'current overflow next pointers follow both chains' => [407, 408, 0, 410, 411, 412, 0],
    'next overflow next pointers are freelist shape for survivors' => [0, 0, 0, null, null, null, null],
    'current pointer map types show first and chained overflow' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'next pointer map types remain only for survivors' => ['free-page', 'free-page', 'free-page', null, null, null, null],
    'truncated pointer map types capture omitted tail' => [null, null, null, 'free-page', 'free-page', 'free-page', 'free-page'],
    'materialized flags split at tail boundary' => [true, true, true, false, false, false, false],
    'truncated flags split at tail boundary' => [false, false, false, true, true, true, true],
    'secure delete marks surviving free pages' => [true, true, true, false, false, false, false],
    'summary is exposed through toArray' => [406, 407, 408, 409, 410, 411, 412],
    'materialized apply omits truncated pages' => [412, 411, 410, 409],
    'final database page count is survivor boundary' => 408,
    'final freelist contains surviving released pages' => [406, 407, 408],
    'surviving helper records free pages' => [406, 407, 408],
    'truncated helper records removed pages' => [409, 410, 411, 412],
    'first surviving page becomes freelist trunk' => 406,
    'final freelist count excludes truncated tail' => 3,
    'first source count remains table chain length' => 3,
    'second source count remains index chain length' => 4,
    'updated page numbers omit truncated tail images' => [1, 311, 406, 407, 408],
    'materialized byte length matches final page count' => 408 * 512,
    'tail page read is unavailable after truncate' => 'unavailable',
];

foreach ($cases as $name => $callback) {
    $tests['btree overflow freeblock truncate overflow rows ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

foreach (range(1, 35) as $index) {
    $tests['btree overflow freeblock truncate overflow rows invariant ' . $index] = static function (TestRunner $t) use ($fixture, $index): void {
        $limit = $index % 3 === 0 ? 5 : 4;
        $secureDelete = $index % 5 !== 0;
        $plan = $fixture($limit, $secureDelete);
        $rows = $plan->overflowFreeblockTruncateRows();
        $survivors = array_values(array_filter($rows, static fn (array $row): bool => $row['materialized']));
        $truncated = array_values(array_filter($rows, static fn (array $row): bool => $row['truncated']));

        $t->same(array_column($survivors, 'page_number'), $plan->survivingFreedPointerMapPages());
        $t->same(array_column($truncated, 'page_number'), $plan->truncatedFreedPointerMapPages());
        $t->same(max(array_column($survivors, 'page_number')), $plan->finalDatabasePageCount());
        $t->same($plan->finalDatabasePageCount() * 512, strlen($plan->materializedBytes()));
        $t->same(array_column($rows, 'page_number'), array_column($plan->toArray()['overflow_freeblock_truncate_rows'], 'page_number'));
        $t->same(count($survivors), $plan->finalFreelistPageCount());
    };
}

$tests['btree overflow freeblock truncate overflow rows preserves payload bytes without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    $plan = $fixture(4, false);
    $rows = $plan->overflowFreeblockTruncateRows();
    $t->same([true, false, false], array_slice(array_column($rows, 'secure_deleted'), 0, 3));
    $t->same(str_repeat('Q', 8), substr($plan->materializedDatabase()->page(407), 4, 8));
};

return $tests;
