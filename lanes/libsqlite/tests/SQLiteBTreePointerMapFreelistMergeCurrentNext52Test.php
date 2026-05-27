<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReleasePlan;
use PortLibs\LibSqlite\SQLiteOverflowPage;
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

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

$fixture = static function (bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 260;
    $firstTrunk = 8;
    $existingLeaves = [130, 131, 132];
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $firstTrunk, 1 + count($existingLeaves));
    $pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

    $tablePayload = str_repeat('merge-current-table:', 58);
    $indexPayload = str_repeat('merge-current-index:', 38);
    $tableOverflowPages = SQLiteOverflowPage::encodeChainAtPages($tablePayload, [20, 22, 106], $pageSize);
    $indexOverflowPages = SQLiteOverflowPage::encodeChainAtPages($indexPayload, [107, 21], $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], $firstTrunk => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($existingLeaves as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    foreach ([20, 107] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4, $pageSize);
    }
    foreach ([22 => 20, 106 => 22, 21 => 107] as $pageNumber => $parentPageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::OVERFLOW_PAGE, $parentPageNumber, $pageSize);
    }
    foreach ($tableOverflowPages + $indexOverflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $database,
        [
            [
                'source' => 'wp_options-current-table-overflow',
                'first_page' => 20,
                'overflow_payload_bytes' => strlen($tablePayload),
                'rowids' => [52],
            ],
            [
                'source' => 'wp_options-current-option-name-overflow',
                'first_page' => 107,
                'overflow_payload_bytes' => strlen($indexPayload),
                'record_values' => [['_transient_merge_current', 52]],
            ],
        ],
        $secureDelete,
    );

    foreach ($plan->freePlan->pageImages() as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    return [
        'database' => $database,
        'plan' => $plan,
        'post' => SQLiteDatabase::fromBytes(implode('', $pages)),
        'existingLeaves' => $existingLeaves,
        'tablePayloadBytes' => strlen($tablePayload),
        'indexPayloadBytes' => strlen($indexPayload),
    ];
};

