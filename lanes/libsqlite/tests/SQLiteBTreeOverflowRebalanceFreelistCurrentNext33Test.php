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
    $stride = intdiv($pageSize, 5) + 1;
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

$fixture = static function (int $count = 7, bool $allowAppend = true) use ($makeFirstPage, $putPointerMapEntry): array {
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
    $plan = $database->planOverflowPageAllocation($count, 11, $allowAppend);
    foreach ($plan->allocatedPageNumbers as $pageNumber) {
        if ($pageNumber > $pageCount) {
            $pages[$pageNumber] = str_repeat("\0", $pageSize);
        }
    }
    foreach ($plan->pageImages() as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    ksort($pages);
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

    return [$database, $plan, $postDatabase];
};

$tests = [];

$cases = [
    'allocated pages drain current next trunks and append past pointer map' => static fn (array $fx): mixed => $fx[1]->allocatedPageNumbers,
    'appended page skips pointer-map page' => static fn (array $fx): mixed => $fx[1]->appendedPageNumbers,
    'header database page count includes append' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->firstPage)->databaseSizePages,
    'first freelist trunk clears after draining both trunks' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->firstPage)->firstFreelistTrunkPage,
    'freelist count clears after draining both trunks' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->firstPage)->freelistPageCount,
    'plan database page count includes appended overflow page' => static fn (array $fx): mixed => $fx[1]->databasePageCount,
    'updated pointer map pages span current next and append stride' => static fn (array $fx): mixed => array_keys($fx[1]->updatedPointerMapPages),
    'page images include first header' => static fn (array $fx): mixed => array_key_exists(1, $fx[1]->pageImages()),
    'page images include pointer map page 2' => static fn (array $fx): mixed => array_key_exists(2, $fx[1]->pageImages()),
    'page images include pointer map page 105' => static fn (array $fx): mixed => array_key_exists(105, $fx[1]->pageImages()),
    'page images include appended pointer map page 208' => static fn (array $fx): mixed => array_key_exists(208, $fx[1]->pageImages()),
    'drained current trunk image is removed' => static fn (array $fx): mixed => array_key_exists(5, $fx[1]->pageImages()),
    'drained next trunk image is removed' => static fn (array $fx): mixed => array_key_exists(106, $fx[1]->pageImages()),
    'allocation step count includes append' => static fn (array $fx): mixed => count($fx[1]->allocationSteps()),
    'step 1 source' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['source'],
    'step 1 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['allocated_page'],
    'step 1 trunk' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['trunk_page'],
    'step 1 next trunk before' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['next_trunk_page_before'],
    'step 2 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['allocated_page'],
    'step 2 leaf count after' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['leaf_count_after'],
    'step 3 consumes current trunk' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[2]['source'],
    'step 3 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[2]['allocated_page'],
    'step 3 next trunk after' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[2]['next_trunk_page_after'],
    'step 4 switches to next trunk leaf' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[3]['source'],
    'step 4 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[3]['allocated_page'],
    'step 4 trunk' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[3]['trunk_page'],
    'step 5 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[4]['allocated_page'],
    'step 6 consumes next trunk' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[5]['source'],
    'step 6 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[5]['allocated_page'],
    'step 7 appends overflow page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[6]['source'],
    'step 7 allocated page' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[6]['allocated_page'],
    'pointer map entry count' => static fn (array $fx): mixed => count($fx[1]->allocatedPointerMapEntries()),
    'pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[1]->allocatedPointerMapEntries(), 'page_number'),
    'pointer map entry type chain' => static fn (array $fx): mixed => array_column($fx[1]->allocatedPointerMapEntries(), 'type_name'),
    'pointer map parent chain' => static fn (array $fx): mixed => array_column($fx[1]->allocatedPointerMapEntries(), 'parent_page_number'),
    'pointer map pages by entry' => static fn (array $fx): mixed => array_column($fx[1]->allocatedPointerMapEntries(), 'pointer_map_page'),
    'page 3 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[0]['offset'],
    'page 104 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[1]['offset'],
    'page 5 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[2]['offset'],
    'page 107 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[3]['offset'],
    'page 206 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[4]['offset'],
    'page 106 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[5]['offset'],
    'page 209 pointer-map offset' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[6]['offset'],
    'post page 3 is first overflow' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(3)->typeName(),
    'post page 104 is overflow' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(104)->typeName(),
    'post page 209 is overflow' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(209)->typeName(),
    'post page 3 parent is btree cell owner' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(3)->parentPageNumber,
    'post page 104 parent is previous overflow' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(104)->parentPageNumber,
    'post page 209 parent is previous overflow' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(209)->parentPageNumber,
    'toArray carries allocated pointer map entries' => static fn (array $fx): mixed => count($fx[1]->toArray()['allocated_pointer_map_entries']),
    'toArray first pointer map type' => static fn (array $fx): mixed => $fx[1]->toArray()['allocated_pointer_map_entries'][0]['type_name'],
    'toArray last pointer map parent' => static fn (array $fx): mixed => $fx[1]->toArray()['allocated_pointer_map_entries'][6]['parent_page_number'],
    'toArray updated pointer map page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'post database page count includes appended page' => static fn (array $fx): mixed => $fx[2]->pageCount(),
    'post database header count includes appended page' => static fn (array $fx): mixed => $fx[2]->header->databaseSizePages,
];

