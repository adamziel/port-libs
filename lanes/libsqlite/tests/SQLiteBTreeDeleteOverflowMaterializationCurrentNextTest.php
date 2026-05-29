<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteOverflowCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
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
    $pages[1] = $firstPage(10);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_a', str_repeat('a', 130)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_b', str_repeat('b', 128)])),
        SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
    ]);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 1])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_a', 2, str_repeat('i', 40)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_b', 3, str_repeat('j', 38)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 4])),
    ]);
    foreach ([6 => 'a', 7 => 'b', 8 => 'c', 9 => 'd', 10 => 'e'] as $pageNumber => $fill) {
        $pages[$pageNumber] = str_repeat($fill, 512);
    }

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 9, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry($pages, 10, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$fixture = static function () use ($databaseFixture): array {
    $database = $databaseFixture();
    $tableCurrentDelete = [
        'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true),
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [6, 7],
    ];
    $table = SQLiteBTreeDeleteOverflowCurrentNextPlan::tableLeafCurrentNext($database, 3, $tableCurrentDelete, 3, [8], true);

    $indexCurrentDelete = [
        'page' => SQLiteIndexLeafPage::deleteCellByRecordValues(
            $database->page(4),
            ['_transient_a', 2, str_repeat('i', 40)],
            secureDelete: true,
        ),
        'record_values' => ['_transient_a', 2, str_repeat('i', 40)],
        'obsolete_overflow_page_numbers' => [9],
    ];
    $index = SQLiteBTreeDeleteOverflowCurrentNextPlan::indexLeafCurrentNext(
        $database,
        4,
        $indexCurrentDelete,
        ['_transient_b', 3, str_repeat('j', 38)],
        [10],
        true,
    );

    return [$database, $table, $index];
};

$throwsMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$tableRows = static fn (string $page): array => array_map(
    static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
    SQLiteTableLeafCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512)),
);
$indexNames = static fn (string $page): array => array_map(
    static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0],
    SQLiteIndexCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512)),
);

