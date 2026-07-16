<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreepagePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
    if ($pageNumber === 1) {
        return;
    }

    $usableSize = $pageSize;
    $stride = intdiv($usableSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$fixture = static function (bool $secureDelete = true, ?int $nextAllocationLimit = 8) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 260;
    $firstTrunk = 8;
    $existingLeaves = range(130, 249);
    $releasedPages = [20, 21, 22, 106, 107, 108];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $firstTrunk, 1 + count($existingLeaves));
    $pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::ROOT_PAGE, 0], $firstTrunk => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($existingLeaves as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $nextPageNumber = 0;
        if ($pageNumber === 20) {
            $nextPageNumber = 21;
        } elseif ($pageNumber === 106) {
            $nextPageNumber = 107;
        }
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $index === 0 || $index === 3 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : ($index === 3 ? 5 : $releasedPages[$index - 1]),
            $pageSize,
        );
        $pages[$pageNumber] = pack('N', $nextPageNumber) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeOverflowVacuumFreepagePlan::fromDeleteResults(
        $database,
        [
            [
                'source' => 'wp_options-transient-table-overflow',
                'obsolete_overflow_page_numbers' => [20, 21, 22],
                'rowids' => [44],
            ],
            [
                'source' => 'wp_options-option-name-index-overflow',
                'obsolete_overflow_page_numbers' => [106, 107, 108],
                'record_values' => [['_transient_timeout_current_next26', 44]],
            ],
        ],
        $secureDelete,
        $nextAllocationLimit,
    );

    return [$database, $plan, $releasedPages, $existingLeaves];
};

$tests = [];

