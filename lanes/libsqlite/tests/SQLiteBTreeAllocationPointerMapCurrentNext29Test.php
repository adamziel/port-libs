<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
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
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$makeLeafPage = static function (int $pageSize, int $cellCount): string {
    $page = str_repeat("\0", $pageSize);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', $cellCount), 3, 2);
    $page = substr_replace($page, pack('n', $pageSize), 5, 2);
    $page[7] = "\x00";

    return $page;
};

$fixture = static function () use ($makeFirstPage, $putPointerMapEntry, $makeLeafPage): array {
    $pageSize = 512;
    $pages = array_fill(1, 8, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, 8, 4, 4);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(null, [6, 7, 8], $pageSize);
    $pages[3] = $makeLeafPage($pageSize, 0);
    $pages[5] = $makeLeafPage($pageSize, 0);
    foreach ([4, 6, 7, 8] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0, $pageSize);
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = $database->planBtreePageAllocation(2, 3, false);
    $post = $database->applyPageAllocationPlan($plan, [
        6 => $makeLeafPage($pageSize, 0),
        8 => $makeLeafPage($pageSize, 0),
    ]);

    return [$database, $plan, $post];
};

$cases = [
    'allocated first page comes from current freelist leaf slot' => static fn (array $fx): mixed => $fx[1]->allocatedPageNumbers[0],
    'allocated second page comes from next freelist leaf slot after slot swap' => static fn (array $fx): mixed => $fx[1]->allocatedPageNumbers[1],
    'no appended pages when freelist satisfies allocation' => static fn (array $fx): mixed => $fx[1]->appendedPageNumbers,
    'database page count remains stable after freelist allocation' => static fn (array $fx): mixed => $fx[1]->databasePageCount,
    'first freelist trunk remains current trunk' => static fn (array $fx): mixed => $fx[1]->firstFreelistTrunkPage,
    'freelist count decrements by two' => static fn (array $fx): mixed => $fx[1]->freelistPageCount,
    'updated freelist page is the current trunk' => static fn (array $fx): mixed => array_keys($fx[1]->updatedFreelistPages),
    'updated pointer map page is current pointer-map page' => static fn (array $fx): mixed => array_keys($fx[1]->updatedPointerMapPages),
    'allocation step one source is freelist leaf' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['source'],
    'allocation step two source is freelist leaf' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['source'],
    'allocation step one trunk is current trunk' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['trunk_page'],
    'allocation step two trunk is current trunk' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['trunk_page'],
    'allocation step one leaf count before' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['leaf_count_before'],
    'allocation step one leaf count after' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['leaf_count_after'],
    'allocation step two leaf count before reflects current swapped slot' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['leaf_count_before'],
    'allocation step two leaf count after leaves one free leaf' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['leaf_count_after'],
    'allocation step one count after' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[0]['freelist_page_count_after'],
    'allocation step two count after' => static fn (array $fx): mixed => $fx[1]->allocationSteps()[1]['freelist_page_count_after'],
    'post header keeps database page count' => static fn (array $fx): mixed => $fx[2]->header->databaseSizePages,
    'post header keeps current freelist trunk' => static fn (array $fx): mixed => $fx[2]->header->firstFreelistTrunkPage,
    'post header keeps next freelist count' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'post freelist page numbers expose remaining trunk and leaf' => static fn (array $fx): mixed => $fx[2]->freelistPageNumbers(),
    'post freelist allocation order uses remaining leaf then trunk' => static fn (array $fx): mixed => $fx[2]->freelistAllocationOrder(),
    'post trunk next pointer stays empty' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[0]->nextTrunkPage,
    'post trunk leaf list keeps final unallocated page' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[0]->leafPageNumbers,
    'allocated pointer-map entry count' => static fn (array $fx): mixed => count($fx[1]->allocatedPointerMapEntries()),
    'first allocated pointer-map page number' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[0]['page_number'],
    'second allocated pointer-map page number' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[1]['page_number'],
    'first allocated pointer-map type is btree' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[0]['type_name'],
    'second allocated pointer-map type is btree' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[1]['type_name'],
    'first allocated pointer-map parent is requested parent' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[0]['parent_page_number'],
    'second allocated pointer-map parent is requested parent' => static fn (array $fx): mixed => $fx[1]->allocatedPointerMapEntries()[1]['parent_page_number'],
    'post pointer-map page six type is btree' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(6)->typeName(),
    'post pointer-map page eight type is btree' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->typeName(),
    'post pointer-map remaining freelist leaf stays free' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(7)->typeName(),
    'post pointer-map trunk stays free' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(4)->typeName(),
    'post pointer-map page six parent is parent page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(6)->parentPageNumber,
    'post pointer-map page eight parent is parent page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->parentPageNumber,
    'post pointer-map remaining leaf parent is zero' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(7)->parentPageNumber,
    'post allocated page six materializes supplied btree leaf' => static fn (array $fx): mixed => ord($fx[2]->page(6)[0]),
    'post allocated page eight materializes supplied btree leaf' => static fn (array $fx): mixed => ord($fx[2]->page(8)[0]),
    'post allocated page six cell count is supplied' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->page(6), 512)->cellCount,
    'post allocated page eight cell count is supplied' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->page(8), 512)->cellCount,
    'toArray allocated pages' => static fn (array $fx): mixed => $fx[1]->toArray()['allocated_page_numbers'],
    'toArray appended pages empty' => static fn (array $fx): mixed => $fx[1]->toArray()['appended_page_numbers'],
    'toArray updated freelist page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_freelist_page_numbers'],
    'toArray updated pointer-map page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'toArray allocated pointer-map entry count' => static fn (array $fx): mixed => count($fx[1]->toArray()['allocated_pointer_map_entries']),
    'original page six pointer-map was free' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(6)->typeName(),
    'original page eight pointer-map was free' => static fn (array $fx): mixed => $fx[0]->pointerMapEntryForPage(8)->typeName(),
    'original freelist allocation order included current next sequence' => static fn (array $fx): mixed => $fx[0]->freelistAllocationOrder(),
    'post integrity check reports ok after allocation apply' => static fn (array $fx): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $fx[2])['rows'],
];

