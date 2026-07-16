<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage149 = static function (int $pageSize, int $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
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

$putPointerMapEntry149 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture149 = static function () use ($makeFirstPage149, $putPointerMapEntry149): SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage149($pageSize, $pageCount);
    $putPointerMapEntry149($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry149($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 4);

    foreach ($releasedPages as $index => $pageNumber) {
        $first = $index === 0 || $index === 3;
        $parent = $first ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
        $putPointerMapEntry149(
            $pages,
            $pageNumber,
            $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow-next149',
                'first_page' => 306,
                'overflow_payload_bytes' => 1524,
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow-next149',
                'first_page' => 309,
                'overflow_payload_bytes' => 1016,
            ],
        ],
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow-next149',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [14901],
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow-next149',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_timeout_next149', 14901]],
            ],
        ],
        3,
        str_repeat('next149-wp-option-overflow-vacuum-pointermap-', 24),
        42,
        true,
    );
};

$cases149 = [
    'action label' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'released pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->basePlan->releasedOverflowPages(),
    'truncated pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->basePlan->truncatedPageNumbers(),
    'allocated pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->basePlan->allocatedOverflowPages(),
    'reused current pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->currentSourcePagesReusedAfterVacuum(),
    'rejected truncated pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->truncatedCurrentSourcePagesRejectedForReuse(),
    'vacuum row pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'page_number'),
    'vacuum row survived flags' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'survived_vacuum_as_free_page'),
    'vacuum row truncated flags' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'truncated_by_vacuum'),
    'vacuum row reused flags' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'reused_as_replacement_overflow'),
    'vacuum row rejected flags' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'rejected_for_reuse_after_truncate'),
    'final pointer types' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'final_pointer_map_type'),
    'final pointer parents' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => array_column($plan->vacuumRows(), 'final_pointer_map_parent'),
    'summary reused pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['current_source_pages_reused_after_vacuum'],
    'summary rejected pages' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['truncated_current_source_pages_rejected_for_reuse'],
    'summary final page count' => static fn (SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
];

$expected149 = [
    'action label' => 'btree-pointermap-overflow-vacuum-current-source-next149',
    'released pages' => [306, 307, 308, 309, 310],
    'truncated pages' => [310, 309, 308],
    'allocated pages' => [307, 306, 308],
    'reused current pages' => [306, 307, 308],
    'rejected truncated pages' => [309, 310],
    'vacuum row pages' => [306, 307, 308, 309, 310],
    'vacuum row survived flags' => [true, true, false, false, false],
    'vacuum row truncated flags' => [false, false, true, true, true],
    'vacuum row reused flags' => [true, true, true, false, false],
    'vacuum row rejected flags' => [false, false, false, true, true],
    'final pointer types' => ['overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'final pointer parents' => [307, 42, 306, null, null],
    'summary reused pages' => [306, 307, 308],
    'summary rejected pages' => [309, 310],
    'summary final page count' => 308,
];

$tests = [];

foreach ($cases149 as $name => $callback) {
    $tests['btree pointermap overflow vacuum current source next149 ' . $name] = static function (TestRunner $t) use ($fixture149, $callback, $expected149, $name): void {
        $t->same($expected149[$name], $callback($fixture149()));
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree pointermap overflow vacuum current source next149 invariant ' . $index] = static function (TestRunner $t) use ($fixture149): void {
        $plan = $fixture149();
        $rows = $plan->vacuumRows();

        $t->same([306, 307, 308], $plan->currentSourcePagesReusedAfterVacuum());
        $t->same([309, 310], $plan->truncatedCurrentSourcePagesRejectedForReuse());
        $t->same([307, 306, 308], $plan->basePlan->allocatedOverflowPages());
        $t->same([308], [unpack('N', substr($plan->basePlan->databaseAfterAllocation->page(306), 0, 4))[1]]);
        $t->same([306], [unpack('N', substr($plan->basePlan->databaseAfterAllocation->page(307), 0, 4))[1]]);
        $t->same([false, false, false, true, true], array_column($rows, 'rejected_for_reuse_after_truncate'));
        $t->same(308, $plan->basePlan->databaseAfterAllocation->pageCount());
        $t->same([], $plan->basePlan->databaseAfterAllocation->freelistPageNumbers());
    };
}

$tests['btree pointermap overflow vacuum current source next149 rejects base without current rows'] = static function (TestRunner $t): void {
    try {
        SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan::fromBasePlan(new class {
        });
        $message = 'not rejected';
    } catch (TypeError $exception) {
        $message = $exception::class;
    }

    $t->same(TypeError::class, $message);
};

return $tests;
