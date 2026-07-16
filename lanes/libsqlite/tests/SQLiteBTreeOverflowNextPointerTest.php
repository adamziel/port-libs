<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowCellReuseDeleteApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageSize = 512, int $databaseSizePages = 1): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$tests = [
    'follows sqlite overflow next pointers when releasing non-contiguous pages' => static function (TestRunner $t) use ($makeFirstPage): void {
        $pageSize = 512;
        $pageCount = 16;
        $emptyPage = str_repeat("\0", $pageSize);
        $firstPage = $makeFirstPage($pageSize, $pageCount);
        $firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

        $pointerMapPage = str_repeat("\0", $pageSize);
        $setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pointerMapPage): void {
            $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
        };
        $setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
        $setPointerMap(11, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
        $setPointerMap(6, SQLitePointerMapEntry::OVERFLOW_PAGE, 11);
        $setPointerMap(14, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

        $keptPayload = SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']);
        $oldPayload = SQLiteRecord::encode([null, '_transient_next_pointer_old', str_repeat('overflow-next-pointer:', 75), 'no']);
        $newPayload = SQLiteRecord::encode([null, '_transient_next_pointer_new', 'local', 'no']);
        $oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
        $overflowPayloadLength = strlen($oldPayload) - $oldLocalLength;
        $overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($oldPayload, $oldLocalLength), [11, 6, 14], $pageSize);
        $leafPage = SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(1, $keptPayload),
            SQLiteTableLeafCell::encode(7, $oldPayload, $pageSize, 11),
        ], $pageSize);

        $pages = [
            1 => $firstPage,
            2 => $pointerMapPage,
            3 => $leafPage,
        ];
        for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[$pageNumber] = $overflowPages[$pageNumber] ?? $emptyPage;
        }
        ksort($pages);
        $database = SQLiteDatabase::fromBytes(implode('', $pages));

        $overflowNumbers = static fn (int $firstOverflowPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstOverflowPage, $byteCount);
        $plan = SQLiteBTreeOverflowCellReuseDeleteApplyPlan::tableCell(
            $database,
            3,
            $leafPage,
            7,
            8,
            $newPayload,
            $overflowNumbers,
            true,
        );

        $postPages = $pages;
        foreach ($plan->pageImages as $pageNumber => $page) {
            $postPages[$pageNumber] = $page;
        }
        ksort($postPages);
        $postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
        $summary = $plan->toArray();

        $t->same([11, 6, 14], SQLiteOverflowPage::pageNumbersFromDatabase($database, 11, $overflowPayloadLength));
        $t->same([11, 6, 14], $plan->obsoleteOverflowPageNumbers);
        $t->same([11, 6, 14], $plan->freePlan->freedPageNumbers);
        $t->same([1, 2, 3, 6, 11, 14], array_keys($plan->pageImages));
        $t->same([2], array_keys($plan->freePlan->updatedPointerMapPages));
        $t->same([6, 14], $plan->freePlan->leafPageNumbers);
        $t->same([11], $plan->freePlan->newTrunkPageNumbers);
        $t->same([6, 14], SQLiteFreelistTrunkPage::parse(11, $plan->pageImages[11], $pageSize, $pageSize)->leafPageNumbers);
        $t->same(11, SQLiteHeader::parse($plan->pageImages[1])->firstFreelistTrunkPage);
        $t->same(3, SQLiteHeader::parse($plan->pageImages[1])->freelistPageCount);
        $t->same([11, 6, 14], $postDatabase->freelistPageNumbers());
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(11)->typeName());
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(6)->typeName());
        $t->same('free-page', $postDatabase->pointerMapEntryForPage(14)->typeName());
        $t->same(0, $postDatabase->pointerMapEntryForPage(11)->parentPageNumber);
        $t->same(0, $postDatabase->pointerMapEntryForPage(6)->parentPageNumber);
        $t->same(0, $postDatabase->pointerMapEntryForPage(14)->parentPageNumber);
        $t->same(str_repeat("\0", $pageSize), $postDatabase->page(6));
        $t->same(str_repeat("\0", $pageSize), $postDatabase->page(14));
        $t->true($plan->remainingFreeblockBytes > 0);
        $t->same([11, 6, 14], $summary['obsolete_overflow_pages']);
        $t->same([11, 6, 14], $summary['freed_pages']);
        $t->same([2], $summary['updated_pointer_map_page_numbers']);
        $t->same([6, 14], $summary['secure_delete_cleared_pages']);
    },
    'collects sqlite overflow next pointers from arbitrary page readers' => static function (TestRunner $t): void {
        $pageSize = 512;
        $payload = str_repeat('next-pointer:', 95);
        $pages = SQLiteOverflowPage::encodeChainAtPages($payload, [9, 4, 12], $pageSize);

        $reader = static fn (int $pageNumber): string => $pages[$pageNumber] ?? throw new InvalidArgumentException('missing page');

        $t->same([9, 4, 12], SQLiteOverflowPage::pageNumbersFromChain(9, strlen($payload), $reader, $pageSize));
        $t->same(4, unpack('N', substr($pages[9], 0, 4))[1]);
        $t->same(12, unpack('N', substr($pages[4], 0, 4))[1]);
        $t->same(0, unpack('N', substr($pages[12], 0, 4))[1]);
        $t->same(3, SQLiteOverflowPage::requiredPageCount(strlen($payload), $pageSize));
        $t->same(strlen($payload), 1235);
    },
    'rejects corrupt sqlite overflow next-pointer chains' => static function (TestRunner $t): void {
        $pageSize = 512;
        $payload = str_repeat('corrupt-next:', 90);
        $pages = SQLiteOverflowPage::encodeChainAtPages($payload, [4, 7, 10], $pageSize);
        $loopPages = $pages;
        $loopPages[10] = substr_replace($loopPages[10], pack('N', 4), 0, 4);
        $shortPages = $pages;
        $shortPages[7] = substr_replace($shortPages[7], pack('N', 0), 0, 4);
        $trailingPages = $pages;
        $trailingPages[10] = substr_replace($trailingPages[10], pack('N', 12), 0, 4);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(1, strlen($payload), static fn (int $pageNumber): string => $pages[$pageNumber], $pageSize));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(4, 0, static fn (int $pageNumber): string => $pages[$pageNumber], $pageSize));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(4, strlen($payload), static fn (int $pageNumber): string => $loopPages[$pageNumber], $pageSize));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(4, strlen($payload), static fn (int $pageNumber): string => $shortPages[$pageNumber], $pageSize));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(4, strlen($payload), static fn (int $pageNumber): string => $trailingPages[$pageNumber], $pageSize));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(4, strlen($payload), static fn (int $_pageNumber): string => str_repeat("\0", 511), $pageSize));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteOverflowPage::pageNumbersFromChain(4, strlen($payload), static fn (int $_pageNumber): string => '', $pageSize));
    },
];

