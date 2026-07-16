<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreeblockApplyPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (int $pageCount, int $firstTrunk, int $freelistCount): string {
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
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $offset = 5 * ($pageNumber - 3);
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), $offset, 5);
};

$databaseFixture = static function () use ($firstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 8, str_repeat("\0", 512));
    $pages[1] = $firstPage(8, 0, 0);
    $pages[2] = str_repeat("\0", 512);

    $tableCells = [
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_feed', str_repeat('x', 120)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_site_transient_update_plugins', 'fresh'])),
    ];
    $indexCells = [
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 1])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_feed', 2, str_repeat('i', 40)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_site_transient_update_plugins', 3])),
    ];

    $pages[3] = SQLiteTableLeafPage::assemble($tableCells);
    $pages[4] = SQLiteIndexLeafPage::assemble($indexCells);
    $pages[6] = str_repeat('o', 512);
    $pages[7] = str_repeat('p', 512);
    $pages[8] = str_repeat('q', 512);

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::OVERFLOW_PAGE, 4);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$fixture = static function () use ($databaseFixture): array {
    $database = $databaseFixture();
    $tableDelete = [
        'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true),
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [6, 7],
    ];
    $indexDelete = [
        'page' => SQLiteIndexLeafPage::deleteCellByRecordValues(
            $database->page(4),
            ['_transient_feed', 2, str_repeat('i', 40)],
            secureDelete: true,
        ),
        'record_values' => ['_transient_feed', 2, str_repeat('i', 40)],
        'obsolete_overflow_page_numbers' => [8],
    ];

    $tablePlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($database, 3, $tableDelete, true);
    $postTablePages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $postTablePages[$pageNumber] = $tablePlan->pageImages[$pageNumber] ?? $database->page($pageNumber);
    }
    $databaseAfterTable = SQLiteDatabase::fromBytes(implode('', $postTablePages));
    $indexPlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($databaseAfterTable, 4, $indexDelete, true);

    return [$database, $databaseAfterTable, $tablePlan, $indexPlan];
};

$throwsMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$tests = [];

