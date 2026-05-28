<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 12), 28, 4);
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
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage();
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_feed_a', str_repeat('a', 128)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_feed_b', str_repeat('b', 126)])),
        SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(5, SQLiteRecord::encode([null, 'blogname', 'Example'])),
    ]);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 1])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_feed_a', 2, str_repeat('i', 38)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_feed_b', 3, str_repeat('j', 36)])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 4])),
        SQLiteIndexCell::encode(SQLiteRecord::encode(['blogname', 5])),
    ]);

    foreach ([6 => 'a', 7 => 'b', 8 => 'c', 9 => 'd', 10 => 'e', 11 => 'f', 12 => 'g'] as $pageNumber => $fill) {
        $pages[$pageNumber] = str_repeat($fill, 512);
    }

    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 9, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);
    $putPointerMapEntry($pages, 11, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    $putPointerMapEntry($pages, 12, SQLitePointerMapEntry::BTREE_PAGE, 4);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tableRowIds = static fn (string $page): array => array_map(
    static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
    SQLiteTableLeafCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512)),
);
$indexNames = static fn (string $page): array => array_map(
    static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[0],
    SQLiteIndexCell::parsePageCells($page, SQLiteBTreePageHeader::parsePage($page, 512)),
);
$throwsMessage = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$fixture = static function () use ($databaseFixture): array {
    $database = $databaseFixture();
    $table = SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($database, 3, [
        ['rowid' => 2, 'obsolete_overflow_page_numbers' => [6, 7]],
        ['rowid' => 3, 'obsolete_overflow_page_numbers' => [8]],
    ], true);
    $index = SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::indexLeaf($database, 4, [
        ['record_values' => ['_transient_feed_a', 2, str_repeat('i', 38)], 'obsolete_overflow_page_numbers' => [9, 10]],
        ['record_values' => ['_transient_feed_b', 3, str_repeat('j', 36)], 'obsolete_overflow_page_numbers' => [11]],
    ], true);

    return [$database, $table, $index];
};

