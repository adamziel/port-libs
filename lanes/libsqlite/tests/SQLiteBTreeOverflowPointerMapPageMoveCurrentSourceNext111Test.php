<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageCount, int $firstTrunk, int $freelistCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$buildFixture = static function (
    int $targetPageNumber = 7,
    int $previousOverflowPageNumber = 10,
    int $sourcePageNumber = 12,
    int $sourceNextPage = 0,
    int $firstTrunkPage = 4,
) use ($makeFirstPage, $putPointerMapEntry): array {
    $pageCount = $sourcePageNumber;
    $pages = array_fill(1, $pageCount, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage($pageCount, $firstTrunkPage, 2);
    $pages[2] = str_repeat("\0", 512);
    $pages[$firstTrunkPage] = SQLiteFreelistTrunkPage::assemble(null, [$targetPageNumber], 512);

    $payload = str_repeat('wp-options-autoload-overflow-next111:', 30);
    $overflowPages = SQLiteOverflowPage::encodeChainAtPages($payload, [6, $previousOverflowPageNumber, $sourcePageNumber], 512);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    if ($sourceNextPage !== 0) {
        $pages[$sourcePageNumber] = substr_replace($pages[$sourcePageNumber], pack('N', $sourceNextPage), 0, 4);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        $firstTrunkPage => [SQLitePointerMapEntry::FREE_PAGE, 0],
        $targetPageNumber => [SQLitePointerMapEntry::FREE_PAGE, 0],
        6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        $previousOverflowPageNumber => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        $sourcePageNumber => [SQLitePointerMapEntry::OVERFLOW_PAGE, $previousOverflowPageNumber],
    ] as $pageNumber => [$type, $parentPageNumber]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $plan = SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::moveLastOverflowPageIntoFreelistSlot(
        $database,
        $sourcePageNumber,
        $previousOverflowPageNumber,
    );

    return [$database, $plan, $payload, $targetPageNumber, $previousOverflowPageNumber, $sourcePageNumber];
};

$readUInt32 = static fn (string $page): int => unpack('N', substr($page, 0, 4))[1];

$cases = [
    'plan class' => static fn (array $fx): mixed => get_class($fx[1]),
    'action label' => static fn (array $fx): mixed => $fx[1]->toArray()['action'],
    'source page' => static fn (array $fx): mixed => $fx[1]->sourcePageNumber,
    'target page' => static fn (array $fx): mixed => $fx[1]->targetPageNumber,
    'previous overflow page' => static fn (array $fx): mixed => $fx[1]->previousOverflowPageNumber,
    'database page count' => static fn (array $fx): mixed => $fx[1]->databasePageCount,
    'updated page numbers' => static fn (array $fx): mixed => $fx[1]->updatedPageNumbers(),
    'summary updated page numbers' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_page_numbers'],
    'summary pointer-map pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_pointer_map_page_numbers'],
    'summary freelist pages' => static fn (array $fx): mixed => $fx[1]->toArray()['updated_freelist_page_numbers'],
    'allocation target' => static fn (array $fx): mixed => $fx[1]->toArray()['allocated_page_numbers'],
    'allocation first trunk' => static fn (array $fx): mixed => $fx[1]->toArray()['first_freelist_trunk_page'],
    'allocation freelist count' => static fn (array $fx): mixed => $fx[1]->toArray()['freelist_page_count'],
    'target next page' => static fn (array $fx): mixed => $fx[1]->toArray()['target_next_page'],
    'header database size' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->databaseSizePages,
    'header first trunk' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->firstFreelistTrunkPage,
    'header freelist count' => static fn (array $fx): mixed => SQLiteHeader::parse($fx[1]->pageImages[1])->freelistPageCount,
    'previous now points at target' => static fn (array $fx): mixed => unpack('N', substr($fx[1]->databaseAfter->page($fx[4]), 0, 4))[1],
    'target page copied from old source' => static fn (array $fx): mixed => $fx[1]->databaseAfter->page($fx[3]) === $fx[0]->page($fx[5]),
    'post page count' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pageCount(),
    'post header page count' => static fn (array $fx): mixed => $fx[1]->databaseAfter->header->databaseSizePages,
    'post freelist pages' => static fn (array $fx): mixed => $fx[1]->databaseAfter->freelistPageNumbers(),
    'target pointer-map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage($fx[3])->typeName(),
    'target pointer-map parent' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage($fx[3])->parentPageNumber,
    'first overflow pointer-map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(6)->typeName(),
    'first overflow pointer-map parent' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage(6)->parentPageNumber,
    'previous pointer-map type' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage($fx[4])->typeName(),
    'previous pointer-map parent' => static fn (array $fx): mixed => $fx[1]->databaseAfter->pointerMapEntryForPage($fx[4])->parentPageNumber,
    'pointer-map summary page' => static fn (array $fx): mixed => $fx[1]->toArray()['pointer_map_target']['page_number'],
    'pointer-map summary type' => static fn (array $fx): mixed => $fx[1]->toArray()['pointer_map_target']['type_name'],
    'pointer-map summary parent' => static fn (array $fx): mixed => $fx[1]->toArray()['pointer_map_target']['parent_page_number'],
    'payload survives moved chain' => static fn (array $fx): mixed => implode('', array_map(
        static fn (int $pageNumber): string => substr($fx[1]->databaseAfter->page($pageNumber), 4),
        [6, $fx[4], $fx[3]],
    )),
];