$cases = [
    'table action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'table leaf page' => static fn (array $fx): mixed => $fx[2]->leafPageNumber,
    'table leaf type' => static fn (array $fx): mixed => $fx[2]->leafPageType,
    'table deleted rowids' => static fn (array $fx): mixed => $fx[2]->deletedRowIds,
    'table deleted record values empty' => static fn (array $fx): mixed => $fx[2]->deletedRecordValues,
    'table cell count before' => static fn (array $fx): mixed => $fx[2]->cellCountBefore,
    'table cell count after' => static fn (array $fx): mixed => $fx[2]->cellCountAfter,
    'table freeblocks before' => static fn (array $fx): mixed => count($fx[2]->freeblocksBefore),
    'table freeblocks after' => static fn (array $fx): mixed => count($fx[2]->freeblocksAfter),
    'table freeblock bytes before nonzero' => static fn (array $fx): mixed => $fx[2]->freeblockBytesBefore > 0,
    'table freeblock bytes after' => static fn (array $fx): mixed => $fx[2]->freeblockBytesAfter,
    'table fragmented after' => static fn (array $fx): mixed => $fx[2]->fragmentedBytesAfter,
    'table content start increases' => static fn (array $fx): mixed => $fx[2]->cellContentStartAfter > $fx[2]->cellContentStartBefore,
    'table pointer count after' => static fn (array $fx): mixed => count($fx[2]->cellPointersAfter),
    'table pointers sorted' => static fn (array $fx): mixed => $fx[2]->cellPointersAfter === array_values($fx[2]->cellPointersAfter),
    'table obsolete overflow pages' => static fn (array $fx): mixed => $fx[2]->obsoleteOverflowPageNumbers,
    'table freed pages' => static fn (array $fx): mixed => $fx[2]->freePlan->freedPageNumbers,
    'table freelist count' => static fn (array $fx): mixed => $fx[2]->freePlan->freelistPageCount,
    'table first freelist trunk' => static fn (array $fx): mixed => $fx[2]->freePlan->firstFreelistTrunkPage,
    'table updated page numbers' => static fn (array $fx): mixed => $fx[2]->updatedPageNumbers(),
    'table updated pointer map pages' => static fn (array $fx): mixed => $fx[2]->toArray()['updated_pointer_map_page_numbers'],
    'table secure delete cleared pages' => static fn (array $fx): mixed => $fx[2]->toArray()['secure_delete_cleared_pages'],
    'table freed pointer map entry count' => static fn (array $fx): mixed => count($fx[2]->toArray()['freed_pointer_map_entries']),
    'table first freed pointer map type' => static fn (array $fx): mixed => $fx[2]->toArray()['freed_pointer_map_entries'][0]['type_name'],
    'table second freed pointer map page' => static fn (array $fx): mixed => $fx[2]->toArray()['freed_pointer_map_entries'][1]['page_number'],
    'table leaf image parse status' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->leafPageImage, 512)->freeblockIntegrityReport($fx[2]->leafPageImage)['status'],
    'table leaf image has no freeblocks' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->leafPageImage, 512)->firstFreeblockOffset,
    'table remaining rowids' => static fn (array $fx): mixed => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($fx[2]->leafPageImage, SQLiteBTreePageHeader::parsePage($fx[2]->leafPageImage, 512))),
    'post table page 6 is free' => static fn (array $fx): mixed => $fx[1]->pointerMapEntryForPage(6)->typeName(),
    'post table page 7 parent zero' => static fn (array $fx): mixed => $fx[1]->pointerMapEntryForPage(7)->parentPageNumber,
    'post table header freelist count' => static fn (array $fx): mixed => $fx[1]->header->freelistPageCount,
    'post table first trunk page' => static fn (array $fx): mixed => $fx[1]->header->firstFreelistTrunkPage,
    'index action' => static fn (array $fx): mixed => $fx[3]->toArray()['action'],
    'index leaf page' => static fn (array $fx): mixed => $fx[3]->leafPageNumber,
    'index leaf type' => static fn (array $fx): mixed => $fx[3]->leafPageType,
    'index deleted rowids empty' => static fn (array $fx): mixed => $fx[3]->deletedRowIds,
    'index deleted record name' => static fn (array $fx): mixed => $fx[3]->deletedRecordValues[0][0],
    'index cell count before' => static fn (array $fx): mixed => $fx[3]->cellCountBefore,
    'index cell count after' => static fn (array $fx): mixed => $fx[3]->cellCountAfter,
    'index freeblocks before' => static fn (array $fx): mixed => count($fx[3]->freeblocksBefore),
    'index freeblocks after' => static fn (array $fx): mixed => count($fx[3]->freeblocksAfter),
    'index freeblock bytes before nonzero' => static fn (array $fx): mixed => $fx[3]->freeblockBytesBefore > 0,
    'index freeblock bytes after' => static fn (array $fx): mixed => $fx[3]->freeblockBytesAfter,
    'index fragmented after' => static fn (array $fx): mixed => $fx[3]->fragmentedBytesAfter,
    'index content start increases' => static fn (array $fx): mixed => $fx[3]->cellContentStartAfter > $fx[3]->cellContentStartBefore,
    'index obsolete overflow pages' => static fn (array $fx): mixed => $fx[3]->obsoleteOverflowPageNumbers,
    'index freed pages' => static fn (array $fx): mixed => $fx[3]->freePlan->freedPageNumbers,
    'index freelist count' => static fn (array $fx): mixed => $fx[3]->freePlan->freelistPageCount,
    'index first freelist trunk remains' => static fn (array $fx): mixed => $fx[3]->freePlan->firstFreelistTrunkPage,
    'index updated page numbers' => static fn (array $fx): mixed => $fx[3]->updatedPageNumbers(),
    'index updated pointer map pages' => static fn (array $fx): mixed => $fx[3]->toArray()['updated_pointer_map_page_numbers'],
    'index secure delete cleared pages' => static fn (array $fx): mixed => $fx[3]->toArray()['secure_delete_cleared_pages'],
    'index freed pointer map type' => static fn (array $fx): mixed => $fx[3]->toArray()['freed_pointer_map_entries'][0]['type_name'],
    'index leaf image parse status' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[3]->leafPageImage, 512)->freeblockIntegrityReport($fx[3]->leafPageImage)['status'],
    'index leaf image has no freeblocks' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[3]->leafPageImage, 512)->firstFreeblockOffset,
    'index remaining record names' => static fn (array $fx): mixed => array_map(static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0], SQLiteIndexCell::parsePageCells($fx[3]->leafPageImage, SQLiteBTreePageHeader::parsePage($fx[3]->leafPageImage, 512))),
    'index toArray freeblock count after' => static fn (array $fx): mixed => count($fx[3]->toArray()['freeblocks_after']),
    'index toArray cell pointers after count' => static fn (array $fx): mixed => count($fx[3]->toArray()['cell_pointers_after']),
    'first page image preserves sqlite header' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[3]->pageImages[1])->databaseSizePages,
    'rejects table delete without rowid' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 3, ['page' => $fx[0]->page(3), 'obsolete_overflow_page_numbers' => []])),
    'rejects index delete without record values' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($fx[0], 4, ['page' => $fx[0]->page(4), 'obsolete_overflow_page_numbers' => []])),
    'rejects no freeblock current page' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 3, ['page' => $fx[0]->page(3), 'rowid' => 2, 'obsolete_overflow_page_numbers' => []])),
    'rejects duplicate overflow pages' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 3, ['page' => SQLiteTableLeafPage::deleteCellByRowId($fx[0]->page(3), 2), 'rowid' => 2, 'obsolete_overflow_page_numbers' => [6, 6]])),
];

