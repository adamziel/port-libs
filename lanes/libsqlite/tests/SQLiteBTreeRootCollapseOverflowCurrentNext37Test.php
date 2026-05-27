<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeRootCollapsePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

$makeFirstPage = static function (int $pageSize, int $databaseSizePages): string {
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

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$fixture = static function () use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 22;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount);

    $payload = SQLiteRecord::encode(['_transient_timeout_plugin_payload', str_repeat('plugin-state:', 85), 91]);
    $encoded = SQLiteIndexCell::encodeWithOverflowPages($payload, 20, $pageSize, $pageSize, 5);
    $overflowPages = SQLiteOverflowPage::encodeChainAtPages(
        substr($payload, $encoded['localPayloadLength']),
        [20, 21, 22],
        $pageSize,
    );

    $pages[3] = SQLiteIndexInteriorPage::assemble([], 4, $pageSize);
    $pages[4] = SQLiteIndexInteriorPage::assemble([$encoded['cell']], 6, $pageSize);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0, $pageSize);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3, $pageSize);
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 4, $pageSize);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::BTREE_PAGE, 4, $pageSize);
    $putPointerMapEntry($pages, 20, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4, $pageSize);
    $putPointerMapEntry($pages, 21, SQLitePointerMapEntry::OVERFLOW_PAGE, 20, $pageSize);
    $putPointerMapEntry($pages, 22, SQLitePointerMapEntry::OVERFLOW_PAGE, 21, $pageSize);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeRootCollapsePlan::collapseOnlyChild($database, 3, true);
    foreach ($plan->pageImages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
    $rootHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);
    $rootCells = SQLiteIndexCell::parsePageCells(
        $postDatabase->page(3),
        $rootHeader,
        $pageSize,
        static fn (int $firstOverflowPage, int $byteCount): string => $postDatabase->readOverflowPayloadForBtreePlan($firstOverflowPage, $byteCount),
    );

    return [$database, $plan, $postDatabase, $rootHeader, $rootCells[0], $payload, $encoded];
};

