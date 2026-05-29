<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage133 = static function (int $pageCount, int $firstFreelistTrunkPage = 0, int $freelistPageCount = 0): string {
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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry133 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = $pageNumber < 105 ? 2 : 105;
    if ($pageNumber === $pointerMapPage) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$database133 = static function (int $pageCount = 106) use ($makeFirstPage133, $putPointerMapEntry133): SQLiteDatabase {
    $pages = array_fill(1, $pageCount, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage133($pageCount);
    $pages[2] = str_repeat("\0", 512);
    if ($pageCount >= 105) {
        $pages[105] = str_repeat("\0", 512);
    }
    $pages[3] = str_repeat("\0", 512);
    $pages[3][0] = "\x0d";
    $pages[104] = pack('N', 106) . str_repeat('D', 508);
    $pages[106] = pack('N', 0) . str_repeat('E', 508);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        104 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        106 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 104],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry133($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$deleteResults133 = [[
    'source' => 'wp_options-autoload-transient-current-source-next133',
    'leaf_page' => 3,
    'obsolete_overflow_page_numbers' => [104, 106],
    'rowids' => [13301],
]];

$plan133 = static fn (): SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan => SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan::fromDeleteResults(
    $database133(),
    $deleteResults133,
    3,
    3,
    str_repeat('N', 600),
);

$throwMessage133 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows133 = static fn (): array => $plan133()->rows;
$rowByPage133 = static function (int $pageNumber) use ($rows133): array {
    foreach ($rows133() as $row) {
        if ($row['page_number'] === $pageNumber) {
            return $row;
        }
    }

    return [];
};

$cases133 = [
    'action label' => static fn (): mixed => $plan133()->toArray()['action'],
    'released overflow pages' => static fn (): mixed => $plan133()->releasedOverflowPages(),
    'truncated page numbers' => static fn (): mixed => $plan133()->truncatedPageNumbers(),
    'recreated pointer map pages' => static fn (): mixed => $plan133()->recreatedPointerMapPages(),
    'allocated overflow pages' => static fn (): mixed => $plan133()->allocatedOverflowPages(),
    'appended overflow pages' => static fn (): mixed => $plan133()->allocationPlan->appendedPageNumbers,
    'vacuum final page count' => static fn (): mixed => $plan133()->vacuumPlan->finalDatabasePageCount(),
    'final database page count' => static fn (): mixed => $plan133()->databaseAfterAllocation->pageCount(),
    'final header page count' => static fn (): mixed => $plan133()->databaseAfterAllocation->header->databaseSizePages,
    'final first freelist trunk' => static fn (): mixed => $plan133()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final freelist count' => static fn (): mixed => $plan133()->databaseAfterAllocation->header->freelistPageCount,
    'final freelist pages' => static fn (): mixed => $plan133()->databaseAfterAllocation->freelistPageNumbers(),
    'allocation pointer map pages' => static fn (): mixed => array_keys($plan133()->allocationPlan->updatedPointerMapPages),
    'allocation pointer map entries pages' => static fn (): mixed => array_column($plan133()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer map entries types' => static fn (): mixed => array_column($plan133()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer map entries parents' => static fn (): mixed => array_column($plan133()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'allocation sources' => static fn (): mixed => array_column($plan133()->allocationPlan->allocationSteps(), 'source'),
    'row page numbers' => static fn (): mixed => array_column($rows133(), 'page_number'),
    'row statuses' => static fn (): mixed => array_column($rows133(), 'page_status'),
    'row release sources' => static fn (): mixed => array_column($rows133(), 'release_source'),
    'row allocated flags' => static fn (): mixed => array_column($rows133(), 'allocated_after_vacuum'),
    'row truncated flags' => static fn (): mixed => array_column($rows133(), 'truncated_by_vacuum'),
    'row recreated flags' => static fn (): mixed => array_column($rows133(), 'recreated_pointer_map_page'),
    'row current types' => static fn (): mixed => array_column($rows133(), 'current_pointer_map_type'),
    'row current parents' => static fn (): mixed => array_column($rows133(), 'current_pointer_map_parent'),
    'row next types' => static fn (): mixed => array_column($rows133(), 'next_pointer_map_type'),
    'row next parents' => static fn (): mixed => array_column($rows133(), 'next_pointer_map_parent'),
    'row current next pages' => static fn (): mixed => array_column($rows133(), 'current_overflow_next_page'),
    'row next overflow pages' => static fn (): mixed => array_column($rows133(), 'next_overflow_next_page'),
    'source pointer map page 105' => static fn (): mixed => $database133()->isPointerMapPage(105),
    'final pointer map page 105' => static fn (): mixed => $plan133()->databaseAfterAllocation->isPointerMapPage(105),
    'source pointer entry 104' => static fn (): mixed => $database133()->pointerMapEntryForPage(104)->toArray()['type_name'],
    'source pointer entry 106' => static fn (): mixed => $database133()->pointerMapEntryForPage(106)->toArray()['type_name'],
    'final pointer entry 104' => static fn (): mixed => $plan133()->databaseAfterAllocation->pointerMapEntryForPage(104)->toArray()['type_name'],
    'final pointer entry 106' => static fn (): mixed => $plan133()->databaseAfterAllocation->pointerMapEntryForPage(106)->toArray()['type_name'],
    'final pointer parent 104' => static fn (): mixed => $plan133()->databaseAfterAllocation->pointerMapEntryForPage(104)->toArray()['parent_page_number'],
    'final pointer parent 106' => static fn (): mixed => $plan133()->databaseAfterAllocation->pointerMapEntryForPage(106)->toArray()['parent_page_number'],
    'final overflow 104 next' => static fn (): mixed => unpack('N', substr($plan133()->databaseAfterAllocation->page(104), 0, 4))[1],
    'final overflow 106 next' => static fn (): mixed => unpack('N', substr($plan133()->databaseAfterAllocation->page(106), 0, 4))[1],
    'final overflow 104 payload' => static fn (): mixed => substr($plan133()->databaseAfterAllocation->page(104), 4, 8),
    'final overflow 106 payload' => static fn (): mixed => substr($plan133()->databaseAfterAllocation->page(106), 4, 8),
    'updated page numbers' => static fn (): mixed => $plan133()->toArray()['updated_page_numbers'],
    'page image keys' => static fn (): mixed => array_keys($plan133()->pageImages()),
    'overflow image keys' => static fn (): mixed => array_keys($plan133()->overflowPageImages()),
    'vacuum materialized omitted pages' => static fn (): mixed => $plan133()->vacuumPlan->materializedApplySummary()['omitted_truncated_page_numbers'],
    'vacuum materialized byte length' => static fn (): mixed => $plan133()->vacuumPlan->materializedApplySummary()['byte_length'],
    'final header from image' => static fn (): mixed => SQLiteHeader::parse($plan133()->pageImages()[1])->databaseSizePages,
    'row 105 status' => static fn (): mixed => $rowByPage133(105)['page_status'],
    'row 104 status' => static fn (): mixed => $rowByPage133(104)['page_status'],
    'row 106 status' => static fn (): mixed => $rowByPage133(106)['page_status'],
    'empty payload rejected' => static fn (): mixed => $throwMessage133(static fn () => SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan::fromDeleteResults($database133(), $deleteResults133, 3, 3, '')),
    'bad parent rejected' => static fn (): mixed => $throwMessage133(static fn () => SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan::fromDeleteResults($database133(), $deleteResults133, 3, 1, 'x')),
    'no truncation boundary rejected' => static fn (): mixed => $throwMessage133(static fn () => SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan::fromDeleteResults($database133(), $deleteResults133, 1, 3, str_repeat('N', 600))),
];

$expected133 = [
    'action label' => 'btree-pointermap-vacuum-overflow-current-source-next133',
    'released overflow pages' => [104, 106],
    'truncated page numbers' => [106, 105, 104],
    'recreated pointer map pages' => [105],
    'allocated overflow pages' => [104, 106],
    'appended overflow pages' => [104, 106],
    'vacuum final page count' => 103,
    'final database page count' => 106,
    'final header page count' => 106,
    'final first freelist trunk' => 0,
    'final freelist count' => 0,
    'final freelist pages' => [],
    'allocation pointer map pages' => [2, 105],
    'allocation pointer map entries pages' => [104, 106],
    'allocation pointer map entries types' => ['first-overflow-page', 'overflow-page'],
    'allocation pointer map entries parents' => [3, 104],
    'allocation sources' => ['append', 'append'],
    'row page numbers' => [104, 105, 106],
    'row statuses' => ['allocated-overflow-page', 'recreated-pointer-map-page', 'allocated-overflow-page'],
    'row release sources' => ['wp_options-autoload-transient-current-source-next133', null, 'wp_options-autoload-transient-current-source-next133'],
    'row allocated flags' => [true, false, true],
    'row truncated flags' => [true, true, true],
    'row recreated flags' => [false, true, false],
    'row current types' => ['first-overflow-page', null, 'overflow-page'],
    'row current parents' => [3, null, 104],
    'row next types' => ['first-overflow-page', null, 'overflow-page'],
    'row next parents' => [3, null, 104],
    'row current next pages' => [106, null, 0],
    'row next overflow pages' => [106, null, 0],
    'source pointer map page 105' => true,
    'final pointer map page 105' => true,
    'source pointer entry 104' => 'first-overflow-page',
    'source pointer entry 106' => 'overflow-page',
    'final pointer entry 104' => 'first-overflow-page',
    'final pointer entry 106' => 'overflow-page',
    'final pointer parent 104' => 3,
    'final pointer parent 106' => 104,
    'final overflow 104 next' => 106,
    'final overflow 106 next' => 0,
    'final overflow 104 payload' => str_repeat('N', 8),
    'final overflow 106 payload' => str_repeat('N', 8),
    'updated page numbers' => [1, 2, 104, 105, 106],
    'page image keys' => [1, 2, 104, 105, 106],
    'overflow image keys' => [104, 106],
    'vacuum materialized omitted pages' => [106, 105, 104],
    'vacuum materialized byte length' => 52736,
    'final header from image' => 106,
    'row 105 status' => 'recreated-pointer-map-page',
    'row 104 status' => 'allocated-overflow-page',
    'row 106 status' => 'allocated-overflow-page',
    'empty payload rejected' => 'SQLite b-tree pointer-map vacuum overflow next133 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree pointer-map vacuum overflow next133 parent b-tree page must be at page 2 or later',
    'no truncation boundary rejected' => 'SQLite b-tree pointer-map vacuum overflow next133 requires a recreated auto-vacuum pointer-map page',
];

$tests = [];

foreach ($cases133 as $name => $callback) {
    $tests['btree pointermap vacuum overflow current source next133 ' . $name] = static function (TestRunner $t) use ($callback, $expected133, $name): void {
        $t->same($expected133[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree pointermap vacuum overflow current source next133 invariant ' . $index] = static function (TestRunner $t) use ($plan133, $rowByPage133): void {
        $plan = $plan133();

        $t->same([104, 106], $plan->releasedOverflowPages());
        $t->same([106, 105, 104], $plan->truncatedPageNumbers());
        $t->same([105], $plan->recreatedPointerMapPages());
        $t->same([104, 106], $plan->allocatedOverflowPages());
        $t->same(['first-overflow-page', 'overflow-page'], array_column($plan->allocationPlan->allocatedPointerMapEntries(), 'type_name'));
        $t->same([3, 104], array_column($plan->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'));
        $t->same('recreated-pointer-map-page', $rowByPage133(105)['page_status']);
        $t->same(106, unpack('N', substr($plan->databaseAfterAllocation->page(104), 0, 4))[1]);
        $t->same(0, unpack('N', substr($plan->databaseAfterAllocation->page(106), 0, 4))[1]);
    };
}

return $tests;
