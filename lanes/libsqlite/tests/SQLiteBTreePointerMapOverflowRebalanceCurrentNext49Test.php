<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockFreelistRebalancePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$fixture = static function (bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 207;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 5, 2);
    $pages[5] = SQLiteFreelistTrunkPage::assemble(null, [206], $pageSize);

    $deletedPayload = SQLiteRecord::encode([
        null,
        '_transient_timeout_current_next49',
        str_repeat('obsolete-overflow-pointer-map:', 80),
        'no',
    ]);
    $localLength = SQLiteTableLeafCell::localPayloadLength(strlen($deletedPayload), $pageSize);
    $deletedCell = SQLiteTableLeafCell::encode(10, $deletedPayload, $pageSize, 8);
    $keepCell = SQLiteTableLeafCell::encode(
        20,
        SQLiteRecord::encode([null, 'autoload_current_next49', 'yes', 'yes']),
        $pageSize,
    );
    $pages[3] = SQLiteTableLeafPage::assemble([$deletedCell, $keepCell], $pageSize);
    foreach (SQLiteOverflowPage::encodeChainAtPages(substr($deletedPayload, $localLength), [8, 104, 107, 205], $pageSize) as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::FREE_PAGE, 0], 206 => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3, $pageSize);
    $putPointerMapEntry($pages, 104, SQLitePointerMapEntry::OVERFLOW_PAGE, 8, $pageSize);
    $putPointerMapEntry($pages, 107, SQLitePointerMapEntry::OVERFLOW_PAGE, 104, $pageSize);
    $putPointerMapEntry($pages, 205, SQLitePointerMapEntry::OVERFLOW_PAGE, 107, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $deleteResult = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
        $database->page(3),
        10,
        static fn (int $firstOverflowPage, int $byteCount): array => $database->overflowPageChainNumbers($firstOverflowPage, $byteCount),
        $pageSize,
        0,
        null,
        $secureDelete,
    );
    $plan = SQLiteBTreeFreeblockFreelistRebalancePlan::tableLeafFromDeleteResult(
        $database,
        3,
        $deleteResult,
        $secureDelete,
    );

    foreach ($plan->pageImages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    ksort($pages);
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
    $postLeafHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);

    return [$database, $deleteResult, $plan, $postDatabase, $postLeafHeader];
};

