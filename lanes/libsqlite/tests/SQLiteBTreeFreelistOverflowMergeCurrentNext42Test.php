<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowFreelistMergePlan;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount, int $firstTrunk, int $freelistCount): string {
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
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize): void {
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
    $pageCount = 220;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $currentLeaves = range(50, 168);
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 5, 122);
    $pages[5] = SQLiteFreelistTrunkPage::assemble(201, $currentLeaves, $pageSize);
    $pages[201] = SQLiteFreelistTrunkPage::assemble(null, [202], $pageSize);
    foreach ([20, 21, 22] as $pageNumber) {
        $pages[$pageNumber] = str_repeat(chr($pageNumber), $pageSize);
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::OVERFLOW_PAGE, $pageNumber - 1, $pageSize);
    }
    foreach ([5, 201, 202] as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    foreach ($currentLeaves as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    ksort($pages);

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $deleteResults = [
        [
            'source' => 'wp_options-table-overflow',
            'obsolete_overflow_page_numbers' => [20, 21],
            'rowids' => [42],
        ],
        [
            'source' => 'wp_options-option-name-index-overflow',
            'obsolete_overflow_page_numbers' => [22],
            'record_values' => [['_transient_timeout_merge_next42', 42]],
        ],
    ];
    $plan = SQLiteOverflowFreelistMergePlan::fromDeleteResults($database, $deleteResults, $secureDelete);
    $postPages = [];
    foreach (range(1, $database->pageCount()) as $pageNumber) {
        $postPages[$pageNumber] = $plan->freePlan->pageImages()[$pageNumber] ?? $database->page($pageNumber);
    }
    ksort($postPages);
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

    return [$database, $plan, $postDatabase];
};

$chainFixture = static function () use ($makeFirstPage, $putPointerMapEntry): SQLiteOverflowFreelistMergePlan {
    $pageSize = 512;
    $pageCount = 64;
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 5, 1);
    $pages[5] = SQLiteFreelistTrunkPage::assemble(null, [], $pageSize);
    foreach (SQLiteOverflowPage::encodeChainAtPages(str_repeat('x', 700), [20, 21], $pageSize) as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
        $putPointerMapEntry($pages, $pageNumber, $pageNumber === 20 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE, $pageNumber === 20 ? 7 : 20, $pageSize);
    }
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    ksort($pages);

    return SQLiteOverflowFreelistMergePlan::fromOverflowChains(
        SQLiteDatabase::fromBytes(implode('', $pages)),
        [['source' => 'wp_options-chain', 'first_page' => 20, 'overflow_payload_bytes' => 700]],
        true,
    );
};

