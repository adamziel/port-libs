<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage146 = static function (int $pageCount, int $firstFreelistTrunkPage = 10, int $freelistPageCount = 3, bool $autoVacuum = true): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    if ($autoVacuum) {
        $page = substr_replace($page, pack('N', 4), 52, 4);
        $page = substr_replace($page, pack('N', 1), 56, 4);
    }

    return $page;
};

$putPointerMapEntry146 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$database146 = static function (bool $autoVacuum = true) use ($firstPage146, $putPointerMapEntry146): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage146(12, 10, 3, $autoVacuum);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(101, SQLiteRecord::encode([null, 'autoload', 'yes'])),
        SQLiteTableLeafCell::encode(102, SQLiteRecord::encode([null, '_transient_rewrite_rules', str_repeat('r', 190)])),
        SQLiteTableLeafCell::encode(103, SQLiteRecord::encode([null, '_site_transient_update_core', 'fresh'])),
    ]);
    $pages[6] = pack('N', 7) . str_repeat('A', 508);
    $pages[7] = pack('N', 0) . str_repeat('B', 508);
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [11, 12], 512);

    if ($autoVacuum) {
        foreach ([
            3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
            6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
            7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
            10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
            11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
            12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        ] as $pageNumber => [$type, $parent]) {
            $putPointerMapEntry146($pages, $pageNumber, $type, $parent);
        }
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$deleteResult146 = static function (SQLiteDatabase $database): array {
    return [
        'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 102, secureDelete: true),
        'rowid' => 102,
        'obsolete_overflow_page_numbers' => [6, 7],
    ];
};

$plan146 = static function (bool $secureDelete = true) use ($database146, $deleteResult146): SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan {
    $database = $database146();

    return SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult(
        $database,
        3,
        $deleteResult146($database),
        3,
        str_repeat('Z', 1200),
        $secureDelete,
    );
};

$throwsMessage146 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$leafHeader146 = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan146()->rebalancePlan->leafPageImage, 512);
$currentRows146 = static fn (): array => $plan146()->currentRows();
$nextRows146 = static fn (): array => $plan146()->nextRows();

$cases146 = [
    'action label' => static fn (): mixed => $plan146()->toArray()['action'],
    'leaf page' => static fn (): mixed => $plan146()->rebalancePlan->leafPageNumber,
    'leaf page type' => static fn (): mixed => $plan146()->rebalancePlan->leafPageType,
    'deleted rowids' => static fn (): mixed => $plan146()->rebalancePlan->deletedRowIds,
    'rebalance cell count before' => static fn (): mixed => $plan146()->rebalancePlan->cellCountBefore,
    'rebalance cell count after' => static fn (): mixed => $plan146()->rebalancePlan->cellCountAfter,
    'freeblock bytes before positive' => static fn (): mixed => $plan146()->rebalancePlan->freeblockBytesBefore > 0,
    'freeblock bytes after' => static fn (): mixed => $plan146()->rebalancePlan->freeblockBytesAfter,
    'fragmented bytes after' => static fn (): mixed => $plan146()->rebalancePlan->fragmentedBytesAfter,
    'leaf integrity status' => static fn (): mixed => $leafHeader146()->freeblockIntegrityReport($plan146()->rebalancePlan->leafPageImage)['status'],
    'remaining rowids' => static fn (): mixed => array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($plan146()->rebalancePlan->leafPageImage, $leafHeader146())),
    'released overflow pages' => static fn (): mixed => $plan146()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $plan146()->allocatedOverflowPages(),
    'reused released overflow pages' => static fn (): mixed => $plan146()->reusedReleasedOverflowPages(),
    'overflow image keys' => static fn (): mixed => array_keys($plan146()->overflowPageImages()),
    'current row pages' => static fn (): mixed => array_column($currentRows146(), 'page_number'),
    'current row pointer types' => static fn (): mixed => array_column($currentRows146(), 'before_pointer_map_type'),
    'current row pointer parents' => static fn (): mixed => array_column($currentRows146(), 'before_pointer_map_parent'),
    'current row obsolete positions' => static fn (): mixed => array_column($currentRows146(), 'obsolete_position'),
    'next row pages' => static fn (): mixed => array_column($nextRows146(), 'page_number'),
    'next row origins' => static fn (): mixed => array_column($nextRows146(), 'page_origin'),
    'next row before pointer types' => static fn (): mixed => array_column($nextRows146(), 'before_pointer_map_type'),
    'next row free pointer types' => static fn (): mixed => array_column($nextRows146(), 'free_pointer_map_type'),
    'next row next pointer types' => static fn (): mixed => array_column($nextRows146(), 'next_pointer_map_type'),
    'next row next pointer parents' => static fn (): mixed => array_column($nextRows146(), 'next_pointer_map_parent'),
    'next row overflow next pages' => static fn (): mixed => array_column($nextRows146(), 'next_overflow_next_page'),
    'next row tail flags' => static fn (): mixed => array_column($nextRows146(), 'next_overflow_is_tail'),
    'allocation sources' => static fn (): mixed => array_column($nextRows146(), 'allocation_source'),
    'allocation trunks' => static fn (): mixed => array_column($nextRows146(), 'allocation_trunk_page'),
    'post rebalance page 6 free' => static fn (): mixed => $plan146()->databaseAfterRebalance->pointerMapEntryForPage(6)->typeName(),
    'post rebalance page 7 parent zero' => static fn (): mixed => $plan146()->databaseAfterRebalance->pointerMapEntryForPage(7)->parentPageNumber,
    'post allocation page 11 first overflow' => static fn (): mixed => $plan146()->databaseAfterAllocation->pointerMapEntryForPage(11)->typeName(),
    'post allocation page 7 parent' => static fn (): mixed => $plan146()->databaseAfterAllocation->pointerMapEntryForPage(7)->parentPageNumber,
    'post allocation page 6 parent' => static fn (): mixed => $plan146()->databaseAfterAllocation->pointerMapEntryForPage(6)->parentPageNumber,
    'final freelist pages' => static fn (): mixed => $plan146()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan146()->databaseAfterAllocation->header->freelistPageCount,
    'final first trunk' => static fn (): mixed => $plan146()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'updated page numbers' => static fn (): mixed => $plan146()->toArray()['updated_page_numbers'],
    'summary current pages' => static fn (): mixed => array_column($plan146()->toArray()['current_source_rows'], 'page_number'),
    'summary next pages' => static fn (): mixed => array_column($plan146()->toArray()['btree_pointermap_freeblock_rebalance_current_source_next146'], 'page_number'),
    'header image freelist count' => static fn (): mixed => SQLiteHeader::parse($plan146()->pageImages()[1])->freelistPageCount,
    'page image keys' => static fn (): mixed => array_keys($plan146()->pageImages()),
    'non autovacuum rejected' => static fn (): mixed => $throwsMessage146(static function () use ($database146, $deleteResult146): void {
        $database = $database146(false);
        SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult($database, 3, $deleteResult146($database), 3, 'z');
    }),
    'empty payload rejected' => static fn (): mixed => $throwsMessage146(static function () use ($database146, $deleteResult146): void {
        $database = $database146();
        SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult($database, 3, $deleteResult146($database), 3, '');
    }),
    'bad parent rejected' => static fn (): mixed => $throwsMessage146(static function () use ($database146, $deleteResult146): void {
        $database = $database146();
        SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult($database, 3, $deleteResult146($database), 1, 'z');
    }),
];

