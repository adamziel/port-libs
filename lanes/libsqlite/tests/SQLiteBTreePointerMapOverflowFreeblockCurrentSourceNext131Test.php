<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage131 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$fragmentedLeaf131 = static function (): string {
    $page = str_repeat("\xcc", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 400), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 384), 5, 2);
    $page[7] = chr(6);
    $page = substr_replace($page, pack('n', 500), 8, 2);
    $page = substr_replace($page, str_repeat('W', 8), 500, 8);
    $page = substr_replace($page, pack('n', 413) . pack('n', 12), 400, 4);
    $page = substr_replace($page, pack('n', 428) . pack('n', 12), 413, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 16), 428, 4);

    return $page;
};

$putPointerMapEntry131 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$database131 = static function (int $pageCount = 9, int $firstFreelistTrunkPage = 8, int $freelistPageCount = 2) use (
    $makeFirstPage131,
    $fragmentedLeaf131,
    $putPointerMapEntry131,
): SQLiteDatabase {
    $pages = array_fill(1, max($pageCount, 9), str_repeat("\0", 512));
    $pages[1] = $makeFirstPage131($pageCount, $firstFreelistTrunkPage, $freelistPageCount);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $fragmentedLeaf131();
    $pages[5] = pack('N', 6) . str_repeat('O', 508);
    $pages[6] = pack('N', 0) . str_repeat('P', 508);
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, [9], 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
        8 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        9 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry131($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', array_slice($pages, 0, $pageCount, true)));
};

$plan131 = static fn (bool $secureDelete = true): SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::pointerMapOverflowFreeblockFromDeleteResults(
    $database131(),
    3,
    [[
        'source' => 'wp_options-current-source-next131',
        'obsolete_overflow_page_numbers' => [5, 6],
        'rowids' => [13101],
    ]],
    3,
    str_repeat('R', 1300),
    $secureDelete,
);

$throwMessage131 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows131 = static fn (): array => $plan131()->rows;
$leafHeader131 = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan131()->databaseAfterAllocation->page(3), 512);

