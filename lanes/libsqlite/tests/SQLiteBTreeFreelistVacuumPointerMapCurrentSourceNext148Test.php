<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage148 = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
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
    $page = substr_replace($page, pack('N', $firstTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry148 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture148 = static function (?int $maxTruncatedPages = null, ?string $payload = null) use ($makeFirstPage148, $putPointerMapEntry148): SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan {
    $pageSize = 512;
    $pageCount = 313;
    $releasedPages = [309, 310, 312, 313];
    $payload ??= str_repeat('next148-autoload-option-replacement-', 16);
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage148($pageSize, $pageCount, 0, 0);
    $pages[311] = str_repeat('P', $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry148($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 2;
        $parent = $isFirst ? 42 : $releasedPages[$index - 1];
        $putPointerMapEntry148(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [309, 312], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan::currentSourcePointerMapFromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-index-surviving-overflow-next148',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['autoload', 'yes', 'theme_mods_next148']],
            ],
            [
                'source' => 'wp_options-autoload-table-tail-overflow-next148',
                'obsolete_overflow_page_numbers' => [312, 313],
                'rowids' => [14801],
            ],
        ],
        $maxTruncatedPages ?? 3,
        $payload,
        42,
        true,
    );
};

$throws148 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases148 = [
    'action label' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'released pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->releasedOverflowPages(),
    'truncated pages include pointer map boundary' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->truncatedPageNumbers(),
    'truncated pointer map pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->truncatedPointerMapPages(),
    'truncated freelist pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->truncatedFreelistPages(),
    'allocated pages after boundary' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->allocatedAfterBoundaryPages(),
    'rejected boundary allocations' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->rejectedBoundaryAllocations(),
    'final page count crossed pointer map boundary' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->databaseAfterAllocation->pageCount(),
    'final freelist pages empty after replacement allocation' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->databaseAfterAllocation->freelistPageNumbers(),
    'boundary row pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'page_number'),
    'boundary pointer map flags' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'pointer_map_page'),
    'boundary freelist flags' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'freelist_page'),
    'boundary released flags' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'released_overflow_page'),
    'boundary allocated flags' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'allocated_after_vacuum'),
    'boundary statuses' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'boundary_status'),
    'boundary current pointer types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'current_pointer_map_type'),
    'boundary current pointer parents' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'current_pointer_map_parent'),
    'boundary materialized flags' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->boundaryRows(), 'final_materialized'),
    'summary truncated pointer map pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['truncated_pointer_map_pages'],
    'summary rejected boundary allocations' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['rejected_boundary_allocations'],
    'summary final page count' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
    'base allocated pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->allocatedOverflowPages(),
    'base attempted truncated reuses' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->attemptedTruncatedReuses(),
    'base vacuum survivors' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->vacuumPlan->survivingFreedPointerMapPages(),
    'base vacuum truncated freed pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->basePlan->vacuumPlan->truncatedFreedPointerMapPages(),
    'allocation sources' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->basePlan->allocationPlan->allocationSteps(), 'source'),
    'allocation trunks' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->basePlan->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocated pointer types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->basePlan->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer parents' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->basePlan->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'page 310 next pointer' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->basePlan->databaseAfterAllocation->page(310), 0, 4))[1],
    'page 309 next pointer' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->basePlan->databaseAfterAllocation->page(309), 0, 4))[1],
];

$expected148 = [
    'action label' => 'btree-freelist-vacuum-pointermap-current-source-next148',
    'released pages' => [309, 310, 312, 313],
    'truncated pages include pointer map boundary' => [313, 312, 311],
    'truncated pointer map pages' => [311],
    'truncated freelist pages' => [313, 312],
    'allocated pages after boundary' => [310, 309],
    'rejected boundary allocations' => [313, 312, 311],
    'final page count crossed pointer map boundary' => 310,
    'final freelist pages empty after replacement allocation' => [],
    'boundary row pages' => [313, 312, 311],
    'boundary pointer map flags' => [false, false, true],
    'boundary freelist flags' => [true, true, false],
    'boundary released flags' => [true, true, false],
    'boundary allocated flags' => [false, false, false],
    'boundary statuses' => ['truncated-freelist-page', 'truncated-freelist-page', 'truncated-auto-vacuum-pointer-map-page'],
    'boundary current pointer types' => ['overflow-page', 'first-overflow-page', null],
    'boundary current pointer parents' => [312, 42, null],
    'boundary materialized flags' => [false, false, false],
    'summary truncated pointer map pages' => [311],
    'summary rejected boundary allocations' => [313, 312, 311],
    'summary final page count' => 310,
    'base allocated pages' => [310, 309],
    'base attempted truncated reuses' => [],
    'base vacuum survivors' => [309, 310],
    'base vacuum truncated freed pages' => [312, 313],
    'allocation sources' => ['freelist-leaf', 'freelist-trunk'],
    'allocation trunks' => [309, 309],
    'allocated pointer types' => ['first-overflow-page', 'overflow-page'],
    'allocated pointer parents' => [42, 310],
    'page 310 next pointer' => 309,
    'page 309 next pointer' => 0,
];

$tests = [];

foreach ($cases148 as $name => $callback) {
    $tests['btree freelist vacuum pointermap current source next148 ' . $name] = static function (TestRunner $t) use ($fixture148, $callback, $expected148, $name): void {
        $t->same($expected148[$name], $callback($fixture148()));
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree freelist vacuum pointermap current source next148 boundary invariant ' . $index] = static function (TestRunner $t) use ($fixture148): void {
        $plan = $fixture148();
        $rows = $plan->boundaryRows();

        $t->same([311], $plan->truncatedPointerMapPages());
        $t->same([], array_values(array_intersect($plan->truncatedPointerMapPages(), $plan->basePlan->allocatedOverflowPages())));
        $t->same([310, 309], $plan->basePlan->allocatedOverflowPages());
        $t->same([313, 312, 311], array_column($rows, 'page_number'));
        $t->same([false, false, true], array_column($rows, 'pointer_map_page'));
        $t->same(310, $plan->basePlan->databaseAfterAllocation->pageCount());
        $t->same('first-overflow-page', $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(310)->typeName());
        $t->same('overflow-page', $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(309)->typeName());
    };
}

$tests['btree freelist vacuum pointermap current source next148 rejects empty replacement payload'] = static function (TestRunner $t) use ($fixture148, $throws148): void {
    $t->same(
        'SQLite b-tree freelist vacuum pointer-map next139 requires replacement overflow payload bytes',
        $throws148(static fn () => $fixture148(null, '')),
    );
};

$tests['btree freelist vacuum pointermap current source next148 rejects over-truncated replacement allocation'] = static function (TestRunner $t) use ($fixture148, $throws148): void {
    $t->same(
        'SQLite freelist does not contain enough pages for this allocation',
        $throws148(static fn () => $fixture148(4)),
    );
};

return $tests;