$cases = [
    'action labels current next vacuum freepage' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'source count' => static fn (array $fx): mixed => count($fx[1]->sources),
    'first source label' => static fn (array $fx): mixed => $fx[1]->sources[0]['source'],
    'second source label' => static fn (array $fx): mixed => $fx[1]->sources[1]['source'],
    'first source pages' => static fn (array $fx): mixed => $fx[1]->sources[0]['pages'],
    'second source pages' => static fn (array $fx): mixed => $fx[1]->sources[1]['pages'],
    'first source count' => static fn (array $fx): mixed => $fx[1]->sources[0]['count'],
    'second source count' => static fn (array $fx): mixed => $fx[1]->sources[1]['count'],
    'released pages preserve delete order' => static fn (array $fx): mixed => $fx[1]->releasePlan->releasedOverflowPages,
    'current first trunk uses first obsolete overflow page' => static fn (array $fx): mixed => $fx[1]->currentFirstFreelistTrunkPage(),
    'current freelist count includes released overflow pages' => static fn (array $fx): mixed => $fx[1]->currentFreelistPageCount(),
    'current database header first trunk matches helper' => static fn (array $fx): mixed => $fx[1]->currentDatabase->header->firstFreelistTrunkPage,
    'current database header count matches helper' => static fn (array $fx): mixed => $fx[1]->currentDatabase->header->freelistPageCount,
    'current freelist starts with new trunk' => static fn (array $fx): mixed => array_slice($fx[1]->currentFreelistPages, 0, 6),
    'current freelist includes existing full trunk tail' => static fn (array $fx): mixed => array_slice($fx[1]->currentFreelistPages, -3),
    'next allocation is bounded by limit' => static fn (array $fx): mixed => count($fx[1]->nextAllocationOrder),
    'next allocation consumes latest leaf first' => static fn (array $fx): mixed => $fx[1]->nextAllocationOrder[0],
    'next allocation includes current trunk after leaves' => static fn (array $fx): mixed => $fx[1]->nextAllocationOrder[5],
    'next allocation continues into previous trunk' => static fn (array $fx): mixed => $fx[1]->nextAllocationOrder[6],
    'new trunk chains to previous trunk' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[0]->nextTrunkPage,
    'new trunk leaf pages' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[0]->leafPageNumbers,
    'previous trunk remains second' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[1]->pageNumber,
    'previous trunk leaf count preserved' => static fn (array $fx): mixed => count($fx[1]->currentDatabase->freelistTrunkPages()[1]->leafPageNumbers),
    'previous trunk first leaf preserved' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[1]->leafPageNumbers[0],
    'previous trunk last leaf preserved' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[1]->leafPageNumbers[119],
    'current page images include database header' => static fn (array $fx): mixed => array_key_exists(1, $fx[1]->currentPageImages),
    'current page images include new trunk' => static fn (array $fx): mixed => array_key_exists(20, $fx[1]->currentPageImages),
    'current page images include first pointer map' => static fn (array $fx): mixed => array_key_exists(2, $fx[1]->currentPageImages),
    'current page images include second pointer map' => static fn (array $fx): mixed => array_key_exists(105, $fx[1]->currentPageImages),
    'current page images include secure deleted leaf 21' => static fn (array $fx): mixed => array_key_exists(21, $fx[1]->currentPageImages),
    'current page images include secure deleted leaf 108' => static fn (array $fx): mixed => array_key_exists(108, $fx[1]->currentPageImages),
    'released trunk not secure-delete cleared' => static fn (array $fx): mixed => bin2hex(substr($fx[1]->currentDatabase->page(20), 0, 8)),
    'secure-delete clears first table overflow leaf' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(21) === str_repeat("\0", 512),
    'secure-delete clears second table overflow leaf' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(22) === str_repeat("\0", 512),
    'secure-delete clears first index overflow leaf' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(106) === str_repeat("\0", 512),
    'secure-delete clears second index overflow leaf' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(107) === str_repeat("\0", 512),
    'secure-delete clears third index overflow leaf' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(108) === str_repeat("\0", 512),
    'page 20 pointer-map free' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(20)->typeName(),
    'page 21 pointer-map free' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(21)->typeName(),
    'page 108 pointer-map free' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(108)->typeName(),
    'page 20 pointer-map parent cleared' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(20)->parentPageNumber,
    'page 108 pointer-map parent cleared' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(108)->parentPageNumber,
    'free plan first trunk' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->firstFreelistTrunkPage,
    'free plan freelist count' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->freelistPageCount,
    'free plan new trunk pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->newTrunkPageNumbers,
    'free plan leaf pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->leafPageNumbers,
    'free plan cleared pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->clearedPageNumbers,
    'free plan pointer-map pages' => static fn (array $fx): mixed => array_keys($fx[1]->releasePlan->freePlan->updatedPointerMapPages),
    'toArray current first trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['current_first_freelist_trunk_page'],
    'toArray current freelist count' => static fn (array $fx): mixed => $fx[1]->toArray()['current_freelist_page_count'],
    'toArray current page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['current_page_numbers'],
    'toArray next allocation order' => static fn (array $fx): mixed => $fx[1]->toArray()['next_allocation_order'],
    'toArray pointer-map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'toArray cleared pages' => static fn (array $fx): mixed => $fx[1]->toArray()['secure_delete_cleared_pages'],
    'database page count unchanged' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pageCount(),
    'header database size unchanged' => static fn (array $fx): mixed => $fx[1]->currentDatabase->header->databaseSizePages,
];

