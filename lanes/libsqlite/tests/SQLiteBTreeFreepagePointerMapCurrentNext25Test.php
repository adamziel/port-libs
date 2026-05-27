<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunk, int $freelistCount): string {
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
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize): void {
    $usableSize = $pageSize;
    $stride = intdiv($usableSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$fixture = static function (?int $parentPageNumber = 11, int $allocationCount = 6, bool $allowAppend = false) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 207;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 5, 6);
    $pages[5] = SQLiteFreelistTrunkPage::assemble(106, [3, 104], $pageSize);
    $pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107, 206], $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::FREE_PAGE, 0], 11 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 106 => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ([3, 104, 107, 206] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = $database->planBtreePageAllocation($allocationCount, $parentPageNumber, $allowAppend);
    foreach ($plan->pageImages() as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

    return [$database, $plan, $postDatabase];
};

$appendFixture = static fn (): array => $fixture(11, 7, true);
$rootFixture = static fn (): array => $fixture(null, 2, false);

$tests = [];

$cases = [
    'current next25 allocated pages drain current and next freelist trunks' => static fn (): mixed => $fixture()[1]->allocatedPageNumbers,
    'current next25 no append when freelist satisfies allocation' => static fn (): mixed => $fixture()[1]->appendedPageNumbers,
    'current next25 first page clears freelist trunk' => static fn (): mixed => SQLiteHeader::parse($fixture()[1]->firstPage)->firstFreelistTrunkPage,
    'current next25 first page clears freelist count' => static fn (): mixed => SQLiteHeader::parse($fixture()[1]->firstPage)->freelistPageCount,
    'current next25 keeps database page count stable' => static fn (): mixed => $fixture()[1]->databasePageCount,
    'current next25 updated pointer map pages span current and next trunks' => static fn (): mixed => array_keys($fixture()[1]->updatedPointerMapPages),
    'current next25 page images include pointer map page 2' => static fn (): mixed => array_key_exists(2, $fixture()[1]->pageImages()),
    'current next25 page images include pointer map page 105' => static fn (): mixed => array_key_exists(105, $fixture()[1]->pageImages()),
    'current next25 page images omit drained current trunk image' => static fn (): mixed => array_key_exists(5, $fixture()[1]->pageImages()),
    'current next25 page images omit drained next trunk image' => static fn (): mixed => array_key_exists(106, $fixture()[1]->pageImages()),
    'current next25 allocation step count' => static fn (): mixed => count($fixture()[1]->allocationSteps()),
    'current next25 step 1 source' => static fn (): mixed => $fixture()[1]->allocationSteps()[0]['source'],
    'current next25 step 1 page' => static fn (): mixed => $fixture()[1]->allocationSteps()[0]['allocated_page'],
    'current next25 step 1 trunk' => static fn (): mixed => $fixture()[1]->allocationSteps()[0]['trunk_page'],
    'current next25 step 1 next trunk before' => static fn (): mixed => $fixture()[1]->allocationSteps()[0]['next_trunk_page_before'],
    'current next25 step 1 leaf count before' => static fn (): mixed => $fixture()[1]->allocationSteps()[0]['leaf_count_before'],
    'current next25 step 1 freelist count after' => static fn (): mixed => $fixture()[1]->allocationSteps()[0]['freelist_page_count_after'],
    'current next25 step 2 page' => static fn (): mixed => $fixture()[1]->allocationSteps()[1]['allocated_page'],
    'current next25 step 2 leaf count after' => static fn (): mixed => $fixture()[1]->allocationSteps()[1]['leaf_count_after'],
    'current next25 step 3 consumes current trunk' => static fn (): mixed => $fixture()[1]->allocationSteps()[2]['source'],
    'current next25 step 3 page' => static fn (): mixed => $fixture()[1]->allocationSteps()[2]['allocated_page'],
    'current next25 step 3 next trunk after' => static fn (): mixed => $fixture()[1]->allocationSteps()[2]['next_trunk_page_after'],
    'current next25 step 4 switches to next trunk leaf' => static fn (): mixed => $fixture()[1]->allocationSteps()[3]['source'],
    'current next25 step 4 page' => static fn (): mixed => $fixture()[1]->allocationSteps()[3]['allocated_page'],
    'current next25 step 4 trunk' => static fn (): mixed => $fixture()[1]->allocationSteps()[3]['trunk_page'],
    'current next25 step 4 has no further trunk' => static fn (): mixed => $fixture()[1]->allocationSteps()[3]['next_trunk_page_before'],
    'current next25 step 5 page' => static fn (): mixed => $fixture()[1]->allocationSteps()[4]['allocated_page'],
    'current next25 step 5 leaf count after' => static fn (): mixed => $fixture()[1]->allocationSteps()[4]['leaf_count_after'],
    'current next25 step 6 consumes next trunk' => static fn (): mixed => $fixture()[1]->allocationSteps()[5]['source'],
    'current next25 step 6 page' => static fn (): mixed => $fixture()[1]->allocationSteps()[5]['allocated_page'],
    'current next25 step 6 freelist count after' => static fn (): mixed => $fixture()[1]->allocationSteps()[5]['freelist_page_count_after'],
    'current next25 entry count' => static fn (): mixed => count($fixture()[1]->allocatedPointerMapEntries()),
    'current next25 entry pages' => static fn (): mixed => array_column($fixture()[1]->allocatedPointerMapEntries(), 'page_number'),
    'current next25 entry type names' => static fn (): mixed => array_values(array_unique(array_column($fixture()[1]->allocatedPointerMapEntries(), 'type_name'))),
    'current next25 entry parents' => static fn (): mixed => array_values(array_unique(array_column($fixture()[1]->allocatedPointerMapEntries(), 'parent_page_number'))),
    'current next25 entry pointer map pages' => static fn (): mixed => array_values(array_unique(array_column($fixture()[1]->allocatedPointerMapEntries(), 'pointer_map_page'))),
    'current next25 page 3 offset' => static fn (): mixed => $fixture()[1]->allocatedPointerMapEntries()[0]['offset'],
    'current next25 page 104 offset' => static fn (): mixed => $fixture()[1]->allocatedPointerMapEntries()[1]['offset'],
    'current next25 page 5 offset' => static fn (): mixed => $fixture()[1]->allocatedPointerMapEntries()[2]['offset'],
    'current next25 page 107 offset' => static fn (): mixed => $fixture()[1]->allocatedPointerMapEntries()[3]['offset'],
    'current next25 page 206 offset' => static fn (): mixed => $fixture()[1]->allocatedPointerMapEntries()[4]['offset'],
    'current next25 page 106 offset' => static fn (): mixed => $fixture()[1]->allocatedPointerMapEntries()[5]['offset'],
    'current next25 post page 3 type' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(3)->typeName(),
    'current next25 post page 104 type' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(104)->typeName(),
    'current next25 post page 5 parent' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(5)->parentPageNumber,
    'current next25 post page 106 parent' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(106)->parentPageNumber,
    'current next25 post page 107 pointer map page' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(107)->pointerMapPageNumber,
    'current next25 post page 206 pointer map page' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(206)->pointerMapPageNumber,
    'current next25 toArray carries allocated pointer map entries' => static fn (): mixed => count($fixture()[1]->toArray()['allocated_pointer_map_entries']),
    'current next25 toArray first allocated pointer map page' => static fn (): mixed => $fixture()[1]->toArray()['allocated_pointer_map_entries'][0]['pointer_map_page'],
    'current next25 toArray last allocated pointer map page' => static fn (): mixed => $fixture()[1]->toArray()['allocated_pointer_map_entries'][5]['pointer_map_page'],
    'current next25 root allocation type is root page' => static fn (): mixed => $rootFixture()[1]->allocatedPointerMapEntries()[0]['type_name'],
    'current next25 root allocation parent is zero' => static fn (): mixed => $rootFixture()[1]->allocatedPointerMapEntries()[0]['parent_page_number'],
    'current next25 append keeps appended page in pointer map entries' => static fn (): mixed => $appendFixture()[1]->allocatedPointerMapEntries()[6]['page_number'],
    'current next25 append pointer map entry type' => static fn (): mixed => $appendFixture()[1]->allocatedPointerMapEntries()[6]['type_name'],
    'current next25 append updates page count' => static fn (): mixed => $appendFixture()[1]->databasePageCount,
    'current next25 append records appended page' => static fn (): mixed => $appendFixture()[1]->appendedPageNumbers,
    'current next25 append toArray includes updated pointer maps' => static fn (): mixed => $appendFixture()[1]->toArray()['updated_pointer_map_page_numbers'],
    'current next25 rejects invalid parent page' => static fn (): mixed => (static function () use ($fixture): string {
        try {
            $fixture(1, 1, false);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return 'no exception';
    })(),
    'current next25 rejects allocation beyond freelist when append disabled' => static fn (): mixed => (static function () use ($fixture): string {
        try {
            $fixture(11, 7, false);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return 'no exception';
    })(),
];

$expected = [
    'current next25 allocated pages drain current and next freelist trunks' => [3, 104, 5, 107, 206, 106],
    'current next25 no append when freelist satisfies allocation' => [],
    'current next25 first page clears freelist trunk' => 0,
    'current next25 first page clears freelist count' => 0,
    'current next25 keeps database page count stable' => 207,
    'current next25 updated pointer map pages span current and next trunks' => [2, 105],
    'current next25 page images include pointer map page 2' => true,
    'current next25 page images include pointer map page 105' => true,
    'current next25 page images omit drained current trunk image' => false,
    'current next25 page images omit drained next trunk image' => false,
    'current next25 allocation step count' => 6,
    'current next25 step 1 source' => 'freelist-leaf',
    'current next25 step 1 page' => 3,
    'current next25 step 1 trunk' => 5,
    'current next25 step 1 next trunk before' => 106,
    'current next25 step 1 leaf count before' => 2,
    'current next25 step 1 freelist count after' => 5,
    'current next25 step 2 page' => 104,
    'current next25 step 2 leaf count after' => 0,
    'current next25 step 3 consumes current trunk' => 'freelist-trunk',
    'current next25 step 3 page' => 5,
    'current next25 step 3 next trunk after' => 106,
    'current next25 step 4 switches to next trunk leaf' => 'freelist-leaf',
    'current next25 step 4 page' => 107,
    'current next25 step 4 trunk' => 106,
    'current next25 step 4 has no further trunk' => null,
    'current next25 step 5 page' => 206,
    'current next25 step 5 leaf count after' => 0,
    'current next25 step 6 consumes next trunk' => 'freelist-trunk',
    'current next25 step 6 page' => 106,
    'current next25 step 6 freelist count after' => 0,
    'current next25 entry count' => 6,
    'current next25 entry pages' => [3, 104, 5, 107, 206, 106],
    'current next25 entry type names' => ['btree-page'],
    'current next25 entry parents' => [11],
    'current next25 entry pointer map pages' => [2, 105],
    'current next25 page 3 offset' => 0,
    'current next25 page 104 offset' => 505,
    'current next25 page 5 offset' => 10,
    'current next25 page 107 offset' => 5,
    'current next25 page 206 offset' => 500,
    'current next25 page 106 offset' => 0,
    'current next25 post page 3 type' => 'btree-page',
    'current next25 post page 104 type' => 'btree-page',
    'current next25 post page 5 parent' => 11,
    'current next25 post page 106 parent' => 11,
    'current next25 post page 107 pointer map page' => 105,
    'current next25 post page 206 pointer map page' => 105,
    'current next25 toArray carries allocated pointer map entries' => 6,
    'current next25 toArray first allocated pointer map page' => 2,
    'current next25 toArray last allocated pointer map page' => 105,
    'current next25 root allocation type is root page' => 'root-page',
    'current next25 root allocation parent is zero' => 0,
    'current next25 append keeps appended page in pointer map entries' => 209,
    'current next25 append pointer map entry type' => 'btree-page',
    'current next25 append updates page count' => 209,
    'current next25 append records appended page' => [209],
    'current next25 append toArray includes updated pointer maps' => [2, 105, 208],
    'current next25 rejects invalid parent page' => 'SQLite b-tree allocation parent page must be null or at page 2 or later',
    'current next25 rejects allocation beyond freelist when append disabled' => 'SQLite freelist does not contain enough pages for this allocation',
];

foreach ($cases as $name => $case) {
    $tests[$name] = static function (TestRunner $t) use ($case, $expected, $name): void {
        $t->same($expected[$name], $case());
    };
}

return $tests;