$cases = [
    'plan class is root collapse' => static fn (array $fx): string => get_class($fx[1]),
    'summary action is root collapse apply' => static fn (array $fx): string => $fx[1]->toArray()['action'],
    'root page is preserved' => static fn (array $fx): int => $fx[1]->rootPageNumber,
    'obsolete child page is freed' => static fn (array $fx): int => $fx[1]->obsoleteChildPageNumber,
    'root starts as index interior' => static fn (array $fx): string => $fx[1]->rootBeforeType,
    'root remains index interior' => static fn (array $fx): string => $fx[1]->rootAfterType,
    'root starts empty' => static fn (array $fx): int => $fx[1]->rootBeforeCellCount,
    'root adopts one interior cell' => static fn (array $fx): int => $fx[1]->rootAfterCellCount,
    'adopted child pointers include left and right children' => static fn (array $fx): array => $fx[1]->childPageNumbers,
    'pointer-map update pages include children and first overflow' => static fn (array $fx): array => array_keys($fx[1]->pointerMapUpdates),
    'left child is reparented to root' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[5]['parent_page_number'],
    'right child is reparented to root' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[6]['parent_page_number'],
    'first overflow is reparented to root' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[20]['parent_page_number'],
    'left child keeps btree pointer-map type' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[5]['type'],
    'right child keeps btree pointer-map type' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[6]['type'],
    'first overflow keeps first-overflow type' => static fn (array $fx): int => $fx[1]->pointerMapUpdates[20]['type'],
    'summary reports pointer-map page 2 once' => static fn (array $fx): array => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'updated page numbers include header pointer-map root and freed child' => static fn (array $fx): array => $fx[1]->updatedPageNumbers(),
    'summary updated pages match plan' => static fn (array $fx): array => $fx[1]->toArray()['updated_page_numbers'],
    'page images include first page header' => static fn (array $fx): bool => array_key_exists(1, $fx[1]->pageImages),
    'page images include pointer-map page' => static fn (array $fx): bool => array_key_exists(2, $fx[1]->pageImages),
    'page images include collapsed root' => static fn (array $fx): bool => array_key_exists(3, $fx[1]->pageImages),
    'page images include freed child trunk' => static fn (array $fx): bool => array_key_exists(4, $fx[1]->pageImages),
    'page images do not rewrite overflow payload page' => static fn (array $fx): bool => array_key_exists(20, $fx[1]->pageImages),
    'page images do not rewrite trailing overflow page' => static fn (array $fx): bool => array_key_exists(21, $fx[1]->pageImages),
    'freed pages contain obsolete child only' => static fn (array $fx): array => $fx[1]->freePlan->freedPageNumbers,
    'freelist count increments for obsolete child' => static fn (array $fx): int => $fx[1]->freePlan->freelistPageCount,
    'first freelist trunk is obsolete child' => static fn (array $fx): int => $fx[1]->freePlan->firstFreelistTrunkPage,
    'free plan updates low pointer-map page' => static fn (array $fx): array => array_keys($fx[1]->freePlan->updatedPointerMapPages),
    'root header parses as index interior' => static fn (array $fx): string => $fx[3]->pageType,
    'root header has one cell' => static fn (array $fx): int => $fx[3]->cellCount,
    'root header rightmost child is preserved' => static fn (array $fx): int => $fx[3]->rightMostPointer,
    'root cell left child is preserved' => static fn (array $fx): int => $fx[4]->leftChildPage,
    'root cell first overflow pointer is preserved' => static fn (array $fx): int => $fx[4]->firstOverflowPage,
    'root cell payload length is preserved' => static fn (array $fx): int => $fx[4]->payloadLength,
    'root cell payload bytes round trip through overflow reader' => static fn (array $fx): bool => $fx[4]->payload === $fx[5],
    'root cell local payload length is preserved' => static fn (array $fx): int => $fx[4]->localPayloadLength,
    'encoded local payload length required overflow' => static fn (array $fx): bool => $fx[6]['localPayloadLength'] < strlen($fx[5]),
    'post header first freelist trunk' => static fn (array $fx): int => $fx[2]->header->firstFreelistTrunkPage,
    'post header freelist page count' => static fn (array $fx): int => $fx[2]->header->freelistPageCount,
    'post freelist pages' => static fn (array $fx): array => $fx[2]->freelistPageNumbers(),
    'post freelist allocation order' => static fn (array $fx): array => $fx[2]->freelistAllocationOrder(),
    'obsolete child pointer-map type is free page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(4)->typeName(),
    'obsolete child pointer-map parent is cleared' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(4)->parentPageNumber,
    'left child pointer-map type is btree page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(5)->typeName(),
    'left child pointer-map parent is root' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(5)->parentPageNumber,
    'right child pointer-map type is btree page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(6)->typeName(),
    'right child pointer-map parent is root' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(6)->parentPageNumber,
    'first overflow pointer-map type is first overflow' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(20)->typeName(),
    'first overflow pointer-map parent is root' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(20)->parentPageNumber,
    'trailing overflow pointer-map type remains overflow page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(21)->typeName(),
    'trailing overflow parent remains previous overflow page' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(21)->parentPageNumber,
    'second trailing overflow pointer-map type remains overflow page' => static fn (array $fx): string => $fx[2]->pointerMapEntryForPage(22)->typeName(),
    'second trailing overflow parent remains previous overflow page' => static fn (array $fx): int => $fx[2]->pointerMapEntryForPage(22)->parentPageNumber,
    'overflow chain numbers remain current-next order' => static fn (array $fx): array => $fx[2]->overflowPageChainNumbers(20, strlen($fx[5]) - $fx[4]->localPayloadLength),
    'overflow payload reader returns trailing bytes' => static fn (array $fx): bool => $fx[2]->readOverflowPayloadForBtreePlan(20, strlen($fx[5]) - $fx[4]->localPayloadLength) === substr($fx[5], $fx[4]->localPayloadLength),
    'summary freed pages' => static fn (array $fx): array => $fx[1]->toArray()['freed_pages'],
    'summary first freelist trunk' => static fn (array $fx): int => $fx[1]->toArray()['first_freelist_trunk_page'],
    'summary secure-delete list is empty for trunk reuse' => static fn (array $fx): array => $fx[1]->toArray()['secure_delete_cleared_pages'],
    'header parsed from updated first page has freelist count' => static fn (array $fx): int => SQLiteHeader::parse($fx[1]->pageImages[1])->freelistPageCount,
    'header parsed from updated first page has first trunk' => static fn (array $fx): int => SQLiteHeader::parse($fx[1]->pageImages[1])->firstFreelistTrunkPage,
    'database page count is unchanged' => static fn (array $fx): int => $fx[2]->pageCount(),
    'database size header is unchanged' => static fn (array $fx): int => $fx[2]->header->databaseSizePages,
];

