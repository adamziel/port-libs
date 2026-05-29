<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage108 = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry108 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    if ($pageNumber === 1) {
        return;
    }

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

$databaseFixture108 = static function () use ($makeFirstPage108, $putPointerMapEntry108): SQLiteDatabase {
    $pageSize = 512;
    $pageCount = 310;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage108($pageSize, $pageCount, 0, 0);
    foreach ([306 => 307, 307 => 308, 308 => 0, 309 => 310, 310 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + ($pageNumber % 26)), $pageSize - 4);
    }

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry108($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([306, 307, 308] as $index => $pageNumber) {
        $putPointerMapEntry108(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 42 : $pageNumber - 1,
            $pageSize,
        );
    }
    foreach ([309, 310] as $index => $pageNumber) {
        $putPointerMapEntry108(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : 309,
            $pageSize,
        );
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$deleteResults108 = static fn (): array => [
    [
        'source' => 'wp_options-tail-overflow',
        'obsolete_overflow_page_numbers' => [306, 307, 308],
    ],
    [
        'source' => 'wp_options-name-index-tail-overflow',
        'obsolete_overflow_page_numbers' => [309, 310],
    ],
];

$allocatedImages108 = static fn (): array => [
    307 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(10810, SQLiteRecord::encode([null, '_transient_next108', 'fresh', 'no'])),
    ]),
    306 => SQLiteIndexLeafPage::assemble([
        SQLiteRecord::encode(['_transient_next108', 10810]),
    ]),
];

$fixture108 = static function (int $allocationCount = 2) use ($databaseFixture108, $deleteResults108, $allocatedImages108): SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan {
    return SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
        $databaseFixture108(),
        $deleteResults108(),
        3,
        $allocationCount,
        42,
        array_slice($allocatedImages108(), 0, $allocationCount, true),
        true,
    );
};

$rows108 = static fn (SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan $plan): array => $plan->incrementalVacuumCurrentSourceNext108Rows();

$cases108 = [
    'summary includes next108 rows' => static fn (): mixed => array_column($fixture108()->toArray()['incremental_vacuum_current_source_next108'], 'page_number'),
    'released pages stay in source order' => static fn (): mixed => array_column($rows108($fixture108()), 'page_number'),
    'source names are preserved' => static fn (): mixed => array_column($rows108($fixture108()), 'source'),
    'source positions are preserved per chain' => static fn (): mixed => array_column($rows108($fixture108()), 'source_position'),
    'current states are obsolete overflow pages' => static fn (): mixed => array_values(array_unique(array_column($rows108($fixture108()), 'current_source_state'))),
    'current pointer map types preserve source chain' => static fn (): mixed => array_column($rows108($fixture108()), 'current_pointer_map_type'),
    'current pointer map parents preserve source chain' => static fn (): mixed => array_column($rows108($fixture108()), 'current_pointer_map_parent'),
    'survivor flags follow incremental vacuum boundary' => static fn (): mixed => array_column($rows108($fixture108()), 'survived_incremental_vacuum'),
    'reuse flags follow freelist allocation order not source order' => static fn (): mixed => array_column($rows108($fixture108()), 'reused_by_next_btree'),
    'tail truncation flags follow removed pages' => static fn (): mixed => array_column($rows108($fixture108()), 'tail_truncated_by_incremental_vacuum'),
    'next source states split reuse and truncation' => static fn (): mixed => array_column($rows108($fixture108()), 'next_source_state'),
    'next pointer map types are present only for surviving pages' => static fn (): mixed => array_column($rows108($fixture108()), 'next_pointer_map_type'),
    'next pointer map parents target rebuilt btree parent' => static fn (): mixed => array_column($rows108($fixture108()), 'next_pointer_map_parent'),
    'materialized flags are true only for reused pages' => static fn (): mixed => array_column($rows108($fixture108()), 'next_page_materialized'),
    'one page allocation leaves trunk survivor free' => static fn (): mixed => array_column($rows108($fixture108(1)), 'next_source_state'),
    'one page allocation pointer map types' => static fn (): mixed => array_column($rows108($fixture108(1)), 'next_pointer_map_type'),
    'one page allocation pointer map parents' => static fn (): mixed => array_column($rows108($fixture108(1)), 'next_pointer_map_parent'),
    'one page allocation materialized flags' => static fn (): mixed => array_column($rows108($fixture108(1)), 'next_page_materialized'),
    'allocated page order remains sqlite freelist order' => static fn (): mixed => $fixture108()->allocatedPageNumbers(),
    'vacuum survivors remain lower released pages' => static fn (): mixed => $fixture108()->vacuumPlan->survivingFreedPointerMapPages(),
    'vacuum truncated pages remain tail pages' => static fn (): mixed => $fixture108()->vacuumPlan->truncatedFreedPointerMapPages(),
    'final page count stops before truncated tail' => static fn (): mixed => $fixture108()->databaseAfterReuse->pageCount(),
    'summary action remains accepted reuse planner' => static fn (): mixed => $fixture108()->toArray()['action'],
    'next108 row count covers every released overflow page' => static fn (): mixed => count($rows108($fixture108())),
    'next108 row keys are stable' => static fn (): mixed => array_keys($rows108($fixture108())[0]),
];