$cases = [
    'table action' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'table leaf type' => static fn (array $fx): mixed => $fx[1]->leafPageType,
    'table leaf page' => static fn (array $fx): mixed => $fx[1]->leafPageNumber,
    'table current rowid' => static fn (array $fx): mixed => $fx[1]->current->deletedRowIds,
    'table next rowid' => static fn (array $fx): mixed => $fx[1]->next->deletedRowIds,
    'table current cell before' => static fn (array $fx): mixed => $fx[1]->current->cellCountBefore,
    'table current cell after' => static fn (array $fx): mixed => $fx[1]->current->cellCountAfter,
    'table next cell before materialized' => static fn (array $fx): mixed => $fx[1]->next->cellCountBefore,
    'table next cell after materialized' => static fn (array $fx): mixed => $fx[1]->next->cellCountAfter,
    'table current overflow pages' => static fn (array $fx): mixed => $fx[1]->current->obsoleteOverflowPageNumbers,
    'table next overflow pages' => static fn (array $fx): mixed => $fx[1]->next->obsoleteOverflowPageNumbers,
    'table all released overflow pages' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPageNumbers(),
    'table current freed pages' => static fn (array $fx): mixed => $fx[1]->current->freePlan->freedPageNumbers,
    'table next freed pages' => static fn (array $fx): mixed => $fx[1]->next->freePlan->freedPageNumbers,
    'table current freelist count' => static fn (array $fx): mixed => $fx[1]->current->freePlan->freelistPageCount,
    'table next freelist count' => static fn (array $fx): mixed => $fx[1]->next->freePlan->freelistPageCount,
    'table first trunk after current' => static fn (array $fx): mixed => $fx[1]->current->freePlan->firstFreelistTrunkPage,
    'table first trunk after next' => static fn (array $fx): mixed => $fx[1]->next->freePlan->firstFreelistTrunkPage,
    'table current page numbers include leaf' => static fn (array $fx): mixed => in_array(3, $fx[1]->toArray()['current_page_numbers'], true),
    'table next page numbers include leaf' => static fn (array $fx): mixed => in_array(3, $fx[1]->toArray()['next_page_numbers'], true),
    'table materialized page numbers' => static fn (array $fx): mixed => $fx[1]->materializedPageNumbers(),
    'table current database rows' => static fn (array $fx): mixed => $tableRows($fx[1]->databaseAfterCurrent->page(3)),
    'table next database rows' => static fn (array $fx): mixed => $tableRows($fx[1]->databaseAfterNext->page(3)),
    'table current removed row missing' => static fn (array $fx): mixed => !in_array(2, $tableRows($fx[1]->databaseAfterCurrent->page(3)), true),
    'table next removed row missing' => static fn (array $fx): mixed => !in_array(3, $tableRows($fx[1]->databaseAfterNext->page(3)), true),
    'table current keeps next target' => static fn (array $fx): mixed => in_array(3, $tableRows($fx[1]->databaseAfterCurrent->page(3)), true),
    'table current leaf integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->current->leafPageImage, 512)->freeblockIntegrityReport($fx[1]->current->leafPageImage)['status'],
    'table next leaf integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->next->leafPageImage, 512)->freeblockIntegrityReport($fx[1]->next->leafPageImage)['status'],
    'table current freeblocks gone' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->current->leafPageImage, 512)->firstFreeblockOffset,
    'table next freeblocks gone' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->next->leafPageImage, 512)->firstFreeblockOffset,
    'table current content start advances' => static fn (array $fx): mixed => $fx[1]->current->cellContentStartAfter > $fx[1]->current->cellContentStartBefore,
    'table next content start advances' => static fn (array $fx): mixed => $fx[1]->next->cellContentStartAfter > $fx[1]->next->cellContentStartBefore,
    'table event phases' => static fn (array $fx): mixed => array_column($fx[1]->events, 'phase'),
    'table first event rowids' => static fn (array $fx): mixed => $fx[1]->events[0]['deleted_rowids'],
    'table second event rowids' => static fn (array $fx): mixed => $fx[1]->events[1]['deleted_rowids'],
    'table toArray current count' => static fn (array $fx): mixed => $fx[1]->toArray()['current_freelist_count'],
    'table toArray next count' => static fn (array $fx): mixed => $fx[1]->toArray()['next_freelist_count'],
    'table pointer map page 6 free' => static fn (array $fx): mixed => $fx[1]->databaseAfterNext->pointerMapEntryForPage(6)->typeName(),
    'table pointer map page 8 free' => static fn (array $fx): mixed => $fx[1]->databaseAfterNext->pointerMapEntryForPage(8)->typeName(),
    'table page count stable' => static fn (array $fx): mixed => $fx[1]->databaseAfterNext->pageCount(),
    'index action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'index leaf type' => static fn (array $fx): mixed => $fx[2]->leafPageType,
    'index leaf page' => static fn (array $fx): mixed => $fx[2]->leafPageNumber,
    'index current record name' => static fn (array $fx): mixed => $fx[2]->current->deletedRecordValues[0][0],
    'index next record name' => static fn (array $fx): mixed => $fx[2]->next->deletedRecordValues[0][0],
    'index current cell before' => static fn (array $fx): mixed => $fx[2]->current->cellCountBefore,
    'index current cell after' => static fn (array $fx): mixed => $fx[2]->current->cellCountAfter,
    'index next cell before materialized' => static fn (array $fx): mixed => $fx[2]->next->cellCountBefore,
    'index next cell after materialized' => static fn (array $fx): mixed => $fx[2]->next->cellCountAfter,
    'index current overflow pages' => static fn (array $fx): mixed => $fx[2]->current->obsoleteOverflowPageNumbers,
    'index next overflow pages' => static fn (array $fx): mixed => $fx[2]->next->obsoleteOverflowPageNumbers,
    'index all released overflow pages' => static fn (array $fx): mixed => $fx[2]->releasedOverflowPageNumbers(),
    'index current freed pages' => static fn (array $fx): mixed => $fx[2]->current->freePlan->freedPageNumbers,
    'index next freed pages' => static fn (array $fx): mixed => $fx[2]->next->freePlan->freedPageNumbers,
    'index current freelist count' => static fn (array $fx): mixed => $fx[2]->current->freePlan->freelistPageCount,
    'index next freelist count' => static fn (array $fx): mixed => $fx[2]->next->freePlan->freelistPageCount,
    'index materialized page numbers' => static fn (array $fx): mixed => $fx[2]->materializedPageNumbers(),
    'index current database names' => static fn (array $fx): mixed => $indexNames($fx[2]->databaseAfterCurrent->page(4)),
    'index next database names' => static fn (array $fx): mixed => $indexNames($fx[2]->databaseAfterNext->page(4)),
    'index current removed name missing' => static fn (array $fx): mixed => !in_array('_transient_a', $indexNames($fx[2]->databaseAfterCurrent->page(4)), true),
    'index next removed name missing' => static fn (array $fx): mixed => !in_array('_transient_b', $indexNames($fx[2]->databaseAfterNext->page(4)), true),
    'index current keeps next target' => static fn (array $fx): mixed => in_array('_transient_b', $indexNames($fx[2]->databaseAfterCurrent->page(4)), true),
    'index current leaf integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->current->leafPageImage, 512)->freeblockIntegrityReport($fx[2]->current->leafPageImage)['status'],
    'index next leaf integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->next->leafPageImage, 512)->freeblockIntegrityReport($fx[2]->next->leafPageImage)['status'],
    'index current freeblocks gone' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->current->leafPageImage, 512)->firstFreeblockOffset,
    'index next freeblocks gone' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->next->leafPageImage, 512)->firstFreeblockOffset,
    'index current content start advances' => static fn (array $fx): mixed => $fx[2]->current->cellContentStartAfter > $fx[2]->current->cellContentStartBefore,
    'index next content start advances' => static fn (array $fx): mixed => $fx[2]->next->cellContentStartAfter > $fx[2]->next->cellContentStartBefore,
    'index event phases' => static fn (array $fx): mixed => array_column($fx[2]->events, 'phase'),
    'index first event record' => static fn (array $fx): mixed => $fx[2]->events[0]['deleted_record_values'][0][0],
    'index second event record' => static fn (array $fx): mixed => $fx[2]->events[1]['deleted_record_values'][0][0],
    'index pointer map page 9 free' => static fn (array $fx): mixed => $fx[2]->databaseAfterNext->pointerMapEntryForPage(9)->typeName(),
    'index pointer map page 10 free' => static fn (array $fx): mixed => $fx[2]->databaseAfterNext->pointerMapEntryForPage(10)->typeName(),
    'index page count stable' => static fn (array $fx): mixed => $fx[2]->databaseAfterNext->pageCount(),
    'rejects missing table current rowid' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteOverflowCurrentNextPlan::tableLeafCurrentNext($fx[0], 3, ['page' => $fx[0]->page(3), 'obsolete_overflow_page_numbers' => []], 3, [])),
    'rejects next missing rowid' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteOverflowCurrentNextPlan::tableLeafCurrentNext($fx[0], 3, ['page' => SQLiteTableLeafPage::deleteCellByRowId($fx[0]->page(3), 2), 'rowid' => 2, 'obsolete_overflow_page_numbers' => []], 99, [])),
    'rejects missing index current record' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteOverflowCurrentNextPlan::indexLeafCurrentNext($fx[0], 4, ['page' => $fx[0]->page(4), 'obsolete_overflow_page_numbers' => []], ['_transient_b', 3, str_repeat('j', 38)], [])),
    'rejects noninteger next overflow page' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteOverflowCurrentNextPlan::tableLeafCurrentNext($fx[0], 3, ['page' => SQLiteTableLeafPage::deleteCellByRowId($fx[0]->page(3), 2), 'rowid' => 2, 'obsolete_overflow_page_numbers' => []], 3, ['bad'])),
];