$expected = [
    'action labels current next vacuum freepage' => 'btree-overflow-vacuum-freepage-current-next',
    'source count' => 2,
    'first source label' => 'wp_options-transient-table-overflow',
    'second source label' => 'wp_options-option-name-index-overflow',
    'first source pages' => [20, 21, 22],
    'second source pages' => [106, 107, 108],
    'first source count' => 3,
    'second source count' => 3,
    'released pages preserve delete order' => [20, 21, 22, 106, 107, 108],
    'current first trunk uses first obsolete overflow page' => 20,
    'current freelist count includes released overflow pages' => 127,
    'current database header first trunk matches helper' => 20,
    'current database header count matches helper' => 127,
    'current freelist starts with new trunk' => [20, 21, 22, 106, 107, 108],
    'current freelist includes existing full trunk tail' => [247, 248, 249],
    'next allocation is bounded by limit' => 8,
    'next allocation consumes latest leaf first' => 21,
    'next allocation includes current trunk after leaves' => 20,
    'next allocation continues into previous trunk' => 130,
    'new trunk chains to previous trunk' => 8,
    'new trunk leaf pages' => [21, 22, 106, 107, 108],
    'previous trunk remains second' => 8,
    'previous trunk leaf count preserved' => 120,
    'previous trunk first leaf preserved' => 130,
    'previous trunk last leaf preserved' => 249,
    'current page images include database header' => true,
    'current page images include new trunk' => true,
    'current page images include first pointer map' => true,
    'current page images include second pointer map' => true,
    'current page images include secure deleted leaf 21' => true,
    'current page images include secure deleted leaf 108' => true,
    'released trunk not secure-delete cleared' => '0000000800000005',
    'secure-delete clears first table overflow leaf' => true,
    'secure-delete clears second table overflow leaf' => true,
    'secure-delete clears first index overflow leaf' => true,
    'secure-delete clears second index overflow leaf' => true,
    'secure-delete clears third index overflow leaf' => true,
    'page 20 pointer-map free' => 'free-page',
    'page 21 pointer-map free' => 'free-page',
    'page 108 pointer-map free' => 'free-page',
    'page 20 pointer-map parent cleared' => 0,
    'page 108 pointer-map parent cleared' => 0,
    'free plan first trunk' => 20,
    'free plan freelist count' => 127,
    'free plan new trunk pages' => [20],
    'free plan leaf pages' => [21, 22, 106, 107, 108],
    'free plan cleared pages' => [21, 22, 106, 107, 108],
    'free plan pointer-map pages' => [2, 105],
    'toArray current first trunk' => 20,
    'toArray current freelist count' => 127,
    'toArray current page numbers' => [1, 2, 20, 21, 22, 105, 106, 107, 108],
    'toArray next allocation order' => [21, 108, 107, 106, 22, 20, 130, 249],
    'toArray pointer-map pages' => [2, 105],
    'toArray cleared pages' => [21, 22, 106, 107, 108],
    'database page count unchanged' => 260,
    'header database size unchanged' => 260,
];

foreach ($cases as $name => $callback) {
    $tests['btree overflow vacuum freepage current next26 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree overflow vacuum freepage current next26 preserves overflow bytes without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(false, 3);
    $t->same(str_repeat('B', 8), substr($plan->currentDatabase->page(21), 4, 8));
    $t->same([21, 108, 107], $plan->nextAllocationOrder);
};

$tests['btree overflow vacuum freepage current next26 builds from overflow chain metadata'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $plan = SQLiteBTreeOverflowVacuumFreepagePlan::fromOverflowChains(
        $database,
        [
            ['source' => 'wp_options-table-chain', 'first_page' => 20, 'overflow_payload_bytes' => 512],
            ['source' => 'wp_options-index-chain', 'first_page' => 106, 'overflow_payload_bytes' => 512],
        ],
        true,
        4,
    );

    $t->same(['wp_options-table-chain', 'wp_options-index-chain'], [$plan->sources[0]['source'], $plan->sources[1]['source']]);
    $t->same([20, 21], $plan->sources[0]['pages']);
    $t->same([106, 107], $plan->sources[1]['pages']);
    $t->same([21, 107, 106, 20], $plan->nextAllocationOrder);
};

$tests['btree overflow vacuum freepage current next26 rejects duplicate current freepage'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    try {
        SQLiteBTreeOverflowVacuumFreepagePlan::fromDeleteResults($database, [
            ['source' => 'table', 'obsolete_overflow_page_numbers' => [20, 21]],
            ['source' => 'index', 'obsolete_overflow_page_numbers' => [21, 22]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->contains('appears more than once', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected duplicate obsolete overflow page rejection');
};

return $tests;
