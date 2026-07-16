<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageMovePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteTableRow;

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
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$setPointerMapEntry = static function (string $pointerMapPage, int $pageNumber, int $type, int $parentPageNumber): string {
    return substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$buildFixture = static function (string $parentReferenceKind, int $overflowFirstPage, int $overflowNextPage) use ($makeFirstPage, $setPointerMapEntry): array {
    $pageSize = 512;
    $sourcePageNumber = 9;
    $targetPageNumber = 5;
    $parentPageNumber = 3;

    $firstPage = substr_replace($makeFirstPage($pageSize, $sourcePageNumber), pack('N', 4), 32, 4);
    $firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);

    $pointerMapPage = str_repeat("\0", $pageSize);
    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        5 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        7 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        8 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        9 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    ] as $pageNumber => [$type, $parentPageNumberForEntry]) {
        $pointerMapPage = $setPointerMapEntry($pointerMapPage, $pageNumber, $type, $parentPageNumberForEntry);
    }
    $pointerMapPage = $setPointerMapEntry($pointerMapPage, $overflowFirstPage, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, $sourcePageNumber);
    $pointerMapPage = $setPointerMapEntry($pointerMapPage, $overflowNextPage, SQLitePointerMapEntry::OVERFLOW_PAGE, $overflowFirstPage);

    $freelistTrunkPage = SQLiteFreelistTrunkPage::assemble(null, [$targetPageNumber], $pageSize);
    $parentPage = $parentReferenceKind === 'right-most'
        ? SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode(6, 100),
            SQLiteTableInteriorCell::encode(7, 150),
        ], $sourcePageNumber, $pageSize)
        : SQLiteTableInteriorPage::assemble([
            SQLiteTableInteriorCell::encode($sourcePageNumber, 100),
            SQLiteTableInteriorCell::encode(6, 150),
        ], 7, $pageSize);

    $overflowPayload = str_repeat('wp-page-move-overflow:', 52) . $parentReferenceKind;
    $largePayload = SQLiteRecord::encode([null, '_transient_move_overflow_' . $parentReferenceKind, $overflowPayload, 'no']);
    $largeLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($largePayload), $pageSize);
    $overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($largePayload, $largeLocalLength), [$overflowFirstPage, $overflowNextPage], $pageSize);
    $sourceLeafPage = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(201, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
        SQLiteTableLeafCell::encode(202, $largePayload, $pageSize, $overflowFirstPage),
    ], $pageSize);

    $pages = [
        1 => $firstPage,
        2 => $pointerMapPage,
        3 => $parentPage,
        4 => $freelistTrunkPage,
        5 => str_repeat("\0", $pageSize),
        6 => str_repeat("\0", $pageSize),
        7 => str_repeat("\0", $pageSize),
        8 => str_repeat("\0", $pageSize),
        9 => $sourceLeafPage,
    ];
    $pages[$overflowFirstPage] = $overflowPages[$overflowFirstPage];
    $pages[$overflowNextPage] = $overflowPages[$overflowNextPage];
    ksort($pages);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreePageMovePlan::moveLastTableLeafIntoFreelistSlot($database, $sourcePageNumber, 3);
    $postPages = [];
    for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
        $postPages[$pageNumber] = $database->page($pageNumber);
    }
    foreach ($plan->pageImages as $pageNumber => $page) {
        if ($pageNumber <= $plan->databasePageCount) {
            $postPages[$pageNumber] = $page;
        }
    }
    ksort($postPages);
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

    return [$database, $plan, $postDatabase, $overflowPayload];
};

