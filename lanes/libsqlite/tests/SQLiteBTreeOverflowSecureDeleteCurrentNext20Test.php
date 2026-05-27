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

$fixture = static function (bool $secureDelete = true) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageSize = 512;
    $pageCount = 260;
    $firstTrunk = 8;
    $existingLeaves = range(130, 249);
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $firstTrunk, 1 + count($existingLeaves));
    $pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

    $tablePayload = str_repeat('table-current-next:', 60);
    $indexPayload = str_repeat('index-current-next:', 35);
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
                'source' => 'wp_options-table-current-overflow',
                'first_page' => 20,
                'overflow_payload_bytes' => strlen($tablePayload),
                'rowids' => [41],
            ],
            [
                'source' => 'wp_options-option-name-current-overflow',
                'first_page' => 107,
                'overflow_payload_bytes' => strlen($indexPayload),
                'record_values' => [['_transient_current_next', 41]],
            ],
        ],
        $secureDelete,
    );

    foreach ($plan->freePlan->pageImages() as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }

    return [$database, $plan, SQLiteDatabase::fromBytes(implode('', $pages)), $pages, $tablePayload, $indexPayload];
};

$cases = [
    'first table chain follows current next pointers' => static fn (array $fx): mixed => $fx[1]->sources[0]['pages'],
    'second index chain follows current next pointers' => static fn (array $fx): mixed => $fx[1]->sources[1]['pages'],
    'released pages preserve chain order across sources' => static fn (array $fx): mixed => $fx[1]->releasedOverflowPages,
    'first released page becomes new trunk when current trunk is full' => static fn (array $fx): mixed => $fx[2]->header->firstFreelistTrunkPage,
    'freelist count includes five released current pages' => static fn (array $fx): mixed => $fx[2]->header->freelistPageCount,
    'new trunk points at previous trunk' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[0]->nextTrunkPage,
    'new trunk leaf pages use remaining released pages' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[0]->leafPageNumbers,
    'old trunk remains second trunk' => static fn (array $fx): mixed => $fx[2]->freelistTrunkPages()[1]->pageNumber,
    'old trunk still has all prior leaves' => static fn (array $fx): mixed => count($fx[2]->freelistTrunkPages()[1]->leafPageNumbers),
    'allocation order starts with first leaf on new trunk' => static fn (array $fx): mixed => array_slice($fx[2]->freelistAllocationOrder(7), 0, 7),
    'table source label is retained' => static fn (array $fx): mixed => $fx[1]->sources[0]['source'],
    'index source label is retained' => static fn (array $fx): mixed => $fx[1]->sources[1]['source'],
    'table source count is three pages' => static fn (array $fx): mixed => $fx[1]->sources[0]['count'],
    'index source count is two pages' => static fn (array $fx): mixed => $fx[1]->sources[1]['count'],
    'summary released page count is five' => static fn (array $fx): mixed => $fx[1]->toArray()['released_overflow_page_count'],
    'free plan freed pages are current next pages' => static fn (array $fx): mixed => $fx[1]->freePlan->freedPageNumbers,
    'free plan records new trunk page' => static fn (array $fx): mixed => $fx[1]->freePlan->newTrunkPageNumbers,
    'free plan records secure deleted leaves' => static fn (array $fx): mixed => $fx[1]->freePlan->clearedPageNumbers,
    'free plan records updated freelist page' => static fn (array $fx): mixed => array_keys($fx[1]->freePlan->updatedFreelistPages),
    'free plan records updated pointer map pages' => static fn (array $fx): mixed => array_keys($fx[1]->freePlan->updatedPointerMapPages),
    'page images include database header' => static fn (array $fx): mixed => array_key_exists(1, $fx[1]->freePlan->pageImages()),
    'page images include pointer map page two' => static fn (array $fx): mixed => array_key_exists(2, $fx[1]->freePlan->pageImages()),
    'page images include pointer map page one hundred five' => static fn (array $fx): mixed => array_key_exists(105, $fx[1]->freePlan->pageImages()),
    'page images include new trunk page' => static fn (array $fx): mixed => array_key_exists(20, $fx[1]->freePlan->pageImages()),
    'page images include cleared leaf twenty two' => static fn (array $fx): mixed => array_key_exists(22, $fx[1]->freePlan->pageImages()),
    'page twenty is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(20)->typeName(),
    'page twenty two is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(22)->typeName(),
    'page one hundred six is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(106)->typeName(),
    'page one hundred seven is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(107)->typeName(),
    'page twenty one is free page' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(21)->typeName(),
    'page twenty parent cleared' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(20)->parentPageNumber,
    'page twenty one parent cleared' => static fn (array $fx): mixed => $fx[2]->pointerMapEntryForPage(21)->parentPageNumber,
    'secure delete clears leaf twenty two' => static fn (array $fx): mixed => $fx[2]->page(22) === str_repeat("\0", 512),
    'secure delete clears leaf one hundred six' => static fn (array $fx): mixed => $fx[2]->page(106) === str_repeat("\0", 512),
    'secure delete clears leaf one hundred seven' => static fn (array $fx): mixed => $fx[2]->page(107) === str_repeat("\0", 512),
    'secure delete clears leaf twenty one' => static fn (array $fx): mixed => $fx[2]->page(21) === str_repeat("\0", 512),
    'new trunk keeps freelist header bytes' => static fn (array $fx): mixed => bin2hex(substr($fx[2]->page(20), 0, 8)),
    'post freelist contains all released pages' => static fn (array $fx): mixed => array_values(array_intersect([20, 22, 106, 107, 21], $fx[2]->freelistPageNumbers())),
    'header parser sees new first trunk' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->freePlan->firstPage)->firstFreelistTrunkPage,
    'header parser sees new free count' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->freePlan->firstPage)->freelistPageCount,
    'summary free plan leaf list' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['leaf_page_numbers'],
    'summary free plan new trunk list' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['new_trunk_page_numbers'],
    'summary free plan pointer maps' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['updated_pointer_map_page_numbers'],
    'summary free plan cleared pages' => static fn (array $fx): mixed => $fx[1]->toArray()['free_plan']['cleared_page_numbers'],
];

