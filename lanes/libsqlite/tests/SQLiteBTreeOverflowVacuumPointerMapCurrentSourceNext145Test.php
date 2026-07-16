<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage145 = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
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

$putPointerMapEntry145 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$fixture145 = static function (?string $payload = null, int $maxTruncatedPages = 3) use ($makeFirstPage145, $putPointerMapEntry145): SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $payload ??= str_repeat('next145-wp-option-overflow-vacuum-pointermap-', 42);
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage145($pageSize, $pageCount, 0, 0);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry145($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
        $putPointerMapEntry145(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    return SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow-next145',
                'first_page' => 306,
                'overflow_payload_bytes' => 1524,
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow-next145',
                'first_page' => 309,
                'overflow_payload_bytes' => 1016,
            ],
        ],
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow-next145',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [14501],
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow-next145',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_timeout_next145', 14501]],
            ],
        ],
        $maxTruncatedPages,
        $payload,
        42,
        true,
    );
};

$throws145 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases145 = [
    'action label' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'current source pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->currentSourceRows(), 'page_number'),
    'current source next pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->currentSourceRows(), 'current_next_page'),
    'current source terminal flags' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->currentSourceRows(), 'current_terminal'),
    'current source pointer types' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->currentSourceRows(), 'current_pointer_map_type'),
    'current source pointer parents' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->currentSourceRows(), 'current_pointer_map_parent'),
    'released pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPages(),
    'truncated pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->truncatedPageNumbers(),
    'survivors' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->survivingFreedPointerMapPages(),
    'truncated freed pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->vacuumPlan->truncatedFreedPointerMapPages(),
    'allocated pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->allocatedOverflowPages(),
    'reused surviving pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->reusedSurvivingOverflowPages(),
    'appended pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->appendedOverflowPages(),
    'truncated not reused' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->truncatedOverflowPagesNotReused(),
    'final page count' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pageCount(),
    'final freelist pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'final first trunk' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final freelist count' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->header->freelistPageCount,
    'transition pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'page_number'),
    'transition vacuum statuses' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'vacuum_status'),
    'transition current sources' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'current_source'),
    'transition allocation flags' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocated_after_vacuum'),
    'transition allocation positions' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocation_position'),
    'transition allocation sources' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'allocation_source'),
    'transition appended flags' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'appended_after_vacuum'),
    'transition final pointer types' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'final_pointer_map_type'),
    'transition final pointer parents' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'final_pointer_map_parent'),
    'transition final next pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'final_overflow_next_page'),
    'transition payload prefixes' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'payload_prefix'),
    'summary current pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary transition pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => array_column($plan->toArray()['btree_overflow_vacuum_pointermap_current_source_next145'], 'page_number'),
    'summary updated pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['updated_page_numbers'],
    'summary final page count' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_database_page_count'],
    'summary appended pages' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->toArray()['appended_overflow_pages'],
    'page 307 next pointer' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfterAllocation->page(307), 0, 4))[1],
    'page 306 next pointer' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfterAllocation->page(306), 0, 4))[1],
    'page 308 next pointer' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfterAllocation->page(308), 0, 4))[1],
    'page 309 next pointer' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfterAllocation->page(309), 0, 4))[1],
    'page 307 final pointer parent' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(307)->parentPageNumber,
    'page 306 final pointer parent' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(306)->parentPageNumber,
    'page 308 final pointer parent' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(308)->parentPageNumber,
    'page 309 final pointer parent' => static fn (SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan $plan): mixed => $plan->databaseAfterAllocation->pointerMapEntryForPage(309)->parentPageNumber,
];

$expected145 = [
    'action label' => 'btree-overflow-vacuum-pointermap-current-source-next145',
    'current source pages' => [306, 307, 308, 309, 310],
    'current source next pages' => [307, 308, 0, 310, 0],
    'current source terminal flags' => [false, false, true, false, true],
    'current source pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'current source pointer parents' => [42, 306, 307, 4, 309],
    'released pages' => [306, 307, 308, 309, 310],
    'truncated pages' => [310, 309, 308],
    'survivors' => [306, 307],
    'truncated freed pages' => [308, 309, 310],
    'allocated pages' => [307, 306, 308, 309],
    'reused surviving pages' => [307, 306],
    'appended pages' => [308, 309],
    'truncated not reused' => [308, 309, 310],
    'final page count' => 309,
    'final freelist pages' => [],
    'final first trunk' => 0,
    'final freelist count' => 0,
    'transition pages' => [306, 307, 308, 309, 310],
    'transition vacuum statuses' => ['survives-as-free-page', 'survives-as-free-page', 'truncated-from-database', 'truncated-from-database', 'truncated-from-database'],
    'transition current sources' => ['wp_options-autoload-table-tail-overflow-next145', 'wp_options-autoload-table-tail-overflow-next145', 'wp_options-autoload-table-tail-overflow-next145', 'wp_options-option-name-index-tail-overflow-next145', 'wp_options-option-name-index-tail-overflow-next145'],
    'transition allocation flags' => [true, true, true, true, false],
    'transition allocation positions' => [1, 0, 2, 3, null],
    'transition allocation sources' => ['freelist-trunk', 'freelist-leaf', 'append', 'append', null],
    'transition appended flags' => [false, false, true, true, false],
    'transition final pointer types' => ['overflow-page', 'first-overflow-page', 'overflow-page', 'overflow-page', null],
    'transition final pointer parents' => [307, 42, 306, 308, null],
    'transition final next pages' => [308, 306, 309, 0, null],
    'transition payload prefixes' => ['tion-overflow-va', 'next145-wp-optio', '-vacuum-pointerm', 'ermap-next145-wp', null],
    'summary current pages' => [306, 307, 308, 309, 310],
    'summary transition pages' => [306, 307, 308, 309, 310],
    'summary updated pages' => [1, 208, 306, 307, 308, 309],
    'summary final page count' => 309,
    'summary appended pages' => [308, 309],
    'page 307 next pointer' => 306,
    'page 306 next pointer' => 308,
    'page 308 next pointer' => 309,
    'page 309 next pointer' => 0,
    'page 307 final pointer parent' => 42,
    'page 306 final pointer parent' => 307,
    'page 308 final pointer parent' => 306,
    'page 309 final pointer parent' => 308,
];