$cases = [
    'table action' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'table leaf type' => static fn (array $fx): mixed => $fx[1]->leafPageType,
    'table leaf page' => static fn (array $fx): mixed => $fx[1]->leafPageNumber,
    'table step count' => static fn (array $fx): mixed => count($fx[1]->steps),
    'table event phases' => static fn (array $fx): mixed => array_column($fx[1]->events, 'phase'),
    'table event indexes' => static fn (array $fx): mixed => array_column($fx[1]->events, 'index'),
    'table first deleted rowid' => static fn (array $fx): mixed => $fx[1]->steps[0]->deletedRowIds,
    'table second deleted rowid' => static fn (array $fx): mixed => $fx[1]->steps[1]->deletedRowIds,
    'table first before count' => static fn (array $fx): mixed => $fx[1]->steps[0]->cellCountBefore,
    'table first after count' => static fn (array $fx): mixed => $fx[1]->steps[0]->cellCountAfter,
    'table second before from current source' => static fn (array $fx): mixed => $fx[1]->steps[1]->cellCountBefore,
    'table second after count' => static fn (array $fx): mixed => $fx[1]->steps[1]->cellCountAfter,
    'table released overflow pages' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPageNumbers(),
    'table first freed pages' => static fn (array $fx): mixed => $fx[1]->steps[0]->freePlan->freedPageNumbers,
    'table second freed pages' => static fn (array $fx): mixed => $fx[1]->steps[1]->freePlan->freedPageNumbers,
    'table first freelist count' => static fn (array $fx): mixed => $fx[1]->steps[0]->freePlan->freelistPageCount,
    'table final freelist count' => static fn (array $fx): mixed => $fx[1]->finalFreelistPageCount(),
    'table materialized pages' => static fn (array $fx): mixed => $fx[1]->materializedPageNumbers(),
    'table database page count before' => static fn (array $fx): mixed => $fx[1]->toArray()['database_page_count_before'],
    'table database page count after' => static fn (array $fx): mixed => $fx[1]->toArray()['database_page_count_after'],
    'table remaining rowids' => static fn (array $fx): mixed => $tableRowIds($fx[1]->databaseAfter->page(3)),
    'table removed first row missing' => static fn (array $fx): mixed => !in_array(2, $tableRowIds($fx[1]->databaseAfter->page(3)), true),
    'table removed second row missing' => static fn (array $fx): mixed => !in_array(3, $tableRowIds($fx[1]->databaseAfter->page(3)), true),
    'table keeps siteurl' => static fn (array $fx): mixed => in_array(4, $tableRowIds($fx[1]->databaseAfter->page(3)), true),
    'table final leaf integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->databaseAfter->page(3), 512)->freeblockIntegrityReport($fx[1]->databaseAfter->page(3))['status'],
    'table final leaf no freeblocks' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->databaseAfter->page(3), 512)->firstFreeblockOffset,
    'table first pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(6)->typeName(),
    'table last pointer map free' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(8)->typeName(),
    'table pointer map parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(7)->parentPageNumber,
    'table toArray final count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_freelist_page_count'],
    'index action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'index leaf type' => static fn (array $fx): mixed => $fx[2]->leafPageType,
    'index leaf page' => static fn (array $fx): mixed => $fx[2]->leafPageNumber,
    'index step count' => static fn (array $fx): mixed => count($fx[2]->steps),
    'index event phases' => static fn (array $fx): mixed => array_column($fx[2]->events, 'phase'),
    'index first deleted name' => static fn (array $fx): mixed => $fx[2]->steps[0]->deletedRecordValues[0][0],
    'index second deleted name' => static fn (array $fx): mixed => $fx[2]->steps[1]->deletedRecordValues[0][0],
    'index first before count' => static fn (array $fx): mixed => $fx[2]->steps[0]->cellCountBefore,
    'index second before from current source' => static fn (array $fx): mixed => $fx[2]->steps[1]->cellCountBefore,
    'index second after count' => static fn (array $fx): mixed => $fx[2]->steps[1]->cellCountAfter,
    'index released overflow pages' => static fn (array $fx): mixed => $fx[2]->releasedOverflowPageNumbers(),
    'index first freed pages' => static fn (array $fx): mixed => $fx[2]->steps[0]->freePlan->freedPageNumbers,
    'index second freed pages' => static fn (array $fx): mixed => $fx[2]->steps[1]->freePlan->freedPageNumbers,
    'index first freelist count' => static fn (array $fx): mixed => $fx[2]->steps[0]->freePlan->freelistPageCount,
    'index final freelist count' => static fn (array $fx): mixed => $fx[2]->finalFreelistPageCount(),
    'index materialized pages' => static fn (array $fx): mixed => $fx[2]->materializedPageNumbers(),
    'index remaining names' => static fn (array $fx): mixed => $indexNames($fx[2]->databaseAfter->page(4)),
    'index removed first name missing' => static fn (array $fx): mixed => !in_array('_transient_feed_a', $indexNames($fx[2]->databaseAfter->page(4)), true),
    'index removed second name missing' => static fn (array $fx): mixed => !in_array('_transient_feed_b', $indexNames($fx[2]->databaseAfter->page(4)), true),
    'index keeps siteurl' => static fn (array $fx): mixed => in_array('siteurl', $indexNames($fx[2]->databaseAfter->page(4)), true),
    'index final leaf integrity' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->databaseAfter->page(4), 512)->freeblockIntegrityReport($fx[2]->databaseAfter->page(4))['status'],
    'index final leaf no freeblocks' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->databaseAfter->page(4), 512)->firstFreeblockOffset,
    'index first pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter->pointerMapEntryForPage(9)->typeName(),
    'index last pointer map free' => static fn (array $fx): mixed => $fx[2]->databaseAfter->pointerMapEntryForPage(11)->typeName(),
    'index pointer map parent zero' => static fn (array $fx): mixed => $fx[2]->databaseAfter->pointerMapEntryForPage(10)->parentPageNumber,
    'index toArray final count' => static fn (array $fx): mixed => $fx[2]->toArray()['final_freelist_page_count'],
    'rejects empty table sequence' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($fx[0], 3, [])),
    'rejects empty index sequence' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::indexLeaf($fx[0], 4, [])),
    'rejects noninteger table rowid' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($fx[0], 3, [['rowid' => '2', 'obsolete_overflow_page_numbers' => []]])),
    'rejects missing overflow list' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($fx[0], 3, [['rowid' => 2]])),
    'rejects noninteger overflow page' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($fx[0], 3, [['rowid' => 2, 'obsolete_overflow_page_numbers' => ['bad']]])),
    'rejects stale repeated rowid from current source' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($fx[0], 3, [
        ['rowid' => 2, 'obsolete_overflow_page_numbers' => [6]],
        ['rowid' => 2, 'obsolete_overflow_page_numbers' => [7]],
    ])),
    'rejects missing index record array' => static fn (array $fx): mixed => $throwsMessage(static fn () => SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::indexLeaf($fx[0], 4, [['obsolete_overflow_page_numbers' => []]])),
];

