<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage128 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeFragmentedLeaf128 = static function (string $pageType = "\x0d"): string {
    $page = str_repeat("\xcc", 512);
    $page[0] = $pageType;
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

$putPointerMapEntry128 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$databaseFixture128 = static function (string $pageType = "\x0d") use ($makeFirstPage128, $makeFragmentedLeaf128, $putPointerMapEntry128): SQLiteDatabase {
    $pages = array_fill(1, 6, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage128(6);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = $makeFragmentedLeaf128($pageType);
    $pages[5] = pack('N', 6) . str_repeat('O', 508);
    $pages[6] = pack('N', 0) . str_repeat('P', 508);

    $putPointerMapEntry128($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry128($pages, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry128($pages, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$payload128 = str_repeat('replacement-app-setting-', 28);

$fixture128 = static function (
    bool $secureDelete = true,
    bool $clearCoalescedFragments = true,
    string $pageType = "\x0d",
) use ($databaseFixture128, $payload128): SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan {
    return SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::baseFromDeleteResults(
        $databaseFixture128($pageType),
        3,
        [[
            'source' => 'app-settings-transient-overflow-replace',
            'obsolete_overflow_page_numbers' => [5, 6],
            'rowids' => [12801],
        ]],
        3,
        $payload128,
        $secureDelete,
        $clearCoalescedFragments,
    );
};

$throwsMessage128 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows128 = static fn (SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan $plan): array => $plan->transitionRows();
$afterHeader128 = static fn (SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan $plan): SQLiteBTreePageHeader => SQLiteBTreePageHeader::parsePage($plan->databaseAfterAllocation->page(3), 512);

$cases128 = [
    'action label' => static fn (): mixed => $fixture128()->toArray()['action'],
    'leaf page number' => static fn (): mixed => $fixture128()->toArray()['leaf_page'],
    'leaf page type' => static fn (): mixed => $fixture128()->coalescePlan->pageType,
    'fragmented bytes before' => static fn (): mixed => $fixture128()->coalescePlan->fragmentedBytesBefore,
    'fragmented bytes after' => static fn (): mixed => $fixture128()->coalescePlan->fragmentedBytesAfter,
    'coalesced fragment bytes' => static fn (): mixed => $fixture128()->coalescePlan->coalescedFragmentBytes,
    'coalesced fragment list' => static fn (): mixed => array_column($fixture128()->coalescePlan->coalescedFragments, 'fragment_bytes'),
    'freeblock count before' => static fn (): mixed => count($fixture128()->coalescePlan->beforeFreeblocks),
    'freeblock count after' => static fn (): mixed => count($fixture128()->coalescePlan->afterFreeblocks),
    'before freeblock offsets' => static fn (): mixed => array_column($fixture128()->coalescePlan->beforeFreeblocks, 'offset'),
    'after freeblock offset' => static fn (): mixed => $fixture128()->coalescePlan->afterFreeblocks[0]['offset'],
    'after freeblock size' => static fn (): mixed => $fixture128()->coalescePlan->afterFreeblocks[0]['size'],
    'released overflow pages' => static fn (): mixed => $fixture128()->releasedOverflowPages(),
    'allocated overflow pages' => static fn (): mixed => $fixture128()->allocatedOverflowPages(),
    'reused overflow pages' => static fn (): mixed => $fixture128()->reusedOverflowPages(),
    'release source' => static fn (): mixed => $fixture128()->releasePlan->sources[0]['source'],
    'release source count' => static fn (): mixed => $fixture128()->releasePlan->sources[0]['count'],
    'freed page numbers' => static fn (): mixed => $fixture128()->releasePlan->freePlan->freedPageNumbers,
    'freelist leaf pages' => static fn (): mixed => $fixture128()->releasePlan->freePlan->leafPageNumbers,
    'new trunk page numbers' => static fn (): mixed => $fixture128()->releasePlan->freePlan->newTrunkPageNumbers,
    'allocation steps sources' => static fn (): mixed => array_column($fixture128()->allocationPlan->allocationSteps(), 'source'),
    'allocation steps trunks' => static fn (): mixed => array_column($fixture128()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation pointer map pages' => static fn (): mixed => array_column($fixture128()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocation pointer map types' => static fn (): mixed => array_column($fixture128()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer map parents' => static fn (): mixed => array_column($fixture128()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'row page numbers' => static fn (): mixed => array_column($rows128($fixture128()), 'page_number'),
    'row chain positions' => static fn (): mixed => array_column($rows128($fixture128()), 'chain_position'),
    'row release sources' => static fn (): mixed => array_column($rows128($fixture128()), 'release_source'),
    'row allocation sources' => static fn (): mixed => array_column($rows128($fixture128()), 'allocation_source'),
    'row before pointer types' => static fn (): mixed => array_column($rows128($fixture128()), 'before_pointer_map_type'),
    'row before pointer parents' => static fn (): mixed => array_column($rows128($fixture128()), 'before_pointer_map_parent'),
    'row free pointer types' => static fn (): mixed => array_column($rows128($fixture128()), 'free_pointer_map_type'),
    'row free pointer parents' => static fn (): mixed => array_column($rows128($fixture128()), 'free_pointer_map_parent'),
    'row next pointer types' => static fn (): mixed => array_column($rows128($fixture128()), 'next_pointer_map_type'),
    'row next pointer parents' => static fn (): mixed => array_column($rows128($fixture128()), 'next_pointer_map_parent'),
    'row next overflow pointers' => static fn (): mixed => array_column($rows128($fixture128()), 'next_overflow_next_page'),
    'row payload prefixes' => static fn (): mixed => array_column($rows128($fixture128()), 'payload_prefix'),
    'row coalesced bytes' => static fn (): mixed => array_column($rows128($fixture128()), 'coalesced_fragment_bytes'),
    'row freeblock counts before' => static fn (): mixed => array_column($rows128($fixture128()), 'freeblock_count_before'),
    'row freeblock counts after' => static fn (): mixed => array_column($rows128($fixture128()), 'freeblock_count_after'),
    'final freelist count' => static fn (): mixed => $fixture128()->databaseAfterAllocation->header->freelistPageCount,
    'final first freelist trunk' => static fn (): mixed => $fixture128()->databaseAfterAllocation->header->firstFreelistTrunkPage,
    'final freelist numbers' => static fn (): mixed => $fixture128()->databaseAfterAllocation->freelistPageNumbers(),
    'final page six pointer type' => static fn (): mixed => $fixture128()->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'final page five pointer type' => static fn (): mixed => $fixture128()->databaseAfterAllocation->pointerMapEntryForPage(5)->typeName(),
    'final page six next pointer' => static fn (): mixed => unpack('N', substr($fixture128()->databaseAfterAllocation->page(6), 0, 4))[1],
    'final page five next pointer' => static fn (): mixed => unpack('N', substr($fixture128()->databaseAfterAllocation->page(5), 0, 4))[1],
    'final leaf fragment report ok' => static fn (): mixed => $afterHeader128($fixture128())->freeblockFragmentReport($fixture128()->databaseAfterAllocation->page(3))['status'],
    'final leaf current next fragment bytes' => static fn (): mixed => $afterHeader128($fixture128())->freeblockFragmentReport($fixture128()->databaseAfterAllocation->page(3))['current_next_fragment_bytes'],
    'final leaf secure delete zeroed' => static fn (): mixed => $afterHeader128($fixture128())->freeblockSecureDeleteReport($fixture128()->databaseAfterAllocation->page(3))['secure_delete_payload_zeroed'],
    'page images keys' => static fn (): mixed => array_keys($fixture128()->pageImages()),
    'summary row pages' => static fn (): mixed => array_column($fixture128()->toArray()['btree_overflow_freeblock_pointermap_current_source'], 'page_number'),
    'summary updated pages' => static fn (): mixed => $fixture128()->toArray()['updated_page_numbers'],
    'summary release count' => static fn (): mixed => $fixture128()->toArray()['release']['released_overflow_page_count'],
    'summary allocation pages' => static fn (): mixed => $fixture128()->toArray()['allocation']['allocated_page_numbers'],
    'header from first page image freelist count' => static fn (): mixed => SQLiteHeader::parse($fixture128()->pageImages()[1])->freelistPageCount,
    'index leaf page type accepted' => static fn (): mixed => $fixture128(true, true, "\x0a")->coalescePlan->pageType,
    'without secure delete keeps old payload until allocation overwrites first byte' => static fn (): mixed => substr($fixture128(false)->databaseAfterRelease->page(6), 4, 1),
    'without clear leaves current fragments payload' => static fn (): mixed => strpos($fixture128(true, false)->databaseAfterAllocation->page(3), str_repeat("\xcc", 4)) !== false,
    'empty payload rejected' => static fn () => $throwsMessage128(static fn () => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::baseFromDeleteResults($databaseFixture128(), 3, [['obsolete_overflow_page_numbers' => [5, 6]]], 3, '')),
    'bad parent rejected' => static fn () => $throwsMessage128(static fn () => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::baseFromDeleteResults($databaseFixture128(), 3, [['obsolete_overflow_page_numbers' => [5, 6]]], 1, 'x')),
    'bad leaf rejected' => static fn () => $throwsMessage128(static fn () => SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::baseFromDeleteResults($databaseFixture128(), 9, [['obsolete_overflow_page_numbers' => [5, 6]]], 3, 'x')),
];

$expected128 = [
    'action label' => 'btree-overflow-freeblock-pointermap-current-source',
    'leaf page number' => 3,
    'leaf page type' => 'table-leaf',
    'fragmented bytes before' => 6,
    'fragmented bytes after' => 2,
    'coalesced fragment bytes' => 4,
    'coalesced fragment list' => [1, 3],
    'freeblock count before' => 3,
    'freeblock count after' => 1,
    'before freeblock offsets' => [400, 413, 428],
    'after freeblock offset' => 400,
    'after freeblock size' => 44,
    'released overflow pages' => [5, 6],
    'allocated overflow pages' => [6, 5],
    'reused overflow pages' => [5, 6],
    'release source' => 'app-settings-transient-overflow-replace',
    'release source count' => 2,
    'freed page numbers' => [5, 6],
    'freelist leaf pages' => [6],
    'new trunk page numbers' => [5],
    'allocation steps sources' => ['freelist-leaf', 'freelist-trunk'],
    'allocation steps trunks' => [5, 5],
    'allocation pointer map pages' => [6, 5],
    'allocation pointer map types' => ['first-overflow-page', 'overflow-page'],
    'allocation pointer map parents' => [3, 6],
    'row page numbers' => [6, 5],
    'row chain positions' => [0, 1],
    'row release sources' => ['app-settings-transient-overflow-replace', 'app-settings-transient-overflow-replace'],
    'row allocation sources' => ['freelist-leaf', 'freelist-trunk'],
    'row before pointer types' => ['overflow-page', 'first-overflow-page'],
    'row before pointer parents' => [5, 3],
    'row free pointer types' => ['free-page', 'free-page'],
    'row free pointer parents' => [0, 0],
    'row next pointer types' => ['first-overflow-page', 'overflow-page'],
    'row next pointer parents' => [3, 6],
    'row next overflow pointers' => [5, 0],
    'row payload prefixes' => ['replacement-app-', 'acement-app-sett'],
    'row coalesced bytes' => [4, 4],
    'row freeblock counts before' => [3, 3],
    'row freeblock counts after' => [1, 1],
    'final freelist count' => 0,
    'final first freelist trunk' => 0,
    'final freelist numbers' => [],
    'final page six pointer type' => 'first-overflow-page',
    'final page five pointer type' => 'overflow-page',
    'final page six next pointer' => 5,
    'final page five next pointer' => 0,
    'final leaf fragment report ok' => 'ok',
    'final leaf current next fragment bytes' => 0,
    'final leaf secure delete zeroed' => true,
    'page images keys' => [1, 2, 3, 5, 6],
    'summary row pages' => [6, 5],
    'summary updated pages' => [1, 2, 3, 5, 6],
    'summary release count' => 2,
    'summary allocation pages' => [6, 5],
    'header from first page image freelist count' => 0,
    'index leaf page type accepted' => 'index-leaf',
    'without secure delete keeps old payload until allocation overwrites first byte' => 'P',
    'without clear leaves current fragments payload' => true,
    'empty payload rejected' => 'SQLite overflow freeblock pointer-map current-source requires replacement overflow payload bytes',
    'bad parent rejected' => 'SQLite overflow freeblock pointer-map current-source parent b-tree page must be at page 2 or later',
    'bad leaf rejected' => 'SQLite freeblock coalesce page is outside the database image',
];

$tests = [];

foreach ($cases128 as $name => $callback) {
    $tests['btree overflow freeblock pointermap current source base ' . $name] = static function (TestRunner $t) use ($callback, $expected128, $name): void {
        $t->same($expected128[$name], $callback());
    };
}

foreach (range(1, 18) as $index) {
    $tests['btree overflow freeblock pointermap current source base invariant ' . $index] = static function (TestRunner $t) use ($fixture128, $rows128, $index): void {
        $plan = $fixture128($index % 4 !== 0, $index % 3 !== 0, $index % 5 === 0 ? "\x0a" : "\x0d");
        $rows = $rows128($plan);

        $t->same($plan->releasedOverflowPages(), [5, 6]);
        $t->same($plan->allocatedOverflowPages(), [6, 5]);
        $t->same($plan->reusedOverflowPages(), [5, 6]);
        $t->same(array_column($rows, 'free_pointer_map_type'), ['free-page', 'free-page']);
        $t->same(array_column($rows, 'next_pointer_map_type'), ['first-overflow-page', 'overflow-page']);
        $t->same(array_column($rows, 'next_overflow_next_page'), [5, 0]);
        $t->same($plan->databaseAfterAllocation->freelistPageNumbers(), []);
        $t->same($plan->toArray()['btree_overflow_freeblock_pointermap_current_source'], $rows);
    };
}

return $tests;