$tests = [
    'moves table leaf overflow pointer-map parent from current page to moved page' => static function (TestRunner $t) use ($buildFixture): void {
        [$database, $plan, $postDatabase, $overflowPayload] = $buildFixture('right-most', 6, 8);
        $postHeader = $postDatabase->pageHeader(5);
        $postRows = $postDatabase->tableRows(5);
        $summary = $plan->toArray();

        $t->same('first-overflow-page', $database->pointerMapEntryForPage(6)->typeName());
        $t->same(9, $database->pointerMapEntryForPage(6)->parentPageNumber);
        $t->same('overflow-page', $database->pointerMapEntryForPage(8)->typeName());
        $t->same(6, $database->pointerMapEntryForPage(8)->parentPageNumber);
        $t->same(5, $plan->targetPageNumber);
        $t->same(8, $plan->databasePageCount);
        $t->same([1, 2, 3, 4, 5], $plan->updatedPageNumbers());
        $t->same([2], $plan->updatedPointerMapPageNumbers);
        $t->same('auto-vacuum-table-leaf-page-move', $summary['action']);
        $t->same(['kind' => 'right-most', 'page' => 3, 'before' => 9, 'after' => 5], $summary['parent_pointer_update']);
        $t->same('table-leaf', $postHeader->pageType);
        $t->same([201, 202], array_map(static fn (SQLiteTableRow $row): int => $row->rowId, $postRows));
        $t->same($overflowPayload, $postRows[1]->values()[2]);
        $t->same('btree-page', $postDatabase->pointerMapEntryForPage(5)->typeName());
        $t->same(3, $postDatabase->pointerMapEntryForPage(5)->parentPageNumber);
        $t->same('first-overflow-page', $postDatabase->pointerMapEntryForPage(6)->typeName());
        $t->same(5, $postDatabase->pointerMapEntryForPage(6)->parentPageNumber);
        $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(8)->typeName());
        $t->same(6, $postDatabase->pointerMapEntryForPage(8)->parentPageNumber);
        $t->same(8, $postDatabase->header->databaseSizePages);
        $t->same([4], $postDatabase->freelistPageNumbers());
        $t->same(4, SQLiteHeader::parse($plan->pageImages[1])->firstFreelistTrunkPage);
    },
    'moves left-child table leaf overflow pointer-map parent from current page to moved page' => static function (TestRunner $t) use ($buildFixture): void {
        [$database, $plan, $postDatabase] = $buildFixture('left-child', 6, 8);
        $postParentHeader = $postDatabase->pageHeader(3);
        $postParentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $postParentHeader);

        $t->same(9, $database->pointerMapEntryForPage(6)->parentPageNumber);
        $t->same(['kind' => 'left-child', 'page' => 3, 'before' => 9, 'after' => 5], $plan->parentPointerUpdate);
        $t->same(['kind' => 'left-child', 'page' => 3, 'before' => 9, 'after' => 5], $plan->toArray()['parent_pointer_update']);
        $t->same(2, $postParentHeader->cellCount);
        $t->same(7, $postParentHeader->rightMostPointer);
        $t->same([5, 6], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $postParentCells));
        $t->same([100, 150], array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $postParentCells));
        $t->same(5, $postDatabase->pointerMapEntryForPage(6)->parentPageNumber);
        $t->same(6, $postDatabase->pointerMapEntryForPage(8)->parentPageNumber);
        $t->same([4], $postDatabase->freelistPageNumbers());
    },
];

$cases = [
    'low adjacent overflow' => [6, 8],
    'high adjacent overflow' => [7, 8],
    'freelist-neighbor overflow' => [6, 7],
    'target-neighbor overflow' => [8, 6],
    'left-child low adjacent overflow' => [6, 8, 'left-child'],
    'left-child high adjacent overflow' => [7, 8, 'left-child'],
    'left-child reverse overflow' => [8, 6, 'left-child'],
    'right-most reverse overflow' => [8, 6, 'right-most'],
    'right-most middle overflow' => [7, 6, 'right-most'],
    'left-child middle overflow' => [7, 6, 'left-child'],
    'source-near previous overflow' => [8, 7, 'right-most'],
    'source-near left-child overflow' => [8, 7, 'left-child'],
    'first low second high' => [6, 7, 'right-most'],
    'first high second low' => [7, 6, 'left-child'],
    'first six second eight right-most' => [6, 8, 'right-most'],
    'first six second eight left-child' => [6, 8, 'left-child'],
    'first seven second eight right-most' => [7, 8, 'right-most'],
    'first seven second eight left-child' => [7, 8, 'left-child'],
    'first eight second six right-most' => [8, 6, 'right-most'],
    'first eight second six left-child' => [8, 6, 'left-child'],
    'first eight second seven right-most' => [8, 7, 'right-most'],
    'first eight second seven left-child' => [8, 7, 'left-child'],
    'first seven second six right-most' => [7, 6, 'right-most'],
    'first seven second six left-child' => [7, 6, 'left-child'],
];

foreach ($cases as $label => $case) {
    $tests['moves table leaf overflow pointer-map for ' . $label] = static function (TestRunner $t) use ($buildFixture, $case): void {
        $firstOverflowPage = $case[0];
        $secondOverflowPage = $case[1];
        $parentReferenceKind = $case[2] ?? 'right-most';

        [$database, $plan, $postDatabase] = $buildFixture($parentReferenceKind, $firstOverflowPage, $secondOverflowPage);

        $t->same(9, $database->pointerMapEntryForPage($firstOverflowPage)->parentPageNumber);
        $t->same($firstOverflowPage, $database->pointerMapEntryForPage($secondOverflowPage)->parentPageNumber);
        $t->same(5, $postDatabase->pointerMapEntryForPage($firstOverflowPage)->parentPageNumber);
        $t->same($firstOverflowPage, $postDatabase->pointerMapEntryForPage($secondOverflowPage)->parentPageNumber);
        $t->same('first-overflow-page', $postDatabase->pointerMapEntryForPage($firstOverflowPage)->typeName());
        $t->same('overflow-page', $postDatabase->pointerMapEntryForPage($secondOverflowPage)->typeName());
        $t->same(['kind' => $parentReferenceKind, 'page' => 3, 'before' => 9, 'after' => 5], $plan->parentPointerUpdate);
        $t->same([2], $plan->updatedPointerMapPageNumbers);
        $t->same([1, 2, 3, 4, 5], $plan->updatedPageNumbers());
        $t->same(8, $postDatabase->header->databaseSizePages);
    };
}

return $tests;
