<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
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

$fixture = static function (int $maxTruncatedPages = 8, bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 260;
    $releasedPages = [257, 258, 259, 260];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 0, 0);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 11 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 11 : $releasedPages[$index - 1],
            $pageSize,
        );
        $pages[$pageNumber] = str_repeat(chr(88 + $index), $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $deleteResults = [
        [
            'source' => 'wp_options-transient-table-tail-overflow',
            'obsolete_overflow_page_numbers' => [257, 258],
            'rowids' => [410],
        ],
        [
            'source' => 'wp_options-option-name-index-tail-overflow',
            'obsolete_overflow_page_numbers' => [259, 260],
            'record_values' => [['_transient_tail_cleanup', 410]],
        ],
    ];
    $plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, $deleteResults, $maxTruncatedPages, $secureDelete);
    $postBytes = '';
    for ($pageNumber = 1; $pageNumber <= $plan->finalDatabasePageCount(); $pageNumber++) {
        $postBytes .= $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
    }
    $postDatabase = SQLiteDatabase::fromBytes($postBytes);

    return [$database, $plan, $postDatabase, $releasedPages];
};

$tests = [];

$cases = [
    'released pages preserve input order' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPages(),
    'truncates tail pages in descending order' => static fn (array $fx): mixed => $fx[1]->truncatedPageNumbers(),
    'final database page count removes released tail' => static fn (array $fx): mixed => $fx[1]->finalDatabasePageCount(),
    'final freelist count is empty after full tail vacuum' => static fn (array $fx): mixed => $fx[1]->finalFreelistPageCount(),
    'final freelist trunk is cleared after full tail vacuum' => static fn (array $fx): mixed => $fx[1]->finalFirstFreelistTrunkPage(),
    'post header database size is truncated' => static fn (array $fx): mixed => $fx[2]->header->databaseSizePages,
    'post page count is truncated' => static fn (array $fx): mixed => $fx[2]->pageCount(),
    'post freelist pages are empty' => static fn (array $fx): mixed => $fx[2]->freelistPageNumbers(),
    'release plan first source labels table overflow' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['source'],
    'release plan second source labels index overflow' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[1]['source'],
    'release plan first source pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['pages'],
    'release plan second source pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[1]['pages'],
    'release free plan first trunk before truncation' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->firstFreelistTrunkPage,
    'release free plan freelist count before truncation' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->freelistPageCount,
    'release free plan new trunk pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->newTrunkPageNumbers,
    'release free plan leaf pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->leafPageNumbers,
    'release free plan secure cleared leaves' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->clearedPageNumbers,
    'release free plan pointer map pages' => static fn (array $fx): mixed => array_keys($fx[1]->releasePlan->freePlan->updatedPointerMapPages),
    'combined page images contain header' => static fn (array $fx): mixed => array_key_exists(1, $fx[1]->pageImages),
    'combined page images keep first pointer map' => static fn (array $fx): mixed => array_key_exists(2, $fx[1]->pageImages),
    'combined page images keep tail pointer map' => static fn (array $fx): mixed => array_key_exists(208, $fx[1]->pageImages),
    'combined page images omit truncated trunk page' => static fn (array $fx): mixed => array_key_exists(257, $fx[1]->pageImages),
    'combined page images omit truncated final page' => static fn (array $fx): mixed => array_key_exists(260, $fx[1]->pageImages),
    'toArray action names composed behavior' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'toArray release count' => static fn (array $fx): mixed => $fx[1]->toArray()['release_plan']['released_overflow_page_count'],
    'toArray truncated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['truncated_page_numbers'],
    'toArray final page count' => static fn (array $fx): mixed => $fx[1]->toArray()['final_database_page_count'],
    'toArray updated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
    'toArray nested truncate count' => static fn (array $fx): mixed => $fx[1]->toArray()['truncate_plan']['freelist_page_count'],
    'toArray nested truncate first trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['truncate_plan']['first_freelist_trunk_page'],
    'toArray nested release free new trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['release_plan']['free_plan']['new_trunk_page_numbers'],
    'toArray nested release pointer map page' => static fn (array $fx): mixed => $fx[1]->toArray()['release_plan']['free_plan']['updated_pointer_map_page_numbers'],
    'tail pointer map entry for btree page survives' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(11)->typeName(),
    'tail pointer map parent for btree page survives' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(11)->parentPageNumber,
    'root pointer map entry survives' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(4)->typeName(),
    'root pointer map parent survives' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(4)->parentPageNumber,
    'truncate plan updated freelist pages empty' => static fn (array $fx): mixed => $fx[1]->truncatePlan->updatedFreelistPages,
    'truncate plan page images only header' => static fn (array $fx): mixed => array_keys($fx[1]->truncatePlan->pageImages()),
    'release plan page images had tail trunk' => static fn (array $fx): mixed => array_key_exists(257, $fx[1]->releasePlan->freePlan->pageImages()),
    'release plan page images had secure-delete leaf' => static fn (array $fx): mixed => array_key_exists(258, $fx[1]->releasePlan->freePlan->pageImages()),
    'original database page count remains unchanged' => static fn (array $fx): mixed => $fx[0]->pageCount(),
    'original header page count remains unchanged' => static fn (array $fx): mixed => $fx[0]->header->databaseSizePages,
    'original database had no freelist pages' => static fn (array $fx): mixed => $fx[0]->freelistPageNumbers(),
    'released pages match fixture tail' => static fn (array $fx): mixed => $fx[3],
    'post database can read page before truncated tail' => static fn (array $fx): mixed => strlen($fx[2]->page(256)),
];

