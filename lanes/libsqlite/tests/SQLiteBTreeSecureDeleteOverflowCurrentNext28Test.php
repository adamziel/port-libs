<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
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
    $pageCount = 260;
    $firstTrunk = 8;
    $existingLeaves = range(130, 249);
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, $firstTrunk, 1 + count($existingLeaves));
    $pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

    $chains = [
        [
            'source' => 'wp_options-table-current-overflow',
            'first_page' => 20,
            'pages' => [20, 22, 106],
            'payload' => str_repeat('table-current-next28:', 60),
            'rowids' => [41],
        ],
        [
            'source' => 'wp_options-index-current-overflow',
            'first_page' => 107,
            'pages' => [107, 21],
            'payload' => str_repeat('index-current-next28:', 35),
            'record_values' => [['_transient_current_next28', 41]],
        ],
    ];

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], $firstTrunk => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($existingLeaves as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }

    foreach ($chains as $chain) {
        $encoded = SQLiteOverflowPage::encodeChainAtPages($chain['payload'], $chain['pages'], $pageSize);
        foreach ($encoded as $pageNumber => $page) {
            $pages[$pageNumber] = $page;
        }

        foreach ($chain['pages'] as $index => $pageNumber) {
            $type = $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE;
            $parent = $index === 0 ? 4 : $chain['pages'][$index - 1];
            $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
        }
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $release = SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $database,
        array_map(
            static fn (array $chain): array => array_filter([
                'source' => $chain['source'],
                'first_page' => $chain['first_page'],
                'overflow_payload_bytes' => strlen($chain['payload']),
                'rowids' => $chain['rowids'] ?? null,
                'record_values' => $chain['record_values'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            $chains,
        ),
        $secureDelete,
    );

    $postPages = $pages;
    foreach ($release->freePlan->pageImages() as $pageNumber => $page) {
        $postPages[$pageNumber] = $page;
    }

    return [
        'database' => $database,
        'postDatabase' => SQLiteDatabase::fromBytes(implode('', $postPages)),
        'release' => $release,
        'chains' => $chains,
        'pageSize' => $pageSize,
    ];
};

$cases = [
    'table current next links are exposed before release' => static fn (array $fx): mixed => SQLiteOverflowPage::chainLinksFromDatabase($fx['database'], 20, strlen($fx['chains'][0]['payload'])),
    'index current next links are exposed before release' => static fn (array $fx): mixed => SQLiteOverflowPage::chainLinksFromDatabase($fx['database'], 107, strlen($fx['chains'][1]['payload'])),
    'table page numbers still follow link map' => static fn (array $fx): mixed => SQLiteOverflowPage::pageNumbersFromDatabase($fx['database'], 20, strlen($fx['chains'][0]['payload'])),
    'index page numbers still follow link map' => static fn (array $fx): mixed => SQLiteOverflowPage::pageNumbersFromDatabase($fx['database'], 107, strlen($fx['chains'][1]['payload'])),
    'released pages retain source order' => static fn (array $fx): mixed => $fx['release']->releasedOverflowPages,
    'table source count remains three current pages' => static fn (array $fx): mixed => $fx['release']->sources[0]['count'],
    'index source count remains two current pages' => static fn (array $fx): mixed => $fx['release']->sources[1]['count'],
    'new freelist trunk is current table page' => static fn (array $fx): mixed => $fx['postDatabase']->header->firstFreelistTrunkPage,
    'new freelist trunk links previous full trunk' => static fn (array $fx): mixed => $fx['postDatabase']->freelistTrunkPages()[0]->nextTrunkPage,
    'new freelist trunk leaves use next pages' => static fn (array $fx): mixed => $fx['postDatabase']->freelistTrunkPages()[0]->leafPageNumbers,
    'freelist contains released current pages' => static fn (array $fx): mixed => array_values(array_intersect([20, 22, 106, 107, 21], $fx['postDatabase']->freelistPageNumbers())),
    'allocation order sees released next pages' => static fn (array $fx): mixed => array_slice($fx['postDatabase']->freelistAllocationOrder(5), 0, 5),
    'free plan cleared only freelist leaves' => static fn (array $fx): mixed => $fx['release']->freePlan->clearedPageNumbers,
    'trunk page keeps freelist header not zero payload' => static fn (array $fx): mixed => bin2hex(substr($fx['postDatabase']->page(20), 0, 8)),
    'first next table page is secure deleted' => static fn (array $fx): mixed => $fx['postDatabase']->page(22) === str_repeat("\0", $fx['pageSize']),
    'second next table page is secure deleted' => static fn (array $fx): mixed => $fx['postDatabase']->page(106) === str_repeat("\0", $fx['pageSize']),
    'first index page is secure deleted when leaf' => static fn (array $fx): mixed => $fx['postDatabase']->page(107) === str_repeat("\0", $fx['pageSize']),
    'index next page is secure deleted' => static fn (array $fx): mixed => $fx['postDatabase']->page(21) === str_repeat("\0", $fx['pageSize']),
    'table current pointer map becomes free page' => static fn (array $fx): mixed => $fx['postDatabase']->pointerMapEntryForPage(20)->typeName(),
    'table next pointer map becomes free page' => static fn (array $fx): mixed => $fx['postDatabase']->pointerMapEntryForPage(22)->typeName(),
    'sparse table next pointer map becomes free page' => static fn (array $fx): mixed => $fx['postDatabase']->pointerMapEntryForPage(106)->typeName(),
    'index current pointer map becomes free page' => static fn (array $fx): mixed => $fx['postDatabase']->pointerMapEntryForPage(107)->typeName(),
    'index next pointer map becomes free page' => static fn (array $fx): mixed => $fx['postDatabase']->pointerMapEntryForPage(21)->typeName(),
    'all released parents are cleared' => static fn (array $fx): mixed => array_map(static fn (int $page): int => $fx['postDatabase']->pointerMapEntryForPage($page)->parentPageNumber, $fx['release']->releasedOverflowPages),
    'pointer map pages span current and high next pages' => static fn (array $fx): mixed => array_keys($fx['release']->freePlan->updatedPointerMapPages),
    'page images include header current trunk and next pages' => static fn (array $fx): mixed => array_values(array_intersect([1, 2, 20, 21, 22, 105, 106, 107], array_keys($fx['release']->freePlan->pageImages()))),
    'summary reports five released pages' => static fn (array $fx): mixed => $fx['release']->toArray()['released_overflow_page_count'],
    'summary reports current next released pages' => static fn (array $fx): mixed => $fx['release']->toArray()['released_overflow_pages'],
    'summary reports secure delete leaves' => static fn (array $fx): mixed => $fx['release']->toArray()['free_plan']['cleared_page_numbers'],
    'summary reports pointer maps' => static fn (array $fx): mixed => $fx['release']->toArray()['free_plan']['updated_pointer_map_page_numbers'],
];

$expected = [
    'table current next links are exposed before release' => [
        ['current_page' => 20, 'next_page' => 22, 'payload_bytes' => 508, 'terminal' => false],
        ['current_page' => 22, 'next_page' => 106, 'payload_bytes' => 508, 'terminal' => false],
        ['current_page' => 106, 'next_page' => 0, 'payload_bytes' => 244, 'terminal' => true],
    ],
    'index current next links are exposed before release' => [
        ['current_page' => 107, 'next_page' => 21, 'payload_bytes' => 508, 'terminal' => false],
        ['current_page' => 21, 'next_page' => 0, 'payload_bytes' => 227, 'terminal' => true],
    ],
    'table page numbers still follow link map' => [20, 22, 106],
    'index page numbers still follow link map' => [107, 21],
    'released pages retain source order' => [20, 22, 106, 107, 21],
    'table source count remains three current pages' => 3,
    'index source count remains two current pages' => 2,
    'new freelist trunk is current table page' => 20,
    'new freelist trunk links previous full trunk' => 8,
    'new freelist trunk leaves use next pages' => [22, 106, 107, 21],
    'freelist contains released current pages' => [20, 22, 106, 107, 21],
    'allocation order sees released next pages' => [22, 21, 107, 106, 20],
    'free plan cleared only freelist leaves' => [22, 106, 107, 21],
    'trunk page keeps freelist header not zero payload' => '0000000800000004',
    'first next table page is secure deleted' => true,
    'second next table page is secure deleted' => true,
    'first index page is secure deleted when leaf' => true,
    'index next page is secure deleted' => true,
    'table current pointer map becomes free page' => 'free-page',
    'table next pointer map becomes free page' => 'free-page',
    'sparse table next pointer map becomes free page' => 'free-page',
    'index current pointer map becomes free page' => 'free-page',
    'index next pointer map becomes free page' => 'free-page',
    'all released parents are cleared' => [0, 0, 0, 0, 0],
    'pointer map pages span current and high next pages' => [2, 105],
    'page images include header current trunk and next pages' => [1, 2, 20, 21, 22, 105, 106, 107],
    'summary reports five released pages' => 5,
    'summary reports current next released pages' => [20, 22, 106, 107, 21],
    'summary reports secure delete leaves' => [22, 106, 107, 21],
    'summary reports pointer maps' => [2, 105],
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['btree secure-delete overflow current-next28 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected, $name): void {
        $t->same($expected[$name], $callback($fixture()));
    };
}

$linkCases = [
    'single page' => [str_repeat('a', 12), [9], [['current_page' => 9, 'next_page' => 0, 'payload_bytes' => 12, 'terminal' => true]]],
    'two forward pages' => [str_repeat('b', 509), [4, 5], [['current_page' => 4, 'next_page' => 5, 'payload_bytes' => 508, 'terminal' => false], ['current_page' => 5, 'next_page' => 0, 'payload_bytes' => 1, 'terminal' => true]]],
    'two sparse pages' => [str_repeat('c', 700), [40, 7], [['current_page' => 40, 'next_page' => 7, 'payload_bytes' => 508, 'terminal' => false], ['current_page' => 7, 'next_page' => 0, 'payload_bytes' => 192, 'terminal' => true]]],
    'three sparse pages' => [str_repeat('d', 1100), [30, 4, 25], [['current_page' => 30, 'next_page' => 4, 'payload_bytes' => 508, 'terminal' => false], ['current_page' => 4, 'next_page' => 25, 'payload_bytes' => 508, 'terminal' => false], ['current_page' => 25, 'next_page' => 0, 'payload_bytes' => 84, 'terminal' => true]]],
    'reserved bytes pages' => [str_repeat('e', 990), [18, 6], [['current_page' => 18, 'next_page' => 6, 'payload_bytes' => 496, 'terminal' => false], ['current_page' => 6, 'next_page' => 0, 'payload_bytes' => 494, 'terminal' => true]], 512, 500],
    'large page pages' => [str_repeat('f', 4100), [100, 15], [['current_page' => 100, 'next_page' => 15, 'payload_bytes' => 4092, 'terminal' => false], ['current_page' => 15, 'next_page' => 0, 'payload_bytes' => 8, 'terminal' => true]], 4096, 4096],
];

foreach ($linkCases as $name => $case) {
    $tests['btree secure-delete overflow current-next28 link audit ' . $name] = static function (TestRunner $t) use ($case): void {
        [$payload, $pageNumbers, $expectedLinks, $pageSize, $usableSize] = $case + [3 => 512, 4 => 512];
        $pages = SQLiteOverflowPage::encodeChainAtPages($payload, $pageNumbers, $pageSize, $usableSize);
        $reader = static fn (int $pageNumber): string => $pages[$pageNumber] ?? throw new InvalidArgumentException('missing overflow page');

        $t->same($expectedLinks, SQLiteOverflowPage::chainLinksFromChain($pageNumbers[0], strlen($payload), $reader, $pageSize, $usableSize));
    };
}

$tests['btree secure-delete overflow current-next28 keeps bytes without secure delete'] = static function (TestRunner $t) use ($fixture): void {
    $fx = $fixture(false);

    $t->same('-current-next28:tabl', substr($fx['postDatabase']->page(22), 5, 20));
};

$tests['btree secure-delete overflow current-next28 rejects trailing next pointer'] = static function (TestRunner $t): void {
    $payload = str_repeat('z', 700);
    $pages = SQLiteOverflowPage::encodeChainAtPages($payload, [4, 6], 512);
    $pages[6] = substr_replace($pages[6], pack('N', 8), 0, 4);

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::chainLinksFromChain(4, strlen($payload), static fn (int $pageNumber): string => $pages[$pageNumber], 512));
};

$tests['btree secure-delete overflow current-next28 rejects short page image'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::chainLinksFromChain(4, 12, static fn (int $_pageNumber): string => str_repeat("\0", 511), 512));
};

$tests['btree secure-delete overflow current-next28 rejects duplicate pages'] = static function (TestRunner $t) use ($fixture): void {
    $fx = $fixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
        $fx['database'],
        [
            ['source' => 'table', 'first_page' => 20, 'overflow_payload_bytes' => strlen($fx['chains'][0]['payload'])],
            ['source' => 'same-table', 'first_page' => 20, 'overflow_payload_bytes' => strlen($fx['chains'][0]['payload'])],
        ],
        true,
    ));
};

$tests['btree secure-delete overflow current-next28 rejects non-positive payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::chainLinksFromChain(4, 0, static fn (int $_pageNumber): string => str_repeat("\0", 512), 512));
};

return $tests;
