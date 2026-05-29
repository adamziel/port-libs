<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage113 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
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
    $page = substr_replace($page, pack('N', 42), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry113 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$databaseFixture113 = static function () use ($makeFirstPage113, $putPointerMapEntry113): SQLiteDatabase {
    $pages = array_fill(1, 112, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage113(112, 4, 4);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
    $pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107], 512);

    $putPointerMapEntry113($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry113($pages, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    $putPointerMapEntry113($pages, 5, SQLitePointerMapEntry::FREE_PAGE, 0);
    $putPointerMapEntry113($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 3);
    $putPointerMapEntry113($pages, 106, SQLitePointerMapEntry::FREE_PAGE, 0);
    $putPointerMapEntry113($pages, 107, SQLitePointerMapEntry::FREE_PAGE, 0);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$allocatedImages113 = static fn (): array => [
    5 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(11301, SQLiteRecord::encode([null, '_transient_reused_leaf_next113', 'leaf', 'no'])),
    ]),
    4 => SQLiteIndexLeafPage::assemble([
        SQLiteRecord::encode(['_transient_reused_trunk_next113', 11301]),
    ]),
];

$fixture113 = static function (int $allocationCount = 2, ?int $parentPage = 42) use ($databaseFixture113, $allocatedImages113): SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan {
    return SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan::fromDatabase(
        $databaseFixture113(),
        $allocationCount,
        $parentPage,
        array_slice($allocatedImages113(), 0, $allocationCount, true),
    );
};

$throwsMessage113 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$trunkRows113 = static fn (SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan $plan): array => $plan->trunkPointerMapReuseRows();

$cases113 = [
    'action label' => static fn (): mixed => $fixture113()->toArray()['action'],
    'allocation pages consume leaf then trunk' => static fn (): mixed => $fixture113()->allocatedPageNumbers(),
    'allocation sources expose trunk reuse' => static fn (): mixed => array_column($fixture113()->allocationPlan->allocationSteps(), 'source'),
    'allocation trunk pages' => static fn (): mixed => array_column($fixture113()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation freelist counts' => static fn (): mixed => array_column($fixture113()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'final first trunk advances to next trunk' => static fn (): mixed => $fixture113()->databaseAfterReuse->header->firstFreelistTrunkPage,
    'final freelist count leaves next trunk and leaf' => static fn (): mixed => $fixture113()->databaseAfterReuse->header->freelistPageCount,
    'final freelist pages preserve next trunk chain' => static fn (): mixed => $fixture113()->databaseAfterReuse->freelistPageNumbers(),
    'trunk row page' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'page_number'),
    'trunk row allocation position' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'allocation_position'),
    'trunk row current source state' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'current_source_state'),
    'trunk row current next trunk' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'current_next_trunk_page'),
    'trunk row current leaf count' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'current_leaf_count'),
    'trunk row next source state' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'next_source_state'),
    'trunk row next first trunk' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'next_first_freelist_trunk_page'),
    'trunk row count after step' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'freelist_page_count_after_step'),
    'trunk row before pointer map type' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'before_pointer_map_type'),
    'trunk row before pointer map parent' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'before_pointer_map_parent'),
    'trunk row after pointer map type' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'after_pointer_map_type'),
    'trunk row after pointer map parent' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'after_pointer_map_parent'),
    'trunk row supplied image flag' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'materialized_with_supplied_image'),
    'trunk row stale header overwritten' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'stale_trunk_header_overwritten'),
    'trunk row page type byte' => static fn (): mixed => array_column($trunkRows113($fixture113()), 'next_page_type_byte'),
    'allocated pointer map pages' => static fn (): mixed => array_column($fixture113()->allocationPlan->allocatedPointerMapEntries(), 'page_number'),
    'allocated pointer map type names' => static fn (): mixed => array_column($fixture113()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocated pointer map parents' => static fn (): mixed => array_column($fixture113()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'updated pointer map page numbers' => static fn (): mixed => array_keys($fixture113()->allocationPlan->updatedPointerMapPages),
    'updated page image numbers' => static fn (): mixed => array_keys($fixture113()->pageImages()),
    'reused leaf page type byte' => static fn (): mixed => ord($fixture113()->databaseAfterReuse->page(5)[0]),
    'reused trunk page type byte' => static fn (): mixed => ord($fixture113()->databaseAfterReuse->page(4)[0]),
    'reused trunk page cell count' => static fn (): mixed => $fixture113()->databaseAfterReuse->pageHeader(4)->cellCount,
    'next trunk remains freelist trunk' => static fn (): mixed => $fixture113()->databaseAfterReuse->freelistTrunkPages()[0]->pageNumber,
    'next trunk remaining leaf survives' => static fn (): mixed => $fixture113()->databaseAfterReuse->freelistTrunkPages()[0]->leafPageNumbers,
    'one allocation does not reuse a trunk page' => static fn (): mixed => $trunkRows113($fixture113(1)),
    'one allocation leaves original first trunk' => static fn (): mixed => $fixture113(1)->databaseAfterReuse->header->firstFreelistTrunkPage,
    'one allocation leaves three free pages' => static fn (): mixed => $fixture113(1)->databaseAfterReuse->header->freelistPageCount,
    'root allocation rewrites reused trunk pointer map as root page' => static fn (): mixed => array_column($trunkRows113($fixture113(2, null)), 'after_pointer_map_type'),
    'root allocation parent is zero' => static fn (): mixed => array_column($trunkRows113($fixture113(2, null)), 'after_pointer_map_parent'),
    'summary embeds trunk rows' => static fn (): mixed => array_column($fixture113()->toArray()['btree_freelist_trunk_pointermap_reuse_current_source_next113'], 'page_number'),
    'summary final freelist pages' => static fn (): mixed => $fixture113()->toArray()['final_freelist_page_numbers'],
    'summary updated page numbers' => static fn (): mixed => $fixture113()->toArray()['updated_page_numbers'],
    'zero allocation is rejected' => static fn (): mixed => $throwsMessage113(static fn () => SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan::fromDatabase($databaseFixture113(), 0, 42)),
    'bad parent is rejected' => static fn (): mixed => $throwsMessage113(static fn () => SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan::fromDatabase($databaseFixture113(), 2, 1)),
    'over allocation without append is rejected' => static fn (): mixed => $throwsMessage113(static fn () => SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan::fromDatabase($databaseFixture113(), 5, 42)),
    'non allocated supplied page image is rejected' => static fn (): mixed => $throwsMessage113(static fn () => SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan::fromDatabase($databaseFixture113(), 2, 42, [6 => str_repeat("\0", 512)])),
];

