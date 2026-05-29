<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
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

$fixture = static function () use ($firstPage, $putPointerMapEntry): array {
    $pages = array_fill(1, 14, str_repeat("\0", 512));
    $pages[1] = $firstPage(14);
    $pages[2] = str_repeat("\0", 512);

    $tableA = SQLiteTableLeafCell::encodeWithOverflowPages(11, SQLiteRecord::encode([null, '_transient_timeout_a86', str_repeat('a', 900)]), 6, 512);
    $tableB = SQLiteTableLeafCell::encodeWithOverflowPages(12, SQLiteRecord::encode([null, '_transient_timeout_b86', str_repeat('b', 520)]), 8, 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'autoload', 'yes'])),
        $tableA['cell'],
        $tableB['cell'],
    ]);
    foreach ([6 => $tableA['overflowPages'][0], 8 => $tableB['overflowPages'][0]] as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    $idxAValues = ['no', '_transient_timeout_a86', str_repeat('idx-a', 180), 11];
    $idxBValues = ['no', '_transient_timeout_b86', str_repeat('idx-b', 120), 12];
    $idxA = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($idxAValues), 9, 512);
    $idxB = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($idxBValues), 11, 512);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload', 10])),
        $idxA['cell'],
        $idxB['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 13])),
    ]);
    foreach ([9 => $idxA['overflowPages'][0], 10 => $idxA['overflowPages'][1], 11 => $idxB['overflowPages'][0], 12 => $idxB['overflowPages'][1]] as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    foreach ([
        3 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
        4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        9 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
        11 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
        12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 11],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $overflowReader = static function (int $firstPage, int $byteCount) use ($database): string {
        $payload = '';
        $pageNumber = $firstPage;
        while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
            $page = $database->page($pageNumber);
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $payload .= substr($page, 4);
        }

        return substr($payload, 0, $byteCount);
    };
    $table = SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan::tableLeafCurrentNext($database, 3, 11, 12, true);
    $index = SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan::indexLeafCurrentNext($database, 4, $idxAValues, $idxBValues, true, overflowReader: $overflowReader);

    return [$database, $table, $index, $idxAValues, $idxBValues];
};

$overflowPayload = static function (SQLiteDatabase $db, int $firstPage, int $byteCount): string {
    $payload = '';
    $pageNumber = $firstPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $db->page($pageNumber);
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};

$rowids = static fn (SQLiteDatabase $db): array => array_map(
    static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
    SQLiteTableLeafCell::parsePageCells(
        $db->page(3),
        SQLiteBTreePageHeader::parsePage($db->page(3), 512),
        $db->usablePageSize(),
        static fn (int $firstPage, int $byteCount): string => $overflowPayload($db, $firstPage, $byteCount),
    ),
);
$indexNames = static fn (SQLiteDatabase $db): array => array_map(
    static fn (SQLiteIndexCell $cell): mixed => $cell->record()->values[1],
    SQLiteIndexCell::parsePageCells(
        $db->page(4),
        SQLiteBTreePageHeader::parsePage($db->page(4), 512),
        $db->usablePageSize(),
        static fn (int $firstPage, int $byteCount): string => $overflowPayload($db, $firstPage, $byteCount),
    ),
);

