<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage116 = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 10), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry116 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$databaseFixture116 = static function () use ($firstPage116, $putPointerMapEntry116): SQLiteDatabase {
    $pages = array_fill(1, 10, str_repeat("\0", 512));
    $pages[1] = $firstPage116();
    $pages[2] = str_repeat("\0", 512);

    $firstPayload = SQLiteRecord::encode([null, '_transient_timeout_theme_roots', str_repeat('a', 1077)]);
    $secondPayload = SQLiteRecord::encode([null, '_transient_update_themes', str_repeat('b', 2095)]);
    $first = SQLiteTableLeafCell::encodeWithOverflowPages(301, $firstPayload, 5, 512);
    $second = SQLiteTableLeafCell::encodeWithOverflowPages(302, $secondPayload, 7, 512);

    $pages[3] = SQLiteTableLeafPage::assemble([$first['cell'], $second['cell']]);
    foreach ($first['overflowPages'] as $offset => $overflowPage) {
        $pages[5 + $offset] = $overflowPage;
    }
    foreach ($second['overflowPages'] as $offset => $overflowPage) {
        $pages[7 + $offset] = $overflowPage;
    }
    $putPointerMapEntry116($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry116($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry116($pages, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry116($pages, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);
    $putPointerMapEntry116($pages, 7, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry116($pages, 8, SQLitePointerMapEntry::OVERFLOW_PAGE, 7);
    $putPointerMapEntry116($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry116($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$throwsMessage116 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$fixture116 = static fn (): array => [
    $databaseFixture116(),
    SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan::tableLeaf($databaseFixture116(), 3, [301, 302], true),
];

$cases116 = [
    'action' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'leaf page type' => static fn (array $fx): mixed => $fx[1]->leafPageType,
    'step count' => static fn (array $fx): mixed => count($fx[1]->transitionRows()),
    'step types' => static fn (array $fx): mixed => array_column($fx[1]->transitionRows(), 'step_type'),
    'phases' => static fn (array $fx): mixed => array_column($fx[1]->transitionRows(), 'phase'),
    'derived chains' => static fn (array $fx): mixed => $fx[1]->toArray()['derived_overflow_page_numbers'],
    'first freed pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[0]['freed_pages'],
    'second freed pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[1]['freed_pages'],
    'released pages' => static fn (array $fx): mixed => $fx[1]->releasedPageNumbers(),
    'materialized pages' => static fn (array $fx): mixed => $fx[1]->materializedPageNumbers(),
    'first freelist count' => static fn (array $fx): mixed => $fx[1]->transitionRows()[0]['freelist_page_count'],
    'final freelist count' => static fn (array $fx): mixed => $fx[1]->finalFreelistPageCount(),
    'databaseAfter helper count' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->header->freelistPageCount,
    'first trunk page' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->header->firstFreelistTrunkPage,
    'freelist traversal' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->freelistPageNumbers(),
    'freelist allocation order' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->freelistAllocationOrder(),
    'leaf page cleared' => static fn (array $fx): mixed => trim($fx[1]->databaseAfter()->page(3), "\0") === '',
    'first trunk next' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->databaseAfter()->page(5), 0, 4))[1],
    'first trunk leaf count' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->databaseAfter()->page(5), 4, 4))[1],
    'first trunk first leaf' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->databaseAfter()->page(5), 8, 4))[1],
    'second chain secure deleted' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->page(7) === str_repeat("\0", 512),
    'first overflow pointer map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(5)->typeName(),
    'middle overflow pointer map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(8)->typeName(),
    'tail overflow pointer map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(10)->typeName(),
    'leaf pointer map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(3)->typeName(),
    'tail parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(10)->parentPageNumber,
    'leaf parent zero' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pointerMapEntryForPage(3)->parentPageNumber,
    'first updated pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[0]['updated_page_numbers'],
    'second updated pages' => static fn (array $fx): mixed => $fx[1]->transitionRows()[1]['updated_page_numbers'],
    'toArray step count' => static fn (array $fx): mixed => $fx[1]->toArray()['step_count'],
    'toArray final count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_freelist_page_count'],
    'toArray event derived first' => static fn (array $fx): mixed => $fx[1]->toArray()['events'][0]['obsolete_overflow_pages'],
    'toArray event derived second' => static fn (array $fx): mixed => $fx[1]->toArray()['events'][1]['obsolete_overflow_pages'],
    'database page count stable' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->pageCount(),
    'header database size stable' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->header->databaseSizePages,
    'survivor page remains' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->page(4) === $fx[0]->page(4),
    'tail overflow cleared' => static fn (array $fx): mixed => $fx[1]->databaseAfter()->page(10) === str_repeat("\0", 512),
    'rejects empty rowids' => static fn (array $fx): mixed => $throwsMessage116(static fn () => SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan::tableLeaf($fx[0], 3, [])),
    'rejects bad rowid' => static fn (array $fx): mixed => $throwsMessage116(static fn () => SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan::tableLeaf($fx[0], 3, ['301'])),
    'rejects missing rowid' => static fn (array $fx): mixed => $throwsMessage116(static fn () => SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan::tableLeaf($fx[0], 3, [999])),
    'rejects stale chain loop' => static function (array $fx) use ($throwsMessage116): mixed {
        $bytes = $fx[0]->toBytes();
        $bytes = substr_replace($bytes, pack('N', 5), (5 - 1) * 512, 4);

        return $throwsMessage116(static fn () => SQLiteBTreeOverflowFreepageApplyCurrentSourceNextPlan::tableLeaf(SQLiteDatabase::fromBytes($bytes), 3, [301]));
    },
];