$tests = [];

foreach ($cases145 as $name => $callback) {
    $tests['btree overflow vacuum pointermap current source next145 ' . $name] = static function (TestRunner $t) use ($fixture145, $callback, $expected145, $name): void {
        $t->same($expected145[$name], $callback($fixture145()));
    };
}

foreach (range(1, 34) as $index) {
    $tests['btree overflow vacuum pointermap current source next145 invariant ' . $index] = static function (TestRunner $t) use ($fixture145): void {
        $plan = $fixture145();
        $rows = $plan->transitionRows();
        $allocated = $plan->allocatedOverflowPages();

        $t->same([307, 306], $plan->reusedSurvivingOverflowPages());
        $t->same([308, 309], $plan->appendedOverflowPages());
        $t->same([], array_values(array_intersect($plan->appendedOverflowPages(), $plan->vacuumPlan->survivingFreedPointerMapPages())));
        $t->same([false, false, true, true], array_slice(array_column($rows, 'appended_after_vacuum'), 0, 4));
        $t->same(['overflow-page', 'first-overflow-page', 'overflow-page', 'overflow-page'], array_slice(array_column($rows, 'final_pointer_map_type'), 0, 4));
        $t->same([42, 307, 306, 308], [$plan->databaseAfterAllocation->pointerMapEntryForPage($allocated[0])->parentPageNumber, $plan->databaseAfterAllocation->pointerMapEntryForPage($allocated[1])->parentPageNumber, $plan->databaseAfterAllocation->pointerMapEntryForPage($allocated[2])->parentPageNumber, $plan->databaseAfterAllocation->pointerMapEntryForPage($allocated[3])->parentPageNumber]);
        $t->same([306, 308, 309, 0], [unpack('N', substr($plan->databaseAfterAllocation->page($allocated[0]), 0, 4))[1], unpack('N', substr($plan->databaseAfterAllocation->page($allocated[1]), 0, 4))[1], unpack('N', substr($plan->databaseAfterAllocation->page($allocated[2]), 0, 4))[1], unpack('N', substr($plan->databaseAfterAllocation->page($allocated[3]), 0, 4))[1]]);
    };
}

$tests['btree overflow vacuum pointermap current source next145 rejects empty chain list'] = static function (TestRunner $t) use ($fixture145, $throws145): void {
    $t->same(
        'SQLite b-tree overflow vacuum pointer-map next145 requires current-source overflow chains',
        $throws145(static fn () => SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
            SQLiteDatabase::fromBytes($fixture145()->vacuumPlan->sourceDatabase->toBytes()),
            [],
            [['obsolete_overflow_page_numbers' => [306]]],
            1,
            'payload',
            42,
        )),
    );
};

$tests['btree overflow vacuum pointermap current source next145 rejects empty payload'] = static function (TestRunner $t) use ($fixture145, $throws145): void {
    $t->same(
        'SQLite b-tree overflow vacuum pointer-map next145 requires replacement overflow payload bytes',
        $throws145(static fn () => $fixture145('', 3)),
    );
};

$tests['btree overflow vacuum pointermap current source next145 rejects bad parent'] = static function (TestRunner $t) use ($fixture145, $throws145): void {
    $source = $fixture145()->vacuumPlan->sourceDatabase;
    $t->same(
        'SQLite b-tree overflow vacuum pointer-map next145 parent b-tree page must be at page 2 or later',
        $throws145(static fn () => SQLiteBTreeOverflowVacuumPointerMapCurrentSourceNextPlan::fromCurrentSourceOverflowChains(
            $source,
            [['first_page' => 306, 'overflow_payload_bytes' => 508]],
            [['obsolete_overflow_page_numbers' => [306]]],
            1,
            'payload',
            1,
        )),
    );
};

return $tests;