$expected = [
    'table action' => 'btree-delete-rebalance-freeblock-apply-current-next',
    'table leaf page' => 3,
    'table leaf type' => 'table-leaf',
    'table deleted rowids' => [2],
    'table deleted record values empty' => [],
    'table cell count before' => 2,
    'table cell count after' => 2,
    'table freeblocks before' => 1,
    'table freeblocks after' => 0,
    'table freeblock bytes before nonzero' => true,
    'table freeblock bytes after' => 0,
    'table fragmented after' => 0,
    'table content start increases' => true,
    'table pointer count after' => 2,
    'table pointers sorted' => true,
    'table obsolete overflow pages' => [6, 7],
    'table freed pages' => [6, 7],
    'table freelist count' => 2,
    'table first freelist trunk' => 6,
    'table updated page numbers' => [1, 2, 3, 6, 7],
    'table updated pointer map pages' => [2],
    'table secure delete cleared pages' => [7],
    'table freed pointer map entry count' => 2,
    'table first freed pointer map type' => 'free-page',
    'table second freed pointer map page' => 7,
    'table leaf image parse status' => 'ok',
    'table leaf image has no freeblocks' => 0,
    'table remaining rowids' => [1, 3],
    'post table page 6 is free' => 'free-page',
    'post table page 7 parent zero' => 0,
    'post table header freelist count' => 2,
    'post table first trunk page' => 6,
    'index action' => 'btree-delete-rebalance-freeblock-apply-current-next',
    'index leaf page' => 4,
    'index leaf type' => 'index-leaf',
    'index deleted rowids empty' => [],
    'index deleted record name' => '_transient_feed',
    'index cell count before' => 2,
    'index cell count after' => 2,
    'index freeblocks before' => 1,
    'index freeblocks after' => 0,
    'index freeblock bytes before nonzero' => true,
    'index freeblock bytes after' => 0,
    'index fragmented after' => 0,
    'index content start increases' => true,
    'index obsolete overflow pages' => [8],
    'index freed pages' => [8],
    'index freelist count' => 3,
    'index first freelist trunk remains' => 6,
    'index updated page numbers' => [1, 2, 4, 6, 8],
    'index updated pointer map pages' => [2],
    'index secure delete cleared pages' => [8],
    'index freed pointer map type' => 'free-page',
    'index leaf image parse status' => 'ok',
    'index leaf image has no freeblocks' => 0,
    'index remaining record names' => ['autoload', '_site_transient_update_plugins'],
    'index toArray freeblock count after' => 0,
    'index toArray cell pointers after count' => 2,
    'first page image preserves sqlite header' => 8,
    'rejects table delete without rowid' => 'SQLite delete rebalance freeblock apply requires deleted table rowids',
    'rejects index delete without record values' => 'SQLite delete rebalance freeblock apply requires deleted index record values',
    'rejects no freeblock current page' => 'SQLite delete rebalance freeblock apply requires at least one current leaf freeblock',
    'rejects duplicate overflow pages' => 'SQLite delete rebalance freeblock apply overflow page 6 appears more than once',
];

foreach ($cases as $name => $case) {
    $tests['btree delete rebalance freeblock apply current next74 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

return $tests;