$expected146 = [
    'action label' => 'btree-pointermap-freeblock-rebalance-current-source-next146',
    'leaf page' => 3,
    'leaf page type' => 'table-leaf',
    'deleted rowids' => [102],
    'rebalance cell count before' => 2,
    'rebalance cell count after' => 2,
    'freeblock bytes before positive' => true,
    'freeblock bytes after' => 0,
    'fragmented bytes after' => 0,
    'leaf integrity status' => 'ok',
    'remaining rowids' => [101, 103],
    'released overflow pages' => [6, 7],
    'allocated overflow pages' => [11, 7, 6],
    'reused released overflow pages' => [6, 7],
    'overflow image keys' => [11, 7, 6],
    'current row pages' => [6, 7],
    'current row pointer types' => ['first-overflow-page', 'overflow-page'],
    'current row pointer parents' => [3, 6],
    'current row obsolete positions' => [0, 1],
    'next row pages' => [11, 7, 6],
    'next row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page'],
    'next row before pointer types' => ['free-page', 'overflow-page', 'first-overflow-page'],
    'next row free pointer types' => ['free-page', 'free-page', 'free-page'],
    'next row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'next row next pointer parents' => [3, 11, 7],
    'next row overflow next pages' => [7, 6, 0],
    'next row tail flags' => [false, false, true],
    'allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'allocation trunks' => [10, 10, 10],
    'post rebalance page 6 free' => 'free-page',
    'post rebalance page 7 parent zero' => 0,
    'post allocation page 11 first overflow' => 'first-overflow-page',
    'post allocation page 7 parent' => 11,
    'post allocation page 6 parent' => 7,
    'final freelist pages' => [10, 12],
    'final freelist count' => 2,
    'final first trunk' => 10,
    'updated page numbers' => [1, 2, 3, 6, 7, 10, 11],
    'summary current pages' => [6, 7],
    'summary next pages' => [11, 7, 6],
    'header image freelist count' => 2,
    'page image keys' => [1, 2, 3, 6, 7, 10, 11],
    'non autovacuum rejected' => 'SQLite b-tree pointer-map freeblock rebalance next146 requires an auto-vacuum database',
    'empty payload rejected' => 'SQLite b-tree pointer-map freeblock rebalance next146 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree pointer-map freeblock rebalance next146 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases146 as $name => $callback) {
    $tests['btree pointermap freeblock rebalance current source next146 ' . $name] = static function (TestRunner $t) use ($callback, $expected146, $name): void {
        $t->same($expected146[$name], $callback());
    };
}

foreach (range(1, 28) as $index) {
    $tests['btree pointermap freeblock rebalance current source next146 invariant ' . $index] = static function (TestRunner $t) use ($plan146): void {
        $plan = $plan146();

        $t->same([6, 7], $plan->releasedOverflowPages());
        $t->same([11, 7, 6], $plan->allocatedOverflowPages());
        $t->same([6, 7], $plan->reusedReleasedOverflowPages());
        $t->same(['free-page', 'free-page', 'free-page'], array_column($plan->nextRows(), 'free_pointer_map_type'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->nextRows(), 'next_pointer_map_type'));
        $t->same([3, 11, 7], array_column($plan->nextRows(), 'next_pointer_map_parent'));
        $t->same([7, 6, 0], array_column($plan->nextRows(), 'next_overflow_next_page'));
        $t->same([10, 12], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
