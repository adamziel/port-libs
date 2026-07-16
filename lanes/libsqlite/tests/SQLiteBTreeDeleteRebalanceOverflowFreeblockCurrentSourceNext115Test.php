<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreeblockApplyPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
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

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$databaseFixture = static function () use ($firstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 10, str_repeat("\0", 512));
    $pages[1] = $firstPage(10, 0, 0);
    $pages[2] = str_repeat("\0", 512);

    $tableCells = [
        SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
        SQLiteTableLeafCell::encode(11, SQLiteRecord::encode([null, '_transient_timeout_theme_roots', str_repeat('t', 130)])),
        SQLiteTableLeafCell::encode(12, SQLiteRecord::encode([null, '_site_transient_update_core', 'fresh'])),
        SQLiteTableLeafCell::encode(13, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 32)])),
    ];
    $indexCells = [
        SQLiteIndexCell::encode(SQLiteRecord::encode(['active_plugins', 10])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_timeout_theme_roots', 11, str_repeat('i', 48)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_site_transient_update_core', 12])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['rewrite_rules', 13])),
    ];

    $pages[3] = SQLiteTableLeafPage::assemble($tableCells);
    $pages[4] = SQLiteIndexLeafPage::assemble($indexCells);
    $pages[6] = str_repeat('a', 512);
    $pages[7] = str_repeat('b', 512);
    $pages[8] = str_repeat('c', 512);
    $pages[9] = str_repeat('d', 512);
    $pages[10] = str_repeat('e', 512);

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::OVERFLOW_PAGE, 7);
    $putPointerMapEntry($pages, 9, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$fixture = static function () use ($databaseFixture): array {
    $database = $databaseFixture();
    $tableDeletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 11, secureDelete: true);
    $tablePlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $tableDeletedPage,
            'rowid' => 11,
            'obsolete_overflow_page_numbers' => [6, 7, 8],
        ],
        true,
    );

    $pagesAfterTable = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pagesAfterTable[$pageNumber] = $tablePlan->pageImages[$pageNumber] ?? $database->page($pageNumber);
    }
    $databaseAfterTable = SQLiteDatabase::fromBytes(implode('', $pagesAfterTable));

    $indexDeletedPage = SQLiteIndexLeafPage::deleteCellByRecordValues(
        $databaseAfterTable->page(4),
        ['_transient_timeout_theme_roots', 11, str_repeat('i', 48)],
        secureDelete: true,
    );
    $indexPlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult(
        $databaseAfterTable,
        4,
        [
            'page' => $indexDeletedPage,
            'record_values' => ['_transient_timeout_theme_roots', 11, str_repeat('i', 48)],
            'obsolete_overflow_page_numbers' => [9, 10],
        ],
        true,
    );

    return [$database, $databaseAfterTable, $tableDeletedPage, $indexDeletedPage, $tablePlan, $indexPlan];
};

$tests = [];

