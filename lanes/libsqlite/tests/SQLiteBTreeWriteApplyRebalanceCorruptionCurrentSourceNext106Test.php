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

$firstPage106 = static function (int $pageCount, int $firstTrunk, int $freelistCount): string {
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

$putPointerMapEntry106 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$databaseFixture106 = static function () use ($firstPage106, $putPointerMapEntry106): SQLiteDatabase {
    $pages = array_fill(1, 9, str_repeat("\0", 512));
    $pages[1] = $firstPage106(9, 0, 0);
    $pages[2] = str_repeat("\0", 512);

    $tableCells = [
        SQLiteTableLeafCell::encode(11, SQLiteRecord::encode([null, '_transient_cache_alpha', 'old-a', 'no'])),
        SQLiteTableLeafCell::encode(12, SQLiteRecord::encode([null, '_transient_cache_beta', str_repeat('b', 120), 'no'])),
        SQLiteTableLeafCell::encode(13, SQLiteRecord::encode([null, '_transient_cache_gamma', 'keep-g', 'yes'])),
        SQLiteTableLeafCell::encode(14, SQLiteRecord::encode([null, '_transient_cache_delta', 'keep-d', 'yes'])),
    ];
    $indexCells = [
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_cache_alpha', 11])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_cache_beta', 12, str_repeat('i', 48)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_cache_gamma', 13])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_cache_delta', 14])),
    ];

    $pages[3] = SQLiteTableLeafPage::assemble($tableCells);
    $pages[4] = SQLiteIndexLeafPage::assemble($indexCells);
    $pages[6] = str_repeat('u', 512);
    $pages[7] = str_repeat('v', 512);
    $pages[8] = str_repeat('w', 512);
    $pages[9] = str_repeat('z', 512);

    $putPointerMapEntry106($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry106($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry106($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry106($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry106($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry106($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry106($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tableDelete106 = static function (SQLiteDatabase $database): array {
    return [
        'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 12, secureDelete: true),
        'rowid' => 12,
        'obsolete_overflow_page_numbers' => [6, 7],
    ];
};

$indexDelete106 = static function (SQLiteDatabase $database): array {
    return [
        'page' => SQLiteIndexLeafPage::deleteCellByRecordValues(
            $database->page(4),
            ['_transient_cache_beta', 12, str_repeat('i', 48)],
            secureDelete: true,
        ),
        'record_values' => ['_transient_cache_beta', 12, str_repeat('i', 48)],
        'obsolete_overflow_page_numbers' => [8, 9],
    ];
};

$applyPages106 = static function (SQLiteDatabase $database, array $pageImages): SQLiteDatabase {
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[$pageNumber] = $pageImages[$pageNumber] ?? $database->page($pageNumber);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$fixture106 = static function () use ($databaseFixture106, $tableDelete106, $indexDelete106, $applyPages106): array {
    $database = $databaseFixture106();
    $tablePlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($database, 3, $tableDelete106($database), true);
    $afterTable = $applyPages106($database, $tablePlan->pageImages);
    $indexPlan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($afterTable, 4, $indexDelete106($afterTable), true);
    $afterIndex = $applyPages106($afterTable, $indexPlan->pageImages);

    return [$database, $afterTable, $afterIndex, $tablePlan, $indexPlan];
};

$throwsMessage106 = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$replaceTableRemainingPayload106 = static function (SQLiteDatabase $database) use ($tableDelete106): array {
    $page = str_replace('keep-g', 'bad-g!', $tableDelete106($database)['page']);

    return [
        'page' => $page,
        'rowid' => 12,
        'obsolete_overflow_page_numbers' => [6, 7],
    ];
};

$replaceIndexRemainingPayload106 = static function (SQLiteDatabase $database) use ($indexDelete106): array {
    $page = str_replace('_transient_cache_gamma', '_transient_cache_gammb', $indexDelete106($database)['page']);

    return [
        'page' => $page,
        'record_values' => ['_transient_cache_beta', 12, str_repeat('i', 48)],
        'obsolete_overflow_page_numbers' => [8, 9],
    ];
};

$cases106 = [
    'table action' => static fn (array $fx): mixed => $fx[3]->toArray()['action'],
    'table current leaf page' => static fn (array $fx): mixed => $fx[3]->leafPageNumber,
    'table deleted rowid admitted' => static fn (array $fx): mixed => $fx[3]->deletedRowIds,
    'table before cell count' => static fn (array $fx): mixed => $fx[3]->cellCountBefore,
    'table after cell count' => static fn (array $fx): mixed => $fx[3]->cellCountAfter,
    'table freeblock bytes before nonzero' => static fn (array $fx): mixed => $fx[3]->freeblockBytesBefore > 0,
    'table freeblock bytes after defrag' => static fn (array $fx): mixed => $fx[3]->freeblockBytesAfter,
    'table remaining rowids' => static fn (array $fx): mixed => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($fx[3]->leafPageImage, SQLiteBTreePageHeader::parsePage($fx[3]->leafPageImage, 512))),
    'table freed pages' => static fn (array $fx): mixed => $fx[3]->freePlan->freedPageNumbers,
    'table freed pointer map count' => static fn (array $fx): mixed => count($fx[3]->toArray()['freed_pointer_map_entries']),
    'table freed pointer map types' => static fn (array $fx): mixed => array_column($fx[3]->toArray()['freed_pointer_map_entries'], 'type_name'),
    'table updated pages' => static fn (array $fx): mixed => $fx[3]->updatedPageNumbers(),
    'table page image integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[3]->leafPageImage, 512)->freeblockIntegrityReport($fx[3]->leafPageImage)['status'],
    'post table page six free' => static fn (array $fx): mixed => $fx[1]->pointerMapEntryForPage(6)->typeName(),
    'post table page seven parent zero' => static fn (array $fx): mixed => $fx[1]->pointerMapEntryForPage(7)->parentPageNumber,
    'post table header freelist count' => static fn (array $fx): mixed => $fx[1]->header->freelistPageCount,
    'index action' => static fn (array $fx): mixed => $fx[4]->toArray()['action'],
    'index current leaf page' => static fn (array $fx): mixed => $fx[4]->leafPageNumber,
    'index deleted record values' => static fn (array $fx): mixed => $fx[4]->deletedRecordValues[0],
    'index before cell count' => static fn (array $fx): mixed => $fx[4]->cellCountBefore,
    'index after cell count' => static fn (array $fx): mixed => $fx[4]->cellCountAfter,
    'index freeblock bytes before nonzero' => static fn (array $fx): mixed => $fx[4]->freeblockBytesBefore > 0,
    'index freeblock bytes after defrag' => static fn (array $fx): mixed => $fx[4]->freeblockBytesAfter,
    'index remaining record names' => static fn (array $fx): mixed => array_map(static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0], SQLiteIndexCell::parsePageCells($fx[4]->leafPageImage, SQLiteBTreePageHeader::parsePage($fx[4]->leafPageImage, 512))),
    'index freed pages' => static fn (array $fx): mixed => $fx[4]->freePlan->freedPageNumbers,
    'index secure delete cleared pages' => static fn (array $fx): mixed => $fx[4]->toArray()['secure_delete_cleared_pages'],
    'index freed pointer map count' => static fn (array $fx): mixed => count($fx[4]->toArray()['freed_pointer_map_entries']),
    'index updated pages' => static fn (array $fx): mixed => $fx[4]->updatedPageNumbers(),
    'index page image integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[4]->leafPageImage, 512)->freeblockIntegrityReport($fx[4]->leafPageImage)['status'],
    'post index page eight free' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->typeName(),
    'post index page nine parent zero' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(9)->parentPageNumber,
    'post index header freelist count' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'first page header still parses' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[2]->page(1))->databaseSizePages,
    'rejects table rowid absent from current leaf' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 3, ['page' => $tableDelete106($fx[0])['page'], 'rowid' => 99, 'obsolete_overflow_page_numbers' => [6, 7]], true)),
    'rejects stale table remaining payload' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 3, $replaceTableRemainingPayload106($fx[0]), true)),
    'rejects stale table page from wrong leaf' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 3, ['page' => str_replace('_transient_cache_alpha', '_transient_cache_omega', $tableDelete106($fx[0])['page']), 'rowid' => 12, 'obsolete_overflow_page_numbers' => [6, 7]], true)),
    'rejects index record absent from current leaf' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($fx[1], 4, ['page' => $indexDelete106($fx[1])['page'], 'record_values' => ['_missing', 12], 'obsolete_overflow_page_numbers' => [8, 9]], true)),
    'rejects stale index remaining payload' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($fx[1], 4, $replaceIndexRemainingPayload106($fx[1]), true)),
    'rejects table delete applied to index page' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($fx[0], 4, ['page' => $tableDelete106($fx[0])['page'], 'rowid' => 12, 'obsolete_overflow_page_numbers' => [6, 7]], true)),
    'rejects index delete applied to table page' => static fn (array $fx): mixed => $throwsMessage106(static fn () => SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::indexLeafFromDeleteResult($fx[1], 3, ['page' => $indexDelete106($fx[1])['page'], 'record_values' => ['_transient_cache_beta', 12, str_repeat('i', 48)], 'obsolete_overflow_page_numbers' => [8, 9]], true)),
];

