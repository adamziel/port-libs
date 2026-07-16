<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 4), 32, 4);
    $page = substr_replace($page, pack('N', 2), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$fixture = static function (string $replacementPayload = '') use ($makeFirstPage): array {
    $pageSize = 512;
    $pageCount = 11;
    $emptyPage = str_repeat("\0", $pageSize);
    $firstPage = $makeFirstPage($pageSize, $pageCount);
    $pointerMapPage = str_repeat("\0", $pageSize);
    foreach ([
        3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
        4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
        7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
    }

    $database = SQLiteDatabase::fromBytes(
        $firstPage
        . $pointerMapPage
        . $emptyPage
        . SQLiteFreelistTrunkPage::assemble(null, [10], $pageSize)
        . $emptyPage
        . $emptyPage
        . $emptyPage
        . $emptyPage
        . $emptyPage
        . $emptyPage
        . $emptyPage,
    );

    $payload = $replacementPayload !== '' ? $replacementPayload : str_repeat('replacement-overflow:', 80);
    $plan = SQLiteBTreeOverflowFreeblockCurrentNextPlan::replaceFromDeleteResults(
        $database,
        [['source' => 'wp-options-delete', 'obsolete_overflow_page_numbers' => [5, 6]]],
        $payload,
        3,
        true,
        true,
    );

    return [$database, $plan, $payload];
};

$cases = [
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'released overflow pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->releasedOverflowPages,
    'replacement overflow pages' => static fn (array $fx): mixed => $fx[1]->replacementOverflowPageNumbers(),
    'reused released pages' => static fn (array $fx): mixed => $fx[1]->reusedReleasedPageNumbers,
    'appended pages remain empty' => static fn (array $fx): mixed => $fx[1]->allocationPlan->appendedPageNumbers,
    'release source label' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['source'],
    'release source pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['pages'],
    'release source count' => static fn (array $fx): mixed => $fx[1]->releasePlan->sources[0]['count'],
    'release freelist count after delete' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->freelistPageCount,
    'release first trunk after delete' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->firstFreelistTrunkPage,
    'release cleared pages' => static fn (array $fx): mixed => $fx[1]->releasePlan->freePlan->clearedPageNumbers,
    'release pointer map pages' => static fn (array $fx): mixed => array_keys($fx[1]->releasePlan->freePlan->updatedPointerMapPages),
    'release free pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[1]->releasePlan->freePlan->freedPointerMapEntries, 'page_number'),
    'release free pointer map entry types' => static fn (array $fx): mixed => array_column($fx[1]->releasePlan->freePlan->freedPointerMapEntries, 'type_name'),
    'allocation pages consume current next freeblocks' => static fn (array $fx): mixed => $fx[1]->allocationPlan->allocatedPageNumbers,
    'allocation first trunk after replacement' => static fn (array $fx): mixed => $fx[1]->allocationPlan->firstFreelistTrunkPage,
    'allocation freelist count after replacement' => static fn (array $fx): mixed => $fx[1]->allocationPlan->freelistPageCount,
    'allocation database page count' => static fn (array $fx): mixed => $fx[1]->allocationPlan->databasePageCount,
    'allocation pointer map page list' => static fn (array $fx): mixed => array_keys($fx[1]->allocationPlan->updatedPointerMapPages),
    'allocation pointer map entry count' => static fn (array $fx): mixed => count($fx[1]->allocationPlan->allocatedPointerMapEntries()),
    'allocation pointer map entry pages' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer map entry types' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer map parents' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'allocation pointer map offsets' => static fn (array $fx): mixed => array_column($fx[1]->allocationPlan->allocatedPointerMapEntries(), 'offset'),
    'chain link count' => static fn (array $fx): mixed => count($fx[1]->chainLinks),
    'chain current pages' => static fn (array $fx): mixed => array_column($fx[1]->chainLinks, 'current_page'),
    'chain next pages' => static fn (array $fx): mixed => array_column($fx[1]->chainLinks, 'next_page'),
    'chain terminal flags' => static fn (array $fx): mixed => array_column($fx[1]->chainLinks, 'terminal'),
    'first chain payload bytes' => static fn (array $fx): mixed => $fx[1]->chainLinks[0]['payload_bytes'],
    'last chain payload bytes' => static fn (array $fx): mixed => $fx[1]->chainLinks[3]['payload_bytes'],
    'overflow page images pages' => static fn (array $fx): mixed => array_keys($fx[1]->overflowPageImages),
    'updated pages include header pointer map and overflow images' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'page image first overflow next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[10], 0, 4))[1],
    'page image second overflow next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[6], 0, 4))[1],
    'page image third overflow next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[5], 0, 4))[1],
    'page image terminal overflow next pointer' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->pageImages[4], 0, 4))[1],
    'post database has no freelist pages' => static fn (array $fx): mixed => $fx[1]->database->freelistPageNumbers(),
    'post allocation order is empty' => static fn (array $fx): mixed => $fx[1]->database->freelistAllocationOrder(),
    'post header first trunk cleared' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->firstFreelistTrunkPage,
    'post header freelist count cleared' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->freelistPageCount,
    'post header page count unchanged' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->databaseSizePages,
    'post pointer map page 10 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(10)->typeName(),
    'post pointer map page 6 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(6)->typeName(),
    'post pointer map page 5 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(5)->typeName(),
    'post pointer map page 4 type' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(4)->typeName(),
    'post pointer map page 10 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(10)->parentPageNumber,
    'post pointer map page 6 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(6)->parentPageNumber,
    'post pointer map page 5 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(5)->parentPageNumber,
    'post pointer map page 4 parent' => static fn (array $fx): mixed => $fx[1]->database->pointerMapEntryForPage(4)->parentPageNumber,
    'toArray replacement pages' => static fn (array $fx): mixed => $fx[1]->toArray()['replacement_overflow_pages'],
    'toArray reused pages' => static fn (array $fx): mixed => $fx[1]->toArray()['reused_released_pages'],
    'toArray updated pointer map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'toArray release nested count' => static fn (array $fx): mixed => $fx[1]->toArray()['release']['released_overflow_page_count'],
    'toArray allocation nested count' => static fn (array $fx): mixed => count($fx[1]->toArray()['allocation']['allocated_page_numbers']),
];

