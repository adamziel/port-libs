<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage142 = static function (int $pageCount, int $firstFreelistTrunkPage = 10, int $freelistPageCount = 3): string {
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

$fragmentedLeaf142 = static function (): string {
    $page = str_repeat("\xdd", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 390), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 376), 5, 2);
    $page[7] = chr(7);
    $page = substr_replace($page, pack('n', 496), 8, 2);
    $page = substr_replace($page, str_repeat('L', 12), 496, 12);
    $page = substr_replace($page, pack('n', 406) . pack('n', 12), 390, 4);
    $page = substr_replace($page, pack('n', 422) . pack('n', 14), 406, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 18), 422, 4);

    return $page;
};

$putPointerMapEntry142 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database142 = static function (int $firstFreelistTrunkPage = 10, int $freelistPageCount = 3) use (
    $makeFirstPage142,
    $fragmentedLeaf142,
    $putPointerMapEntry142,
): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage142(12, $firstFreelistTrunkPage, $freelistPageCount);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $fragmentedLeaf142();
    $pages[5] = pack('N', 6) . str_repeat('A', 508);
    $pages[6] = pack('N', 7) . str_repeat('B', 508);
    $pages[7] = pack('N', 0) . str_repeat('C', 508);
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [11, 12], 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
        7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry142($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan142 = static fn (bool $secureDelete = true): SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan => SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult(
    $database142(),
    3,
    [[
        'source' => 'wp_options-transient-timeout-current-source-next142',
        'first_page' => 5,
        'overflow_payload_bytes' => 1524,
    ]],
    [
        'source' => 'wp_options-transient-timeout-current-source-next142',
        'rowid' => 14201,
        'obsolete_overflow_page_numbers' => [5, 6, 7],
    ],
    3,
    str_repeat('Z', 1600),
    $secureDelete,
);

$throwsMessage142 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$currentRows142 = static fn (): array => $plan142()->currentSourceRows();
$nextRows142 = static fn (): array => $plan142()->nextRows();
$leafHeader142 = static fn (): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan142()->databaseAfterAllocation->page(3), 512);

$cases142 = [
    'action label' => static fn (): mixed => $plan142()->toArray()['action'],
    'leaf page' => static fn (): mixed => $plan142()->toArray()['leaf_page'],
    'coalesced fragment bytes' => static fn (): mixed => $plan142()->coalescePlan->coalescedFragmentBytes,
    'fragmented bytes before' => static fn (): mixed => $plan142()->coalescePlan->fragmentedBytesBefore,
    'fragmented bytes after' => static fn (): mixed => $plan142()->coalescePlan->fragmentedBytesAfter,
    'current source pages' => static fn (): mixed => array_column($currentRows142(), 'page_number'),
    'current source next pages' => static fn (): mixed => array_column($currentRows142(), 'current_next_page'),
    'current source terminal flags' => static fn (): mixed => array_column($currentRows142(), 'current_terminal'),
    'current source payload bytes' => static fn (): mixed => array_column($currentRows142(), 'current_payload_bytes'),
    'current source pointer types' => static fn (): mixed => array_column($currentRows142(), 'current_pointer_map_type'),
    'current source pointer parents' => static fn (): mixed => array_column($currentRows142(), 'current_pointer_map_parent'),
    'released overflow pages' => static fn (): mixed => $plan142()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $plan142()->allocatedOverflowPages(),
    'reused released overflow pages' => static fn (): mixed => $plan142()->reusedReleasedOverflowPages(),
    'allocated existing freelist pages' => static fn (): mixed => $plan142()->allocatedExistingFreelistPages(),
    'next row pages' => static fn (): mixed => array_column($nextRows142(), 'page_number'),
    'next row origins' => static fn (): mixed => array_column($nextRows142(), 'page_origin'),
    'next row current sources' => static fn (): mixed => array_column($nextRows142(), 'current_source'),
    'next row current positions' => static fn (): mixed => array_column($nextRows142(), 'current_chain_position'),
    'next row current next pages' => static fn (): mixed => array_column($nextRows142(), 'current_next_page'),
    'next row before pointer types' => static fn (): mixed => array_column($nextRows142(), 'before_pointer_map_type'),
    'next row free pointer types' => static fn (): mixed => array_column($nextRows142(), 'free_pointer_map_type'),
    'next row next pointer types' => static fn (): mixed => array_column($nextRows142(), 'next_pointer_map_type'),
    'next row next pointer parents' => static fn (): mixed => array_column($nextRows142(), 'next_pointer_map_parent'),
    'next row allocation sources' => static fn (): mixed => array_column($nextRows142(), 'allocation_source'),
    'next row allocation trunks' => static fn (): mixed => array_column($nextRows142(), 'allocation_trunk_page'),
    'next row overflow next pages' => static fn (): mixed => array_column($nextRows142(), 'next_overflow_next_page'),
    'next row tail flags' => static fn (): mixed => array_column($nextRows142(), 'next_overflow_is_tail'),
    'next row payload prefixes' => static fn (): mixed => array_column($nextRows142(), 'payload_prefix'),
    'final freelist pages' => static fn (): mixed => $plan142()->databaseAfterAllocation->freelistPageNumbers(),
    'final freelist count' => static fn (): mixed => $plan142()->databaseAfterAllocation->header->freelistPageCount,
    'final first trunk' => static fn (): mixed => $plan142()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'updated page numbers' => static fn (): mixed => $plan142()->toArray()['updated_page_numbers'],
    'page image keys' => static fn (): mixed => array_keys($plan142()->pageImages()),
    'overflow image keys' => static fn (): mixed => array_keys($plan142()->overflowPageImages()),
    'header from image freelist count' => static fn (): mixed => SQLiteHeader::parse($plan142()->pageImages()[1])->freelistPageCount,
    'leaf fragment status' => static fn (): mixed => $leafHeader142()->freeblockFragmentReport($plan142()->databaseAfterAllocation->page(3))['status'],
    'leaf secure delete zeroed' => static fn (): mixed => $leafHeader142()->freeblockSecureDeleteReport($plan142()->databaseAfterAllocation->page(3))['secure_delete_payload_zeroed'],
    'without secure delete keeps released payload before allocation overwrite' => static fn (): mixed => substr($plan142(false)->databaseAfterRelease->page(7), 4, 1),
    'summary current pages' => static fn (): mixed => array_column($plan142()->toArray()['current_source_overflow_chain_rows'], 'page_number'),
    'summary next pages' => static fn (): mixed => array_column($plan142()->toArray()['btree_overflow_pointermap_freeblock_current_source_next142'], 'page_number'),
    'empty chain rejected' => static fn (): mixed => $throwsMessage142(static fn () => SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult($database142(), 3, [], ['obsolete_overflow_page_numbers' => [5]], 3, 'x')),
    'empty payload rejected' => static fn (): mixed => $throwsMessage142(static fn () => SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult($database142(), 3, [['first_page' => 5, 'overflow_payload_bytes' => 508]], ['obsolete_overflow_page_numbers' => [5]], 3, '')),
    'bad parent rejected' => static fn (): mixed => $throwsMessage142(static fn () => SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult($database142(), 3, [['first_page' => 5, 'overflow_payload_bytes' => 508]], ['obsolete_overflow_page_numbers' => [5]], 1, 'x')),
];

