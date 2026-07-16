<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage125 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry125 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$makeOverflowPage125 = static fn (?int $nextPage, string $payload): string => str_pad(
    pack('N', $nextPage ?? 0) . $payload,
    512,
    "\0",
);

$databaseFixture125 = static function () use ($makeFirstPage125, $putPointerMapEntry125, $makeOverflowPage125): SQLiteDatabase {
    $pages = array_fill(1, 8, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage125(8, 8, 1);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[5] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $makeOverflowPage125(7, str_repeat('O', 508));
    $pages[7] = $makeOverflowPage125(null, str_repeat('P', 192));
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

    $putPointerMapEntry125($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry125($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry125($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry125($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry125($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry125($pages, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payload125 = static fn (): string => str_repeat('N', 508) . str_repeat('Q', 192);

$planFixture125 = static function (bool $secureDelete = false) use ($databaseFixture125, $payload125): SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan {
    return SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan::fromOverflowChains(
        $databaseFixture125(),
        [[
            'source' => 'wp-options-large-transient-rewrite',
            'first_page' => 6,
            'overflow_payload_bytes' => 700,
            'rowids' => [12501],
        ]],
        700,
        3,
        $payload125(),
        $secureDelete,
    );
};

$throwsMessage125 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows125 = static fn (SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan $plan): array => $plan->transitionRows();

$cases125 = [
    'action label' => static fn (): mixed => $planFixture125()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $planFixture125()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $planFixture125()->allocatedOverflowPages(),
    'reused overflow pages' => static fn (): mixed => $planFixture125()->reusedOverflowPages(),
    'release source pages' => static fn (): mixed => $planFixture125()->releasePlan->sources[0]['pages'],
    'release source count' => static fn (): mixed => $planFixture125()->releasePlan->sources[0]['count'],
    'free plan freed pages' => static fn (): mixed => $planFixture125()->releasePlan->freePlan->freedPageNumbers,
    'free plan leaf pages' => static fn (): mixed => $planFixture125()->releasePlan->freePlan->leafPageNumbers,
    'free plan new trunks' => static fn (): mixed => $planFixture125()->releasePlan->freePlan->newTrunkPageNumbers,
    'free plan updated freelist pages' => static fn (): mixed => array_keys($planFixture125()->releasePlan->freePlan->updatedFreelistPages),
    'free plan updated pointer map pages' => static fn (): mixed => array_keys($planFixture125()->releasePlan->freePlan->updatedPointerMapPages),
    'free pointer map entry types' => static fn (): mixed => array_column($planFixture125()->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'free pointer map parents' => static fn (): mixed => array_column($planFixture125()->releasePlan->freePlan->freedPointerMapEntries, 'parent_page_number'),
    'after release freelist count' => static fn (): mixed => $planFixture125()->databaseAfterRelease->header->freelistPageCount,
    'after release freelist pages' => static fn (): mixed => $planFixture125()->databaseAfterRelease->freelistPageNumbers(),
    'after release page six pointer type' => static fn (): mixed => $planFixture125()->databaseAfterRelease->pointerMapEntryForPage(6)->typeName(),
    'after release page seven pointer type' => static fn (): mixed => $planFixture125()->databaseAfterRelease->pointerMapEntryForPage(7)->typeName(),
    'allocation no appended pages' => static fn (): mixed => $planFixture125()->allocationPlan->appendedPageNumbers,
    'allocation step sources' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocationSteps(), 'source'),
    'allocation step trunk pages' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation leaf count before' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocationSteps(), 'leaf_count_before'),
    'allocation leaf count after' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocationSteps(), 'leaf_count_after'),
    'allocation freelist count after' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'allocation pointer map pages' => static fn (): mixed => array_keys($planFixture125()->allocationPlan->updatedPointerMapPages),
    'allocation pointer map types' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer map parents' => static fn (): mixed => array_column($planFixture125()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'final freelist count' => static fn (): mixed => $planFixture125()->databaseAfterAllocation->header->freelistPageCount,
    'final freelist pages' => static fn (): mixed => $planFixture125()->databaseAfterAllocation->freelistPageNumbers(),
    'final page six pointer type' => static fn (): mixed => $planFixture125()->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'final page seven pointer type' => static fn (): mixed => $planFixture125()->databaseAfterAllocation->pointerMapEntryForPage(7)->typeName(),
    'final page six pointer parent' => static fn (): mixed => $planFixture125()->databaseAfterAllocation->pointerMapEntryForPage(6)->parentPageNumber,
    'final page seven pointer parent' => static fn (): mixed => $planFixture125()->databaseAfterAllocation->pointerMapEntryForPage(7)->parentPageNumber,
    'final page six next pointer' => static fn (): mixed => unpack('N', substr($planFixture125()->databaseAfterAllocation->page(6), 0, 4))[1],
    'final page seven next pointer' => static fn (): mixed => unpack('N', substr($planFixture125()->databaseAfterAllocation->page(7), 0, 4))[1],
    'final page six payload prefix' => static fn (): mixed => substr($planFixture125()->databaseAfterAllocation->page(6), 4, 12),
    'final page seven payload prefix' => static fn (): mixed => substr($planFixture125()->databaseAfterAllocation->page(7), 4, 12),
    'transition row pages' => static fn (): mixed => array_column($rows125($planFixture125()), 'page_number'),
    'transition row release source' => static fn (): mixed => array_column($rows125($planFixture125()), 'release_source'),
    'transition row before types' => static fn (): mixed => array_column($rows125($planFixture125()), 'before_pointer_map_type'),
    'transition row before parents' => static fn (): mixed => array_column($rows125($planFixture125()), 'before_pointer_map_parent'),
    'transition row free types' => static fn (): mixed => array_column($rows125($planFixture125()), 'free_pointer_map_type'),
    'transition row free parents' => static fn (): mixed => array_column($rows125($planFixture125()), 'free_pointer_map_parent'),
    'transition row next types' => static fn (): mixed => array_column($rows125($planFixture125()), 'next_pointer_map_type'),
    'transition row next parents' => static fn (): mixed => array_column($rows125($planFixture125()), 'next_pointer_map_parent'),
    'transition row next pointers' => static fn (): mixed => array_column($rows125($planFixture125()), 'next_overflow_next_page'),
    'transition row payload prefixes' => static fn (): mixed => array_column($rows125($planFixture125()), 'payload_prefix'),
    'allocated page images keys' => static fn (): mixed => array_keys($planFixture125()->allocatedPageImages()),
    'page images include first pointer freelist and overflow pages' => static fn (): mixed => array_keys($planFixture125()->pageImages()),
    'summary updated page numbers' => static fn (): mixed => $planFixture125()->toArray()['updated_page_numbers'],
    'summary final freelist pages' => static fn (): mixed => $planFixture125()->toArray()['final_freelist_page_numbers'],
    'summary embeds transition pages' => static fn (): mixed => array_column($planFixture125()->toArray()['btree_overflow_pointermap_freelist_current_source_next125'], 'page_number'),
    'secure delete cleared pages' => static fn (): mixed => $planFixture125(true)->releasePlan->freePlan->clearedPageNumbers,
    'secure delete cleared image length' => static fn (): mixed => strlen($planFixture125(true)->releasePlan->freePlan->clearedPageImages[6]),
    'final integrity check reports ok' => static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $planFixture125()->databaseAfterAllocation)['rows'],
    'zero payload rejected' => static function () use ($databaseFixture125, $payload125, $throwsMessage125): mixed {
        return $throwsMessage125(static fn () => SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan::fromOverflowChains($databaseFixture125(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 0, 3, $payload125()));
    },
    'short payload rejected' => static function () use ($databaseFixture125, $throwsMessage125): mixed {
        return $throwsMessage125(static fn () => SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan::fromOverflowChains($databaseFixture125(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 700, 3, 'short'));
    },
    'invalid parent rejected' => static function () use ($databaseFixture125, $payload125, $throwsMessage125): mixed {
        return $throwsMessage125(static fn () => SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan::fromOverflowChains($databaseFixture125(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 700, 1, $payload125()));
    },
    'insufficient freelist rejected' => static function () use ($databaseFixture125, $payload125, $throwsMessage125): mixed {
        return $throwsMessage125(static fn () => SQLiteBTreeOverflowPointerMapFreelistCurrentSourceNextPlan::fromOverflowChains($databaseFixture125(), [['first_page' => 6, 'overflow_payload_bytes' => 700]], 1718, 3, $payload125() . str_repeat('Z', 1018)));
    },
];

$expected125 = [
    'action label' => 'btree-overflow-pointermap-freelist-current-source-next125',
    'released overflow pages' => [6, 7],
    'allocated overflow pages' => [6, 7],
    'reused overflow pages' => [6, 7],
    'release source pages' => [6, 7],
    'release source count' => 2,
    'free plan freed pages' => [6, 7],
    'free plan leaf pages' => [6, 7],
    'free plan new trunks' => [],
    'free plan updated freelist pages' => [8],
    'free plan updated pointer map pages' => [2],
    'free pointer map entry types' => ['free-page', 'free-page'],
    'free pointer map parents' => [0, 0],
    'after release freelist count' => 3,
    'after release freelist pages' => [8, 6, 7],
    'after release page six pointer type' => 'free-page',
    'after release page seven pointer type' => 'free-page',
    'allocation no appended pages' => [],
    'allocation step sources' => ['freelist-leaf', 'freelist-leaf'],
    'allocation step trunk pages' => [8, 8],
    'allocation leaf count before' => [2, 1],
    'allocation leaf count after' => [1, 0],
    'allocation freelist count after' => [2, 1],
    'allocation pointer map pages' => [2],
    'allocation pointer map types' => ['first-overflow-page', 'overflow-page'],
    'allocation pointer map parents' => [3, 6],
    'final freelist count' => 1,
    'final freelist pages' => [8],
    'final page six pointer type' => 'first-overflow-page',
    'final page seven pointer type' => 'overflow-page',
    'final page six pointer parent' => 3,
    'final page seven pointer parent' => 6,
    'final page six next pointer' => 7,
    'final page seven next pointer' => 0,
    'final page six payload prefix' => str_repeat('N', 12),
    'final page seven payload prefix' => str_repeat('Q', 12),
    'transition row pages' => [6, 7],
    'transition row release source' => ['wp-options-large-transient-rewrite', 'wp-options-large-transient-rewrite'],
    'transition row before types' => ['first-overflow-page', 'overflow-page'],
    'transition row before parents' => [3, 6],
    'transition row free types' => ['free-page', 'free-page'],
    'transition row free parents' => [0, 0],
    'transition row next types' => ['first-overflow-page', 'overflow-page'],
    'transition row next parents' => [3, 6],
    'transition row next pointers' => [7, 0],
    'transition row payload prefixes' => [str_repeat('N', 12), str_repeat('Q', 12)],
    'allocated page images keys' => [6, 7],
    'page images include first pointer freelist and overflow pages' => [1, 2, 6, 7, 8],
    'summary updated page numbers' => [1, 2, 6, 7, 8],
    'summary final freelist pages' => [8],
    'summary embeds transition pages' => [6, 7],
    'secure delete cleared pages' => [6, 7],
    'secure delete cleared image length' => 512,
    'final integrity check reports ok' => [['integrity_check' => 'ok']],
    'zero payload rejected' => 'SQLite overflow pointer-map freelist next125 payload byte count must be positive',
    'short payload rejected' => 'SQLite overflow pointer-map freelist next125 payload image is shorter than requested bytes',
    'invalid parent rejected' => 'SQLite overflow pointer-map freelist next125 parent b-tree page must be at page 2 or later',
    'insufficient freelist rejected' => 'SQLite freelist does not contain enough pages for this allocation',
];

$tests = [];

foreach ($cases125 as $name => $callback) {
    $tests['btree overflow pointermap freelist current source next125 ' . $name] = static function (TestRunner $t) use ($callback, $expected125, $name): void {
        $t->same($expected125[$name], $callback());
    };
}

foreach (range(1, 18) as $index) {
    $tests['btree overflow pointermap freelist current source next125 repeated transition invariant ' . $index] = static function (TestRunner $t) use ($planFixture125, $rows125): void {
        $plan = $planFixture125();
        $rows = $rows125($plan);

        $t->same([6, 7], $plan->releasedOverflowPages());
        $t->same([6, 7], $plan->allocatedOverflowPages());
        $t->same([6, 7], $plan->reusedOverflowPages());
        $t->same(['free-page', 'free-page'], array_column($rows, 'free_pointer_map_type'));
        $t->same(['first-overflow-page', 'overflow-page'], array_column($rows, 'next_pointer_map_type'));
        $t->same([7, 0], array_column($rows, 'next_overflow_next_page'));
    };
}

return $tests;