$expected = [
    'first table chain follows current next pointers' => [20, 22, 106],
    'second index chain follows current next pointers' => [107, 21],
    'released pages preserve chain order across sources' => [20, 22, 106, 107, 21],
    'first released page becomes new trunk when current trunk is full' => 20,
    'freelist count includes five released current pages' => 126,
    'new trunk points at previous trunk' => 8,
    'new trunk leaf pages use remaining released pages' => [22, 106, 107, 21],
    'old trunk remains second trunk' => 8,
    'old trunk still has all prior leaves' => 120,
    'allocation order starts with first leaf on new trunk' => [22, 21, 107, 106, 20, 130, 249],
    'table source label is retained' => 'wp_options-table-current-overflow',
    'index source label is retained' => 'wp_options-option-name-current-overflow',
    'table source count is three pages' => 3,
    'index source count is two pages' => 2,
    'summary released page count is five' => 5,
    'free plan freed pages are current next pages' => [20, 22, 106, 107, 21],
    'free plan records new trunk page' => [20],
    'free plan records secure deleted leaves' => [22, 106, 107, 21],
    'free plan records updated freelist page' => [20],
    'free plan records updated pointer map pages' => [2, 105],
    'page images include database header' => true,
    'page images include pointer map page two' => true,
    'page images include pointer map page one hundred five' => true,
    'page images include new trunk page' => true,
    'page images include cleared leaf twenty two' => true,
    'page twenty is free page' => 'free-page',
    'page twenty two is free page' => 'free-page',
    'page one hundred six is free page' => 'free-page',
    'page one hundred seven is free page' => 'free-page',
    'page twenty one is free page' => 'free-page',
    'page twenty parent cleared' => 0,
    'page twenty one parent cleared' => 0,
    'secure delete clears leaf twenty two' => true,
    'secure delete clears leaf one hundred six' => true,
    'secure delete clears leaf one hundred seven' => true,
    'secure delete clears leaf twenty one' => true,
    'new trunk keeps freelist header bytes' => '0000000800000004',
    'post freelist contains all released pages' => [20, 22, 106, 107, 21],
    'header parser sees new first trunk' => 20,
    'header parser sees new free count' => 126,
    'summary free plan leaf list' => [22, 106, 107, 21],
    'summary free plan new trunk list' => [20],
    'summary free plan pointer maps' => [2, 105],
    'summary free plan cleared pages' => [22, 106, 107, 21],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree overflow secure-delete current next20 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$tests['btree overflow secure-delete current next20 rejects corrupt trailing pointer'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();
    $page = $database->page(106);
    $page = substr_replace($page, pack('N', 200), 0, 4);
    $reflected = new ReflectionClass($database);
    $method = $reflected->getMethod('withPageImages');
    $method->setAccessible(true);
    $corruptDatabase = $method->invoke($database, [106 => $page]);

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $corruptDatabase,
        [['first_page' => 20, 'overflow_payload_bytes' => 1140]],
        true,
    ));
};

$tests['btree overflow secure-delete current next20 preserves overflow bytes without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    [, , $postDatabase] = $fixture(false);

    $t->same('next:table-current', substr($postDatabase->page(22), 4, 18));
};

$tests['btree overflow secure-delete current next20 non-secure still updates pointer map'] = static function (TestRunner $t) use ($fixture): void {
    [, , $postDatabase] = $fixture(false);

    $t->same('free-page', $postDatabase->pointerMapEntryForPage(22)->typeName());
};

$tests['btree overflow secure-delete current next20 rejects duplicate page across chains'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $database,
        [
            ['first_page' => 20, 'overflow_payload_bytes' => 1140],
            ['first_page' => 20, 'overflow_payload_bytes' => 1140],
        ],
        true,
    ));
};

$tests['btree overflow secure-delete current next20 rejects empty chain list'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains($database, [], true));
};

$tests['btree overflow secure-delete current next20 rejects missing first page'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $database,
        [['overflow_payload_bytes' => 1140]],
        true,
    ));
};

$tests['btree overflow secure-delete current next20 rejects missing payload byte count'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $database,
        [['first_page' => 20]],
        true,
    ));
};

$tests['btree overflow secure-delete current next20 rejects blank source label'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $database,
        [['source' => '', 'first_page' => 20, 'overflow_payload_bytes' => 1140]],
        true,
    ));
};

return $tests;