$expected106 = [
    'table action' => 'btree-delete-rebalance-freeblock-apply-current-next',
    'table current leaf page' => 3,
    'table deleted rowid admitted' => [12],
    'table before cell count' => 3,
    'table after cell count' => 3,
    'table freeblock bytes before nonzero' => true,
    'table freeblock bytes after defrag' => 0,
    'table remaining rowids' => [11, 13, 14],
    'table freed pages' => [6, 7],
    'table freed pointer map count' => 2,
    'table freed pointer map types' => ['free-page', 'free-page'],
    'table updated pages' => [1, 2, 3, 6, 7],
    'table page image integrity' => 'ok',
    'post table page six free' => 'free-page',
    'post table page seven parent zero' => 0,
    'post table header freelist count' => 2,
    'index action' => 'btree-delete-rebalance-freeblock-apply-current-next',
    'index current leaf page' => 4,
    'index deleted record values' => ['_transient_cache_beta', 12, str_repeat('i', 48)],
    'index before cell count' => 3,
    'index after cell count' => 3,
    'index freeblock bytes before nonzero' => true,
    'index freeblock bytes after defrag' => 0,
    'index remaining record names' => ['_transient_cache_alpha', '_transient_cache_gamma', '_transient_cache_delta'],
    'index freed pages' => [8, 9],
    'index secure delete cleared pages' => [8, 9],
    'index freed pointer map count' => 2,
    'index updated pages' => [1, 2, 4, 6, 8, 9],
    'index page image integrity' => 'ok',
    'post index page eight free' => 'free-page',
    'post index page nine parent zero' => 0,
    'post index header freelist count' => 4,
    'first page header still parses' => 9,
    'rejects table rowid absent from current leaf' => 'SQLite delete rebalance freeblock apply table rowid is not present in the current source leaf',
    'rejects stale table remaining payload' => 'SQLite delete rebalance freeblock apply rejected stale table leaf delete result',
    'rejects stale table page from wrong leaf' => 'SQLite delete rebalance freeblock apply rejected stale table leaf delete result',
    'rejects index record absent from current leaf' => 'SQLite delete rebalance freeblock apply index record is not present in the current source leaf',
    'rejects stale index remaining payload' => 'SQLite delete rebalance freeblock apply rejected stale index leaf delete result',
    'rejects table delete applied to index page' => 'SQLite delete rebalance freeblock apply expected table-leaf current-source page image',
    'rejects index delete applied to table page' => 'SQLite delete rebalance freeblock apply expected index-leaf current-source page image',
];

$tests = [];
foreach ($cases106 as $name => $case) {
    $tests['btree write apply rebalance corruption current source next106 ' . $name] = static function (TestRunner $t) use ($fixture106, $case, $expected106, $name): void {
        $t->same($expected106[$name], $case($fixture106()));
    };
}

return $tests;