$nextPointerCases = [
    'two page forward chain' => ['payload' => str_repeat('a', 509), 'pages' => [4, 5]],
    'two page backward chain' => ['payload' => str_repeat('b', 509), 'pages' => [9, 4]],
    'two page high gap chain' => ['payload' => str_repeat('c', 700), 'pages' => [15, 6]],
    'three page mixed chain' => ['payload' => str_repeat('d', 1040), 'pages' => [8, 3, 14]],
    'three page descending chain' => ['payload' => str_repeat('e', 1100), 'pages' => [12, 7, 4]],
    'three page sparse chain' => ['payload' => str_repeat('f', 1200), 'pages' => [20, 11, 18]],
    'four page forward chain' => ['payload' => str_repeat('g', 1700), 'pages' => [3, 4, 5, 6]],
    'four page mixed chain' => ['payload' => str_repeat('h', 1800), 'pages' => [16, 5, 21, 9]],
    'four page descending chain' => ['payload' => str_repeat('i', 1900), 'pages' => [30, 22, 14, 6]],
    'five page sparse chain' => ['payload' => str_repeat('j', 2300), 'pages' => [25, 10, 18, 7, 31]],
    'five page local payload chain' => ['payload' => str_repeat('k', 2500), 'pages' => [6, 12, 4, 20, 8]],
    'single page exact chain' => ['payload' => str_repeat('l', 508), 'pages' => [4]],
    'single page short chain' => ['payload' => str_repeat('m', 17), 'pages' => [13]],
    'single page full usable chain' => ['payload' => str_repeat('n', 480), 'pages' => [44]],
    'two page reserved-space chain' => ['payload' => str_repeat('o', 509), 'pages' => [5, 17], 'usableSize' => 500],
    'three page reserved-space chain' => ['payload' => str_repeat('p', 1000), 'pages' => [23, 4, 19], 'usableSize' => 500],
    'four page reserved-space chain' => ['payload' => str_repeat('q', 1500), 'pages' => [41, 9, 33, 12], 'usableSize' => 500],
    'large page two page chain' => ['payload' => str_repeat('r', 4097), 'pages' => [4, 40], 'pageSize' => 4096],
    'large page three page chain' => ['payload' => str_repeat('s', 8200), 'pages' => [50, 7, 42], 'pageSize' => 4096],
    'large usable reserved chain' => ['payload' => str_repeat('t', 8200), 'pages' => [60, 17, 52], 'pageSize' => 4096, 'usableSize' => 4088],
    'non sequential near pointer-map pages' => ['payload' => str_repeat('u', 1030), 'pages' => [103, 2, 205]],
    'non sequential high page numbers' => ['payload' => str_repeat('v', 1530), 'pages' => [900, 104, 777, 305]],
    'four page alternating chain' => ['payload' => str_repeat('w', 1600), 'pages' => [32, 4, 28, 8]],
    'five page alternating chain' => ['payload' => str_repeat('x', 2400), 'pages' => [18, 3, 22, 7, 26]],
];

foreach ($nextPointerCases as $label => $case) {
    $tests['collects sqlite overflow next pointers for ' . $label] = static function (TestRunner $t) use ($case): void {
        $pageSize = $case['pageSize'] ?? 512;
        $usableSize = $case['usableSize'] ?? $pageSize;
        $payload = $case['payload'];
        $pageNumbers = $case['pages'];
        $pages = SQLiteOverflowPage::encodeChainAtPages($payload, $pageNumbers, $pageSize, $usableSize);
        $reader = static fn (int $pageNumber): string => $pages[$pageNumber] ?? throw new InvalidArgumentException('missing overflow page');

        $t->same($pageNumbers, SQLiteOverflowPage::pageNumbersFromChain($pageNumbers[0], strlen($payload), $reader, $pageSize, $usableSize));
        $t->same(count($pageNumbers), SQLiteOverflowPage::requiredPageCount(strlen($payload), $pageSize, $usableSize));
    };
}

return $tests;