$cases = [
    'plan class' => static fn (array $fx): mixed => get_class($fx[2]),
    'summary action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'leaf page number' => static fn (array $fx): mixed => $fx[2]->leafPageNumber,
    'leaf page type' => static fn (array $fx): mixed => $fx[2]->leafPageType,
    'deleted rowids' => static fn (array $fx): mixed => $fx[2]->deletedRowIds,
    'deleted record values are empty for table leaf' => static fn (array $fx): mixed => $fx[2]->deletedRecordValues,
    'obsolete overflow pages' => static fn (array $fx): mixed => $fx[2]->obsoleteOverflowPageNumbers,
    'freed page numbers' => static fn (array $fx): mixed => $fx[2]->freePlan->freedPageNumbers,
    'freelist leaf page numbers' => static fn (array $fx): mixed => $fx[2]->freePlan->leafPageNumbers,
    'new trunk page numbers remain empty' => static fn (array $fx): mixed => $fx[2]->freePlan->newTrunkPageNumbers,
    'first freelist trunk remains existing trunk' => static fn (array $fx): mixed => $fx[2]->freePlan->firstFreelistTrunkPage,
    'freelist count includes existing plus released overflow pages' => static fn (array $fx): mixed => $fx[2]->freePlan->freelistPageCount,
    'database page count remains stable' => static fn (array $fx): mixed => $fx[2]->freePlan->databasePageCount,
    'updated freelist pages' => static fn (array $fx): mixed => array_keys($fx[2]->freePlan->updatedFreelistPages),
    'updated pointer map pages span current and next map pages' => static fn (array $fx): mixed => array_keys($fx[2]->freePlan->updatedPointerMapPages),
    'page images include first page' => static fn (array $fx): mixed => array_key_exists(1, $fx[2]->pageImages),
    'page images include pointer map page 2' => static fn (array $fx): mixed => array_key_exists(2, $fx[2]->pageImages),
    'page images include pointer map page 105' => static fn (array $fx): mixed => array_key_exists(105, $fx[2]->pageImages),
    'page images include leaf page' => static fn (array $fx): mixed => array_key_exists(3, $fx[2]->pageImages),
    'page images include freelist trunk' => static fn (array $fx): mixed => array_key_exists(5, $fx[2]->pageImages),
    'secure delete clears released overflow pages' => static fn (array $fx): mixed => $fx[2]->freePlan->clearedPageNumbers,
    'cleared page image count' => static fn (array $fx): mixed => count($fx[2]->freePlan->clearedPageImages),
    'freed pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[2]->freePlan->freedPointerMapEntries, 'page_number'),
    'freed pointer map entry type names' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[2]->freePlan->freedPointerMapEntries, 'type_name'))),
    'freed pointer map entry parents' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[2]->freePlan->freedPointerMapEntries, 'parent_page_number'))),
    'freed pointer map entry pointer map pages' => static fn (array $fx): mixed => array_column($fx[2]->freePlan->freedPointerMapEntries, 'pointer_map_page'),
    'freed pointer map entry offsets' => static fn (array $fx): mixed => array_column($fx[2]->freePlan->freedPointerMapEntries, 'offset'),
    'summary freed pointer map pages' => static fn (array $fx): mixed => array_column($fx[2]->toArray()['freed_pointer_map_entries'], 'page_number'),
    'summary freed pointer map types' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[2]->toArray()['freed_pointer_map_entries'], 'type_name'))),
    'summary updated pointer map pages' => static fn (array $fx): mixed => $fx[2]->toArray()['updated_pointer_map_page_numbers'],
    'summary updated page numbers' => static fn (array $fx): mixed => $fx[2]->toArray()['updated_page_numbers'],
    'free plan summary carries freed pointer map entries' => static fn (array $fx): mixed => count($fx[2]->freePlan->toArray()['freed_pointer_map_entries']),
    'free plan summary first freed pointer map page' => static fn (array $fx): mixed => $fx[2]->freePlan->toArray()['freed_pointer_map_entries'][0]['pointer_map_page'],
    'free plan summary last freed pointer map page' => static fn (array $fx): mixed => $fx[2]->freePlan->toArray()['freed_pointer_map_entries'][2]['pointer_map_page'],
    'post page 8 pointer map type' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(8)->typeName(),
    'post page 104 pointer map type' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(104)->typeName(),
    'post page 107 pointer map type' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(107)->typeName(),
    'post page 8 pointer map parent' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(8)->parentPageNumber,
    'post page 104 pointer map parent' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(104)->parentPageNumber,
    'post page 107 pointer map parent' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(107)->parentPageNumber,
    'post page 8 pointer map page' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(8)->pointerMapPageNumber,
    'post page 104 pointer map page' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(104)->pointerMapPageNumber,
    'post page 107 pointer map page' => static fn (array $fx): mixed => $fx[3]->pointerMapEntryForPage(107)->pointerMapPageNumber,
    'post header first freelist trunk' => static fn (array $fx): mixed => $fx[3]->header->firstFreelistTrunkPage,
    'post header freelist count' => static fn (array $fx): mixed => $fx[3]->header->freelistPageCount,
    'post freelist pages' => static fn (array $fx): mixed => $fx[3]->freelistPageNumbers(),
    'post freelist allocation order' => static fn (array $fx): mixed => $fx[3]->freelistAllocationOrder(),
    'post leaf retains one cell' => static fn (array $fx): mixed => $fx[4]->cellCount,
    'post leaf has reusable freeblock' => static fn (array $fx): mixed => count($fx[4]->freeblocks($fx[3]->page(3))),
    'post leaf freeblock bytes are reported' => static fn (array $fx): mixed => $fx[2]->leafFreeblockBytes > 0,
    'post leaf free space is reported' => static fn (array $fx): mixed => $fx[2]->leafFreeSpaceBytes > 0,
    'header from plan first page first trunk' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[2]->pageImages[1])->firstFreelistTrunkPage,
    'header from plan first page freelist count' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[2]->pageImages[1])->freelistPageCount,
    'delete result rowid' => static fn (array $fx): mixed => $fx[1]['rowid'],
    'delete result obsolete overflow pages' => static fn (array $fx): mixed => $fx[1]['obsolete_overflow_page_numbers'],
    'delete result page has one cell after delete' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]['page'], 512)->cellCount,
];