$cases = [
    'table action' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'table leaf type' => static fn (array $fx): mixed => $fx[1]->deletePlan->leafPageType,
    'table released current source pages' => static fn (array $fx): mixed => $fx[1]->deletePlan->current->obsoleteOverflowPageNumbers,
    'table released next source pages' => static fn (array $fx): mixed => $fx[1]->deletePlan->next->obsoleteOverflowPageNumbers,
    'table released all pages' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPageNumbers(),
    'table materialized pages' => static fn (array $fx): mixed => $fx[1]->materializedPageNumbers(),
    'table current rows' => static fn (array $fx): mixed => $rowids($fx[1]->deletePlan->databaseAfterCurrent),
    'table next rows' => static fn (array $fx): mixed => $rowids($fx[1]->deletePlan->databaseAfterNext),
    'table current keeps next row' => static fn (array $fx): mixed => in_array(12, $rowids($fx[1]->deletePlan->databaseAfterCurrent), true),
    'table next removes both transient rows' => static fn (array $fx): mixed => array_intersect([11, 12], $rowids($fx[1]->deletePlan->databaseAfterNext)),
    'table current free pages' => static fn (array $fx): mixed => $fx[1]->deletePlan->current->freePlan->freedPageNumbers,
    'table next free pages' => static fn (array $fx): mixed => $fx[1]->deletePlan->next->freePlan->freedPageNumbers,
    'table next freelist count' => static fn (array $fx): mixed => $fx[1]->deletePlan->next->freePlan->freelistPageCount,
    'table first trunk after next' => static fn (array $fx): mixed => $fx[1]->deletePlan->next->freePlan->firstFreelistTrunkPage,
    'table transition pages' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'page_number'),
    'table transition current types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'current_type_name'),
    'table transition current parents' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'current_parent_page_number'),
    'table after current types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'after_current_type_name'),
    'table after next types' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'after_next_type_name'),
    'table after next parents' => static fn (array $fx): mixed => array_column($fx[1]->pointerMapTransitions, 'after_next_parent_page_number'),
    'table summary pointer transitions count' => static fn (array $fx): mixed => count($fx[1]->toArray()['pointer_map_transitions']),
    'table page 6 free after current' => static fn (array $fx): mixed => $fx[1]->deletePlan->databaseAfterCurrent->pointerMapEntryForPage(6)->typeName(),
    'table page 8 still first overflow after current' => static fn (array $fx): mixed => $fx[1]->deletePlan->databaseAfterCurrent->pointerMapEntryForPage(8)->typeName(),
    'table page 8 free after next' => static fn (array $fx): mixed => $fx[1]->deletePlan->databaseAfterNext->pointerMapEntryForPage(8)->typeName(),
    'table page 6 parent changes to zero' => static fn (array $fx): mixed => $fx[1]->deletePlan->databaseAfterNext->pointerMapEntryForPage(6)->parentPageNumber,
    'table leaf freeblock integrity current' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->deletePlan->current->leafPageImage, 512)->freeblockIntegrityReport($fx[1]->deletePlan->current->leafPageImage)['status'],
    'table leaf freeblock integrity next' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[1]->deletePlan->next->leafPageImage, 512)->freeblockIntegrityReport($fx[1]->deletePlan->next->leafPageImage)['status'],
    'index action' => static fn (array $fx): mixed => $fx[2]->toArray()['action'],
    'index leaf type' => static fn (array $fx): mixed => $fx[2]->deletePlan->leafPageType,
    'index released current source pages' => static fn (array $fx): mixed => $fx[2]->deletePlan->current->obsoleteOverflowPageNumbers,
    'index released next source pages' => static fn (array $fx): mixed => $fx[2]->deletePlan->next->obsoleteOverflowPageNumbers,
    'index released all pages' => static fn (array $fx): mixed => $fx[2]->releasedOverflowPageNumbers(),
    'index materialized pages' => static fn (array $fx): mixed => $fx[2]->materializedPageNumbers(),
    'index current names' => static fn (array $fx): mixed => $indexNames($fx[2]->deletePlan->databaseAfterCurrent),
    'index next names' => static fn (array $fx): mixed => $indexNames($fx[2]->deletePlan->databaseAfterNext),
    'index current keeps next record' => static fn (array $fx): mixed => in_array('_transient_timeout_b86', $indexNames($fx[2]->deletePlan->databaseAfterCurrent), true),
    'index next removes both transient records' => static fn (array $fx): mixed => array_values(array_intersect(['_transient_timeout_a86', '_transient_timeout_b86'], $indexNames($fx[2]->deletePlan->databaseAfterNext))),
    'index current free pages' => static fn (array $fx): mixed => $fx[2]->deletePlan->current->freePlan->freedPageNumbers,
    'index next free pages' => static fn (array $fx): mixed => $fx[2]->deletePlan->next->freePlan->freedPageNumbers,
    'index next freelist count' => static fn (array $fx): mixed => $fx[2]->deletePlan->next->freePlan->freelistPageCount,
    'index transition pages' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'page_number'),
    'index transition current types' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'current_type_name'),
    'index transition current parents' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'current_parent_page_number'),
    'index after current types' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'after_current_type_name'),
    'index after next types' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'after_next_type_name'),
    'index after next parents' => static fn (array $fx): mixed => array_column($fx[2]->pointerMapTransitions, 'after_next_parent_page_number'),
    'index summary pointer transitions count' => static fn (array $fx): mixed => count($fx[2]->toArray()['pointer_map_transitions']),
    'index page 9 free after current' => static fn (array $fx): mixed => $fx[2]->deletePlan->databaseAfterCurrent->pointerMapEntryForPage(9)->typeName(),
    'index page 11 still first overflow after current' => static fn (array $fx): mixed => $fx[2]->deletePlan->databaseAfterCurrent->pointerMapEntryForPage(11)->typeName(),
    'index page 11 free after next' => static fn (array $fx): mixed => $fx[2]->deletePlan->databaseAfterNext->pointerMapEntryForPage(11)->typeName(),
    'index page 12 parent changes to zero' => static fn (array $fx): mixed => $fx[2]->deletePlan->databaseAfterNext->pointerMapEntryForPage(12)->parentPageNumber,
    'index leaf freeblock integrity current' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->deletePlan->current->leafPageImage, 512)->freeblockIntegrityReport($fx[2]->deletePlan->current->leafPageImage)['status'],
    'index leaf freeblock integrity next' => static fn (array $fx): mixed => SQLiteBTreePageHeader::parsePage($fx[2]->deletePlan->next->leafPageImage, 512)->freeblockIntegrityReport($fx[2]->deletePlan->next->leafPageImage)['status'],
    'summary nested delete action' => static fn (array $fx): mixed => $fx[2]->toArray()['delete']['action'],
    'summary current count' => static fn (array $fx): mixed => $fx[2]->toArray()['current_freelist_count'],
    'summary next count' => static fn (array $fx): mixed => $fx[2]->toArray()['next_freelist_count'],
    'rejects missing table current row' => static fn (array $fx): mixed => (static function () use ($fx): string {
        try {
            SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan::tableLeafCurrentNext($fx[0], 3, 99, 12);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
        return 'not rejected';
    })(),
    'rejects missing index next record' => static fn (array $fx): mixed => (static function () use ($fx): string {
        try {
            SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan::indexLeafCurrentNext($fx[0], 4, $fx[3], ['missing']);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
        return 'not rejected';
    })(),
];

$expected = [
    'table action' => 'btree-overflow-delete-pointermap-current-source-next86',
    'table leaf type' => 'table-leaf',
    'table released current source pages' => [6],
    'table released next source pages' => [8],
    'table released all pages' => [6, 8],
    'table materialized pages' => [1, 2, 3, 6, 8],
    'table current rows' => [10, 12],
    'table next rows' => [10],
    'table current keeps next row' => true,
    'table next removes both transient rows' => [],
    'table current free pages' => [6],
    'table next free pages' => [8],
    'table next freelist count' => 2,
    'table first trunk after next' => 6,
    'table transition pages' => [6, 8],
    'table transition current types' => ['first-overflow-page', 'first-overflow-page'],
    'table transition current parents' => [3, 3],
    'table after current types' => ['free-page', 'first-overflow-page'],
    'table after next types' => ['free-page', 'free-page'],
    'table after next parents' => [0, 0],
    'table summary pointer transitions count' => 2,
    'table page 6 free after current' => 'free-page',
    'table page 8 still first overflow after current' => 'first-overflow-page',
    'table page 8 free after next' => 'free-page',
    'table page 6 parent changes to zero' => 0,
    'table leaf freeblock integrity current' => 'ok',
    'table leaf freeblock integrity next' => 'ok',
    'index action' => 'btree-overflow-delete-pointermap-current-source-next86',
    'index leaf type' => 'index-leaf',
    'index released current source pages' => [9, 10],
    'index released next source pages' => [11, 12],
    'index released all pages' => [9, 10, 11, 12],
    'index materialized pages' => [1, 2, 4, 9, 10, 11, 12],
    'index current names' => ['autoload', '_transient_timeout_b86', 'siteurl'],
    'index next names' => ['autoload', 'siteurl'],
    'index current keeps next record' => true,
    'index next removes both transient records' => [],
    'index current free pages' => [9, 10],
    'index next free pages' => [11, 12],
    'index next freelist count' => 4,
    'index transition pages' => [9, 10, 11, 12],
    'index transition current types' => ['first-overflow-page', 'overflow-page', 'first-overflow-page', 'overflow-page'],
    'index transition current parents' => [4, 9, 4, 11],
    'index after current types' => ['free-page', 'free-page', 'first-overflow-page', 'overflow-page'],
    'index after next types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'index after next parents' => [0, 0, 0, 0],
    'index summary pointer transitions count' => 4,
    'index page 9 free after current' => 'free-page',
    'index page 11 still first overflow after current' => 'first-overflow-page',
    'index page 11 free after next' => 'free-page',
    'index page 12 parent changes to zero' => 0,
    'index leaf freeblock integrity current' => 'ok',
    'index leaf freeblock integrity next' => 'ok',
    'summary nested delete action' => 'btree-delete-overflow-materialization-current-next80',
    'summary current count' => 2,
    'summary next count' => 4,
    'rejects missing table current row' => 'SQLite table leaf rowid was not found',
    'rejects missing index next record' => 'SQLite index leaf cell record was not found',
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree overflow delete pointermap current source next86 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

return $tests;
