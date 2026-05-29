<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage159 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 0), 32, 4);
    $page = substr_replace($page, pack('N', 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry159 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$overflowPage159 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database159 = static function () use ($makeFirstPage159, $putPointerMapEntry159, $overflowPage159): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage159(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next159', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage159(107, 'A');
    $pages[107] = $overflowPage159(108, 'B');
    $pages[108] = $overflowPage159(109, 'C');
    $pages[109] = $overflowPage159(110, 'D');
    $pages[110] = $overflowPage159(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry159($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan159 = static function (int $maxTruncatedPages = 3, ?string $payload = null) use ($database159): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database159();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext159(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next159-replacement-overflow-page-chain-', 18),
        3,
        true,
    );
};

$message159 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases159 = [
    'action label' => static fn (): mixed => $plan159()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan159()->toArray()['released_overflow_pages'],
    'allocated overflow pages' => static fn (): mixed => $plan159()->toArray()['allocated_overflow_pages'],
    'reused surviving chain pages' => static fn (): mixed => $plan159()->reusedSurvivingChainPages(),
    'rejected truncated chain pages' => static fn (): mixed => $plan159()->rejectedTruncatedChainPages(),
    'final database page count' => static fn (): mixed => $plan159()->toArray()['final_database_page_count'],
    'final freelist page numbers' => static fn (): mixed => $plan159()->toArray()['final_freelist_page_numbers'],
    'chain row pages' => static fn (): mixed => array_column($plan159()->chainRows(), 'page_number'),
    'source overflow next pages' => static fn (): mixed => array_column($plan159()->chainRows(), 'source_overflow_next_page'),
    'post vacuum overflow next pages' => static fn (): mixed => array_column($plan159()->chainRows(), 'post_vacuum_overflow_next_page'),
    'final overflow next pages' => static fn (): mixed => array_column($plan159()->chainRows(), 'final_overflow_next_page'),
    'source pointer map types' => static fn (): mixed => array_column($plan159()->chainRows(), 'source_pointer_map_type'),
    'source pointer map parents' => static fn (): mixed => array_column($plan159()->chainRows(), 'source_pointer_map_parent'),
    'post vacuum pointer map types' => static fn (): mixed => array_column($plan159()->chainRows(), 'post_vacuum_pointer_map_type'),
    'post vacuum pointer map parents' => static fn (): mixed => array_column($plan159()->chainRows(), 'post_vacuum_pointer_map_parent'),
    'final pointer map types' => static fn (): mixed => array_column($plan159()->chainRows(), 'final_pointer_map_type'),
    'final pointer map parents' => static fn (): mixed => array_column($plan159()->chainRows(), 'final_pointer_map_parent'),
    'allocated flags' => static fn (): mixed => array_column($plan159()->chainRows(), 'allocated_for_replacement'),
    'reused flags' => static fn (): mixed => array_column($plan159()->chainRows(), 'reused_surviving_released_page'),
    'rejected flags' => static fn (): mixed => array_column($plan159()->chainRows(), 'rejected_after_truncate'),
    'materialized flags' => static fn (): mixed => array_column($plan159()->chainRows(), 'final_materialized'),
    'base surviving released pages' => static fn (): mixed => $plan159()->basePlan->basePlan->basePlan->survivingReleasedOverflowPages(),
    'base truncated released pages' => static fn (): mixed => $plan159()->basePlan->basePlan->basePlan->truncatedReleasedOverflowPages(),
    'base rejected truncated pages' => static fn (): mixed => $plan159()->basePlan->truncatedReleasedOverflowPagesRejected(),
    'allocation sources' => static fn (): mixed => array_column($plan159()->basePlan->allocationPlan->allocationSteps(), 'source'),
    'allocation trunks' => static fn (): mixed => array_column($plan159()->basePlan->allocationPlan->allocationSteps(), 'trunk_page'),
    'overflow image pages' => static fn (): mixed => array_keys($plan159()->basePlan->overflowPageImages()),
    'page image pages' => static fn (): mixed => array_keys($plan159()->basePlan->pageImages()),
    'single page replacement rejected' => static fn (): mixed => $message159(static fn () => $plan159(3, str_repeat('short', 20))),
];

$expected159 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next159',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'allocated overflow pages' => [107, 106],
    'reused surviving chain pages' => [106, 107],
    'rejected truncated chain pages' => [108, 109, 110],
    'final database page count' => 107,
    'final freelist page numbers' => [],
    'chain row pages' => [106, 107, 108, 109, 110],
    'source overflow next pages' => [107, 108, 109, 110, 0],
    'post vacuum overflow next pages' => [0, 0, null, null, null],
    'final overflow next pages' => [0, 106, null, null, null],
    'source pointer map types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'source pointer map parents' => [3, 106, 107, 108, 109],
    'post vacuum pointer map types' => ['free-page', 'free-page', null, null, null],
    'post vacuum pointer map parents' => [0, 0, null, null, null],
    'final pointer map types' => ['overflow-page', 'first-overflow-page', null, null, null],
    'final pointer map parents' => [107, 3, null, null, null],
    'allocated flags' => [true, true, false, false, false],
    'reused flags' => [true, true, false, false, false],
    'rejected flags' => [false, false, true, true, true],
    'materialized flags' => [true, true, false, false, false],
    'base surviving released pages' => [106, 107],
    'base truncated released pages' => [108, 109, 110],
    'base rejected truncated pages' => [108, 109, 110],
    'allocation sources' => ['freelist-leaf', 'freelist-trunk'],
    'allocation trunks' => [106, 106],
    'overflow image pages' => [107, 106],
    'page image pages' => [1, 3, 105, 106, 107],
    'single page replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next159 requires a multi-page replacement overflow chain',
];

$tests = [];

foreach ($cases159 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next159 ' . $name] = static function (TestRunner $t) use ($callback, $expected159, $name): void {
        $t->same($expected159[$name], $callback());
    };
}

foreach (range(1, 36) as $index) {
    $tests['btree vacuum pointermap freeblock current source next159 invariant ' . $index] = static function (TestRunner $t) use ($plan159): void {
        $plan = $plan159();

        $t->same([107, 106], $plan->basePlan->allocatedOverflowPages());
        $t->same([106, 107], $plan->reusedSurvivingChainPages());
        $t->same([108, 109, 110], $plan->rejectedTruncatedChainPages());
        $t->same(107, $plan->basePlan->databaseAfterAllocation->pageCount());
        $t->same(0, unpack('N', substr($plan->basePlan->databaseAfterAllocation->page(106), 0, 4))[1]);
        $t->same(106, unpack('N', substr($plan->basePlan->databaseAfterAllocation->page(107), 0, 4))[1]);
        $t->same('overflow-page', $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(106)->typeName());
        $t->same('first-overflow-page', $plan->basePlan->databaseAfterAllocation->pointerMapEntryForPage(107)->typeName());
        $t->same([true, true, false, false, false], array_column($plan->chainRows(), 'allocated_for_replacement'));
        $t->same([false, false, true, true, true], array_column($plan->chainRows(), 'rejected_after_truncate'));
    };
}

return $tests;