$expected = [
    'plan class is root collapse' => SQLiteBTreeRootCollapsePlan::class,
    'summary action is root collapse apply' => 'root-collapse-apply',
    'root page is preserved' => 3,
    'obsolete child page is freed' => 4,
    'root starts as index interior' => 'index-interior',
    'root remains index interior' => 'index-interior',
    'root starts empty' => 0,
    'root adopts one interior cell' => 1,
    'adopted child pointers include left and right children' => [5, 6],
    'pointer-map update pages include children and first overflow' => [5, 6, 20],
    'left child is reparented to root' => 3,
    'right child is reparented to root' => 3,
    'first overflow is reparented to root' => 3,
    'left child keeps btree pointer-map type' => SQLitePointerMapEntry::BTREE_PAGE,
    'right child keeps btree pointer-map type' => SQLitePointerMapEntry::BTREE_PAGE,
    'first overflow keeps first-overflow type' => SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
    'summary reports pointer-map page 2 once' => [2],
    'updated page numbers include header pointer-map root and freed child' => [1, 2, 3, 4],
    'summary updated pages match plan' => [1, 2, 3, 4],
    'page images include first page header' => true,
    'page images include pointer-map page' => true,
    'page images include collapsed root' => true,
    'page images include freed child trunk' => true,
    'page images do not rewrite overflow payload page' => false,
    'page images do not rewrite trailing overflow page' => false,
    'freed pages contain obsolete child only' => [4],
    'freelist count increments for obsolete child' => 1,
    'first freelist trunk is obsolete child' => 4,
    'free plan updates low pointer-map page' => [2],
    'root header parses as index interior' => 'index-interior',
    'root header has one cell' => 1,
    'root header rightmost child is preserved' => 6,
    'root cell left child is preserved' => 5,
    'root cell first overflow pointer is preserved' => 20,
    'root cell payload length is preserved' => 1144,
    'root cell payload bytes round trip through overflow reader' => true,
    'root cell local payload length is preserved' => 39,
    'encoded local payload length required overflow' => true,
    'post header first freelist trunk' => 4,
    'post header freelist page count' => 1,
    'post freelist pages' => [4],
    'post freelist allocation order' => [4],
    'obsolete child pointer-map type is free page' => 'free-page',
    'obsolete child pointer-map parent is cleared' => 0,
    'left child pointer-map type is btree page' => 'btree-page',
    'left child pointer-map parent is root' => 3,
    'right child pointer-map type is btree page' => 'btree-page',
    'right child pointer-map parent is root' => 3,
    'first overflow pointer-map type is first overflow' => 'first-overflow-page',
    'first overflow pointer-map parent is root' => 3,
    'trailing overflow pointer-map type remains overflow page' => 'overflow-page',
    'trailing overflow parent remains previous overflow page' => 20,
    'second trailing overflow pointer-map type remains overflow page' => 'overflow-page',
    'second trailing overflow parent remains previous overflow page' => 21,
    'overflow chain numbers remain current-next order' => [20, 21, 22],
    'overflow payload reader returns trailing bytes' => true,
    'summary freed pages' => [4],
    'summary first freelist trunk' => 4,
    'summary secure-delete list is empty for trunk reuse' => [],
    'header parsed from updated first page has freelist count' => 1,
    'header parsed from updated first page has first trunk' => 4,
    'database page count is unchanged' => 22,
    'database size header is unchanged' => 22,
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree interior root collapse overflow current next37 ' . $name] = static function (TestRunner $t) use ($fixture, $read, $expected, $name): void {
        $t->same($expected[$name], $read($fixture()));
    };
}

return $tests;
