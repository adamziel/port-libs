<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReleasePlan;
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

$fixture = static function (array $releasedPages = [20, 21, 22, 106, 107], bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 260;
    $firstTrunk = 8;
    $existingLeaves = range(130, 249);
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $firstTrunk, 1 + count($existingLeaves));
    $pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], $firstTrunk => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($existingLeaves as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : $releasedPages[$index - 1],
            $pageSize,
        );
        $pages[$pageNumber] = str_repeat(chr(65 + $index), $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $deleteResults = [
        [
            'source' => 'wp_options-transient-table-overflow',
            'obsolete_overflow_page_numbers' => array_slice($releasedPages, 0, 3),
            'rowids' => [17],
        ],
        [
            'source' => 'wp_options-option-name-index-overflow',
            'obsolete_overflow_page_numbers' => array_slice($releasedPages, 3),
            'record_values' => [['_transient_spill', 17]],
        ],
    ];
    $plan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, $deleteResults, $secureDelete);
    foreach ($plan->freePlan->pageImages() as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

    return [$database, $plan, $postDatabase, $releasedPages, $existingLeaves, $pages];
};

$tests = [];

$cases = [
    'new trunk page is the first released overflow page' => static fn (array $fx): mixed => $fx[2]->header->firstFreelistTrunkPage,
    'freelist count includes released overflow pages' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'new trunk chains to previous full trunk' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[0]->nextTrunkPage,
    'new trunk stores remaining released pages as leaves' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[0]->leafPageNumbers,
    'old trunk remains second trunk' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[1]->pageNumber,
    'old trunk leaf count remains full' => static fn (array $fx): mixed => count($fx[2]->freelistTrunkPages()[1]->leafPageNumbers),
    'allocation order consumes new leaf first' => static fn (array $fx): mixed => array_slice($fx[2]->freelistAllocationOrder(6), 0, 6),
    'release plan records table source pages' => static fn (array $fx): mixed => $fx[1]->sources[0]['pages'],
    'release plan records index source pages' => static fn (array $fx): mixed => $fx[1]->sources[1]['pages'],
    'release plan records all pages in order' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPages,
    'new trunk page image is not secure-delete zeroed' => static fn (array $fx): mixed => bin2hex(substr($fx[2]->page(20), 0, 8)),
    'secure-delete zeroes leaf 21' => static fn (array $fx): mixed => $fx[2]->page(21) === str_repeat("\0", 512),
    'secure-delete zeroes leaf 22' => static fn (array $fx): mixed => $fx[2]->page(22) === str_repeat("\0", 512),
    'secure-delete zeroes leaf 106' => static fn (array $fx): mixed => $fx[2]->page(106) === str_repeat("\0", 512),
    'secure-delete zeroes leaf 107' => static fn (array $fx): mixed => $fx[2]->page(107) === str_repeat("\0", 512),
    'first pointer-map page updated' => static fn (array $fx): mixed => array_key_exists(2, $fx[1]->freePlan->updatedPointerMapPages),
    'second pointer-map page updated' => static fn (array $fx): mixed => array_key_exists(105, $fx[1]->freePlan->updatedPointerMapPages),
    'page 20 pointer-map type' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(20)->typeName(),
    'page 21 pointer-map type' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(21)->typeName(),
    'page 22 pointer-map type' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(22)->typeName(),
    'page 106 pointer-map type' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(106)->typeName(),
    'page 107 pointer-map type' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(107)->typeName(),
    'page 20 pointer-map parent cleared' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(20)->parentPageNumber,
    'page 107 pointer-map parent cleared' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(107)->parentPageNumber,
    'updated page images include first page' => static fn (array $fx): mixed => array_key_exists(1, $fx[1]->freePlan->pageImages()),
    'updated page images include new trunk' => static fn (array $fx): mixed => array_key_exists(20, $fx[1]->freePlan->pageImages()),
    'updated page images omit unchanged old trunk' => static fn (array $fx): mixed => array_key_exists(8, $fx[1]->freePlan->pageImages()),
    'updated page images include second pointer map' => static fn (array $fx): mixed => array_key_exists(105, $fx[1]->freePlan->pageImages()),
    'toArray freed page count' => static fn (array $fx): mixed => count($fx[1]->toArray()['free_plan']['freed_page_numbers']),
    'toArray new trunk list' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['new_trunk_page_numbers'],
    'toArray leaf list' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['leaf_page_numbers'],
    'toArray pointer-map page list' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['updated_pointer_map_page_numbers'],
    'toArray cleared leaves omit trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['cleared_page_numbers'],
    'post freelist contains released pages' => static fn (array $fx): mixed => array_values(array_intersect($fx[3], $fx[2]->freelistPageNumbers())),
    'post freelist contains old full leaves' => static fn (array $fx): mixed => count(array_intersect(array_slice($fx[4], 0, 12), $fx[2]->freelistPageNumbers())),
    'old first trunk page remains free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(8)->typeName(),
    'existing full trunk first leaf preserved' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[1]->leafPageNumbers[0],
    'existing full trunk last leaf preserved' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[1]->leafPageNumbers[119],
    'database page count unchanged by freelist release' => static fn (array $fx): mixed => $fx[2]->pageCount(),
    'header database size unchanged by freelist release' => static fn (array $fx): mixed => $fx[2]->header->databaseSizePages,
    'freelist allocation after release leaves count stable' => static fn (array $fx): mixed => count($fx[2]->freelistAllocationOrder(10)),
    'release plan first source count' => static fn (array $fx): mixed => $fx[1]->sources[0]['count'],
    'release plan second source count' => static fn (array $fx): mixed => $fx[1]->sources[1]['count'],
    'free plan database page count' => static fn (array $fx): mixed => $fx[1]->freePlan->databasePageCount,
    'free plan first trunk number' => static fn (array $fx): mixed => $fx[1]->freePlan->firstFreelistTrunkPage,
    'free plan freelist page count' => static fn (array $fx): mixed => $fx[1]->freePlan->freelistPageCount,
    'free plan leaf page numbers' => static fn (array $fx): mixed => $fx[1]->freePlan->leafPageNumbers,
    'free plan new trunk page numbers' => static fn (array $fx): mixed => $fx[1]->freePlan->newTrunkPageNumbers,
    'free plan cleared page numbers' => static fn (array $fx): mixed => $fx[1]->freePlan->clearedPageNumbers,
    'free plan updated freelist pages' => static fn (array $fx): mixed => array_keys($fx[1]->freePlan->updatedFreelistPages),
    'free plan updated pointer map pages' => static fn (array $fx): mixed => array_keys($fx[1]->freePlan->updatedPointerMapPages),
];