$cases = [
    'returns merge plan class' => static fn (): mixed => get_class($fixture()[1]),
    'reports action' => static fn (): mixed => $fixture()[1]->toArray()['action'],
    'source count' => static fn (): mixed => count($fixture()[1]->sources),
    'first source label' => static fn (): mixed => $fixture()[1]->sources[0]['source'],
    'first source pages' => static fn (): mixed => $fixture()[1]->sources[0]['pages'],
    'first source count' => static fn (): mixed => $fixture()[1]->sources[0]['count'],
    'second source label' => static fn (): mixed => $fixture()[1]->sources[1]['source'],
    'second source pages' => static fn (): mixed => $fixture()[1]->sources[1]['pages'],
    'released page numbers' => static fn (): mixed => $fixture()[1]->freePlan->freedPageNumbers,
    'trunks before count' => static fn (): mixed => count($fixture()[1]->trunksBefore),
    'current trunk before page' => static fn (): mixed => $fixture()[1]->trunksBefore[0]['page_number'],
    'current trunk before next' => static fn (): mixed => $fixture()[1]->trunksBefore[0]['next_trunk_page'],
    'current trunk before leaf count' => static fn (): mixed => $fixture()[1]->trunksBefore[0]['leaf_count'],
    'next trunk before page' => static fn (): mixed => $fixture()[1]->trunksBefore[1]['page_number'],
    'next trunk before leaf count' => static fn (): mixed => $fixture()[1]->trunksBefore[1]['leaf_count'],
    'trunks after count' => static fn (): mixed => count($fixture()[1]->trunksAfter),
    'new current trunk page' => static fn (): mixed => $fixture()[1]->trunksAfter[0]['page_number'],
    'new current trunk next points to old current' => static fn (): mixed => $fixture()[1]->trunksAfter[0]['next_trunk_page'],
    'new current trunk leaf pages' => static fn (): mixed => $fixture()[1]->trunksAfter[0]['leaf_page_numbers'],
    'old current trunk remains next' => static fn (): mixed => $fixture()[1]->trunksAfter[1]['page_number'],
    'old current trunk leaf count after merge' => static fn (): mixed => $fixture()[1]->trunksAfter[1]['leaf_count'],
    'old current trunk appended leaf' => static fn (): mixed => $fixture()[1]->trunksAfter[1]['leaf_page_numbers'][119],
    'old next trunk remains linked' => static fn (): mixed => $fixture()[1]->trunksAfter[2]['page_number'],
    'merge step count' => static fn (): mixed => count($fixture()[1]->mergeSteps),
    'step 1 disposition' => static fn (): mixed => $fixture()[1]->mergeSteps[0]['disposition'],
    'step 1 page' => static fn (): mixed => $fixture()[1]->mergeSteps[0]['page_number'],
    'step 1 trunk' => static fn (): mixed => $fixture()[1]->mergeSteps[0]['trunk_page'],
    'step 1 leaf count before' => static fn (): mixed => $fixture()[1]->mergeSteps[0]['leaf_count_before'],
    'step 1 leaf count after' => static fn (): mixed => $fixture()[1]->mergeSteps[0]['leaf_count_after'],
    'step 1 next trunk before' => static fn (): mixed => $fixture()[1]->mergeSteps[0]['next_trunk_page_before'],
    'step 2 disposition' => static fn (): mixed => $fixture()[1]->mergeSteps[1]['disposition'],
    'step 2 page' => static fn (): mixed => $fixture()[1]->mergeSteps[1]['page_number'],
    'step 2 new trunk points to current' => static fn (): mixed => $fixture()[1]->mergeSteps[1]['next_trunk_page_after'],
    'step 3 disposition' => static fn (): mixed => $fixture()[1]->mergeSteps[2]['disposition'],
    'step 3 page' => static fn (): mixed => $fixture()[1]->mergeSteps[2]['page_number'],
    'step 3 trunk' => static fn (): mixed => $fixture()[1]->mergeSteps[2]['trunk_page'],
    'step 3 leaf count after' => static fn (): mixed => $fixture()[1]->mergeSteps[2]['leaf_count_after'],
    'free plan leaf pages' => static fn (): mixed => $fixture()[1]->freePlan->leafPageNumbers,
    'free plan new trunk pages' => static fn (): mixed => $fixture()[1]->freePlan->newTrunkPageNumbers,
    'free plan first trunk page' => static fn (): mixed => $fixture()[1]->freePlan->firstFreelistTrunkPage,
    'free plan page count increments' => static fn (): mixed => $fixture()[1]->freePlan->freelistPageCount,
    'updated freelist pages' => static fn (): mixed => array_keys($fixture()[1]->freePlan->updatedFreelistPages),
    'updated pointer map pages' => static fn (): mixed => array_keys($fixture()[1]->freePlan->updatedPointerMapPages),
    'secure delete cleared leaves only' => static fn (): mixed => $fixture()[1]->freePlan->clearedPageNumbers,
    'page images include first page' => static fn (): mixed => array_key_exists(1, $fixture()[1]->freePlan->pageImages()),
    'page images include current trunk' => static fn (): mixed => array_key_exists(5, $fixture()[1]->freePlan->pageImages()),
    'page images include new trunk' => static fn (): mixed => array_key_exists(21, $fixture()[1]->freePlan->pageImages()),
    'page images include pointer map' => static fn (): mixed => array_key_exists(2, $fixture()[1]->freePlan->pageImages()),
    'post header first trunk' => static fn (): mixed => SQLiteHeader::parse($fixture()[2]->page(1))->firstFreelistTrunkPage,
    'post header freelist count' => static fn (): mixed => SQLiteHeader::parse($fixture()[2]->page(1))->freelistPageCount,
    'post page 20 pointer map is free' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(20)->typeName(),
    'post page 21 pointer map is free' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(21)->typeName(),
    'post page 22 pointer map is free' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(22)->typeName(),
    'post page 22 parent is zero' => static fn (): mixed => $fixture()[2]->pointerMapEntryForPage(22)->parentPageNumber,
    'toArray merged leaves' => static fn (): mixed => $fixture()[1]->toArray()['merged_leaf_pages'],
    'toArray new current trunks' => static fn (): mixed => $fixture()[1]->toArray()['new_current_trunk_pages'],
    'toArray updated pointer map pages' => static fn (): mixed => $fixture()[1]->toArray()['updated_pointer_map_page_numbers'],
    'toArray secure delete pages' => static fn (): mixed => $fixture()[1]->toArray()['secure_delete_cleared_pages'],
    'chain fixture releases decoded pages' => static fn (): mixed => $chainFixture()->freePlan->freedPageNumbers,
    'chain fixture source label' => static fn (): mixed => $chainFixture()->sources[0]['source'],
    'chain fixture secure delete pages' => static fn (): mixed => $chainFixture()->freePlan->clearedPageNumbers,
    'non secure delete keeps clear list empty' => static fn (): mixed => $fixture(false)[1]->freePlan->clearedPageNumbers,
    'duplicate page guard' => static function () use ($fixture): string {
        try {
            SQLiteOverflowFreelistMergePlan::fromDeleteResults($fixture()[0], [
                ['source' => 'a', 'obsolete_overflow_page_numbers' => [20]],
                ['source' => 'b', 'obsolete_overflow_page_numbers' => [20]],
            ]);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
    'empty input guard' => static function () use ($fixture): string {
        try {
            SQLiteOverflowFreelistMergePlan::fromDeleteResults($fixture()[0], []);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'not rejected';
    },
];

$expected = [
    'returns merge plan class' => SQLiteOverflowFreelistMergePlan::class,
    'reports action' => 'btree-overflow-freelist-merge-current-next',
    'source count' => 2,
    'first source label' => 'wp_options-table-overflow',
    'first source pages' => [20, 21],
    'first source count' => 2,
    'second source label' => 'wp_options-option-name-index-overflow',
    'second source pages' => [22],
    'released page numbers' => [20, 21, 22],
    'trunks before count' => 2,
    'current trunk before page' => 5,
    'current trunk before next' => 201,
    'current trunk before leaf count' => 119,
    'next trunk before page' => 201,
    'next trunk before leaf count' => 1,
    'trunks after count' => 3,
    'new current trunk page' => 21,
    'new current trunk next points to old current' => 5,
    'new current trunk leaf pages' => [22],
    'old current trunk remains next' => 5,
    'old current trunk leaf count after merge' => 120,
    'old current trunk appended leaf' => 20,
    'old next trunk remains linked' => 201,
    'merge step count' => 3,
    'step 1 disposition' => 'merged-into-current-trunk',
    'step 1 page' => 20,
    'step 1 trunk' => 5,
    'step 1 leaf count before' => 119,
    'step 1 leaf count after' => 120,
    'step 1 next trunk before' => 201,
    'step 2 disposition' => 'became-current-trunk',
    'step 2 page' => 21,
    'step 2 new trunk points to current' => 5,
    'step 3 disposition' => 'merged-into-current-trunk',
    'step 3 page' => 22,
    'step 3 trunk' => 21,
    'step 3 leaf count after' => 1,
    'free plan leaf pages' => [20, 22],
    'free plan new trunk pages' => [21],
    'free plan first trunk page' => 21,
    'free plan page count increments' => 125,
    'updated freelist pages' => [5, 21],
    'updated pointer map pages' => [2],
    'secure delete cleared leaves only' => [20, 22],
    'page images include first page' => true,
    'page images include current trunk' => true,
    'page images include new trunk' => true,
    'page images include pointer map' => true,
    'post header first trunk' => 21,
    'post header freelist count' => 125,
    'post page 20 pointer map is free' => 'free-page',
    'post page 21 pointer map is free' => 'free-page',
    'post page 22 pointer map is free' => 'free-page',
    'post page 22 parent is zero' => 0,
    'toArray merged leaves' => [20, 22],
    'toArray new current trunks' => [21],
    'toArray updated pointer map pages' => [2],
    'toArray secure delete pages' => [20, 22],
    'chain fixture releases decoded pages' => [20, 21],
    'chain fixture source label' => 'wp_options-chain',
    'chain fixture secure delete pages' => [20, 21],
    'non secure delete keeps clear list empty' => [],
    'duplicate page guard' => 'SQLite overflow freelist merge page 20 appears more than once',
    'empty input guard' => 'SQLite overflow freelist merge requires at least one delete result',
];

$tests = [];
foreach ($cases as $name => $read) {
    $tests['btree freelist overflow merge current next42 ' . $name] = static function (TestRunner $t) use ($read, $expected, $name): void {
        $t->same($expected[$name], $read());
    };
}

return $tests;