$cases = [
    'table chain follows current next pages' => static fn (array $fx): mixed => $fx['plan']->sources[0]['pages'],
    'index chain follows current next pages' => static fn (array $fx): mixed => $fx['plan']->sources[1]['pages'],
    'release order preserves table then index chains' => static fn (array $fx): mixed => $fx['plan']->releasedOverflowPages,
    'first trunk remains existing trunk' => static fn (array $fx): mixed => $fx['post']->header->firstFreelistTrunkPage,
    'freelist count appends five pages' => static fn (array $fx): mixed => $fx['post']->header->freelistPageCount,
    'no new trunk is promoted' => static fn (array $fx): mixed => $fx['plan']->freePlan->newTrunkPageNumbers,
    'free pages are stored as leaves' => static fn (array $fx): mixed => $fx['plan']->freePlan->leafPageNumbers,
    'existing trunk diagnostic is retained' => static fn (array $fx): mixed => $fx['plan']->freePlan->existingTrunkPageNumbers(),
    'updated freelist pages include only existing trunk' => static fn (array $fx): mixed => array_keys($fx['plan']->freePlan->updatedFreelistPages),
    'existing trunk next pointer remains null' => static fn (array $fx): mixed => $fx['post']->freelistTrunkPages()[0]->nextTrunkPage,
    'existing trunk contains old and released leaves' => static fn (array $fx): mixed => $fx['post']->freelistTrunkPages()[0]->leafPageNumbers,
    'allocation order starts with old first leaf' => static fn (array $fx): mixed => array_slice($fx['post']->freelistAllocationOrder(8), 0, 8),
    'database page count is stable' => static fn (array $fx): mixed => $fx['post']->pageCount(),
    'header page count is stable' => static fn (array $fx): mixed => $fx['post']->header->databaseSizePages,
    'database header parser sees existing first trunk' => static fn (array $fx): mixed => SQLiteHeader::parse($fx['plan']->freePlan->firstPage)->firstFreelistTrunkPage,
    'database header parser sees merged free count' => static fn (array $fx): mixed => SQLiteHeader::parse($fx['plan']->freePlan->firstPage)->freelistPageCount,
    'page images include header' => static fn (array $fx): mixed => array_key_exists(1, $fx['plan']->freePlan->pageImages()),
    'page images include existing trunk' => static fn (array $fx): mixed => array_key_exists(8, $fx['plan']->freePlan->pageImages()),
    'page images omit new trunk twenty' => static fn (array $fx): mixed => array_key_exists(20, $fx['plan']->freePlan->updatedFreelistPages),
    'page images include pointer map page two' => static fn (array $fx): mixed => array_key_exists(2, $fx['plan']->freePlan->pageImages()),
    'page images include pointer map page one hundred five' => static fn (array $fx): mixed => array_key_exists(105, $fx['plan']->freePlan->pageImages()),
    'secure delete clears page twenty' => static fn (array $fx): mixed => $fx['post']->page(20) === str_repeat("\0", 512),
    'secure delete clears page twenty two' => static fn (array $fx): mixed => $fx['post']->page(22) === str_repeat("\0", 512),
    'secure delete clears page one hundred six' => static fn (array $fx): mixed => $fx['post']->page(106) === str_repeat("\0", 512),
    'secure delete clears page one hundred seven' => static fn (array $fx): mixed => $fx['post']->page(107) === str_repeat("\0", 512),
    'secure delete clears page twenty one' => static fn (array $fx): mixed => $fx['post']->page(21) === str_repeat("\0", 512),
    'cleared pages include all released overflow pages' => static fn (array $fx): mixed => $fx['plan']->freePlan->clearedPageNumbers,
    'first pointer-map page updated' => static fn (array $fx): mixed => array_key_exists(2, $fx['plan']->freePlan->updatedPointerMapPages),
    'second pointer-map page updated' => static fn (array $fx): mixed => array_key_exists(105, $fx['plan']->freePlan->updatedPointerMapPages),
    'page twenty pointer map type is free' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(20)->typeName(),
    'page twenty two pointer map type is free' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(22)->typeName(),
    'page one hundred six pointer map type is free' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(106)->typeName(),
    'page one hundred seven pointer map type is free' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(107)->typeName(),
    'page twenty one pointer map type is free' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(21)->typeName(),
    'page twenty pointer map parent cleared' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(20)->parentPageNumber,
    'page one hundred seven pointer map parent cleared' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(107)->parentPageNumber,
    'freed pointer map entries are reported' => static fn (array $fx): mixed => count($fx['plan']->freePlan->freedPointerMapEntries),
    'first freed pointer map page is page two' => static fn (array $fx): mixed => $fx['plan']->freePlan->freedPointerMapEntries[0]['pointer_map_page'],
    'last freed pointer map page is page two' => static fn (array $fx): mixed => $fx['plan']->freePlan->freedPointerMapEntries[4]['pointer_map_page'],
    'middle freed pointer map page crosses to page one hundred five' => static fn (array $fx): mixed => $fx['plan']->freePlan->freedPointerMapEntries[2]['pointer_map_page'],
    'summary includes existing trunk diagnostic' => static fn (array $fx): mixed => $fx['plan']->freePlan->toArray()['existing_trunk_page_numbers'],
    'summary omits new trunks' => static fn (array $fx): mixed => $fx['plan']->freePlan->toArray()['new_trunk_page_numbers'],
    'summary carries merged leaf pages' => static fn (array $fx): mixed => $fx['plan']->freePlan->toArray()['leaf_page_numbers'],
    'summary carries updated freelist pages' => static fn (array $fx): mixed => $fx['plan']->freePlan->toArray()['updated_freelist_page_numbers'],
    'summary carries pointer-map pages' => static fn (array $fx): mixed => $fx['plan']->freePlan->toArray()['updated_pointer_map_page_numbers'],
    'summary carries cleared pages' => static fn (array $fx): mixed => $fx['plan']->freePlan->toArray()['cleared_page_numbers'],
    'summary carries freed pointer-map entries' => static fn (array $fx): mixed => count($fx['plan']->freePlan->toArray()['freed_pointer_map_entries']),
    'release summary source count' => static fn (array $fx): mixed => count($fx['plan']->toArray()['sources']),
    'release summary page count' => static fn (array $fx): mixed => $fx['plan']->toArray()['released_overflow_page_count'],
    'release summary free count' => static fn (array $fx): mixed => $fx['plan']->toArray()['free_plan']['freelist_page_count'],
    'release summary first trunk' => static fn (array $fx): mixed => $fx['plan']->toArray()['free_plan']['first_freelist_trunk_page'],
    'table source keeps rowid label' => static fn (array $fx): mixed => $fx['plan']->sources[0]['source'],
    'index source keeps option-name label' => static fn (array $fx): mixed => $fx['plan']->sources[1]['source'],
    'table source count is three' => static fn (array $fx): mixed => $fx['plan']->sources[0]['count'],
    'index source count is two' => static fn (array $fx): mixed => $fx['plan']->sources[1]['count'],
    'old trunk pointer-map entry remains free' => static fn (array $fx): mixed => $fx['post']->pointerMapEntryForPage(8)->typeName(),
    'old first leaf is still present' => static fn (array $fx): mixed => $fx['post']->freelistTrunkPages()[0]->leafPageNumbers[0],
    'old last leaf is still present before merge tail' => static fn (array $fx): mixed => $fx['post']->freelistTrunkPages()[0]->leafPageNumbers[2],
    'merged tail first page is twenty' => static fn (array $fx): mixed => $fx['post']->freelistTrunkPages()[0]->leafPageNumbers[3],
    'merged tail final page is twenty one' => static fn (array $fx): mixed => $fx['post']->freelistTrunkPages()[0]->leafPageNumbers[7],
];