$cases = [
    'table current source hash matches source page' => static fn (array $fx): mixed => $fx[4]->currentSourcePageHash === hash('sha256', $fx[0]->page(3)),
    'table deleted leaf hash matches delete result' => static fn (array $fx): mixed => $fx[4]->deletedLeafPageHash === hash('sha256', $fx[2]),
    'table next leaf hash matches plan image' => static fn (array $fx): mixed => $fx[4]->nextLeafPageHash === hash('sha256', $fx[4]->leafPageImage),
    'table source hash differs from deleted hash' => static fn (array $fx): mixed => $fx[4]->currentSourcePageHash !== $fx[4]->deletedLeafPageHash,
    'table deleted hash differs from next hash' => static fn (array $fx): mixed => $fx[4]->deletedLeafPageHash !== $fx[4]->nextLeafPageHash,
    'table hash length' => static fn (array $fx): mixed => strlen($fx[4]->nextLeafPageHash),
    'table array source hash' => static fn (array $fx): mixed => $fx[4]->toArray()['current_source_page_hash'] === $fx[4]->currentSourcePageHash,
    'table array deleted hash' => static fn (array $fx): mixed => $fx[4]->toArray()['deleted_leaf_page_hash'] === $fx[4]->deletedLeafPageHash,
    'table array next hash' => static fn (array $fx): mixed => $fx[4]->toArray()['next_leaf_page_hash'] === $fx[4]->nextLeafPageHash,
    'table cell count delta' => static fn (array $fx): mixed => $fx[4]->cellCountDelta,
    'table array cell count delta' => static fn (array $fx): mixed => $fx[4]->toArray()['cell_count_delta'],
    'table freeblock delta is negative before bytes' => static fn (array $fx): mixed => $fx[4]->freeblockBytesDelta === -$fx[4]->freeblockBytesBefore,
    'table array freeblock delta' => static fn (array $fx): mixed => $fx[4]->toArray()['freeblock_bytes_delta'] === $fx[4]->freeblockBytesDelta,
    'table fragmented delta' => static fn (array $fx): mixed => $fx[4]->fragmentedBytesDelta,
    'table array fragmented delta' => static fn (array $fx): mixed => $fx[4]->toArray()['fragmented_bytes_delta'] === $fx[4]->fragmentedBytesDelta,
    'table content start delta positive' => static fn (array $fx): mixed => $fx[4]->cellContentStartDelta > 0,
    'table array content start delta' => static fn (array $fx): mixed => $fx[4]->toArray()['cell_content_start_delta'] === $fx[4]->cellContentStartDelta,
    'table write order starts with leaf' => static fn (array $fx): mixed => $fx[4]->writeOrderPageNumbers[0],
    'table write order includes trunk' => static fn (array $fx): mixed => in_array(6, $fx[4]->writeOrderPageNumbers, true),
    'table write order includes pointer map page' => static fn (array $fx): mixed => in_array(2, $fx[4]->writeOrderPageNumbers, true),
    'table write order includes header page last' => static fn (array $fx): mixed => $fx[4]->writeOrderPageNumbers[array_key_last($fx[4]->writeOrderPageNumbers)],
    'table array write order' => static fn (array $fx): mixed => $fx[4]->toArray()['write_order_page_numbers'] === $fx[4]->writeOrderPageNumbers,
    'table updated pages contain write order' => static fn (array $fx): mixed => count(array_diff($fx[4]->writeOrderPageNumbers, $fx[4]->updatedPageNumbers())),
    'table obsolete overflow count' => static fn (array $fx): mixed => count($fx[4]->obsoleteOverflowPageNumbers),
    'table freed pointer map entries count' => static fn (array $fx): mixed => count($fx[4]->toArray()['freed_pointer_map_entries']),
    'table freed pointer map first page' => static fn (array $fx): mixed => $fx[4]->toArray()['freed_pointer_map_entries'][0]['page_number'],
    'table freed pointer map last page' => static fn (array $fx): mixed => $fx[4]->toArray()['freed_pointer_map_entries'][2]['page_number'],
    'table post page eight free' => static fn (array $fx): mixed => SQLiteDatabase::fromBytes(implode('', array_replace(array_combine(range(1, $fx[0]->pageCount()), array_map(fn (int $page): string => $fx[0]->page($page), range(1, $fx[0]->pageCount()))), $fx[4]->pageImages)))->pointerMapEntryForPage(8)->typeName(),
    'table next leaf freeblock status' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[4]->leafPageImage, 512)->freeblockIntegrityReport($fx[4]->leafPageImage)['status'],
    'table next leaf rowids' => static fn (array $fx): mixed => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($fx[4]->leafPageImage, SQLiteBTreePageHeader::parsePage($fx[4]->leafPageImage, 512))),
    'index current source hash matches table-applied page' => static fn (array $fx): mixed => $fx[5]->currentSourcePageHash === hash('sha256', $fx[1]->page(4)),
    'index deleted leaf hash matches delete result' => static fn (array $fx): mixed => $fx[5]->deletedLeafPageHash === hash('sha256', $fx[3]),
    'index next leaf hash matches plan image' => static fn (array $fx): mixed => $fx[5]->nextLeafPageHash === hash('sha256', $fx[5]->leafPageImage),
    'index source hash differs from deleted hash' => static fn (array $fx): mixed => $fx[5]->currentSourcePageHash !== $fx[5]->deletedLeafPageHash,
    'index deleted hash differs from next hash' => static fn (array $fx): mixed => $fx[5]->deletedLeafPageHash !== $fx[5]->nextLeafPageHash,
    'index hash length' => static fn (array $fx): mixed => strlen($fx[5]->nextLeafPageHash),
    'index array source hash' => static fn (array $fx): mixed => $fx[5]->toArray()['current_source_page_hash'] === $fx[5]->currentSourcePageHash,
    'index array deleted hash' => static fn (array $fx): mixed => $fx[5]->toArray()['deleted_leaf_page_hash'] === $fx[5]->deletedLeafPageHash,
    'index array next hash' => static fn (array $fx): mixed => $fx[5]->toArray()['next_leaf_page_hash'] === $fx[5]->nextLeafPageHash,
    'index cell count delta' => static fn (array $fx): mixed => $fx[5]->cellCountDelta,
    'index freeblock delta is negative before bytes' => static fn (array $fx): mixed => $fx[5]->freeblockBytesDelta === -$fx[5]->freeblockBytesBefore,
    'index array freeblock delta' => static fn (array $fx): mixed => $fx[5]->toArray()['freeblock_bytes_delta'] === $fx[5]->freeblockBytesDelta,
    'index fragmented delta' => static fn (array $fx): mixed => $fx[5]->fragmentedBytesDelta,
    'index content start delta positive' => static fn (array $fx): mixed => $fx[5]->cellContentStartDelta > 0,
    'index write order starts with leaf' => static fn (array $fx): mixed => $fx[5]->writeOrderPageNumbers[0],
    'index write order includes trunk' => static fn (array $fx): mixed => in_array(6, $fx[5]->writeOrderPageNumbers, true),
    'index write order includes pointer map page' => static fn (array $fx): mixed => in_array(2, $fx[5]->writeOrderPageNumbers, true),
    'index write order includes header page last' => static fn (array $fx): mixed => $fx[5]->writeOrderPageNumbers[array_key_last($fx[5]->writeOrderPageNumbers)],
    'index array write order' => static fn (array $fx): mixed => $fx[5]->toArray()['write_order_page_numbers'] === $fx[5]->writeOrderPageNumbers,
    'index updated pages contain write order' => static fn (array $fx): mixed => count(array_diff($fx[5]->writeOrderPageNumbers, $fx[5]->updatedPageNumbers())),
    'index obsolete overflow count' => static fn (array $fx): mixed => count($fx[5]->obsoleteOverflowPageNumbers),
    'index freed pointer map entries count' => static fn (array $fx): mixed => count($fx[5]->toArray()['freed_pointer_map_entries']),
    'index freed pointer map first page' => static fn (array $fx): mixed => $fx[5]->toArray()['freed_pointer_map_entries'][0]['page_number'],
    'index freed pointer map last page' => static fn (array $fx): mixed => $fx[5]->toArray()['freed_pointer_map_entries'][1]['page_number'],
    'index next leaf freeblock status' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[5]->leafPageImage, 512)->freeblockIntegrityReport($fx[5]->leafPageImage)['status'],
    'index next leaf names' => static fn (array $fx): mixed => array_map(static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0], SQLiteIndexCell::parsePageCells($fx[5]->leafPageImage, SQLiteBTreePageHeader::parsePage($fx[5]->leafPageImage, 512))),
    'index page images include leaf' => static fn (array $fx): mixed => array_key_exists(4, $fx[5]->pageImages),
    'index page images include pointer map' => static fn (array $fx): mixed => array_key_exists(2, $fx[5]->pageImages),
    'index page images include header' => static fn (array $fx): mixed => array_key_exists(1, $fx[5]->pageImages),
    'index page images include freed trunk' => static fn (array $fx): mixed => array_key_exists(6, $fx[5]->pageImages),
    'index page images include freed overflow' => static fn (array $fx): mixed => array_key_exists(10, $fx[5]->pageImages),
];

