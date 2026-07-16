<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$tests = [];

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 1): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeDatabase = static function () use ($makeFirstPage): SQLiteDatabase {
    $pageSize = 512;
    $emptyPage = str_repeat("\0", $pageSize);
    $firstPage = $makeFirstPage($pageSize, 9);
    $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
    $firstPage = substr_replace($firstPage, pack('N', 5), 36, 4);

    return SQLiteDatabase::fromBytes(
        $firstPage
        . $emptyPage
        . $emptyPage
        . $emptyPage
        . SQLiteFreelistTrunkPage::assemble(8, [3, 7], $pageSize)
        . $emptyPage
        . $emptyPage
        . SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize)
        . $emptyPage,
    );
};

$makeAutoVacuumDatabase = static function () use ($makeFirstPage): SQLiteDatabase {
    $pageSize = 512;
    $emptyPage = str_repeat("\0", $pageSize);
    $firstPage = $makeFirstPage($pageSize, 9);
    $firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
    $firstPage = substr_replace($firstPage, pack('N', 5), 36, 4);
    $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
    $pointerMap = str_repeat("\0", $pageSize);
    foreach ([3, 4, 5, 7, 8] as $pageNumber) {
        $pointerMap = substr_replace($pointerMap, chr(SQLitePointerMapEntry::FREE_PAGE) . pack('N', 0), 5 * ($pageNumber - 3), 5);
    }

    return SQLiteDatabase::fromBytes(
        $firstPage
        . $pointerMap
        . $emptyPage
        . $emptyPage
        . SQLiteFreelistTrunkPage::assemble(8, [3, 7], $pageSize)
        . $emptyPage
        . $emptyPage
        . SQLiteFreelistTrunkPage::assemble(null, [4], $pageSize)
        . $emptyPage,
    );
};