$cases131 = [
    'action label' => static fn (): mixed => $plan131()->toArray()['action'],
    'leaf page' => static fn (): mixed => $plan131()->toArray()['leaf_page'],
    'coalesced fragment bytes' => static fn (): mixed => $plan131()->coalescePlan->coalescedFragmentBytes,
    'fragmented bytes before' => static fn (): mixed => $plan131()->coalescePlan->fragmentedBytesBefore,
    'fragmented bytes after' => static fn (): mixed => $plan131()->coalescePlan->fragmentedBytesAfter,
    'freeblock offsets before' => static fn (): mixed => array_column($plan131()->coalescePlan->beforeFreeblocks, 'offset'),
    'freeblock size after' => static fn (): mixed => $plan131()->coalescePlan->afterFreeblocks[0]['size'],
    'released overflow pages' => static fn (): mixed => $plan131()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $plan131()->allocatedOverflowPages(),
    'reused released pages' => static fn (): mixed => $plan131()->reusedReleasedOverflowPages(),
    'allocated existing freelist pages' => static fn (): mixed => $plan131()->allocatedExistingFreelistPages(),
    'freed page numbers' => static fn (): mixed => $plan131()->releasePlan->freePlan->freedPageNumbers,
    'cleared page numbers' => static fn (): mixed => $plan131()->releasePlan->freePlan->clearedPageNumbers,
    'allocation pointer map pages' => static fn (): mixed => array_column($plan131()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer map types' => static fn (): mixed => array_column($plan131()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer map parents' => static fn (): mixed => array_column($plan131()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'row page numbers' => static fn (): mixed => array_column($rows131(), 'page_number'),
    'row origins' => static fn (): mixed => array_column($rows131(), 'page_origin'),
    'row release sources' => static fn (): mixed => array_column($rows131(), 'release_source'),
    'row allocation sources' => static fn (): mixed => array_column($rows131(), 'allocation_source'),
    'row trunk pages' => static fn (): mixed => array_column($rows131(), 'freelist_trunk_page'),
    'row before pointer types' => static fn (): mixed => array_column($rows131(), 'before_pointer_map_type'),
    'row before pointer parents' => static fn (): mixed => array_column($rows131(), 'before_pointer_map_parent'),
    'row free pointer types' => static fn (): mixed => array_column($rows131(), 'free_pointer_map_type'),
    'row free pointer parents' => static fn (): mixed => array_column($rows131(), 'free_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($rows131(), 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($rows131(), 'next_pointer_map_parent'),
    'row next pages' => static fn (): mixed => array_column($rows131(), 'next_overflow_next_page'),
    'row tail flags' => static fn (): mixed => array_column($rows131(), 'next_overflow_is_tail'),
    'row payload prefixes' => static fn (): mixed => array_column($rows131(), 'payload_prefix'),
    'final freelist page numbers' => static fn (): mixed => $plan131()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan131()->databaseAfterAllocation->header->freelistPageCount,
    'final first trunk' => static fn (): mixed => $plan131()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'updated page numbers' => static fn (): mixed => $plan131()->toArray()['updated_page_numbers'],
    'page image keys' => static fn (): mixed => array_keys($plan131()->pageImages()),
    'overflow image keys' => static fn (): mixed => array_keys($plan131()->overflowPageImages()),
    'summary row count' => static fn (): mixed => count($plan131()->toArray()['btree_pointermap_overflow_freeblock_current_source_next131']),
    'header from image freelist count' => static fn (): mixed => SQLiteHeader::parse($plan131()->pageImages()[1])->freelistPageCount,
    'leaf fragment report ok' => static fn (): mixed => $leafHeader131()->freeblockFragmentReport($plan131()->databaseAfterAllocation->page(3))['status'],
    'leaf secure delete zeroed' => static fn (): mixed => $leafHeader131()->freeblockSecureDeleteReport($plan131()->databaseAfterAllocation->page(3))['secure_delete_payload_zeroed'],
    'without secure delete keeps released payload before allocation overwrite' => static fn (): mixed => substr($plan131(false)->databaseAfterRelease->page(6), 4, 1),
    'empty payload rejected' => static fn (): mixed => $throwMessage131(static fn () => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::pointerMapOverflowFreeblockFromDeleteResults($database131(), 3, [['obsolete_overflow_page_numbers' => [5, 6]]], 3, '')),
    'bad parent rejected' => static fn (): mixed => $throwMessage131(static fn () => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::pointerMapOverflowFreeblockFromDeleteResults($database131(), 3, [['obsolete_overflow_page_numbers' => [5, 6]]], 1, 'x')),
    'no existing freelist rejected' => static fn (): mixed => $throwMessage131(static fn () => SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::pointerMapOverflowFreeblockFromDeleteResults($database131(7, 0, 0), 3, [['obsolete_overflow_page_numbers' => [5, 6]]], 3, str_repeat('R', 900))),
];

$expected131 = [
    'action label' => 'btree-pointermap-overflow-freeblock-current-source-next131',
    'leaf page' => 3,
    'coalesced fragment bytes' => 4,
    'fragmented bytes before' => 6,
    'fragmented bytes after' => 2,
    'freeblock offsets before' => [400, 413, 428],
    'freeblock size after' => 44,
    'released overflow pages' => [5, 6],
    'allocated overflow pages' => [9, 6, 5],
    'reused released pages' => [5, 6],
    'allocated existing freelist pages' => [9],
    'freed page numbers' => [5, 6],
    'cleared page numbers' => [5, 6],
    'allocation pointer map pages' => [9, 6, 5],
    'allocation pointer map types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'allocation pointer map parents' => [3, 9, 6],
    'row page numbers' => [9, 6, 5],
    'row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page'],
    'row release sources' => [null, 'wp_options-current-source-next131', 'wp_options-current-source-next131'],
    'row allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'row trunk pages' => [8, 8, 8],
    'row before pointer types' => ['free-page', 'overflow-page', 'first-overflow-page'],
    'row before pointer parents' => [0, 5, 3],
    'row free pointer types' => ['free-page', 'free-page', 'free-page'],
    'row free pointer parents' => [0, 0, 0],
    'row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'row next pointer parents' => [3, 9, 6],
    'row next pages' => [6, 5, 0],
    'row tail flags' => [false, false, true],
    'row payload prefixes' => [str_repeat('R', 14), str_repeat('R', 14), str_repeat('R', 14)],
    'final freelist page numbers' => [8],
    'final freelist count' => 1,
    'final first trunk' => 8,
    'updated page numbers' => [1, 2, 3, 5, 6, 8, 9],
    'page image keys' => [1, 2, 3, 5, 6, 8, 9],
    'overflow image keys' => [9, 6, 5],
    'summary row count' => 3,
    'header from image freelist count' => 1,
    'leaf fragment report ok' => 'ok',
    'leaf secure delete zeroed' => true,
    'without secure delete keeps released payload before allocation overwrite' => 'P',
    'empty payload rejected' => 'SQLite b-tree pointer-map overflow freeblock next131 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree pointer-map overflow freeblock next131 parent b-tree page must be at page 2 or later',
    'no existing freelist rejected' => 'SQLite b-tree pointer-map overflow freeblock next131 requires both released overflow and existing freelist allocation sources',
];

$tests = [];

foreach ($cases131 as $name => $callback) {
    $tests['btree pointermap overflow freeblock current source next131 ' . $name] = static function (TestRunner $t) use ($callback, $expected131, $name): void {
        $t->same($expected131[$name], $callback());
    };
}

foreach (range(1, 36) as $index) {
    $tests['btree pointermap overflow freeblock current source next131 invariant ' . $index] = static function (TestRunner $t) use ($plan131): void {
        $plan = $plan131();

        $t->same([9, 6, 5], $plan->allocatedOverflowPages());
        $t->same([5, 6], $plan->reusedReleasedOverflowPages());
        $t->same([9], $plan->allocatedExistingFreelistPages());
        $t->same(['existing-freelist-page', 'released-overflow-page', 'released-overflow-page'], array_column($plan->rows, 'page_origin'));
        $t->same(['free-page', 'free-page', 'free-page'], array_column($plan->rows, 'free_pointer_map_type'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->rows, 'next_pointer_map_type'));
        $t->same([3, 9, 6], array_column($plan->rows, 'next_pointer_map_parent'));
        $t->same([6, 5, 0], array_column($plan->rows, 'next_overflow_next_page'));
        $t->same([8], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