$expected = [
    'released pages preserve input order' => [257, 258, 259, 260],
    'truncates tail pages in descending order' => [260, 259, 258, 257],
    'final database page count removes released tail' => 256,
    'final freelist count is empty after full tail vacuum' => 0,
    'final freelist trunk is cleared after full tail vacuum' => 0,
    'post header database size is truncated' => 256,
    'post page count is truncated' => 256,
    'post freelist pages are empty' => [],
    'release plan first source labels table overflow' => 'wp_options-transient-table-tail-overflow',
    'release plan second source labels index overflow' => 'wp_options-option-name-index-tail-overflow',
    'release plan first source pages' => [257, 258],
    'release plan second source pages' => [259, 260],
    'release free plan first trunk before truncation' => 257,
    'release free plan freelist count before truncation' => 4,
    'release free plan new trunk pages' => [257],
    'release free plan leaf pages' => [258, 259, 260],
    'release free plan secure cleared leaves' => [258, 259, 260],
    'release free plan pointer map pages' => [208],
    'combined page images contain header' => true,
    'combined page images keep first pointer map' => false,
    'combined page images keep tail pointer map' => true,
    'combined page images omit truncated trunk page' => false,
    'combined page images omit truncated final page' => false,
    'toArray action names composed behavior' => 'overflow-freelist-release-vacuum-truncate',
    'toArray release count' => 4,
    'toArray truncated page numbers' => [260, 259, 258, 257],
    'toArray final page count' => 256,
    'toArray updated page numbers' => [1, 208],
    'toArray nested truncate count' => 0,
    'toArray nested truncate first trunk' => 0,
    'toArray nested release free new trunk' => [257],
    'toArray nested release pointer map page' => [208],
    'tail pointer map entry for btree page survives' => 'btree-page',
    'tail pointer map parent for btree page survives' => 4,
    'root pointer map entry survives' => 'root-page',
    'root pointer map parent survives' => 0,
    'truncate plan updated freelist pages empty' => [],
    'truncate plan page images only header' => [1],
    'release plan page images had tail trunk' => true,
    'release plan page images had secure-delete leaf' => true,
    'original database page count remains unchanged' => 260,
    'original header page count remains unchanged' => 260,
    'original database had no freelist pages' => [],
    'released pages match fixture tail' => [257, 258, 259, 260],
    'post database can read page before truncated tail' => 512,
];

foreach ($cases as $name => $callback) {
    $tests['btree overflow vacuum current next16 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree overflow vacuum current next16 keeps non-tail release on freelist'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(2);
    $t->same([260, 259], $plan->truncatedPageNumbers());
    $t->same(257, $plan->finalFirstFreelistTrunkPage());
    $t->same(2, $plan->finalFreelistPageCount());
};

$tests['btree overflow vacuum current next16 rejects zero truncation limit'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, [
        ['source' => 'table', 'obsolete_overflow_page_numbers' => [257]],
    ], 0));
};

$tests['btree overflow vacuum current next16 rejects empty delete results'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, [], 4));
};

$tests['btree overflow vacuum current next16 rejects duplicate release pages before vacuum'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, [
        ['source' => 'table', 'obsolete_overflow_page_numbers' => [257]],
        ['source' => 'index', 'obsolete_overflow_page_numbers' => [257]],
    ], 4));
};

$tests['btree overflow vacuum current next16 preserves uncleared tail leaves when secure delete is off'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan] = $fixture(2, false);
    $t->same([], $plan->releasePlan->freePlan->clearedPageNumbers);
    $t->same([260, 259], $plan->truncatedPageNumbers());
};

return $tests;