$expected = [
    'table chain follows current next pages' => [20, 22, 106],
    'index chain follows current next pages' => [107, 21],
    'release order preserves table then index chains' => [20, 22, 106, 107, 21],
    'first trunk remains existing trunk' => 8,
    'freelist count appends five pages' => 9,
    'no new trunk is promoted' => [],
    'free pages are stored as leaves' => [20, 22, 106, 107, 21],
    'existing trunk diagnostic is retained' => [8],
    'updated freelist pages include only existing trunk' => [8],
    'existing trunk next pointer remains null' => null,
    'existing trunk contains old and released leaves' => [130, 131, 132, 20, 22, 106, 107, 21],
    'allocation order starts with old first leaf' => [130, 21, 107, 106, 22, 20, 132, 131],
    'database page count is stable' => 260,
    'header page count is stable' => 260,
    'database header parser sees existing first trunk' => 8,
    'database header parser sees merged free count' => 9,
    'page images include header' => true,
    'page images include existing trunk' => true,
    'page images omit new trunk twenty' => false,
    'page images include pointer map page two' => true,
    'page images include pointer map page one hundred five' => true,
    'secure delete clears page twenty' => true,
    'secure delete clears page twenty two' => true,
    'secure delete clears page one hundred six' => true,
    'secure delete clears page one hundred seven' => true,
    'secure delete clears page twenty one' => true,
    'cleared pages include all released overflow pages' => [20, 22, 106, 107, 21],
    'first pointer-map page updated' => true,
    'second pointer-map page updated' => true,
    'page twenty pointer map type is free' => 'free-page',
    'page twenty two pointer map type is free' => 'free-page',
    'page one hundred six pointer map type is free' => 'free-page',
    'page one hundred seven pointer map type is free' => 'free-page',
    'page twenty one pointer map type is free' => 'free-page',
    'page twenty pointer map parent cleared' => 0,
    'page one hundred seven pointer map parent cleared' => 0,
    'freed pointer map entries are reported' => 5,
    'first freed pointer map page is page two' => 2,
    'last freed pointer map page is page two' => 2,
    'middle freed pointer map page crosses to page one hundred five' => 105,
    'summary includes existing trunk diagnostic' => [8],
    'summary omits new trunks' => [],
    'summary carries merged leaf pages' => [20, 22, 106, 107, 21],
    'summary carries updated freelist pages' => [8],
    'summary carries pointer-map pages' => [2, 105],
    'summary carries cleared pages' => [20, 22, 106, 107, 21],
    'summary carries freed pointer-map entries' => 5,
    'release summary source count' => 2,
    'release summary page count' => 5,
    'release summary free count' => 9,
    'release summary first trunk' => 8,
    'table source keeps rowid label' => 'wp_options-current-table-overflow',
    'index source keeps option-name label' => 'wp_options-current-option-name-overflow',
    'table source count is three' => 3,
    'index source count is two' => 2,
    'old trunk pointer-map entry remains free' => 'free-page',
    'old first leaf is still present' => 130,
    'old last leaf is still present before merge tail' => 132,
    'merged tail first page is twenty' => 20,
    'merged tail final page is twenty one' => 21,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree pointer-map freelist merge current-next52 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree pointer-map freelist merge current-next52 preserves current page bytes without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    $fx = $fixture(false);

    $t->same('merge-current-table:', substr($fx['post']->page(20), 4, 20));
};

$tests['btree pointer-map freelist merge current-next52 still frees pointer map without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    $fx = $fixture(false);

    $t->same('free-page', $fx['post']->pointerMapEntryForPage(106)->typeName());
};

$tests['btree pointer-map freelist merge current-next52 rejects releasing current trunk page'] = static function (TestRunner $t) use ($fixture): void {
    $fx = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromDeleteResults(
        $fx['database'],
        [['source' => 'bad-trunk-release', 'obsolete_overflow_page_numbers' => [8]]],
        true,
    ));
};

$tests['btree pointer-map freelist merge current-next52 rejects pointer-map page release'] = static function (TestRunner $t) use ($fixture): void {
    $fx = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromDeleteResults(
        $fx['database'],
        [['source' => 'bad-pointer-map-release', 'obsolete_overflow_page_numbers' => [105]]],
        true,
    ));
};

return $tests;
