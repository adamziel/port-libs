<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage155 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry155 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage155 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database155 = static function () use ($makeFirstPage155, $putPointerMapEntry155, $overflowPage155): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage155(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_next155', str_repeat('x', 96)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage155(107, 'A');
    $pages[107] = $overflowPage155(108, 'B');
    $pages[108] = $overflowPage155(109, 'C');
    $pages[109] = $overflowPage155(110, 'D');
    $pages[110] = $overflowPage155(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry155($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan155 = static function (int $maxTruncatedPages = 4, ?int $parentPage = 3): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Plan {
    global $database155;

    $database = $database155();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
    $newLeaf = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(40, SQLiteRecord::encode([null, '_site_transient_update_plugins', 'fresh'])),
        SQLiteTableLeafCell::encode(41, SQLiteRecord::encode([null, '_transient_feed_next155', 'cached'])),
    ]);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $parentPage,
        [$newLeaf],
        true,
    );
};

$message155 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases155 = [
    'action label' => static fn (): mixed => $plan155()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan155()->toArray()['released_overflow_pages'],
    'surviving vacuum free pages' => static fn (): mixed => $plan155()->toArray()['surviving_vacuum_free_pages'],
    'truncated vacuum pages' => static fn (): mixed => $plan155()->toArray()['truncated_vacuum_pages'],
    'allocated btree pages' => static fn (): mixed => $plan155()->allocatedBtreePages(),
    'reused vacuum free pages' => static fn (): mixed => $plan155()->reusedVacuumFreePages(),
    'allocation step sources' => static fn (): mixed => array_column($plan155()->allocationPlan->allocationSteps(), 'source'),
    'allocation step trunks' => static fn (): mixed => array_column($plan155()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation step freelist counts' => static fn (): mixed => array_column($plan155()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'allocated pointer page numbers' => static fn (): mixed => array_column($plan155()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocated pointer types' => static fn (): mixed => array_column($plan155()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer parents' => static fn (): mixed => array_column($plan155()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'row page numbers' => static fn (): mixed => array_column($plan155()->rows, 'page_number'),
    'row allocation sources' => static fn (): mixed => array_column($plan155()->rows, 'allocation_source'),
    'row source pointer types' => static fn (): mixed => array_column($plan155()->rows, 'source_pointer_map_type'),
    'row source pointer parents' => static fn (): mixed => array_column($plan155()->rows, 'source_pointer_map_parent'),
    'row vacuum pointer types' => static fn (): mixed => array_column($plan155()->rows, 'vacuum_pointer_map_type'),
    'row vacuum pointer parents' => static fn (): mixed => array_column($plan155()->rows, 'vacuum_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($plan155()->rows, 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($plan155()->rows, 'next_pointer_map_parent'),
    'row btree page type' => static fn (): mixed => array_column($plan155()->rows, 'btree_page_type'),
    'row btree cell count' => static fn (): mixed => array_column($plan155()->rows, 'btree_cell_count'),
    'row btree freeblock status' => static fn (): mixed => array_column($plan155()->rows, 'btree_freeblock_status'),
    'row freelist count after' => static fn (): mixed => array_column($plan155()->rows, 'freelist_count_after'),
    'final freelist pages' => static fn (): mixed => $plan155()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan155()->databaseAfterAllocation->header->freelistPageCount,
    'final first freelist trunk' => static fn (): mixed => $plan155()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final database page count' => static fn (): mixed => $plan155()->databaseAfterAllocation->pageCount(),
    'new page rowids' => static function () use ($plan155): mixed {
        $page = $plan155()->databaseAfterAllocation->page(106);
        $header = SQLiteBTreePageHeader::parsePage($page, 512);

        return array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($page, $header));
    },
    'new page freeblock status' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($plan155()->databaseAfterAllocation->page(106), 512)->freeblockIntegrityReport($plan155()->databaseAfterAllocation->page(106))['status'],
    'leaf delete freeblock survives next allocation' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($plan155()->databaseAfterAllocation->page(3), 512)->freeblockIntegrityReport($plan155()->databaseAfterAllocation->page(3))['status'],
    'summary updated pages' => static fn (): mixed => $plan155()->toArray()['updated_page_numbers'],
    'summary rows page numbers' => static fn (): mixed => array_column($plan155()->toArray()['btree_vacuum_pointermap_freeblock_current_source_next155'], 'page_number'),
    'summary final freelist pages' => static fn (): mixed => $plan155()->toArray()['final_freelist_page_numbers'],
    'wide vacuum leaves no reusable page' => static fn (): mixed => $message155(static fn () => $plan155(6)),
    'bad parent rejected' => static fn (): mixed => $message155(static fn () => $plan155(4, 1)),
    'empty image set rejected' => static function () use ($database155, $message155): mixed {
        $database = $database155();
        $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

        return $message155(static fn () => SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Plan::tableLeafFromDeleteResult(
            $database,
            3,
            ['page' => $deletedPage, 'rowid' => 2, 'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110]],
            4,
            3,
            [],
            true,
        ));
    },
    'bad image size rejected' => static function () use ($database155, $message155): mixed {
        $database = $database155();
        $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

        return $message155(static fn () => SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext155Plan::tableLeafFromDeleteResult(
            $database,
            3,
            ['page' => $deletedPage, 'rowid' => 2, 'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110]],
            4,
            3,
            ['short'],
            true,
        ));
    },
];

$expected155 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next155',
    'released overflow pages' => [106, 107, 108, 109, 110],
    'surviving vacuum free pages' => [106],
    'truncated vacuum pages' => [107, 108, 109, 110],
    'allocated btree pages' => [106],
    'reused vacuum free pages' => [106],
    'allocation step sources' => ['freelist-trunk'],
    'allocation step trunks' => [106],
    'allocation step freelist counts' => [0],
    'allocated pointer page numbers' => [106],
    'allocated pointer types' => ['btree-page'],
    'allocated pointer parents' => [3],
    'row page numbers' => [106],
    'row allocation sources' => ['freelist-trunk'],
    'row source pointer types' => ['first-overflow-page'],
    'row source pointer parents' => [3],
    'row vacuum pointer types' => ['free-page'],
    'row vacuum pointer parents' => [0],
    'row next pointer types' => ['btree-page'],
    'row next pointer parents' => [3],
    'row btree page type' => ['table-leaf'],
    'row btree cell count' => [2],
    'row btree freeblock status' => ['ok'],
    'row freelist count after' => [0],
    'final freelist pages' => [],
    'final freelist count' => 0,
    'final first freelist trunk' => 0,
    'final database page count' => 106,
    'new page rowids' => [40, 41],
    'new page freeblock status' => 'ok',
    'leaf delete freeblock survives next allocation' => 'ok',
    'summary updated pages' => [1, 3, 105, 106],
    'summary rows page numbers' => [106],
    'summary final freelist pages' => [],
    'wide vacuum leaves no reusable page' => 'SQLite freelist does not contain enough pages for this allocation',
    'bad parent rejected' => 'SQLite b-tree vacuum pointer-map freeblock next155 parent page must be null or at page 2 or later',
    'empty image set rejected' => 'SQLite b-tree vacuum pointer-map freeblock next155 requires a replacement b-tree page image',
    'bad image size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next155 page image length does not match page size',
];

$tests = [];

foreach ($cases155 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next155 ' . $name] = static function (TestRunner $t) use ($callback, $expected155, $name): void {
        $t->same($expected155[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree vacuum pointermap freeblock current source next155 invariant ' . $index] = static function (TestRunner $t) use ($plan155): void {
        $plan = $plan155();

        $t->same([106], $plan->allocatedBtreePages());
        $t->same([106], $plan->reusedVacuumFreePages());
        $t->same([], $plan->databaseAfterAllocation->freelistPageNumbers());
        $t->same('btree-page', $plan->databaseAfterAllocation->pointerMapEntryForPage(106)->typeName());
        $t->same(3, $plan->databaseAfterAllocation->pointerMapEntryForPage(106)->parentPageNumber);
        $t->same('ok', SQLiteBTreePageHeader::parsePage($plan->databaseAfterAllocation->page(106), 512)->freeblockIntegrityReport($plan->databaseAfterAllocation->page(106))['status']);
    };
}

return $tests;
