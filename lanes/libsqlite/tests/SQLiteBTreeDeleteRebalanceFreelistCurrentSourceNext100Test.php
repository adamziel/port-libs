<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage100 = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 150), 28, 4);
    $page = substr_replace($page, pack('N', 5), 32, 4);
    $page = substr_replace($page, pack('N', 120), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry100 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 104) {
        return;
    }
    $pointerMapPage = $pageNumber < 104 ? 2 : 104;
    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), $offset, 5);
};

$databaseFixture100 = static function () use ($firstPage100, $putPointerMapEntry100): SQLiteDatabase {
    $pages = array_fill(1, 150, str_repeat("\0", 512));
    $pages[1] = $firstPage100();
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_cache_a', str_repeat('a', 124)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_cache_b', str_repeat('b', 122)])),
        SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
    ]);
    $existingFreelistLeaves = array_merge(range(20, 103), range(105, 139));
    $pages[5] = SQLiteFreelistTrunkPage::assemble(null, $existingFreelistLeaves, 512, 512);
    $pages[6] = str_repeat('x', 512);
    $pages[7] = str_repeat('y', 512);

    $putPointerMapEntry100($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry100($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry100($pages, 5, SQLitePointerMapEntry::FREE_PAGE, 0);
    $putPointerMapEntry100($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry100($pages, 7, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    foreach ($existingFreelistLeaves as $pageNumber) {
        $putPointerMapEntry100($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$fixture100 = static function () use ($databaseFixture100): SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan {
    return SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf($databaseFixture100(), 3, [
        ['rowid' => 2, 'obsolete_overflow_page_numbers' => [6]],
        ['rowid' => 3, 'obsolete_overflow_page_numbers' => [7]],
    ], true);
};

$rowIds100 = static function (SQLiteDatabase $database): array {
    return array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($database->page(3), SQLiteBTreePageHeader::parsePage($database->page(3), 512)),
    );
};

$throwsMessage100 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases100 = [
    'action stays current source' => static fn (): mixed => $fixture100()->toArray()['action'],
    'step count' => static fn (): mixed => count($fixture100()->steps),
    'first delete fills existing trunk leaf slot' => static fn (): mixed => $fixture100()->steps[0]->freePlan->leafPageNumbers,
    'first delete does not create new trunk' => static fn (): mixed => $fixture100()->steps[0]->freePlan->newTrunkPageNumbers,
    'first delete freelist count' => static fn (): mixed => $fixture100()->steps[0]->freePlan->freelistPageCount,
    'first delete first trunk remains existing' => static fn (): mixed => $fixture100()->steps[0]->freePlan->firstFreelistTrunkPage,
    'second delete creates new trunk' => static fn (): mixed => $fixture100()->steps[1]->freePlan->newTrunkPageNumbers,
    'second delete first trunk changes' => static fn (): mixed => $fixture100()->steps[1]->freePlan->firstFreelistTrunkPage,
    'second delete has no leaf append' => static fn (): mixed => $fixture100()->steps[1]->freePlan->leafPageNumbers,
    'second delete links old trunk as existing trunk' => static fn (): mixed => $fixture100()->steps[1]->freePlan->existingTrunkPageNumbers(),
    'final freelist count' => static fn (): mixed => $fixture100()->finalFreelistPageCount(),
    'final trunk count' => static fn (): mixed => count($fixture100()->finalFreelistTrunkPages()),
    'final first trunk page' => static fn (): mixed => $fixture100()->finalFreelistTrunkPages()[0]['page_number'],
    'final first trunk next page' => static fn (): mixed => $fixture100()->finalFreelistTrunkPages()[0]['next_trunk_page'],
    'final first trunk leaf count' => static fn (): mixed => count($fixture100()->finalFreelistTrunkPages()[0]['leaf_page_numbers']),
    'final second trunk page' => static fn (): mixed => $fixture100()->finalFreelistTrunkPages()[1]['page_number'],
    'final second trunk next page' => static fn (): mixed => $fixture100()->finalFreelistTrunkPages()[1]['next_trunk_page'],
    'final second trunk leaf count' => static fn (): mixed => count($fixture100()->finalFreelistTrunkPages()[1]['leaf_page_numbers']),
    'filled existing trunk retains first leaf' => static fn (): mixed => $fixture100()->finalFreelistTrunkPages()[1]['leaf_page_numbers'][0],
    'filled existing trunk appends overflow leaf last' => static fn (): mixed => $fixture100()->finalFreelistTrunkPages()[1]['leaf_page_numbers'][119],
    'allocation order begins with new trunk' => static fn (): mixed => array_slice($fixture100()->finalFreelistAllocationOrder(), 0, 3),
    'allocation order ends with old trunk' => static fn (): mixed => array_slice($fixture100()->finalFreelistAllocationOrder(), -3),
    'released overflow pages' => static fn (): mixed => $fixture100()->releasedOverflowPageNumbers(),
    'materialized page numbers' => static fn (): mixed => $fixture100()->materializedPageNumbers(),
    'final rowids' => static fn (): mixed => $rowIds100($fixture100()->databaseAfter),
    'deleted row two missing' => static fn (): mixed => !in_array(2, $rowIds100($fixture100()->databaseAfter), true),
    'deleted row three missing' => static fn (): mixed => !in_array(3, $rowIds100($fixture100()->databaseAfter), true),
    'siteurl remains' => static fn (): mixed => in_array(4, $rowIds100($fixture100()->databaseAfter), true),
    'final leaf integrity' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($fixture100()->databaseAfter->page(3), 512)->freeblockIntegrityReport($fixture100()->databaseAfter->page(3))['status'],
    'final leaf freeblock cleared' => static fn (): mixed => SQLiteBTreePageHeader::parsePage($fixture100()->databaseAfter->page(3), 512)->firstFreeblockOffset,
    'header first trunk' => static fn (): mixed => $fixture100()->databaseAfter->header->firstFreelistTrunkPage,
    'header freelist count' => static fn (): mixed => $fixture100()->databaseAfter->header->freelistPageCount,
    'pointer map page six free' => static fn (): mixed => $fixture100()->databaseAfter->pointerMapEntryForPage(6)->typeName(),
    'pointer map page seven free' => static fn (): mixed => $fixture100()->databaseAfter->pointerMapEntryForPage(7)->typeName(),
    'pointer map page six parent' => static fn (): mixed => $fixture100()->databaseAfter->pointerMapEntryForPage(6)->parentPageNumber,
    'pointer map page seven parent' => static fn (): mixed => $fixture100()->databaseAfter->pointerMapEntryForPage(7)->parentPageNumber,
    'page six cleared as secure delete leaf' => static fn (): mixed => $fixture100()->databaseAfter->page(6) === str_repeat("\0", 512),
    'page seven is trunk not cleared leaf' => static fn (): mixed => substr($fixture100()->databaseAfter->page(7), 0, 8),
    'toArray trunk pages match accessor' => static fn (): mixed => $fixture100()->toArray()['final_freelist_trunk_pages'] === $fixture100()->finalFreelistTrunkPages(),
    'toArray allocation order match accessor' => static fn (): mixed => $fixture100()->toArray()['final_freelist_allocation_order'] === $fixture100()->finalFreelistAllocationOrder(),
    'event first freed pages' => static fn (): mixed => $fixture100()->events[0]['freed_pages'],
    'event second freed pages' => static fn (): mixed => $fixture100()->events[1]['freed_pages'],
    'event first freelist count' => static fn (): mixed => $fixture100()->events[0]['freelist_page_count'],
    'event second freelist count' => static fn (): mixed => $fixture100()->events[1]['freelist_page_count'],
    'event first updated page numbers' => static fn (): mixed => $fixture100()->events[0]['updated_page_numbers'],
    'event second updated page numbers' => static fn (): mixed => $fixture100()->events[1]['updated_page_numbers'],
    'second free plan updated freelist pages' => static fn (): mixed => array_keys($fixture100()->steps[1]->freePlan->updatedFreelistPages),
    'second free plan pointer map pages' => static fn (): mixed => array_keys($fixture100()->steps[1]->freePlan->updatedPointerMapPages),
    'second free plan cleared pages' => static fn (): mixed => $fixture100()->steps[1]->freePlan->clearedPageNumbers,
    'second free pointer map entry page' => static fn (): mixed => $fixture100()->steps[1]->freePlan->freedPointerMapEntries[0]['page_number'],
    'second free pointer map entry type' => static fn (): mixed => $fixture100()->steps[1]->freePlan->freedPointerMapEntries[0]['type_name'],
    'rejects already free existing trunk page' => static fn (): mixed => $throwsMessage100(static fn () => $databaseFixture100()->planPageFreeList([5])),
    'rejects pointer map page free' => static fn (): mixed => $throwsMessage100(static fn () => $databaseFixture100()->planPageFreeList([2])),
];

$expected100 = [
    'action stays current source' => 'btree-delete-rebalance-freelist-current-source-next84',
    'step count' => 2,
    'first delete fills existing trunk leaf slot' => [6],
    'first delete does not create new trunk' => [],
    'first delete freelist count' => 121,
    'first delete first trunk remains existing' => 5,
    'second delete creates new trunk' => [7],
    'second delete first trunk changes' => 7,
    'second delete has no leaf append' => [],
    'second delete links old trunk as existing trunk' => [],
    'final freelist count' => 122,
    'final trunk count' => 2,
    'final first trunk page' => 7,
    'final first trunk next page' => 5,
    'final first trunk leaf count' => 0,
    'final second trunk page' => 5,
    'final second trunk next page' => null,
    'final second trunk leaf count' => 120,
    'filled existing trunk retains first leaf' => 20,
    'filled existing trunk appends overflow leaf last' => 6,
    'allocation order begins with new trunk' => [7, 20, 6],
    'allocation order ends with old trunk' => [22, 21, 5],
    'released overflow pages' => [6, 7],
    'materialized page numbers' => [1, 2, 3, 5, 6, 7],
    'final rowids' => [1, 4],
    'deleted row two missing' => true,
    'deleted row three missing' => true,
    'siteurl remains' => true,
    'final leaf integrity' => 'ok',
    'final leaf freeblock cleared' => 0,
    'header first trunk' => 7,
    'header freelist count' => 122,
    'pointer map page six free' => 'free-page',
    'pointer map page seven free' => 'free-page',
    'pointer map page six parent' => 0,
    'pointer map page seven parent' => 0,
    'page six cleared as secure delete leaf' => true,
    'page seven is trunk not cleared leaf' => pack('N', 5) . pack('N', 0),
    'toArray trunk pages match accessor' => true,
    'toArray allocation order match accessor' => true,
    'event first freed pages' => [6],
    'event second freed pages' => [7],
    'event first freelist count' => 121,
    'event second freelist count' => 122,
    'event first updated page numbers' => [1, 2, 3, 5, 6],
    'event second updated page numbers' => [1, 2, 3, 7],
    'second free plan updated freelist pages' => [7],
    'second free plan pointer map pages' => [2],
    'second free plan cleared pages' => [],
    'second free pointer map entry page' => 7,
    'second free pointer map entry type' => 'free-page',
    'rejects already free existing trunk page' => 'SQLite page 5 is already on the freelist',
    'rejects pointer map page free' => 'SQLite auto-vacuum pointer-map pages cannot be placed on the freelist',
];

$tests = [];
foreach ($cases100 as $name => $case) {
    $tests['btree delete rebalance freelist current source next100 ' . $name] = static function (TestRunner $t) use ($case, $expected100, $name): void {
        $t->same($expected100[$name], $case());
    };
}

return $tests;
