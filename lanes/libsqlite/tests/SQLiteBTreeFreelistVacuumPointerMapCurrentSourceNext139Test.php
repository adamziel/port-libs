<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage139 = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
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

$putPointerMapEntry139 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture139 = static function (int $maxTruncatedPages = 3, ?string $payload = null) use ($makeFirstPage139, $putPointerMapEntry139): SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $payload ??= str_repeat('next139-wp-option-replacement-', 26);
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage139($pageSize, $pageCount, 0, 0);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry139($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
        $putPointerMapEntry139(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan::next139FromDeleteResults(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow-next139',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [13901],
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow-next139',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_timeout_next139', 13901]],
            ],
        ],
        $maxTruncatedPages,
        $payload,
        42,
        true,
    );
};

$throws139 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows139 = static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): array => $plan->transitionRows();

$cases139 = [
    'action label' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'released pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPages(),
    'truncated pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->truncatedPageNumbers(),
    'vacuum survivors' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->survivingFreedPointerMapPages(),
    'vacuum truncated freed pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->truncatedFreedPointerMapPages(),
    'allocated pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->allocatedOverflowPages(),
    'reused survivors' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->reusedSurvivingFreelistPages(),
    'attempted truncated reuse is empty' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->attemptedTruncatedReuses(),
    'final page count remains vacuumed boundary' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pageCount(),
    'final freelist empty after replacement allocation' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'final first freelist trunk' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final freelist count' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->header->freelistPageCount,
    'allocation steps sources' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->allocationPlan->allocationSteps(), 'source'),
    'allocation steps trunks' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->allocationPlan->allocationSteps(), 'trunk_page'),
    'row page numbers' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'page_number'),
    'row vacuum statuses' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'vacuum_status'),
    'row vacuum current types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'vacuum_current_type'),
    'row vacuum next types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'vacuum_next_type'),
    'row truncated types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'truncated_type'),
    'row allocated flags' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocated_after_vacuum'),
    'row allocation positions' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocation_position'),
    'row allocation sources' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocation_source'),
    'row allocation trunks' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocation_trunk_page'),
    'row final pointer types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'final_pointer_map_type'),
    'row final pointer parents' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'final_pointer_map_parent'),
    'row final overflow next pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'final_overflow_next_page'),
    'row payload prefixes' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'payload_prefix'),
    'row source pointer types' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'source_pointer_map_type'),
    'row source pointer parents' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'source_pointer_map_parent'),
    'summary rows pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['btree_freelist_vacuum_pointermap_current_source_next139'], 'page_number'),
    'summary updated pages' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['updated_page_numbers'],
    'summary attempted truncated reuses' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['attempted_truncated_reuses'],
    'summary final page count' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
    'summary final freelist count' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_freelist_page_count'],
    'page 307 next pointer' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfterAllocation->page(307), 0, 4))[1],
    'page 306 next pointer' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfterAllocation->page(306), 0, 4))[1],
    'page 307 final pointer type' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(307)->typeName(),
    'page 306 final pointer type' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(306)->typeName(),
    'page 307 final pointer parent' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(307)->parentPageNumber,
    'page 306 final pointer parent' => static fn (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(306)->parentPageNumber,
    'truncated page 308 final read rejected' => static function (SQLiteBTreeFreelistVacuumPointerMapCurrentSourceNextPlan $plan): string {
        try {
            $plan->databaseAfterAllocation->page(308);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
];

$expected139 = [
    'action label' => 'btree-freelist-vacuum-pointermap-current-source-next139',
    'released pages' => [306, 307, 308, 309, 310],
    'truncated pages' => [310, 309, 308],
    'vacuum survivors' => [306, 307],
    'vacuum truncated freed pages' => [308, 309, 310],
    'allocated pages' => [307, 306],
    'reused survivors' => [307, 306],
    'attempted truncated reuse is empty' => [],
    'final page count remains vacuumed boundary' => 307,
    'final freelist empty after replacement allocation' => [],
    'final first freelist trunk' => 0,
    'final freelist count' => 0,
    'allocation steps sources' => ['freelist-leaf', 'freelist-trunk'],
    'allocation steps trunks' => [306, 306],
    'row page numbers' => [306, 307, 308, 309, 310],
    'row vacuum statuses' => ['survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'row vacuum current types' => ['free-page', 'free-page', 'free-page', 'free-page', 'free-page'],
    'row vacuum next types' => ['free-page', 'free-page', null, null, null],
    'row truncated types' => [null, null, 'free-page', 'free-page', 'free-page'],
    'row allocated flags' => [true, true, false, false, false],
    'row allocation positions' => [1, 0, null, null, null],
    'row allocation sources' => ['freelist-trunk', 'freelist-leaf', null, null, null],
    'row allocation trunks' => [306, 306, null, null, null],
    'row final pointer types' => ['overflow-page', 'first-overflow-page', null, null, null],
    'row final pointer parents' => [307, 42, null, null, null],
    'row final overflow next pages' => [0, 306, null, null, null],
    'row payload prefixes' => ['t-next139-wp-opt', 'next139-wp-optio', null, null, null],
    'row source pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'row source pointer parents' => [42, 306, 307, 4, 309],
    'summary rows pages' => [306, 307, 308, 309, 310],
    'summary updated pages' => [1, 208, 306, 307],
    'summary attempted truncated reuses' => [],
    'summary final page count' => 307,
    'summary final freelist count' => 0,
    'page 307 next pointer' => 306,
    'page 306 next pointer' => 0,
    'page 307 final pointer type' => 'first-overflow-page',
    'page 306 final pointer type' => 'overflow-page',
    'page 307 final pointer parent' => 42,
    'page 306 final pointer parent' => 307,
    'truncated page 308 final read rejected' => 'SQLite page 308 is not present in the database image',
];

$tests = [];

foreach ($cases139 as $name => $callback) {
    $tests['btree freelist vacuum pointermap current source next139 ' . $name] = static function (TestRunner $t) use ($fixture139, $callback, $expected139, $name): void {
        $t->same($expected139[$name], $callback($fixture139()));
    };
}

foreach (range(1, 35) as $index) {
    $tests['btree freelist vacuum pointermap current source next139 invariant ' . $index] = static function (TestRunner $t) use ($fixture139, $rows139, $index): void {
        $plan = $fixture139(3);
        $rows = $rows139($plan);
        $allocated = $plan->allocatedOverflowPages();
        $survivors = $plan->vacuumPlan->survivingFreedPointerMapPages();
        $truncated = $plan->vacuumPlan->truncatedFreedPointerMapPages();

        $t->same([], $plan->attemptedTruncatedReuses());
        $t->same($allocated, array_values(array_intersect($allocated, $survivors)));
        $t->same([], array_values(array_intersect($allocated, $truncated)));
        $t->same(array_fill(0, count($allocated), true), array_values(array_filter(array_column($rows, 'allocated_after_vacuum'))));
        $t->same(array_fill(0, count($truncated), false), array_slice(array_column($rows, 'allocated_after_vacuum'), count($survivors)));
        $t->same('first-overflow-page', $plan->databaseAfterAllocation->pointerMapEntryForPage($allocated[0])->typeName());
        $t->same('overflow-page', $plan->databaseAfterAllocation->pointerMapEntryForPage($allocated[1])->typeName());
        $t->same($allocated[1], unpack('N', substr($plan->databaseAfterAllocation->page($allocated[0]), 0, 4))[1]);
        $t->same(0, unpack('N', substr($plan->databaseAfterAllocation->page($allocated[1]), 0, 4))[1]);
    };
}

$tests['btree freelist vacuum pointermap current source next139 rejects allocation when vacuum leaves too few free pages'] = static function (TestRunner $t) use ($fixture139, $throws139): void {
    $t->same(
        'SQLite freelist does not contain enough pages for this allocation',
        $throws139(static fn () => $fixture139(4)),
    );
};

$tests['btree freelist vacuum pointermap current source next139 rejects empty payload'] = static function (TestRunner $t) use ($fixture139, $throws139): void {
    $t->same(
        'SQLite b-tree freelist vacuum pointer-map next139 requires replacement overflow payload bytes',
        $throws139(static fn () => $fixture139(3, '')),
    );
};

$tests['btree freelist vacuum pointermap current source next139 rejects zero vacuum limit'] = static function (TestRunner $t) use ($fixture139): void {
    $t->throws(InvalidArgumentException::class, static function () use ($fixture139): void {
        $fixture139(0);
    });
};

return $tests;