$expected = [
    'allocated first page comes from current freelist leaf slot' => 6,
    'allocated second page comes from next freelist leaf slot after slot swap' => 8,
    'no appended pages when freelist satisfies allocation' => [],
    'database page count remains stable after freelist allocation' => 8,
    'first freelist trunk remains current trunk' => 4,
    'freelist count decrements by two' => 2,
    'updated freelist page is the current trunk' => [4],
    'updated pointer map page is current pointer-map page' => [2],
    'allocation step one source is freelist leaf' => 'freelist-leaf',
    'allocation step two source is freelist leaf' => 'freelist-leaf',
    'allocation step one trunk is current trunk' => 4,
    'allocation step two trunk is current trunk' => 4,
    'allocation step one leaf count before' => 3,
    'allocation step one leaf count after' => 2,
    'allocation step two leaf count before reflects current swapped slot' => 2,
    'allocation step two leaf count after leaves one free leaf' => 1,
    'allocation step one count after' => 3,
    'allocation step two count after' => 2,
    'post header keeps database page count' => 8,
    'post header keeps current freelist trunk' => 4,
    'post header keeps next freelist count' => 2,
    'post freelist page numbers expose remaining trunk and leaf' => [4, 7],
    'post freelist allocation order uses remaining leaf then trunk' => [7, 4],
    'post trunk next pointer stays empty' => null,
    'post trunk leaf list keeps final unallocated page' => [7],
    'allocated pointer-map entry count' => 2,
    'first allocated pointer-map page number' => 6,
    'second allocated pointer-map page number' => 8,
    'first allocated pointer-map type is btree' => 'btree-page',
    'second allocated pointer-map type is btree' => 'btree-page',
    'first allocated pointer-map parent is requested parent' => 3,
    'second allocated pointer-map parent is requested parent' => 3,
    'post pointer-map page six type is btree' => 'btree-page',
    'post pointer-map page eight type is btree' => 'btree-page',
    'post pointer-map remaining freelist leaf stays free' => 'free-page',
    'post pointer-map trunk stays free' => 'free-page',
    'post pointer-map page six parent is parent page' => 3,
    'post pointer-map page eight parent is parent page' => 3,
    'post pointer-map remaining leaf parent is zero' => 0,
    'post allocated page six materializes supplied btree leaf' => 13,
    'post allocated page eight materializes supplied btree leaf' => 13,
    'post allocated page six cell count is supplied' => 0,
    'post allocated page eight cell count is supplied' => 0,
    'toArray allocated pages' => [6, 8],
    'toArray appended pages empty' => [],
    'toArray updated freelist page numbers' => [4],
    'toArray updated pointer-map page numbers' => [2],
    'toArray allocated pointer-map entry count' => 2,
    'original page six pointer-map was free' => 'free-page',
    'original page eight pointer-map was free' => 'free-page',
    'original freelist allocation order included current next sequence' => [6, 8, 7, 4],
    'post integrity check reports ok after allocation apply' => [['integrity_check' => 'ok']],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree allocation pointer-map current next29 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree allocation pointer-map current next29 rejects foreign allocated page image'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDatabase::fromBytes($plan->firstPage . str_repeat("\0", 512 * 7))->applyPageAllocationPlan($plan, [5 => str_repeat("\0", 512)]));
};

$tests['btree allocation pointer-map current next29 rejects short allocated page image'] = static function (TestRunner $t) use ($fixture): void {
    [$database, $plan] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => $database->applyPageAllocationPlan($plan, [6 => str_repeat("\0", 511)]));
};

return $tests;