$expected = [
    'plan class' => SQLiteBTreeFreeblockFreelistRebalancePlan::class,
    'summary action' => 'btree-freeblock-freelist-rebalance',
    'leaf page number' => 3,
    'leaf page type' => 'table-leaf',
    'deleted rowids' => [10],
    'deleted record values are empty for table leaf' => [],
    'obsolete overflow pages' => [8, 104, 107, 205],
    'freed page numbers' => [8, 104, 107, 205],
    'freelist leaf page numbers' => [8, 104, 107, 205],
    'new trunk page numbers remain empty' => [],
    'first freelist trunk remains existing trunk' => 5,
    'freelist count includes existing plus released overflow pages' => 6,
    'database page count remains stable' => 207,
    'updated freelist pages' => [5],
    'updated pointer map pages span current and next map pages' => [2, 105],
    'page images include first page' => true,
    'page images include pointer map page 2' => true,
    'page images include pointer map page 105' => true,
    'page images include leaf page' => true,
    'page images include freelist trunk' => true,
    'secure delete clears released overflow pages' => [8, 104, 107, 205],
    'cleared page image count' => 4,
    'freed pointer map entry pages' => [8, 104, 107, 205],
    'freed pointer map entry type names' => ['free-page'],
    'freed pointer map entry parents' => [0],
    'freed pointer map entry pointer map pages' => [2, 2, 105, 105],
    'freed pointer map entry offsets' => [25, 505, 5, 495],
    'summary freed pointer map pages' => [8, 104, 107, 205],
    'summary freed pointer map types' => ['free-page'],
    'summary updated pointer map pages' => [2, 105],
    'summary updated page numbers' => [1, 2, 3, 5, 8, 104, 105, 107, 205],
    'free plan summary carries freed pointer map entries' => 4,
    'free plan summary first freed pointer map page' => 2,
    'free plan summary last freed pointer map page' => 105,
    'post page 8 pointer map type' => 'free-page',
    'post page 104 pointer map type' => 'free-page',
    'post page 107 pointer map type' => 'free-page',
    'post page 8 pointer map parent' => 0,
    'post page 104 pointer map parent' => 0,
    'post page 107 pointer map parent' => 0,
    'post page 8 pointer map page' => 2,
    'post page 104 pointer map page' => 2,
    'post page 107 pointer map page' => 105,
    'post header first freelist trunk' => 5,
    'post header freelist count' => 6,
    'post freelist pages' => [5, 206, 8, 104, 107, 205],
    'post freelist allocation order' => [206, 205, 107, 104, 8, 5],
    'post leaf retains one cell' => 1,
    'post leaf has reusable freeblock' => 1,
    'post leaf freeblock bytes are reported' => true,
    'post leaf free space is reported' => true,
    'header from plan first page first trunk' => 5,
    'header from plan first page freelist count' => 6,
    'delete result rowid' => 10,
    'delete result obsolete overflow pages' => [8, 104, 107, 205],
    'delete result page has one cell after delete' => 1,
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree pointer-map overflow rebalance current next49 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

for ($index = 0; $index < 12; $index++) {
    $tests['btree pointer-map overflow rebalance current next49 generated pointer-map free entry ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($fixture, $index): void {
        $entries = $fixture()[2]->freePlan->freedPointerMapEntries;
        $entry = $entries[$index % 4];

        $t->same('free-page', $entry['type_name']);
        $t->same(0, $entry['parent_page_number']);
        $t->same([8, 104, 107, 205][$index % 4], $entry['page_number']);
    };
}

$tests['btree pointer-map overflow rebalance current next49 secure-delete can be disabled'] = static function (TestRunner $t) use ($fixture): void {
    $plan = $fixture(false)[2];

    $t->same([], $plan->freePlan->clearedPageNumbers);
    $t->same([], $plan->freePlan->clearedPageImages);
    $t->same([8, 104, 107, 205], array_column($plan->freePlan->freedPointerMapEntries, 'page_number'));
};

return $tests;
