<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage156 = static function (int $pageCount): string {
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

$putPointerMapEntry156 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage156 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database156 = static function () use ($makeFirstPage156, $putPointerMapEntry156, $overflowPage156): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage156(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next156', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage156(107, 'A');
    $pages[107] = $overflowPage156(108, 'B');
    $pages[108] = $overflowPage156(109, 'C');
    $pages[109] = $overflowPage156(110, 'D');
    $pages[110] = $overflowPage156(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry156($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan156 = static function (
    int $maxTruncatedPages = 4,
    ?string $payload = null,
    int $parentPage = 3,
) use ($database156): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    $database = $database156();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafOverflowAllocationFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next156-replacement-overflow-', 10),
        $parentPage,
        true,
    );
};

$message156 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases156 = [
    'action label' => static fn (): mixed => $plan156()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan156()->toArray()['released_overflow_pages'],
    'surviving released pages' => static fn (): mixed => $plan156()->toArray()['surviving_released_overflow_pages'],
    'truncated released pages' => static fn (): mixed => $plan156()->toArray()['truncated_released_overflow_pages'],
    'truncated pointer map pages' => static fn (): mixed => $plan156()->toArray()['truncated_pointer_map_pages'],
    'allocated overflow pages' => static fn (): mixed => $plan156()->allocatedOverflowPages(),
    'surviving pages reused' => static fn (): mixed => $plan156()->survivingReleasedOverflowPagesReused(),
    'truncated released pages rejected' => static fn (): mixed => $plan156()->truncatedReleasedOverflowPagesRejected(),
    'truncated pointer pages rejected' => static fn (): mixed => $plan156()->truncatedPointerMapPagesRejected(),
    'final page count' => static fn (): mixed => $plan156()->toArray()['final_database_page_count'],
    'final freelist pages' => static fn (): mixed => $plan156()->toArray()['final_freelist_page_numbers'],
    'updated pages' => static fn (): mixed => $plan156()->toArray()['updated_page_numbers'],
    'row kinds' => static fn (): mixed => array_column($plan156()->rows, 'kind'),
    'row pages' => static fn (): mixed => array_column($plan156()->rows, 'page_number'),
    'row post vacuum statuses' => static fn (): mixed => array_column($plan156()->rows, 'post_vacuum_status'),
    'row allocated flags' => static fn (): mixed => array_column($plan156()->rows, 'allocated_for_replacement'),
    'row rejected flags' => static fn (): mixed => array_column($plan156()->rows, 'rejected_after_truncate'),
    'row final materialized flags' => static fn (): mixed => array_column($plan156()->rows, 'final_materialized'),
    'row final pointer types' => static fn (): mixed => array_column($plan156()->rows, 'final_pointer_map_type'),
    'row final pointer parents' => static fn (): mixed => array_column($plan156()->rows, 'final_pointer_map_parent'),
    'row post vacuum pointer types' => static fn (): mixed => array_column($plan156()->rows, 'post_vacuum_pointer_map_type'),
    'allocation sources' => static fn (): mixed => array_column($plan156()->allocationPlan->allocationSteps(), 'source'),
    'allocation trunks' => static fn (): mixed => array_column($plan156()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocated pointer types' => static fn (): mixed => array_column($plan156()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer parents' => static fn (): mixed => array_column($plan156()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'overflow image pages' => static fn (): mixed => array_keys($plan156()->overflowPageImages()),
    'page image pages' => static fn (): mixed => array_keys($plan156()->pageImages()),
    'allocated page 106 next pointer' => static fn (): mixed => unpack('N', substr($plan156()->databaseAfterAllocation->page(106), 0, 4))[1],
    'leaf freeblock status' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($plan156()->basePlan->basePlan->basePlan->deletePlan->leafPageImage, 512)->freeblockIntegrityReport($plan156()->basePlan->basePlan->basePlan->deletePlan->leafPageImage)['status'],
    'wide vacuum rejected allocation' => static fn (): mixed => $message156(static fn () => $plan156(6)),
    'empty payload rejected' => static fn (): mixed => $message156(static fn () => $plan156(4, '')),
    'bad parent rejected' => static fn (): mixed => $message156(static fn () => $plan156(4, null, 1)),
];

$expected156 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next156',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving released pages' => [106],
    'truncated released pages' => [107, 108, 109, 110],
    'truncated pointer map pages' => [],
    'allocated overflow pages' => [106],
    'surviving pages reused' => [106],
    'truncated released pages rejected' => [107, 108, 109, 110],
    'truncated pointer pages rejected' => [],
    'final page count' => 106,
    'final freelist pages' => [],
    'updated pages' => [1, 3, 105, 106],
    'row kinds' => ['deleted-leaf-freeblock', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page'],
    'row pages' => [3, 106, 107, 108, 109, 110],
    'row post vacuum statuses' => ['materialized-leaf-page', 'survives-as-free-page', 'truncated', 'truncated', 'truncated', 'truncated'],
    'row allocated flags' => [false, true, false, false, false, false],
    'row rejected flags' => [false, false, true, true, true, true],
    'row final materialized flags' => [true, true, false, false, false, false],
    'row final pointer types' => ['root-page', 'first-overflow-page', null, null, null, null],
    'row final pointer parents' => [0, 3, null, null, null, null],
    'row post vacuum pointer types' => ['root-page', 'free-page', null, null, null, null],
    'allocation sources' => ['freelist-trunk'],
    'allocation trunks' => [106],
    'allocated pointer types' => ['first-overflow-page'],
    'allocated pointer parents' => [3],
    'overflow image pages' => [106],
    'page image pages' => [1, 3, 105, 106],
    'allocated page 106 next pointer' => 0,
    'leaf freeblock status' => 'ok',
    'wide vacuum rejected allocation' => 'SQLite freelist does not contain enough pages for this allocation',
    'empty payload rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree vacuum pointer-map freeblock next156 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases156 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next156 ' . $name] = static function (TestRunner $t) use ($callback, $expected156, $name): void {
        $t->same($expected156[$name], $callback());
    };
}

foreach (range(1, 42) as $index) {
    $tests['btree vacuum pointermap freeblock current source next156 invariant ' . $index] = static function (TestRunner $t) use ($plan156): void {
        $plan = $plan156();

        $t->same([106], $plan->allocatedOverflowPages());
        $t->same([106], $plan->survivingReleasedOverflowPagesReused());
        $t->same([107, 108, 109, 110], $plan->truncatedReleasedOverflowPagesRejected());
        $t->same([], $plan->databaseAfterAllocation->freelistPageNumbers());
        $t->same('first-overflow-page', $plan->databaseAfterAllocation->pointerMapEntryForPage(106)->typeName());
        $t->same(3, $plan->databaseAfterAllocation->pointerMapEntryForPage(106)->parentPageNumber);
        $t->same([false, true, false, false, false, false], array_column($plan->rows, 'allocated_for_replacement'));
        $t->same([false, false, true, true, true, true], array_column($plan->rows, 'rejected_after_truncate'));
    };
}

return $tests;