$expected = [
    'table action' => 'btree-delete-rebalance-freelist-current-source-next84',
    'table leaf type' => 'table-leaf',
    'table leaf page' => 3,
    'table step count' => 2,
    'table event phases' => ['table-delete', 'table-delete'],
    'table event indexes' => [0, 1],
    'table first deleted rowid' => [2],
    'table second deleted rowid' => [3],
    'table first before count' => 4,
    'table first after count' => 4,
    'table second before from current source' => 3,
    'table second after count' => 3,
    'table released overflow pages' => [6, 7, 8],
    'table first freed pages' => [6, 7],
    'table second freed pages' => [8],
    'table first freelist count' => 2,
    'table final freelist count' => 3,
    'table materialized pages' => [1, 2, 3, 6, 7, 8],
    'table database page count before' => 12,
    'table database page count after' => 12,
    'table remaining rowids' => [1, 4, 5],
    'table removed first row missing' => true,
    'table removed second row missing' => true,
    'table keeps siteurl' => true,
    'table final leaf integrity' => 'ok',
    'table final leaf no freeblocks' => 0,
    'table first pointer map free' => 'free-page',
    'table last pointer map free' => 'free-page',
    'table pointer map parent zero' => 0,
    'table toArray final count' => 3,
    'index action' => 'btree-delete-rebalance-freelist-current-source-next84',
    'index leaf type' => 'index-leaf',
    'index leaf page' => 4,
    'index step count' => 2,
    'index event phases' => ['index-delete', 'index-delete'],
    'index first deleted name' => '_transient_feed_a',
    'index second deleted name' => '_transient_feed_b',
    'index first before count' => 4,
    'index second before from current source' => 3,
    'index second after count' => 3,
    'index released overflow pages' => [9, 10, 11],
    'index first freed pages' => [9, 10],
    'index second freed pages' => [11],
    'index first freelist count' => 2,
    'index final freelist count' => 3,
    'index materialized pages' => [1, 2, 4, 9, 10, 11],
    'index remaining names' => ['autoload', 'siteurl', 'blogname'],
    'index removed first name missing' => true,
    'index removed second name missing' => true,
    'index keeps siteurl' => true,
    'index final leaf integrity' => 'ok',
    'index final leaf no freeblocks' => 0,
    'index first pointer map free' => 'free-page',
    'index last pointer map free' => 'free-page',
    'index pointer map parent zero' => 0,
    'index toArray final count' => 3,
    'rejects empty table sequence' => 'SQLite current-source delete rebalance requires at least one table delete',
    'rejects empty index sequence' => 'SQLite current-source delete rebalance requires at least one index delete',
    'rejects noninteger table rowid' => 'SQLite current-source table delete rowid must be an integer',
    'rejects missing overflow list' => 'SQLite current-source delete rebalance requires obsolete overflow page numbers',
    'rejects noninteger overflow page' => 'SQLite current-source delete rebalance overflow page numbers must be integers',
    'rejects stale repeated rowid from current source' => 'SQLite table leaf rowid was not found',
    'rejects missing index record array' => 'SQLite current-source index delete record values must be an array',
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree delete rebalance freelist current source next84 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

return $tests;