$expected = [
    'new trunk page is the first released overflow page' => 20,
    'freelist count includes released overflow pages' => 126,
    'new trunk chains to previous full trunk' => 8,
    'new trunk stores remaining released pages as leaves' => [21, 22, 106, 107],
    'old trunk remains second trunk' => 8,
    'old trunk leaf count remains full' => 120,
    'allocation order consumes new leaf first' => [21, 107, 106, 22, 20, 130],
    'release plan records table source pages' => [20, 21, 22],
    'release plan records index source pages' => [106, 107],
    'release plan records all pages in order' => [20, 21, 22, 106, 107],
    'new trunk page image is not secure-delete zeroed' => '0000000800000004',
    'secure-delete zeroes leaf 21' => true,
    'secure-delete zeroes leaf 22' => true,
    'secure-delete zeroes leaf 106' => true,
    'secure-delete zeroes leaf 107' => true,
    'first pointer-map page updated' => true,
    'second pointer-map page updated' => true,
    'page 20 pointer-map type' => 'free-page',
    'page 21 pointer-map type' => 'free-page',
    'page 22 pointer-map type' => 'free-page',
    'page 106 pointer-map type' => 'free-page',
    'page 107 pointer-map type' => 'free-page',
    'page 20 pointer-map parent cleared' => 0,
    'page 107 pointer-map parent cleared' => 0,
    'updated page images include first page' => true,
    'updated page images include new trunk' => true,
    'updated page images omit unchanged old trunk' => false,
    'updated page images include second pointer map' => true,
    'toArray freed page count' => 5,
    'toArray new trunk list' => [20],
    'toArray leaf list' => [21, 22, 106, 107],
    'toArray pointer-map page list' => [2, 105],
    'toArray cleared leaves omit trunk' => [21, 22, 106, 107],
    'post freelist contains released pages' => [20, 21, 22, 106, 107],
    'post freelist contains old full leaves' => 12,
    'old first trunk page remains free page' => 'free-page',
    'existing full trunk first leaf preserved' => 130,
    'existing full trunk last leaf preserved' => 249,
    'database page count unchanged by freelist release' => 260,
    'header database size unchanged by freelist release' => 260,
    'freelist allocation after release leaves count stable' => 10,
    'release plan first source count' => 3,
    'release plan second source count' => 2,
    'free plan database page count' => 260,
    'free plan first trunk number' => 20,
    'free plan freelist page count' => 126,
    'free plan leaf page numbers' => [21, 22, 106, 107],
    'free plan new trunk page numbers' => [20],
    'free plan cleared page numbers' => [21, 22, 106, 107],
    'free plan updated freelist pages' => [20],
    'free plan updated pointer map pages' => [2, 105],
];

foreach ($cases as $name => $callback) {
    $tests['btree overflow autovacuum freelist spill ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree overflow autovacuum freelist spill rejects duplicate obsolete overflow page'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    try {
        SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, [
            ['source' => 'table', 'obsolete_overflow_page_numbers' => [20]],
            ['source' => 'index', 'obsolete_overflow_page_numbers' => [20]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->contains('appears more than once', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected duplicate obsolete overflow page rejection');
};

$tests['btree overflow autovacuum freelist spill rejects pointer map page release'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    try {
        SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, [
            ['source' => 'table', 'obsolete_overflow_page_numbers' => [105]],
        ]);
    } catch (InvalidArgumentException $exception) {
        $t->contains('pointer-map pages cannot be placed on the freelist', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected pointer-map release rejection');
};

$tests['btree overflow autovacuum freelist spill preserves released page bytes without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    [, , $postDatabase] = $fixture([20, 21, 22, 106, 107], false);
    $t->same(str_repeat('B', 8), substr($postDatabase->page(21), 0, 8));
};

return $tests;