$expected = [
    'allocated pages drain current next trunks and append past pointer map' => [3, 104, 5, 107, 206, 106, 209],
    'appended page skips pointer-map page' => [209],
    'header database page count includes append' => 209,
    'first freelist trunk clears after draining both trunks' => 0,
    'freelist count clears after draining both trunks' => 0,
    'plan database page count includes appended overflow page' => 209,
    'updated pointer map pages span current next and append stride' => [2, 105, 208],
    'page images include first header' => true,
    'page images include pointer map page 2' => true,
    'page images include pointer map page 105' => true,
    'page images include appended pointer map page 208' => true,
    'drained current trunk image is removed' => false,
    'drained next trunk image is removed' => false,
    'allocation step count includes append' => 7,
    'step 1 source' => 'freelist-leaf',
    'step 1 allocated page' => 3,
    'step 1 trunk' => 5,
    'step 1 next trunk before' => 106,
    'step 2 allocated page' => 104,
    'step 2 leaf count after' => 0,
    'step 3 consumes current trunk' => 'freelist-trunk',
    'step 3 allocated page' => 5,
    'step 3 next trunk after' => 106,
    'step 4 switches to next trunk leaf' => 'freelist-leaf',
    'step 4 allocated page' => 107,
    'step 4 trunk' => 106,
    'step 5 allocated page' => 206,
    'step 6 consumes next trunk' => 'freelist-trunk',
    'step 6 allocated page' => 106,
    'step 7 appends overflow page' => 'append',
    'step 7 allocated page' => 209,
    'pointer map entry count' => 7,
    'pointer map entry pages' => [3, 104, 5, 107, 206, 106, 209],
    'pointer map entry type chain' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'pointer map parent chain' => [11, 3, 104, 5, 107, 206, 106],
    'pointer map pages by entry' => [2, 2, 2, 105, 105, 105, 208],
    'page 3 pointer-map offset' => 0,
    'page 104 pointer-map offset' => 505,
    'page 5 pointer-map offset' => 10,
    'page 107 pointer-map offset' => 5,
    'page 206 pointer-map offset' => 500,
    'page 106 pointer-map offset' => 0,
    'page 209 pointer-map offset' => 0,
    'post page 3 is first overflow' => 'first-overflow-page',
    'post page 104 is overflow' => 'overflow-page',
    'post page 209 is overflow' => 'overflow-page',
    'post page 3 parent is btree cell owner' => 11,
    'post page 104 parent is previous overflow' => 3,
    'post page 209 parent is previous overflow' => 106,
    'toArray carries allocated pointer map entries' => 7,
    'toArray first pointer map type' => 'first-overflow-page',
    'toArray last pointer map parent' => 106,
    'toArray updated pointer map page numbers' => [2, 105, 208],
    'post database page count includes appended page' => 209,
    'post database header count includes appended page' => 209,
];

foreach ($cases as $name => $case) {
    $tests['btree overflow rebalance freelist current next33 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

$tests['btree overflow rebalance freelist current next33 root parent guard'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture(1, true);

    try {
        $database->planOverflowPageAllocation(1, 1);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite overflow allocation parent b-tree page must be at page 2 or later', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected invalid overflow parent page rejection');
};

$tests['btree overflow rebalance freelist current next33 append guard'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture(1, true);

    try {
        $database->planOverflowPageAllocation(7, 11, false);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite freelist does not contain enough pages for this allocation', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected no-append overflow allocation rejection');
};

return $tests;