$expected = [
    'table action' => 'btree-delete-overflow-materialization-current-next',
    'table leaf type' => 'table-leaf',
    'table leaf page' => 3,
    'table current rowid' => [2],
    'table next rowid' => [3],
    'table current cell before' => 3,
    'table current cell after' => 3,
    'table next cell before materialized' => 2,
    'table next cell after materialized' => 2,
    'table current overflow pages' => [6, 7],
    'table next overflow pages' => [8],
    'table all released overflow pages' => [6, 7, 8],
    'table current freed pages' => [6, 7],
    'table next freed pages' => [8],
    'table current freelist count' => 2,
    'table next freelist count' => 3,
    'table first trunk after current' => 6,
    'table first trunk after next' => 6,
    'table current page numbers include leaf' => true,
    'table next page numbers include leaf' => true,
    'table materialized page numbers' => [1, 2, 3, 6, 7, 8],
    'table current database rows' => [1, 3, 4],
    'table next database rows' => [1, 4],
    'table current removed row missing' => true,
    'table next removed row missing' => true,
    'table current keeps next target' => true,
    'table current leaf integrity' => 'ok',
    'table next leaf integrity' => 'ok',
    'table current freeblocks gone' => 0,
    'table next freeblocks gone' => 0,
    'table current content start advances' => true,
    'table next content start advances' => true,
    'table event phases' => ['current-delete', 'next-delete'],
    'table first event rowids' => [2],
    'table second event rowids' => [3],
    'table toArray current count' => 2,
    'table toArray next count' => 3,
    'table pointer map page 6 free' => 'free-page',
    'table pointer map page 8 free' => 'free-page',
    'table page count stable' => 10,
    'index action' => 'btree-delete-overflow-materialization-current-next',
    'index leaf type' => 'index-leaf',
    'index leaf page' => 4,
    'index current record name' => '_transient_a',
    'index next record name' => '_transient_b',
    'index current cell before' => 3,
    'index current cell after' => 3,
    'index next cell before materialized' => 2,
    'index next cell after materialized' => 2,
    'index current overflow pages' => [9],
    'index next overflow pages' => [10],
    'index all released overflow pages' => [9, 10],
    'index current freed pages' => [9],
    'index next freed pages' => [10],
    'index current freelist count' => 1,
    'index next freelist count' => 2,
    'index materialized page numbers' => [1, 2, 4, 9, 10],
    'index current database names' => ['autoload', '_transient_b', 'siteurl'],
    'index next database names' => ['autoload', 'siteurl'],
    'index current removed name missing' => true,
    'index next removed name missing' => true,
    'index current keeps next target' => true,
    'index current leaf integrity' => 'ok',
    'index next leaf integrity' => 'ok',
    'index current freeblocks gone' => 0,
    'index next freeblocks gone' => 0,
    'index current content start advances' => true,
    'index next content start advances' => true,
    'index event phases' => ['current-delete', 'next-delete'],
    'index first event record' => '_transient_a',
    'index second event record' => '_transient_b',
    'index pointer map page 9 free' => 'free-page',
    'index pointer map page 10 free' => 'free-page',
    'index page count stable' => 10,
    'rejects missing table current rowid' => 'SQLite delete rebalance freeblock apply requires deleted table rowids',
    'rejects next missing rowid' => 'SQLite table leaf rowid was not found',
    'rejects missing index current record' => 'SQLite delete rebalance freeblock apply requires deleted index record values',
    'rejects noninteger next overflow page' => 'SQLite current/next delete materialization overflow page numbers must be integers',
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree delete overflow materialization current next ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

return $tests;
