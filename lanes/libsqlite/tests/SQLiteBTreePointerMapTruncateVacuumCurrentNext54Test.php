<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
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

    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$fixture = static function (int $maxTruncatedPages = 16, bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 310;
    $releasedPages = [306, 307, 308, 309, 310];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 0, 0);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 42 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $isFirst = $index === 0 || $index === 3;
        $parent = $isFirst ? ($index === 0 ? 42 : 5) : $releasedPages[$index - 1];
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $parent,
            $pageSize,
        );
        $next = in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0;
        $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(65 + $index), $pageSize - 4);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
        $database,
        [
            [
                'source' => 'wp_options-autoload-table-tail-overflow',
                'obsolete_overflow_page_numbers' => [306, 307, 308],
                'rowids' => [9001],
            ],
            [
                'source' => 'wp_options-option-name-index-tail-overflow',
                'obsolete_overflow_page_numbers' => [309, 310],
                'record_values' => [['_transient_doing_cron', 9001]],
            ],
        ],
        $maxTruncatedPages,
        $secureDelete,
    );

    return [$database, $plan, $releasedPages];
};

$tests = [];

$cases = [
    'released pages preserve table then index order' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPages(),
    'truncated pages descend from physical tail' => static fn (array $fx): mixed => $fx[1]->truncatedPageNumbers(),
    'current page count keeps pre-vacuum size' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pageCount(),
    'current header page count keeps pre-vacuum size' => static fn (array $fx): mixed => $fx[1]->currentDatabase->header->databaseSizePages,
    'next page count removes released tail' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pageCount(),
    'next header page count removes released tail' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->databaseSizePages,
    'final page count helper matches next database' => static fn (array $fx): mixed => $fx[1]->finalDatabasePageCount(),
    'current first freelist trunk is first released page' => static fn (array $fx): mixed => $fx[1]->currentDatabase->header->firstFreelistTrunkPage,
    'current freelist count includes every released overflow page' => static fn (array $fx): mixed => $fx[1]->currentDatabase->header->freelistPageCount,
    'current helper returns released freelist pages' => static fn (array $fx): mixed => $fx[1]->currentFreelistPageNumbers(),
    'next first freelist trunk is cleared after tail truncation' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->firstFreelistTrunkPage,
    'next freelist count is cleared after tail truncation' => static fn (array $fx): mixed => $fx[1]->nextDatabase->header->freelistPageCount,
    'next helper returns empty freelist pages' => static fn (array $fx): mixed => $fx[1]->nextFreelistPageNumbers(),
    'current trunk chains nowhere' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[0]->nextTrunkPage,
    'current trunk carries four leaves' => static fn (array $fx): mixed => $fx[1]->currentDatabase->freelistTrunkPages()[0]->leafPageNumbers,
    'current pointer map entries count every released page' => static fn (array $fx): mixed => count($fx[1]->currentFreedPointerMapEntries()),
    'current pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[1]->currentFreedPointerMapEntries(), 'page_number'),
    'current pointer map pages are third pointer-map page' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[1]->currentFreedPointerMapEntries(), 'pointer_map_page'))),
    'current pointer map entry types are free' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[1]->currentFreedPointerMapEntries(), 'type_name'))),
    'current pointer map parents are zero' => static fn (array $fx): mixed => array_values(array_unique(array_column($fx[1]->currentFreedPointerMapEntries(), 'parent_page_number'))),
    'current page 306 pointer map free' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(306)->typeName(),
    'current page 310 pointer map free' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(310)->typeName(),
    'current page 306 pointer map parent cleared' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(306)->parentPageNumber,
    'current page 310 pointer map parent cleared' => static fn (array $fx): mixed => $fx[1]->currentDatabase->pointerMapEntryForPage(310)->parentPageNumber,
    'current trunk is not secure-delete cleared' => static fn (array $fx): mixed => bin2hex(substr($fx[1]->currentDatabase->page(306), 0, 8)),
    'current first leaf is secure-delete cleared' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(307) === str_repeat("\0", 512),
    'current last leaf is secure-delete cleared' => static fn (array $fx): mixed => $fx[1]->currentDatabase->page(310) === str_repeat("\0", 512),
    'page images retain header and pointer map only after tail drops' => static fn (array $fx): mixed => array_keys($fx[1]->pageImages),
    'release free plan new trunk page' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->newTrunkPageNumbers,
    'release free plan leaf pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->leafPageNumbers,
    'release free plan pointer-map page' => static fn (array $fx): mixed => array_keys($fx[1]->releasePlan->freePlan->updatedPointerMapPages),
    'release free plan cleared pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->clearedPageNumbers,
    'truncate plan page image only header' => static fn (array $fx): mixed => array_keys($fx[1]->truncatePlan->pageImages()),
    'truncate plan final first trunk cleared' => static fn (array $fx): mixed => $fx[1]->truncatePlan->firstFreelistTrunkPage,
    'truncate plan final freelist count cleared' => static fn (array $fx): mixed => $fx[1]->truncatePlan->freelistPageCount,
    'summary action names composed current next behavior' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'summary current database page count' => static fn (array $fx): mixed => $fx[1]->toArray()['current_database_page_count'],
    'summary current first freelist trunk page' => static fn (array $fx): mixed => $fx[1]->toArray()['current_first_freelist_trunk_page'],
    'summary current freelist page count' => static fn (array $fx): mixed => $fx[1]->toArray()['current_freelist_page_count'],
    'summary current freelist page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['current_freelist_page_numbers'],
    'summary current freed pointer map pages' => static fn (array $fx): mixed => array_column($fx[1]->toArray()['current_freed_pointer_map_entries'], 'page_number'),
    'summary final page count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_database_page_count'],
    'summary final first trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['final_first_freelist_trunk_page'],
    'summary final freelist count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_freelist_page_count'],
    'summary next freelist page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['next_freelist_page_numbers'],
    'summary updated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
    'summary nested truncate pages' => static fn (array $fx): mixed => $fx[1]->toArray()['truncate_plan']['truncated_page_numbers'],
    'summary nested release pages' => static fn (array $fx): mixed => $fx[1]->toArray()['release_plan']['released_overflow_pages'],
    'summary nested pointer map entries count' => static fn (array $fx): mixed => count($fx[1]->toArray()['release_plan']['free_plan']['freed_pointer_map_entries']),
    'original database page count unchanged' => static fn (array $fx): mixed => $fx[0]->pageCount(),
    'original header page count unchanged' => static fn (array $fx): mixed => $fx[0]->header->databaseSizePages,
    'original database has no freelist pages' => static fn (array $fx): mixed => $fx[0]->freelistPageNumbers(),
    'fixture released pages match current freelist' => static fn (array $fx): mixed => $fx[2],
    'surviving root pointer map type remains root' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(4)->typeName(),
    'surviving btree pointer map type remains btree' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(42)->typeName(),
    'surviving btree pointer map parent remains root' => static fn (array $fx): mixed => $fx[1]->nextDatabase->pointerMapEntryForPage(42)->parentPageNumber,
];