$expected = [
    'table current source hash matches source page' => true,
    'table deleted leaf hash matches delete result' => true,
    'table next leaf hash matches plan image' => true,
    'table source hash differs from deleted hash' => true,
    'table deleted hash differs from next hash' => true,
    'table hash length' => 64,
    'table array source hash' => true,
    'table array deleted hash' => true,
    'table array next hash' => true,
    'table cell count delta' => 0,
    'table array cell count delta' => 0,
    'table freeblock delta is negative before bytes' => true,
    'table array freeblock delta' => true,
    'table fragmented delta' => 0,
    'table array fragmented delta' => true,
    'table content start delta positive' => true,
    'table array content start delta' => true,
    'table write order starts with leaf' => 3,
    'table write order includes trunk' => true,
    'table write order includes pointer map page' => true,
    'table write order includes header page last' => 1,
    'table array write order' => true,
    'table updated pages contain write order' => 0,
    'table obsolete overflow count' => 3,
    'table freed pointer map entries count' => 3,
    'table freed pointer map first page' => 6,
    'table freed pointer map last page' => 8,
    'table post page eight free' => 'free-page',
    'table next leaf freeblock status' => 'ok',
    'table next leaf rowids' => [10, 12, 13],
    'index current source hash matches table-applied page' => true,
    'index deleted leaf hash matches delete result' => true,
    'index next leaf hash matches plan image' => true,
    'index source hash differs from deleted hash' => true,
    'index deleted hash differs from next hash' => true,
    'index hash length' => 64,
    'index array source hash' => true,
    'index array deleted hash' => true,
    'index array next hash' => true,
    'index cell count delta' => 0,
    'index freeblock delta is negative before bytes' => true,
    'index array freeblock delta' => true,
    'index fragmented delta' => 0,
    'index content start delta positive' => true,
    'index write order starts with leaf' => 4,
    'index write order includes trunk' => true,
    'index write order includes pointer map page' => true,
    'index write order includes header page last' => 1,
    'index array write order' => true,
    'index updated pages contain write order' => 0,
    'index obsolete overflow count' => 2,
    'index freed pointer map entries count' => 2,
    'index freed pointer map first page' => 9,
    'index freed pointer map last page' => 10,
    'index next leaf freeblock status' => 'ok',
    'index next leaf names' => ['active_plugins', '_site_transient_update_core', 'rewrite_rules'],
    'index page images include leaf' => true,
    'index page images include pointer map' => true,
    'index page images include header' => true,
    'index page images include freed trunk' => true,
    'index page images include freed overflow' => true,
];

foreach ($cases as $name => $case) {
    $tests['btree delete rebalance overflow freeblock current source next115 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

return $tests;