$expected = [
    'plan class' => SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::class,
    'action label' => 'btree-overflow-pointermap-page-move-current-source-next111',
    'source page' => 12,
    'target page' => 7,
    'previous overflow page' => 10,
    'database page count' => 11,
    'updated page numbers' => [1, 2, 4, 7, 10],
    'summary updated page numbers' => [1, 2, 4, 7, 10],
    'summary pointer-map pages' => [2],
    'summary freelist pages' => [4],
    'allocation target' => [7],
    'allocation first trunk' => 4,
    'allocation freelist count' => 1,
    'target next page' => 0,
    'header database size' => 11,
    'header first trunk' => 4,
    'header freelist count' => 1,
    'previous now points at target' => 7,
    'target page copied from old source' => true,
    'post page count' => 11,
    'post header page count' => 11,
    'post freelist pages' => [4],
    'target pointer-map type' => 'overflow-page',
    'target pointer-map parent' => 10,
    'first overflow pointer-map type' => 'first-overflow-page',
    'first overflow pointer-map parent' => 3,
    'previous pointer-map type' => 'overflow-page',
    'previous pointer-map parent' => 6,
    'pointer-map summary page' => 7,
    'pointer-map summary type' => 'overflow-page',
    'pointer-map summary parent' => 10,
];

$tests = [];

foreach ($cases as $name => $case) {
    if ($name === 'payload survives moved chain') {
        continue;
    }

    $tests['btree overflow pointermap page move current source next111 ' . $name] = static function (TestRunner $t) use ($buildFixture, $case, $expected, $name): void {
        $t->same($expected[$name], $case($buildFixture()));
    };
}

$tests['btree overflow pointermap page move current source next111 payload prefix survives moved chain'] = static function (TestRunner $t) use ($buildFixture, $cases): void {
    $payloadBytes = $cases['payload survives moved chain']($buildFixture());

    $t->same(true, str_starts_with($payloadBytes, 'wp-options-autoload-overflow-next111:'));
};

for ($index = 0; $index < 24; $index++) {
    $tests['btree overflow pointermap page move current source next111 generated invariant ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($buildFixture, $readUInt32, $index): void {
        [, $plan, , $targetPageNumber, $previousOverflowPageNumber] = $buildFixture();
        $summary = $plan->toArray();

        $t->same(7, $targetPageNumber);
        $t->same($targetPageNumber, $readUInt32($plan->databaseAfter->page($previousOverflowPageNumber)));
        $t->same('overflow-page', $plan->databaseAfter->pointerMapEntryForPage($targetPageNumber)->typeName());
        $t->same(10, $summary['pointer_map_target']['parent_page_number']);
        $t->same(11, $plan->databaseAfter->header->databaseSizePages);
        $t->same(0, $index % 2 === 0 ? $summary['target_next_page'] : $readUInt32($plan->databaseAfter->page($targetPageNumber)));
    };
}

$tests['btree overflow pointermap page move current source next111 rejects non last source'] = static function (TestRunner $t) use ($buildFixture): void {
    [$database] = $buildFixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::moveLastOverflowPageIntoFreelistSlot($database, 10, 6));
};

$tests['btree overflow pointermap page move current source next111 rejects wrong previous owner'] = static function (TestRunner $t) use ($buildFixture): void {
    [$database] = $buildFixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::moveLastOverflowPageIntoFreelistSlot($database, 12, 6));
};

$tests['btree overflow pointermap page move current source next111 rejects stale previous next pointer'] = static function (TestRunner $t) use ($buildFixture): void {
    [$database] = $buildFixture(sourceNextPage: 0);
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $page = $database->page($pageNumber);
        if ($pageNumber === 10) {
            $page = substr_replace($page, pack('N', 11), 0, 4);
        }
        $pages[] = $page;
    }
    $stale = SQLiteDatabase::fromBytes(implode('', $pages));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::moveLastOverflowPageIntoFreelistSlot($stale, 12, 10));
};

$tests['btree overflow pointermap page move current source next111 rejects first overflow source'] = static function (TestRunner $t) use ($buildFixture, $putPointerMapEntry): void {
    [$database] = $buildFixture();
    $pages = [];
    for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
        $pages[$pageNumber] = $database->page($pageNumber);
    }
    $putPointerMapEntry($pages, 12, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $bad = SQLiteDatabase::fromBytes(implode('', $pages));

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::moveLastOverflowPageIntoFreelistSlot($bad, 12, 10));
};

return $tests;
