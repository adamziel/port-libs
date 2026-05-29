<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage120 = static function (int $pageCount): string {
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

$putPointerMapEntry120 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$tableDatabase120 = static function () use ($firstPage120, $putPointerMapEntry120): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage120(12);
    $pages[2] = str_repeat("\0", 512);
    $firstPayload = SQLiteRecord::encode([null, '_transient_timeout_plugins', str_repeat('a', 1077)]);
    $secondPayload = SQLiteRecord::encode([null, '_site_transient_update_plugins', str_repeat('b', 2095)]);
    $first = SQLiteTableLeafCell::encodeWithOverflowPages(21, $firstPayload, 6, 512);
    $second = SQLiteTableLeafCell::encodeWithOverflowPages(22, $secondPayload, 8, 512);
    $pages[3] = SQLiteTableLeafPage::assemble([$first['cell'], $second['cell']]);
    foreach ($first['overflowPages'] as $offset => $overflowPage) {
        $pages[6 + $offset] = $overflowPage;
    }
    foreach ($second['overflowPages'] as $offset => $overflowPage) {
        $pages[8 + $offset] = $overflowPage;
    }
    $putPointerMapEntry120($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry120($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry120($pages, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry120($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry120($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry120($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry120($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry120($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);
    $putPointerMapEntry120($pages, 11, SQLitePointerMapEntry::OVERFLOW_PAGE, 10);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$indexRecords120 = static fn (): array => [
    ['autoload', '_transient_timeout_plugins', str_repeat('a', 1077), 21],
    ['autoload', '_site_transient_update_plugins', str_repeat('b', 2095), 22],
];

$indexDatabase120 = static function () use ($firstPage120, $putPointerMapEntry120, $indexRecords120): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage120(12);
    $pages[2] = str_repeat("\0", 512);
    $first = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($indexRecords120()[0]), 6, 512);
    $second = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($indexRecords120()[1]), 8, 512);
    $pages[3] = SQLiteIndexLeafPage::assemble([$first['cell'], $second['cell']]);
    foreach ($first['overflowPages'] as $offset => $overflowPage) {
        $pages[6 + $offset] = $overflowPage;
    }
    foreach ($second['overflowPages'] as $offset => $overflowPage) {
        $pages[8 + $offset] = $overflowPage;
    }
    $putPointerMapEntry120($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 5);
    $putPointerMapEntry120($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry120($pages, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry120($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry120($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry120($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry120($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry120($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);
    $putPointerMapEntry120($pages, 11, SQLitePointerMapEntry::OVERFLOW_PAGE, 10);
    $putPointerMapEntry120($pages, 12, SQLitePointerMapEntry::OVERFLOW_PAGE, 11);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tableFixture120 = static fn (): SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::tableLeaf(
    $tableDatabase120(),
    3,
    [21, 22],
    true,
);

$indexFixture120 = static fn (): SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::indexLeaf(
    $indexDatabase120(),
    3,
    $indexRecords120(),
    true,
);

$throwsMessage120 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$commonCases120 = [
    'action' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'],
    'step count' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->toArray()['step_count'],
    'step types' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => array_column($plan->transitionRows(), 'step_type'),
    'derived overflow chains' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->toArray()['derived_overflow_page_numbers'],
    'first freed pages' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->transitionRows()[0]['freed_pages'],
    'second freed pages' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->transitionRows()[1]['freed_pages'],
    'released pages' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->releasedPageNumbers(),
    'materialized pages' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->materializedPageNumbers(),
    'first freelist count' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->transitionRows()[0]['freelist_page_count'],
    'final freelist count' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->finalFreelistPageCount(),
    'header freelist count' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->header->freelistPageCount,
    'first trunk page' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->header->firstFreelistTrunkPage,
    'freelist traversal' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->freelistPageNumbers(),
    'allocation order' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->finalFreelistAllocationOrder(),
    'leaf page cleared' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => trim($plan->databaseAfter()->page(3), "\0") === '',
    'first trunk next' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfter()->page(6), 0, 4))[1],
    'first trunk leaf count' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfter()->page(6), 4, 4))[1],
    'first trunk first leaf' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => unpack('N', substr($plan->databaseAfter()->page(6), 8, 4))[1],
    'tail page secure deleted' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->page(10) === str_repeat("\0", 512),
    'leaf pointer map type' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->pointerMapEntryForPage(3)->typeName(),
    'first overflow pointer map type' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->pointerMapEntryForPage(6)->typeName(),
    'tail overflow pointer map type' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->pointerMapEntryForPage(10)->typeName(),
    'leaf parent zero' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->pointerMapEntryForPage(3)->parentPageNumber,
    'tail parent zero' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->pointerMapEntryForPage(10)->parentPageNumber,
    'first updated pages' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->transitionRows()[0]['updated_page_numbers'],
    'second updated pages' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->transitionRows()[1]['updated_page_numbers'],
    'first write order starts leaf' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => array_slice($plan->events[0]['write_order_page_numbers'], 0, 3),
    'second write order includes header last' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => array_slice($plan->events[1]['write_order_page_numbers'], -1)[0],
    'database page count stable' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->pageCount(),
    'header database size stable' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter()->header->databaseSizePages,
    'toArray final count' => static fn (SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan $plan): mixed => $plan->toArray()['final_freelist_page_count'],
];

$expectedCommonTable120 = [
    'action' => 'btree-freelist-overflow-rebalance-current-source-next120',
    'step count' => 2,
    'step types' => ['freeblock-rebalance', 'empty-leaf-free'],
    'derived overflow chains' => [[6, 7], [8, 9, 10, 11]],
    'first freed pages' => [6, 7],
    'second freed pages' => [3, 8, 9, 10, 11],
    'released pages' => [6, 7, 3, 8, 9, 10, 11],
    'materialized pages' => [1, 2, 3, 6, 7, 8, 9, 10, 11],
    'first freelist count' => 2,
    'final freelist count' => 7,
    'header freelist count' => 7,
    'first trunk page' => 6,
    'freelist traversal' => [6, 7, 3, 8, 9, 10, 11],
    'allocation order' => [7, 11, 10, 9, 8, 3, 6],
    'leaf page cleared' => true,
    'first trunk next' => 0,
    'first trunk leaf count' => 6,
    'first trunk first leaf' => 7,
    'tail page secure deleted' => true,
    'leaf pointer map type' => 'free-page',
    'first overflow pointer map type' => 'free-page',
    'tail overflow pointer map type' => 'free-page',
    'leaf parent zero' => 0,
    'tail parent zero' => 0,
    'first updated pages' => [1, 2, 3, 6, 7],
    'second updated pages' => [1, 2, 3, 6, 8, 9, 10, 11],
    'first write order starts leaf' => [3, 6, 7],
    'second write order includes header last' => 11,
    'database page count stable' => 12,
    'header database size stable' => 12,
    'toArray final count' => 7,
];

$expectedCommonIndex120 = $expectedCommonTable120;
$expectedCommonIndex120['derived overflow chains'] = [[6, 7], [8, 9, 10, 11, 12]];
$expectedCommonIndex120['second freed pages'] = [3, 8, 9, 10, 11, 12];
$expectedCommonIndex120['released pages'] = [6, 7, 3, 8, 9, 10, 11, 12];
$expectedCommonIndex120['materialized pages'] = [1, 2, 3, 6, 7, 8, 9, 10, 11, 12];
$expectedCommonIndex120['final freelist count'] = 8;
$expectedCommonIndex120['header freelist count'] = 8;
$expectedCommonIndex120['freelist traversal'] = [6, 7, 3, 8, 9, 10, 11, 12];
$expectedCommonIndex120['allocation order'] = [7, 12, 11, 10, 9, 8, 3, 6];
$expectedCommonIndex120['first trunk leaf count'] = 7;
$expectedCommonIndex120['second updated pages'] = [1, 2, 3, 6, 8, 9, 10, 11, 12];
$expectedCommonIndex120['toArray final count'] = 8;
$expectedCommonIndex120['second write order includes header last'] = 12;

$tests = [];
foreach ($commonCases120 as $name => $case) {
    $tests['btree freelist overflow rebalance current source next120 table ' . $name] = static function (TestRunner $t) use ($tableFixture120, $case, $expectedCommonTable120, $name): void {
        $t->same($expectedCommonTable120[$name], $case($tableFixture120()));
    };
    $tests['btree freelist overflow rebalance current source next120 index ' . $name] = static function (TestRunner $t) use ($indexFixture120, $case, $expectedCommonIndex120, $name): void {
        $t->same($expectedCommonIndex120[$name], $case($indexFixture120()));
    };
}

$tests['btree freelist overflow rebalance current source next120 table phases'] = static function (TestRunner $t) use ($tableFixture120): void {
    $t->same(['table-delete-current-source', 'table-delete-current-source'], array_column($tableFixture120()->transitionRows(), 'phase'));
};
$tests['btree freelist overflow rebalance current source next120 index phases'] = static function (TestRunner $t) use ($indexFixture120): void {
    $t->same(['index-delete-current-source', 'index-delete-current-source'], array_column($indexFixture120()->transitionRows(), 'phase'));
};
$tests['btree freelist overflow rebalance current source next120 table deleted rowids'] = static function (TestRunner $t) use ($tableFixture120): void {
    $t->same([[21], [22]], array_map(static fn (array $event): array => $event['deleted_rowids'], $tableFixture120()->events));
};
$tests['btree freelist overflow rebalance current source next120 index deleted records'] = static function (TestRunner $t) use ($indexFixture120, $indexRecords120): void {
    $t->same([[$indexRecords120()[0]], [$indexRecords120()[1]]], array_map(static fn (array $event): array => $event['deleted_record_values'], $indexFixture120()->events));
};
$tests['btree freelist overflow rebalance current source next120 table leaf type'] = static function (TestRunner $t) use ($tableFixture120): void {
    $t->same('table-leaf', $tableFixture120()->leafPageType);
};
$tests['btree freelist overflow rebalance current source next120 index leaf type'] = static function (TestRunner $t) use ($indexFixture120): void {
    $t->same('index-leaf', $indexFixture120()->leafPageType);
};
$tests['btree freelist overflow rebalance current source next120 table final header page type gone'] = static function (TestRunner $t) use ($tableFixture120): void {
    $t->same('free-page', $tableFixture120()->databaseAfter()->pointerMapEntryForPage(3)->typeName());
};
$tests['btree freelist overflow rebalance current source next120 index final header page type gone'] = static function (TestRunner $t) use ($indexFixture120): void {
    $t->same('free-page', $indexFixture120()->databaseAfter()->pointerMapEntryForPage(3)->typeName());
};
$tests['btree freelist overflow rebalance current source next120 table rejects empty input'] = static function (TestRunner $t) use ($tableDatabase120, $throwsMessage120): void {
    $t->same('SQLite freelist overflow rebalance current-source next120 requires at least one table rowid', $throwsMessage120(static fn () => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::tableLeaf($tableDatabase120(), 3, [])));
};
$tests['btree freelist overflow rebalance current source next120 index rejects empty input'] = static function (TestRunner $t) use ($indexDatabase120, $throwsMessage120): void {
    $t->same('SQLite freelist overflow rebalance current-source next120 requires at least one index record', $throwsMessage120(static fn () => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::indexLeaf($indexDatabase120(), 3, [])));
};
$tests['btree freelist overflow rebalance current source next120 table rejects stale rowid'] = static function (TestRunner $t) use ($tableDatabase120, $throwsMessage120): void {
    $t->same('SQLite table leaf rowid was not found', $throwsMessage120(static fn () => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::tableLeaf($tableDatabase120(), 3, [999])));
};
$tests['btree freelist overflow rebalance current source next120 index rejects stale record'] = static function (TestRunner $t) use ($indexDatabase120, $throwsMessage120): void {
    $t->same('SQLite index leaf cell record was not found', $throwsMessage120(static fn () => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::indexLeaf($indexDatabase120(), 3, [['missing']])));
};
$tests['btree freelist overflow rebalance current source next120 table detects overflow loop'] = static function (TestRunner $t) use ($tableDatabase120, $throwsMessage120): void {
    $bytes = $tableDatabase120()->toBytes();
    $bytes = substr_replace($bytes, pack('N', 6), (6 - 1) * 512, 4);
    $t->same('SQLite overflow chain loops at page 6', $throwsMessage120(static fn () => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::tableLeaf(SQLiteDatabase::fromBytes($bytes), 3, [21])));
};
$tests['btree freelist overflow rebalance current source next120 index detects overflow loop'] = static function (TestRunner $t) use ($indexDatabase120, $indexRecords120, $throwsMessage120): void {
    $bytes = $indexDatabase120()->toBytes();
    $bytes = substr_replace($bytes, pack('N', 6), (6 - 1) * 512, 4);
    $t->same('SQLite overflow chain loops at page 6', $throwsMessage120(static fn () => SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::indexLeaf(SQLiteDatabase::fromBytes($bytes), 3, [$indexRecords120()[0]])));
};

return $tests;