$expected = [
    'action label' => 'btree-overflow-freeblock-current-next',
    'released overflow pages' => [5, 6],
    'replacement overflow pages' => [10, 6, 5, 4],
    'reused released pages' => [6, 5],
    'appended pages remain empty' => [],
    'release source label' => 'wp-options-delete',
    'release source pages' => [5, 6],
    'release source count' => 2,
    'release freelist count after delete' => 4,
    'release first trunk after delete' => 4,
    'release cleared pages' => [5, 6],
    'release pointer map pages' => [2],
    'release free pointer map entry pages' => [5, 6],
    'release free pointer map entry types' => ['free-page', 'free-page'],
    'allocation pages consume current next freeblocks' => [10, 6, 5, 4],
    'allocation first trunk after replacement' => 0,
    'allocation freelist count after replacement' => 0,
    'allocation database page count' => 11,
    'allocation pointer map page list' => [2],
    'allocation pointer map entry count' => 4,
    'allocation pointer map entry pages' => [10, 6, 5, 4],
    'allocation pointer map entry types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer map parents' => [3, 10, 6, 5],
    'allocation pointer map offsets' => [35, 15, 10, 5],
    'chain link count' => 4,
    'chain current pages' => [10, 6, 5, 4],
    'chain next pages' => [6, 5, 4, 0],
    'chain terminal flags' => [false, false, false, true],
    'first chain payload bytes' => 508,
    'last chain payload bytes' => 156,
    'overflow page images pages' => [10, 6, 5, 4],
    'updated pages include header pointer map and overflow images' => [1, 2, 4, 5, 6, 10],
    'page image first overflow next pointer' => 6,
    'page image second overflow next pointer' => 5,
    'page image third overflow next pointer' => 4,
    'page image terminal overflow next pointer' => 0,
    'post database has no freelist pages' => [],
    'post allocation order is empty' => [],
    'post header first trunk cleared' => 0,
    'post header freelist count cleared' => 0,
    'post header page count unchanged' => 11,
    'post pointer map page 10 type' => 'first-overflow-page',
    'post pointer map page 6 type' => 'overflow-page',
    'post pointer map page 5 type' => 'overflow-page',
    'post pointer map page 4 type' => 'overflow-page',
    'post pointer map page 10 parent' => 3,
    'post pointer map page 6 parent' => 10,
    'post pointer map page 5 parent' => 6,
    'post pointer map page 4 parent' => 5,
    'toArray replacement pages' => [10, 6, 5, 4],
    'toArray reused pages' => [6, 5],
    'toArray updated pointer map pages' => [2],
    'toArray release nested count' => 2,
    'toArray allocation nested count' => 4,
];

$tests = [];
foreach ($cases as $name => $case) {
    $tests['btree overflow freeblock current next72 ' . $name] = static function (TestRunner $t) use ($fixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($fixture()));
    };
}

$tests['btree overflow freeblock current next72 rejects empty replacement payload'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    try {
        SQLiteBTreeOverflowFreeblockCurrentNextPlan::replaceFromDeleteResults($database, [['obsolete_overflow_page_numbers' => [5]]], '', 3);
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite overflow freeblock current/next replacement requires overflow payload bytes', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected empty replacement overflow payload rejection');
};

$tests['btree overflow freeblock current next72 rejects insufficient freelist without append'] = static function (TestRunner $t) use ($fixture): void {
    [$database] = $fixture();

    try {
        SQLiteBTreeOverflowFreeblockCurrentNextPlan::replaceFromDeleteResults(
            $database,
            [['obsolete_overflow_page_numbers' => [5, 6]]],
            str_repeat('needs-five-pages:', 130),
            3,
            true,
            false,
        );
    } catch (InvalidArgumentException $exception) {
        $t->same('SQLite freelist does not contain enough pages for this allocation', $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected no-append overflow current/next allocation rejection');
};

$tests['btree overflow freeblock current next72 reads replacement payload back from current next chain'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan, $payload] = $fixture();
    $readBack = '';
    $pageNumber = 10;
    while ($pageNumber !== 0 && strlen($readBack) < strlen($payload)) {
        $page = $plan->database->page($pageNumber);
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $readBack .= substr($page, 4);
    }

    $t->same($payload, substr($readBack, 0, strlen($payload)));
};

return $tests;