$expected = [
    'released pages preserve table then index order' => [306, 307, 308, 309, 310],
    'truncated pages descend from physical tail' => [310, 309, 308, 307, 306],
    'current page count keeps pre-vacuum size' => 310,
    'current header page count keeps pre-vacuum size' => 310,
    'next page count removes released tail' => 305,
    'next header page count removes released tail' => 305,
    'final page count helper matches next database' => 305,
    'current first freelist trunk is first released page' => 306,
    'current freelist count includes every released overflow page' => 5,
    'current helper returns released freelist pages' => [306, 307, 308, 309, 310],
    'next first freelist trunk is cleared after tail truncation' => 0,
    'next freelist count is cleared after tail truncation' => 0,
    'next helper returns empty freelist pages' => [],
    'current trunk chains nowhere' => null,
    'current trunk carries four leaves' => [307, 308, 309, 310],
    'current pointer map entries count every released page' => 5,
    'current pointer map entry pages' => [306, 307, 308, 309, 310],
    'current pointer map pages are third pointer-map page' => [208],
    'current pointer map entry types are free' => ['free-page'],
    'current pointer map parents are zero' => [0],
    'current page 306 pointer map free' => 'free-page',
    'current page 310 pointer map free' => 'free-page',
    'current page 306 pointer map parent cleared' => 0,
    'current page 310 pointer map parent cleared' => 0,
    'current trunk is not secure-delete cleared' => '0000000000000004',
    'current first leaf is secure-delete cleared' => true,
    'current last leaf is secure-delete cleared' => true,
    'page images retain header and pointer map only after tail drops' => [1, 208],
    'release free plan new trunk page' => [306],
    'release free plan leaf pages' => [307, 308, 309, 310],
    'release free plan pointer-map page' => [208],
    'release free plan cleared pages' => [307, 308, 309, 310],
    'truncate plan page image only header' => [1],
    'truncate plan final first trunk cleared' => 0,
    'truncate plan final freelist count cleared' => 0,
    'summary action names composed current next behavior' => 'overflow-freelist-release-vacuum-truncate',
    'summary current database page count' => 310,
    'summary current first freelist trunk page' => 306,
    'summary current freelist page count' => 5,
    'summary current freelist page numbers' => [306, 307, 308, 309, 310],
    'summary current freed pointer map pages' => [306, 307, 308, 309, 310],
    'summary final page count' => 305,
    'summary final first trunk' => 0,
    'summary final freelist count' => 0,
    'summary next freelist page numbers' => [],
    'summary updated page numbers' => [1, 208],
    'summary nested truncate pages' => [310, 309, 308, 307, 306],
    'summary nested release pages' => [306, 307, 308, 309, 310],
    'summary nested pointer map entries count' => 5,
    'original database page count unchanged' => 310,
    'original header page count unchanged' => 310,
    'original database has no freelist pages' => [],
    'fixture released pages match current freelist' => [306, 307, 308, 309, 310],
    'surviving root pointer map type remains root' => 'root-page',
    'surviving btree pointer map type remains btree' => 'btree-page',
    'surviving btree pointer map parent remains root' => 4,
];