$expected116 = [
    'action' => 'btree-overflow-freepage-apply-current-source-next116',
    'leaf page type' => 'table-leaf',
    'step count' => 2,
    'step types' => ['freeblock-rebalance', 'empty-leaf-free'],
    'phases' => ['table-delete-current-source', 'table-delete-current-source'],
    'derived chains' => [[5, 6], [7, 8, 9, 10]],
    'first freed pages' => [5, 6],
    'second freed pages' => [3, 7, 8, 9, 10],
    'released pages' => [5, 6, 3, 7, 8, 9, 10],
    'materialized pages' => [1, 2, 3, 5, 6, 7, 8, 9, 10],
    'first freelist count' => 2,
    'final freelist count' => 7,
    'databaseAfter helper count' => 7,
    'first trunk page' => 5,
    'freelist traversal' => [5, 6, 3, 7, 8, 9, 10],
    'freelist allocation order' => [6, 10, 9, 8, 7, 3, 5],
    'leaf page cleared' => true,
    'first trunk next' => 0,
    'first trunk leaf count' => 6,
    'first trunk first leaf' => 6,
    'second chain secure deleted' => true,
    'first overflow pointer map type' => 'free-page',
    'middle overflow pointer map type' => 'free-page',
    'tail overflow pointer map type' => 'free-page',
    'leaf pointer map type' => 'free-page',
    'tail parent zero' => 0,
    'leaf parent zero' => 0,
    'first updated pages' => [1, 2, 3, 5, 6],
    'second updated pages' => [1, 2, 3, 5, 7, 8, 9, 10],
    'toArray step count' => 2,
    'toArray final count' => 7,
    'toArray event derived first' => [5, 6],
    'toArray event derived second' => [7, 8, 9, 10],
    'database page count stable' => 10,
    'header database size stable' => 10,
    'survivor page remains' => true,
    'tail overflow cleared' => true,
    'rejects empty rowids' => 'SQLite overflow freepage apply current-source next116 requires at least one table rowid',
    'rejects bad rowid' => 'SQLite overflow freepage apply current-source next116 table rowids must be integers',
    'rejects missing rowid' => 'SQLite table leaf rowid was not found',
    'rejects stale chain loop' => 'SQLite overflow chain loops at page 5',
];

$tests = [];
foreach ($cases116 as $name => $case) {
    $tests['btree overflow freepage apply current source next116 ' . $name] = static function (TestRunner $t) use ($fixture116, $case, $expected116, $name): void {
        $t->same($expected116[$name], $case($fixture116()));
    };
}

return $tests;