$expected142 = [
    'action label' => 'btree-overflow-pointermap-freeblock-current-source-next142',
    'leaf page' => 3,
    'coalesced fragment bytes' => 2,
    'fragmented bytes before' => 7,
    'fragmented bytes after' => 5,
    'current source pages' => [5, 6, 7],
    'current source next pages' => [6, 7, 0],
    'current source terminal flags' => [false, false, true],
    'current source payload bytes' => [508, 508, 508],
    'current source pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page'],
    'current source pointer parents' => [3, 5, 6],
    'released overflow pages' => [5, 6, 7],
    'allocated overflow pages' => [11, 7, 6, 5],
    'reused released overflow pages' => [5, 6, 7],
    'allocated existing freelist pages' => [11],
    'next row pages' => [11, 7, 6, 5],
    'next row origins' => ['existing-freelist-page', 'released-overflow-page', 'released-overflow-page', 'released-overflow-page'],
    'next row current sources' => [null, 'wp_options-transient-timeout-current-source-next142', 'wp_options-transient-timeout-current-source-next142', 'wp_options-transient-timeout-current-source-next142'],
    'next row current positions' => [null, 2, 1, 0],
    'next row current next pages' => [null, 0, 7, 6],
    'next row before pointer types' => ['free-page', 'overflow-page', 'overflow-page', 'first-overflow-page'],
    'next row free pointer types' => ['free-page', 'free-page', 'free-page', 'free-page'],
    'next row next pointer types' => ['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'],
    'next row next pointer parents' => [3, 11, 7, 6],
    'next row allocation sources' => ['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf'],
    'next row allocation trunks' => [10, 10, 10, 10],
    'next row overflow next pages' => [7, 6, 5, 0],
    'next row tail flags' => [false, false, false, true],
    'next row payload prefixes' => [str_repeat('Z', 12), str_repeat('Z', 12), str_repeat('Z', 12), str_repeat('Z', 12)],
    'final freelist pages' => [10, 12],
    'final freelist count' => 2,
    'final first trunk' => 10,
    'updated page numbers' => [1, 2, 3, 5, 6, 7, 10, 11],
    'page image keys' => [1, 2, 3, 5, 6, 7, 10, 11],
    'overflow image keys' => [11, 7, 6, 5],
    'header from image freelist count' => 2,
    'leaf fragment status' => 'ok',
    'leaf secure delete zeroed' => true,
    'without secure delete keeps released payload before allocation overwrite' => 'C',
    'summary current pages' => [5, 6, 7],
    'summary next pages' => [11, 7, 6, 5],
    'empty chain rejected' => 'SQLite b-tree overflow pointer-map freeblock next142 requires current-source overflow chains',
    'empty payload rejected' => 'SQLite b-tree overflow pointer-map freeblock next142 requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite b-tree overflow pointer-map freeblock next142 parent b-tree page must be at page 2 or later',
];

$tests = [];

foreach ($cases142 as $name => $callback) {
    $tests['btree overflow pointermap freeblock current source next142 ' . $name] = static function (TestRunner $t) use ($callback, $expected142, $name): void {
        $t->same($expected142[$name], $callback());
    };
}

foreach (range(1, 36) as $index) {
    $tests['btree overflow pointermap freeblock current source next142 invariant ' . $index] = static function (TestRunner $t) use ($plan142): void {
        $plan = $plan142();

        $t->same([5, 6, 7], array_column($plan->currentSourceRows(), 'page_number'));
        $t->same([11, 7, 6, 5], $plan->allocatedOverflowPages());
        $t->same([5, 6, 7], $plan->reusedReleasedOverflowPages());
        $t->same([11], $plan->allocatedExistingFreelistPages());
        $t->same(['free-page', 'free-page', 'free-page', 'free-page'], array_column($plan->nextRows(), 'free_pointer_map_type'));
        $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->nextRows(), 'next_pointer_map_type'));
        $t->same([3, 11, 7, 6], array_column($plan->nextRows(), 'next_pointer_map_parent'));
        $t->same([7, 6, 5, 0], array_column($plan->nextRows(), 'next_overflow_next_page'));
        $t->same([10, 12], $plan->databaseAfterAllocation->freelistPageNumbers());
    };
}

return $tests;