$expected108 = [
    'summary includes next108 rows' => [306, 307, 308, 309, 310],
    'released pages stay in source order' => [306, 307, 308, 309, 310],
    'source names are preserved' => ['wp_options-tail-overflow', 'wp_options-tail-overflow', 'wp_options-tail-overflow', 'wp_options-name-index-tail-overflow', 'wp_options-name-index-tail-overflow'],
    'source positions are preserved per chain' => [0, 1, 2, 0, 1],
    'current states are obsolete overflow pages' => ['obsolete-overflow-page'],
    'current pointer map types preserve source chain' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'current pointer map parents preserve source chain' => [42, 306, 307, 4, 309],
    'survivor flags follow incremental vacuum boundary' => [true, true, false, false, false],
    'reuse flags follow freelist allocation order not source order' => [true, true, false, false, false],
    'tail truncation flags follow removed pages' => [false, false, true, true, true],
    'next source states split reuse and truncation' => ['reused-as-btree-page', 'reused-as-btree-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'next pointer map types are present only for surviving pages' => ['btree-page', 'btree-page', null, null, null],
    'next pointer map parents target rebuilt btree parent' => [42, 42, null, null, null],
    'materialized flags are true only for reused pages' => [true, true, false, false, false],
    'one page allocation leaves trunk survivor free' => ['survives-as-free-page', 'reused-as-btree-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'one page allocation pointer map types' => ['free-page', 'btree-page', null, null, null],
    'one page allocation pointer map parents' => [0, 42, null, null, null],
    'one page allocation materialized flags' => [true, true, false, false, false],
    'allocated page order remains sqlite freelist order' => [307, 306],
    'vacuum survivors remain lower released pages' => [306, 307],
    'vacuum truncated pages remain tail pages' => [308, 309, 310],
    'final page count stops before truncated tail' => 307,
    'summary action remains accepted reuse planner' => 'btree-freelist-pointermap-vacuum-reuse-current-source-next104',
    'next108 row count covers every released overflow page' => 5,
    'next108 row keys are stable' => [
        'page_number',
        'source',
        'source_position',
        'current_source_state',
        'current_pointer_map_type',
        'current_pointer_map_parent',
        'survived_incremental_vacuum',
        'reused_by_next_btree',
        'tail_truncated_by_incremental_vacuum',
        'next_source_state',
        'next_pointer_map_type',
        'next_pointer_map_parent',
        'next_page_materialized',
    ],
];

$tests = [];

foreach ($cases108 as $name => $callback) {
    $tests['btree incremental vacuum current source next108 ' . $name] = static function (TestRunner $t) use ($callback, $expected108, $name): void {
        $t->same($expected108[$name], $callback());
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree incremental vacuum current source next108 invariant ' . $index] = static function (TestRunner $t) use ($fixture108, $rows108, $index): void {
        $allocationCount = $index % 2 === 0 ? 1 : 2;
        $plan = $fixture108($allocationCount);
        $rows = $rows108($plan);

        $t->same([306, 307, 308, 309, 310], array_column($rows, 'page_number'));
        $t->same([false, false, true, true, true], array_column($rows, 'tail_truncated_by_incremental_vacuum'));
        $t->same([true, true, false, false, false], array_column($rows, 'survived_incremental_vacuum'));
        $t->same('truncated-from-database', $rows[2]['next_source_state']);
        $t->same(null, $rows[4]['next_pointer_map_type']);
        $t->same(307, $plan->databaseAfterReuse->pageCount());
        $t->same([308, 309, 310], $plan->truncatedPagesNotReused());
        $t->same(array_slice([307, 306], 0, $allocationCount), $plan->allocatedPageNumbers());
    };
}

return $tests;
