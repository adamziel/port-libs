<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage161 = static function (int $pageCount): string {
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

$putPointerMapEntry161 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage161 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database161 = static function () use ($makeFirstPage161, $putPointerMapEntry161, $overflowPage161): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage161(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next161', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage161(107, 'A');
    $pages[107] = $overflowPage161(108, 'B');
    $pages[108] = $overflowPage161(109, 'C');
    $pages[109] = $overflowPage161(110, 'D');
    $pages[110] = $overflowPage161(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry161($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan161 = static function (
    ?string $payload = null,
    int $parentPage = 3,
) use ($database161): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database161();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext161(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        4,
        $payload ?? str_repeat('next161-replacement-overflow-page-chain-', 38),
        $parentPage,
        true,
    );
};

$message161 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases161 = [
    'action label' => static fn (): mixed => $plan161()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan161()->toArray()['released_overflow_pages'],
    'surviving released pages' => static fn (): mixed => $plan161()->toArray()['surviving_released_overflow_pages'],
    'truncated released pages' => static fn (): mixed => $plan161()->toArray()['truncated_released_overflow_pages'],
    'allocated overflow pages' => static fn (): mixed => $plan161()->allocatedOverflowPages(),
    'appended overflow pages' => static fn (): mixed => $plan161()->appendedOverflowPages(),
    'reused surviving pages' => static fn (): mixed => $plan161()->reusedSurvivingReleasedOverflowPages(),
    'appended previously truncated pages' => static fn (): mixed => $plan161()->appendedPreviouslyTruncatedOverflowPages(),
    'final database page count' => static fn (): mixed => $plan161()->toArray()['final_database_page_count'],
    'final freelist page numbers' => static fn (): mixed => $plan161()->toArray()['final_freelist_page_numbers'],
    'row pages' => static fn (): mixed => array_column($plan161()->rows, 'page_number'),
    'row post vacuum statuses' => static fn (): mixed => array_column($plan161()->rows, 'post_vacuum_status'),
    'row post vacuum materialized' => static fn (): mixed => array_column($plan161()->rows, 'post_vacuum_materialized'),
    'row allocated flags' => static fn (): mixed => array_column($plan161()->rows, 'allocated_for_replacement'),
    'row appended flags' => static fn (): mixed => array_column($plan161()->rows, 'appended_for_replacement'),
    'row was truncated flags' => static fn (): mixed => array_column($plan161()->rows, 'was_truncated_by_vacuum'),
    'row appended after truncate flags' => static fn (): mixed => array_column($plan161()->rows, 'appended_after_truncate'),
    'row final pointer types' => static fn (): mixed => array_column($plan161()->rows, 'final_pointer_map_type'),
    'row final pointer parents' => static fn (): mixed => array_column($plan161()->rows, 'final_pointer_map_parent'),
    'row final overflow next pages' => static fn (): mixed => array_column($plan161()->rows, 'final_overflow_next_page'),
    'row final materialized flags' => static fn (): mixed => array_column($plan161()->rows, 'final_materialized'),
    'allocation sources' => static fn (): mixed => array_column($plan161()->allocationPlan->allocationSteps(), 'source'),
    'allocation allocated pages' => static fn (): mixed => array_column($plan161()->allocationPlan->allocationSteps(), 'allocated_page'),
    'allocation trunks' => static fn (): mixed => array_column($plan161()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocated pointer map pages' => static fn (): mixed => array_column($plan161()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocated pointer map types' => static fn (): mixed => array_column($plan161()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer map parents' => static fn (): mixed => array_column($plan161()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'overflow image pages' => static fn (): mixed => array_keys($plan161()->overflowPageImages()),
    'page image pages' => static fn (): mixed => array_keys($plan161()->pageImages()),
    'header page count' => static fn (): mixed => $plan161()->databaseAfterAllocation->header->databaseSizePages,
    'small replacement rejected' => static fn (): mixed => $message161(static fn () => $plan161(str_repeat('small', 20))),
    'empty payload rejected' => static fn (): mixed => $message161(static fn () => $plan161('')),
    'bad parent rejected' => static fn (): mixed => $message161(static fn () => $plan161(null, 1)),
];

$expected161 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next161',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving released pages' => [106],
    'truncated released pages' => [107, 108, 109, 110],
    'allocated overflow pages' => [106, 107, 108],
    'appended overflow pages' => [107, 108],
    'reused surviving pages' => [106],
    'appended previously truncated pages' => [107, 108],
    'final database page count' => 108,
    'final freelist page numbers' => [],
    'row pages' => [3, 106, 107, 108, 109, 110],
    'row post vacuum statuses' => ['materialized-leaf-page', 'survives-as-free-page', 'truncated', 'truncated', 'truncated', 'truncated'],
    'row post vacuum materialized' => [true, true, false, false, false, false],
    'row allocated flags' => [false, true, true, true, false, false],
    'row appended flags' => [false, false, true, true, false, false],
    'row was truncated flags' => [false, false, true, true, true, true],
    'row appended after truncate flags' => [false, false, true, true, false, false],
    'row final pointer types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', null, null],
    'row final pointer parents' => [0, 3, 106, 107, null, null],
    'row final overflow next pages' => [null, 107, 108, 0, null, null],
    'row final materialized flags' => [true, true, true, true, false, false],
    'allocation sources' => ['freelist-trunk', 'append', 'append'],
    'allocation allocated pages' => [106, 107, 108],
    'allocation trunks' => [106, null, null],
    'allocated pointer map pages' => [106, 107, 108],
    'allocated pointer map types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'allocated pointer map parents' => [3, 106, 107],
    'overflow image pages' => [106, 107, 108],
    'page image pages' => [1, 3, 105, 106, 107, 108],
    'header page count' => 108,
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases161 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next161 ' . $name] = static function (TestRunner $t) use ($callback, $expected161, $name): void {
        $t->same($expected161[$name], $callback());
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree vacuum pointermap freeblock current source next161 invariant ' . $index] = static function (TestRunner $t) use ($plan161): void {
        $plan = $plan161();

        $t->same([106, 107, 108], $plan->allocatedOverflowPages());
        $t->same([107, 108], $plan->appendedOverflowPages());
        $t->same([107, 108], $plan->appendedPreviouslyTruncatedOverflowPages());
        $t->same([106], $plan->reusedSurvivingReleasedOverflowPages());
        $t->same([], $plan->databaseAfterAllocation->freelistPageNumbers());
        $t->same([107, 108, 0], [
            unpack('N', substr($plan->databaseAfterAllocation->page(106), 0, 4))[1],
            unpack('N', substr($plan->databaseAfterAllocation->page(107), 0, 4))[1],
            unpack('N', substr($plan->databaseAfterAllocation->page(108), 0, 4))[1],
        ]);
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], [
            $plan->databaseAfterAllocation->pointerMapEntryForPage(106)->typeName(),
            $plan->databaseAfterAllocation->pointerMapEntryForPage(107)->typeName(),
            $plan->databaseAfterAllocation->pointerMapEntryForPage(108)->typeName(),
        ]);
        $t->same([3, 106, 107], [
            $plan->databaseAfterAllocation->pointerMapEntryForPage(106)->parentPageNumber,
            $plan->databaseAfterAllocation->pointerMapEntryForPage(107)->parentPageNumber,
            $plan->databaseAfterAllocation->pointerMapEntryForPage(108)->parentPageNumber,
        ]);
    };
}

return $tests;