$expected113 = [
    'action label' => 'btree-freelist-trunk-pointermap-reuse-current-source-next113',
    'allocation pages consume leaf then trunk' => [5, 4],
    'allocation sources expose trunk reuse' => ['freelist-leaf', 'freelist-trunk'],
    'allocation trunk pages' => [4, 4],
    'allocation freelist counts' => [3, 2],
    'final first trunk advances to next trunk' => 106,
    'final freelist count leaves next trunk and leaf' => 2,
    'final freelist pages preserve next trunk chain' => [106, 107],
    'trunk row page' => [4],
    'trunk row allocation position' => [1],
    'trunk row current source state' => ['freelist-trunk'],
    'trunk row current next trunk' => [106],
    'trunk row current leaf count' => [1],
    'trunk row next source state' => ['reused-as-btree-page'],
    'trunk row next first trunk' => [106],
    'trunk row count after step' => [2],
    'trunk row before pointer map type' => ['free-page'],
    'trunk row before pointer map parent' => [0],
    'trunk row after pointer map type' => ['btree-page'],
    'trunk row after pointer map parent' => [42],
    'trunk row supplied image flag' => [true],
    'trunk row stale header overwritten' => [true],
    'trunk row page type byte' => [10],
    'allocated pointer map pages' => [5, 4],
    'allocated pointer map type names' => ['btree-page', 'btree-page'],
    'allocated pointer map parents' => [42, 42],
    'updated pointer map page numbers' => [2],
    'updated page image numbers' => [1, 2, 4, 5],
    'reused leaf page type byte' => 13,
    'reused trunk page type byte' => 10,
    'reused trunk page cell count' => 1,
    'next trunk remains freelist trunk' => 106,
    'next trunk remaining leaf survives' => [107],
    'one allocation does not reuse a trunk page' => [],
    'one allocation leaves original first trunk' => 4,
    'one allocation leaves three free pages' => 3,
    'root allocation rewrites reused trunk pointer map as root page' => ['root-page'],
    'root allocation parent is zero' => [0],
    'summary embeds trunk rows' => [4],
    'summary final freelist pages' => [106, 107],
    'summary updated page numbers' => [1, 2, 4, 5],
    'zero allocation is rejected' => 'SQLite freelist trunk pointer-map reuse allocation count must be positive',
    'bad parent is rejected' => 'SQLite b-tree allocation parent page must be null or at page 2 or later',
    'over allocation without append is rejected' => 'SQLite freelist does not contain enough pages for this allocation',
    'non allocated supplied page image is rejected' => 'SQLite allocated page image was not part of the allocation plan',
];

$tests = [];

foreach ($cases113 as $name => $callback) {
    $tests['btree freelist trunk pointermap reuse current source next113 ' . $name] = static function (TestRunner $t) use ($callback, $expected113, $name): void {
        $t->same($expected113[$name], $callback());
    };
}

foreach (range(1, 30) as $index) {
    $tests['btree freelist trunk pointermap reuse current source next113 invariant ' . $index] = static function (TestRunner $t) use ($fixture113, $trunkRows113, $index): void {
        $parentPage = $index % 5 === 0 ? null : 42;
        $plan = $fixture113(2, $parentPage);
        $rows = $trunkRows113($plan);

        $t->same([5, 4], $plan->allocatedPageNumbers());
        $t->same([], $plan->allocationPlan->appendedPageNumbers);
        $t->same(106, $plan->databaseAfterReuse->header->firstFreelistTrunkPage);
        $t->same(2, $plan->databaseAfterReuse->header->freelistPageCount);
        $t->same([106, 107], $plan->databaseAfterReuse->freelistPageNumbers());
        $t->same([4], array_column($rows, 'page_number'));
        $t->same([106], array_column($rows, 'current_next_trunk_page'));
        $t->same(['free-page'], array_column($rows, 'before_pointer_map_type'));
        $t->same([$parentPage === null ? 'root-page' : 'btree-page'], array_column($rows, 'after_pointer_map_type'));
        $t->same([$parentPage ?? 0], array_column($rows, 'after_pointer_map_parent'));
        $t->same([true], array_column($rows, 'stale_trunk_header_overwritten'));
    };
}

return $tests;