$plan = static fn () => $makeDatabase()->planPageAllocation(6, true);
$btreePlan = static fn () => $makeAutoVacuumDatabase()->planBtreePageAllocation(5, 11, false);
$btreePostDatabase = static function () use ($makeAutoVacuumDatabase, $btreePlan): SQLiteDatabase {
    $database = $makeAutoVacuumDatabase();
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[$pageNumber] = $database->page($pageNumber);
    }
    foreach ($btreePlan()->pageImages() as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$cases = [
    'current next allocation returns reusable leaves, current trunk, next leaf, next trunk, then append' => static fn (): mixed => $plan()->allocatedPageNumbers,
    'current next allocation appends only after freelist depletion' => static fn (): mixed => $plan()->appendedPageNumbers,
    'current next allocation records six provenance steps' => static fn (): mixed => count($plan()->allocationSteps()),
    'current next allocation first step source is current trunk leaf' => static fn (): mixed => $plan()->allocationSteps()[0]['source'],
    'current next allocation first step allocated page is first current leaf' => static fn (): mixed => $plan()->allocationSteps()[0]['allocated_page'],
    'current next allocation first step trunk page is current trunk' => static fn (): mixed => $plan()->allocationSteps()[0]['trunk_page'],
    'current next allocation first step preserves next trunk before' => static fn (): mixed => $plan()->allocationSteps()[0]['next_trunk_page_before'],
    'current next allocation first step preserves next trunk after' => static fn (): mixed => $plan()->allocationSteps()[0]['next_trunk_page_after'],
    'current next allocation first step leaf count before' => static fn (): mixed => $plan()->allocationSteps()[0]['leaf_count_before'],
    'current next allocation first step leaf count after' => static fn (): mixed => $plan()->allocationSteps()[0]['leaf_count_after'],
    'current next allocation first step freelist count after' => static fn (): mixed => $plan()->allocationSteps()[0]['freelist_page_count_after'],
    'current next allocation second step source remains current trunk leaf' => static fn (): mixed => $plan()->allocationSteps()[1]['source'],
    'current next allocation second step allocated page is remaining current leaf' => static fn (): mixed => $plan()->allocationSteps()[1]['allocated_page'],
    'current next allocation second step leaf count before' => static fn (): mixed => $plan()->allocationSteps()[1]['leaf_count_before'],
    'current next allocation second step leaf count after' => static fn (): mixed => $plan()->allocationSteps()[1]['leaf_count_after'],
    'current next allocation second step freelist count after' => static fn (): mixed => $plan()->allocationSteps()[1]['freelist_page_count_after'],
    'current next allocation third step consumes current trunk' => static fn (): mixed => $plan()->allocationSteps()[2]['source'],
    'current next allocation third step allocated page is current trunk' => static fn (): mixed => $plan()->allocationSteps()[2]['allocated_page'],
    'current next allocation third step next trunk before' => static fn (): mixed => $plan()->allocationSteps()[2]['next_trunk_page_before'],
    'current next allocation third step next trunk after' => static fn (): mixed => $plan()->allocationSteps()[2]['next_trunk_page_after'],
    'current next allocation third step freelist count after' => static fn (): mixed => $plan()->allocationSteps()[2]['freelist_page_count_after'],
    'current next allocation fourth step switches to next trunk leaf' => static fn (): mixed => $plan()->allocationSteps()[3]['source'],
    'current next allocation fourth step allocated page is next trunk leaf' => static fn (): mixed => $plan()->allocationSteps()[3]['allocated_page'],
    'current next allocation fourth step trunk page is next trunk' => static fn (): mixed => $plan()->allocationSteps()[3]['trunk_page'],
    'current next allocation fourth step has no further next trunk' => static fn (): mixed => $plan()->allocationSteps()[3]['next_trunk_page_before'],
    'current next allocation fourth step leaf count before' => static fn (): mixed => $plan()->allocationSteps()[3]['leaf_count_before'],
    'current next allocation fourth step leaf count after' => static fn (): mixed => $plan()->allocationSteps()[3]['leaf_count_after'],
    'current next allocation fifth step consumes next trunk' => static fn (): mixed => $plan()->allocationSteps()[4]['source'],
    'current next allocation fifth step allocated page is next trunk' => static fn (): mixed => $plan()->allocationSteps()[4]['allocated_page'],
    'current next allocation fifth step freelist count after' => static fn (): mixed => $plan()->allocationSteps()[4]['freelist_page_count_after'],
    'current next allocation sixth step appends after depletion' => static fn (): mixed => $plan()->allocationSteps()[5]['source'],
    'current next allocation sixth step appended page' => static fn (): mixed => $plan()->allocationSteps()[5]['allocated_page'],
    'current next allocation sixth step has no trunk page' => static fn (): mixed => $plan()->allocationSteps()[5]['trunk_page'],
    'current next allocation clears first trunk after depletion' => static fn (): mixed => $plan()->firstFreelistTrunkPage,
    'current next allocation clears freelist count after depletion' => static fn (): mixed => $plan()->freelistPageCount,
    'current next allocation updates database page count after append' => static fn (): mixed => $plan()->databasePageCount,
    'current next allocation first page header page count is updated' => static fn (): mixed => SQLiteHeader::parse($plan()->firstPage)->databaseSizePages,
    'current next allocation first page header clears freelist trunk' => static fn (): mixed => SQLiteHeader::parse($plan()->firstPage)->firstFreelistTrunkPage,
    'current next allocation first page header clears freelist count' => static fn (): mixed => SQLiteHeader::parse($plan()->firstPage)->freelistPageCount,
    'current next allocation has no remaining freelist page images' => static fn (): mixed => $plan()->updatedFreelistPages,
    'current next allocation page images only include header when depleted' => static fn (): mixed => array_keys($plan()->pageImages()),
    'current next btree allocation preserves allocation steps through pointer-map decoration' => static fn (): mixed => count($btreePlan()->allocationSteps()),
    'current next btree allocation updates pointer-map page only' => static fn (): mixed => array_keys($btreePlan()->updatedPointerMapPages),
    'current next btree allocation leaves no appended pages when append disabled and enough free pages exist' => static fn (): mixed => $btreePlan()->appendedPageNumbers,
    'current next btree allocation reports reusable pages only' => static fn (): mixed => $btreePlan()->allocatedPageNumbers,
    'current next btree allocation pointer-map marks allocated current leaf as btree page' => static fn (): mixed => $btreePostDatabase()->pointerMapEntryForPage(3)->typeName(),
    'current next btree allocation pointer-map marks allocated current trunk as btree page' => static fn (): mixed => $btreePostDatabase()->pointerMapEntryForPage(5)->typeName(),
    'current next btree allocation pointer-map marks allocated next trunk as btree page' => static fn (): mixed => $btreePostDatabase()->pointerMapEntryForPage(8)->typeName(),
    'current next btree allocation pointer-map parent is propagated to current leaf' => static fn (): mixed => $btreePostDatabase()->pointerMapEntryForPage(3)->parentPageNumber,
    'current next btree allocation pointer-map parent is propagated to next trunk' => static fn (): mixed => $btreePostDatabase()->pointerMapEntryForPage(8)->parentPageNumber,
    'current next btree allocation final first freelist trunk is zero' => static fn (): mixed => $btreePlan()->firstFreelistTrunkPage,
    'current next btree allocation final freelist count is zero' => static fn (): mixed => $btreePlan()->freelistPageCount,
];

$expected = [
    'current next allocation returns reusable leaves, current trunk, next leaf, next trunk, then append' => [3, 7, 5, 4, 8, 10],
    'current next allocation appends only after freelist depletion' => [10],
    'current next allocation records six provenance steps' => 6,
    'current next allocation first step source is current trunk leaf' => 'freelist-leaf',
    'current next allocation first step allocated page is first current leaf' => 3,
    'current next allocation first step trunk page is current trunk' => 5,
    'current next allocation first step preserves next trunk before' => 8,
    'current next allocation first step preserves next trunk after' => 8,
    'current next allocation first step leaf count before' => 2,
    'current next allocation first step leaf count after' => 1,
    'current next allocation first step freelist count after' => 4,
    'current next allocation second step source remains current trunk leaf' => 'freelist-leaf',
    'current next allocation second step allocated page is remaining current leaf' => 7,
    'current next allocation second step leaf count before' => 1,
    'current next allocation second step leaf count after' => 0,
    'current next allocation second step freelist count after' => 3,
    'current next allocation third step consumes current trunk' => 'freelist-trunk',
    'current next allocation third step allocated page is current trunk' => 5,
    'current next allocation third step next trunk before' => 8,
    'current next allocation third step next trunk after' => 8,
    'current next allocation third step freelist count after' => 2,
    'current next allocation fourth step switches to next trunk leaf' => 'freelist-leaf',
    'current next allocation fourth step allocated page is next trunk leaf' => 4,
    'current next allocation fourth step trunk page is next trunk' => 8,
    'current next allocation fourth step has no further next trunk' => null,
    'current next allocation fourth step leaf count before' => 1,
    'current next allocation fourth step leaf count after' => 0,
    'current next allocation fifth step consumes next trunk' => 'freelist-trunk',
    'current next allocation fifth step allocated page is next trunk' => 8,
    'current next allocation fifth step freelist count after' => 0,
    'current next allocation sixth step appends after depletion' => 'append',
    'current next allocation sixth step appended page' => 10,
    'current next allocation sixth step has no trunk page' => null,
    'current next allocation clears first trunk after depletion' => 0,
    'current next allocation clears freelist count after depletion' => 0,
    'current next allocation updates database page count after append' => 10,
    'current next allocation first page header page count is updated' => 10,
    'current next allocation first page header clears freelist trunk' => 0,
    'current next allocation first page header clears freelist count' => 0,
    'current next allocation has no remaining freelist page images' => [],
    'current next allocation page images only include header when depleted' => [1],
    'current next btree allocation preserves allocation steps through pointer-map decoration' => 5,
    'current next btree allocation updates pointer-map page only' => [2],
    'current next btree allocation leaves no appended pages when append disabled and enough free pages exist' => [],
    'current next btree allocation reports reusable pages only' => [3, 7, 5, 4, 8],
    'current next btree allocation pointer-map marks allocated current leaf as btree page' => 'btree-page',
    'current next btree allocation pointer-map marks allocated current trunk as btree page' => 'btree-page',
    'current next btree allocation pointer-map marks allocated next trunk as btree page' => 'btree-page',
    'current next btree allocation pointer-map parent is propagated to current leaf' => 11,
    'current next btree allocation pointer-map parent is propagated to next trunk' => 11,
    'current next btree allocation final first freelist trunk is zero' => 0,
    'current next btree allocation final freelist count is zero' => 0,
];

foreach ($cases as $name => $case) {
    $tests[$name] = static function (TestRunner $t) use ($case, $expected, $name): void {
        $t->same($expected[$name], $case());
    };
}

return $tests;
