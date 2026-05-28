<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowAutoVacuumPointerMapPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount, bool $autoVacuum = true): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', $autoVacuum ? 3 : 0), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = $pageNumber < 105 ? 2 : 105;
    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$makeCurrentNextFreelistDatabase = static function () use ($makeFirstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 107, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage(107, 4, 4);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
    $pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107], 512);

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    foreach ([4, 5, 106, 107] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payloadForPages = static function (int $pageCount, string $seed): string {
    return str_repeat($seed, (($pageCount - 1) * 508) + 1);
};

$tests = [];

foreach ([
    'four pages across current and next trunk' => [4, 3, [5, 4, 107, 106], [2, 105]],
    'three pages consumes current trunk and next leaf' => [3, 9, [5, 4, 107], [2, 105]],
    'two pages stays on current trunk leaf and trunk' => [2, 11, [5, 4], [2]],
] as $name => [$pageCount, $parentPage, $expectedPages, $expectedPointerMapPages]) {
    $tests['btree overflow autovacuum current next ' . $name] = static function (TestRunner $t) use (
        $makeCurrentNextFreelistDatabase,
        $payloadForPages,
        $pageCount,
        $parentPage,
        $expectedPages,
        $expectedPointerMapPages,
    ): void {
        $database = $makeCurrentNextFreelistDatabase();
        $plan = SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain(
            $database,
            $parentPage,
            $payloadForPages($pageCount, 'w'),
        );
        $summary = $plan->toArray();

        $t->same($expectedPages, $summary['allocated_overflow_pages']);
        $t->same($expectedPointerMapPages, $summary['updated_pointer_map_page_numbers']);
        $t->same($pageCount, count($summary['chain_links']));
        $t->same(0, $summary['chain_links'][$pageCount - 1]['next_page']);
        $t->same($expectedPages, array_column($summary['pointer_map_entries'], 'page_number'));
        $t->same('first-overflow-page', $summary['pointer_map_entries'][0]['type_name']);
        $t->same($parentPage, $summary['pointer_map_entries'][0]['parent_page_number']);
        foreach (array_slice($summary['pointer_map_entries'], 1) as $index => $entry) {
            $t->same('overflow-page', $entry['type_name']);
            $t->same($expectedPages[$index], $entry['parent_page_number']);
        }
    };
}

foreach (range(1, 47) as $index) {
    $tests['btree overflow autovacuum current next chain invariant ' . $index] = static function (TestRunner $t) use (
        $makeCurrentNextFreelistDatabase,
        $payloadForPages,
        $index,
    ): void {
        $database = $makeCurrentNextFreelistDatabase();
        $parentPage = 3 + $index;
        $plan = SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain(
            $database,
            $parentPage,
            $payloadForPages(4, chr(65 + ($index % 26))),
        );
        $summary = $plan->toArray();

        $t->same('btree-overflow-autovacuum-pointermap-current-next', $summary['action']);
        $t->same([5, 4, 107, 106], $summary['allocated_overflow_pages']);
        $t->same([2, 105], $summary['updated_pointer_map_page_numbers']);
        $t->same([4, 4, 106, 106], array_column($summary['allocation_steps'], 'trunk_page'));
        $t->same(['freelist-leaf', 'freelist-trunk', 'freelist-leaf', 'freelist-trunk'], array_column($summary['allocation_steps'], 'source'));
        $t->same([5, 4, 107, 106], array_column($summary['chain_links'], 'current_page'));
        $t->same([4, 107, 106, 0], array_column($summary['chain_links'], 'next_page'));
        $t->same([$parentPage, 5, 4, 107], array_column($summary['pointer_map_entries'], 'parent_page_number'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($summary['pointer_map_entries'], 'type_name'));
        $t->same(0, $summary['freelist_page_count']);
    };
}

$tests['btree overflow autovacuum current next rejects non autovacuum'] = static function (TestRunner $t) use ($makeFirstPage): void {
    $pages = array_fill(1, 4, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage(4, 4, 1, false);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(null, [], 512);
    $database = SQLiteDatabase::fromBytes(implode('', $pages));

    $t->throws(InvalidArgumentException::class, static function () use ($database): void {
        SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain($database, 3, 'payload');
    });
};

$tests['btree overflow autovacuum current next rejects empty payload'] = static function (TestRunner $t) use ($makeCurrentNextFreelistDatabase): void {
    $database = $makeCurrentNextFreelistDatabase();

    $t->throws(InvalidArgumentException::class, static function () use ($database): void {
        SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain($database, 3, '');
    });
};

$tests['btree overflow autovacuum current next permits explicit append fallback'] = static function (TestRunner $t) use ($makeCurrentNextFreelistDatabase, $payloadForPages): void {
    $database = $makeCurrentNextFreelistDatabase();

    $plan = SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain($database, 3, $payloadForPages(5, 'z'), true);

    $t->same([5, 4, 107, 106, 108], $plan->allocationPlan->allocatedPageNumbers);
    $t->same([108], $plan->allocationPlan->appendedPageNumbers);
    $t->same([2, 105], $plan->updatedPointerMapPageNumbers());
    $t->same(108, $plan->database->pageCount());
    $t->same('overflow-page', $plan->database->pointerMapEntryForPage(108)->typeName());
};

return $tests;