foreach ($cases as $name => $callback) {
    $tests['btree pointermap truncate vacuum current next54 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree pointermap truncate vacuum current next54 partial truncation keeps current lower tail free'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(3);

    $t->same([310, 309, 308], $plan->truncatedPageNumbers());
    $t->same(307, $plan->finalDatabasePageCount());
    $t->same(306, $plan->nextDatabase->header->firstFreelistTrunkPage);
    $t->same(2, $plan->nextDatabase->header->freelistPageCount);
    $t->same([306, 307], $plan->nextFreelistPageNumbers());
};

$tests['btree pointermap truncate vacuum current next54 secure delete off keeps current overflow bytes before truncation'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(3, false);

    $t->same([], $plan->releasePlan->freePlan->clearedPageNumbers);
    $t->same(str_repeat('B', 8), substr($plan->currentDatabase->page(307), 4, 8));
    $t->same(str_repeat('E', 8), substr($plan->currentDatabase->page(310), 4, 8));
};

$tests['btree pointermap truncate vacuum current next54 rejects pointer map page release before truncate'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    try {
        SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, [
            ['source' => 'bad-pointer-map-release', 'obsolete_overflow_page_numbers' => [208]],
        ], 4);
    } catch (InvalidArgumentException $exception) {
        $t->contains('pointer-map pages cannot be placed on the freelist', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected pointer-map release rejection');
};

return $tests;
