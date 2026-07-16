<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage124 = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
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
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry124 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$databaseFixture124 = static function () use ($makeFirstPage124, $putPointerMapEntry124): SQLiteDatabase {
    $pages = array_fill(1, 132, str_repeat("\0", 512));
    $existingLeaves = range(5, 124);
    $pages[1] = $makeFirstPage124(132, 4, 121);
    $pages[4] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[125] = SQLiteTableLeafPage::assemble([]);

    $putPointerMapEntry124($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry124($pages, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
    foreach ($existingLeaves as $leafPageNumber) {
        $putPointerMapEntry124($pages, $leafPageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
    }
    $putPointerMapEntry124($pages, 125, SQLitePointerMapEntry::BTREE_PAGE, 3);
    foreach ([126, 127, 128, 129, 131, 132] as $pageNumber) {
        $putPointerMapEntry124($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 125);
    }
    $putPointerMapEntry124($pages, 130, SQLitePointerMapEntry::BTREE_PAGE, 125);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$allocatedImages124 = static fn (): array => [
    130 => SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(12401, SQLiteRecord::encode([null, '_transient_promoted_trunk_reused_next124', 'current source reuse', 'no'])),
    ]),
];

$fixture124 = static function (?int $parentPage = 125, bool $secureDelete = false) use ($databaseFixture124, $allocatedImages124): SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan {
    return SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan::fromFreedPages(
        $databaseFixture124(),
        [130],
        1,
        $parentPage,
        $allocatedImages124(),
        $secureDelete,
    );
};

$throwsMessage124 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$rows124 = static fn (SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan $plan): array => $plan->reuseRows;

$cases124 = [
    'action label' => static fn (): mixed => $fixture124()->toArray()['action'],
    'free plan freed pages' => static fn (): mixed => $fixture124()->freePlan->freedPageNumbers,
    'free plan promotes freed page to trunk' => static fn (): mixed => $fixture124()->freePlan->newTrunkPageNumbers,
    'free plan does not append leaf into full trunk' => static fn (): mixed => $fixture124()->freePlan->leafPageNumbers,
    'free plan first trunk is promoted page' => static fn (): mixed => $fixture124()->freePlan->firstFreelistTrunkPage,
    'free plan count includes promoted trunk' => static fn (): mixed => $fixture124()->freePlan->freelistPageCount,
    'free plan updated freelist pages' => static fn (): mixed => array_keys($fixture124()->freePlan->updatedFreelistPages),
    'free plan updated pointer-map pages' => static fn (): mixed => array_keys($fixture124()->freePlan->updatedPointerMapPages),
    'database after free first trunk' => static fn (): mixed => $fixture124()->databaseAfterFree->header->firstFreelistTrunkPage,
    'database after free freelist count' => static fn (): mixed => $fixture124()->databaseAfterFree->header->freelistPageCount,
    'database after free allocation order first page' => static fn (): mixed => $fixture124()->databaseAfterFree->freelistAllocationOrder(1),
    'promoted trunk next pointer targets old trunk' => static fn (): mixed => $fixture124()->databaseAfterFree->freelistTrunkPages()[0]->nextTrunkPage,
    'promoted trunk has no leaves' => static fn (): mixed => $fixture124()->databaseAfterFree->freelistTrunkPages()[0]->leafPageNumbers,
    'promoted page pointer-map is free after free' => static fn (): mixed => $fixture124()->databaseAfterFree->pointerMapEntryForPage(130)->typeName(),
    'allocation pages reuse promoted trunk' => static fn (): mixed => $fixture124()->allocationPlan->allocatedPageNumbers,
    'allocation source is freelist trunk' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocationSteps(), 'source'),
    'allocation step trunk page' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocationSteps(), 'trunk_page'),
    'allocation next trunk before' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocationSteps(), 'next_trunk_page_before'),
    'allocation next trunk after' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocationSteps(), 'next_trunk_page_after'),
    'allocation count after step' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocationSteps(), 'freelist_page_count_after'),
    'allocation pointer-map pages' => static fn (): mixed => array_keys($fixture124()->allocationPlan->updatedPointerMapPages),
    'allocation pointer-map type' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocatedPointerMapEntries(), 'type_name'),
    'allocation pointer-map parent' => static fn (): mixed => array_column($fixture124()->allocationPlan->allocatedPointerMapEntries(), 'parent_page_number'),
    'final first trunk returns to old trunk' => static fn (): mixed => $fixture124()->databaseAfterReuse->header->firstFreelistTrunkPage,
    'final freelist count returns to original' => static fn (): mixed => $fixture124()->databaseAfterReuse->header->freelistPageCount,
    'final allocation order starts with old trunk leaf' => static fn (): mixed => $fixture124()->databaseAfterReuse->freelistAllocationOrder(2),
    'final page pointer-map type is btree' => static fn (): mixed => $fixture124()->databaseAfterReuse->pointerMapEntryForPage(130)->typeName(),
    'final page pointer-map parent' => static fn (): mixed => $fixture124()->databaseAfterReuse->pointerMapEntryForPage(130)->parentPageNumber,
    'final page type byte' => static fn (): mixed => ord($fixture124()->databaseAfterReuse->page(130)[0]),
    'final page cell count' => static fn (): mixed => $fixture124()->databaseAfterReuse->pageHeader(130)->cellCount,
    'reused promoted trunk pages' => static fn (): mixed => $fixture124()->reusedPromotedTrunkPageNumbers(),
    'reuse row page' => static fn (): mixed => array_column($rows124($fixture124()), 'page_number'),
    'reuse row free state' => static fn (): mixed => array_column($rows124($fixture124()), 'free_source_state'),
    'reuse row allocation source' => static fn (): mixed => array_column($rows124($fixture124()), 'allocation_source'),
    'reuse row promoted next trunk' => static fn (): mixed => array_column($rows124($fixture124()), 'promoted_next_trunk_page'),
    'reuse row promoted leaf count' => static fn (): mixed => array_column($rows124($fixture124()), 'promoted_leaf_count'),
    'reuse row before pointer type' => static fn (): mixed => array_column($rows124($fixture124()), 'before_pointer_map_type'),
    'reuse row before pointer parent' => static fn (): mixed => array_column($rows124($fixture124()), 'before_pointer_map_parent'),
    'reuse row free pointer type' => static fn (): mixed => array_column($rows124($fixture124()), 'free_pointer_map_type'),
    'reuse row free pointer parent' => static fn (): mixed => array_column($rows124($fixture124()), 'free_pointer_map_parent'),
    'reuse row reuse pointer type' => static fn (): mixed => array_column($rows124($fixture124()), 'reuse_pointer_map_type'),
    'reuse row reuse pointer parent' => static fn (): mixed => array_column($rows124($fixture124()), 'reuse_pointer_map_parent'),
    'reuse row supplied image flag' => static fn (): mixed => array_column($rows124($fixture124()), 'materialized_with_supplied_image'),
    'reuse row promoted header overwritten' => static fn (): mixed => array_column($rows124($fixture124()), 'promoted_trunk_header_overwritten'),
    'reuse row page type byte' => static fn (): mixed => array_column($rows124($fixture124()), 'next_page_type_byte'),
    'summary promoted trunks' => static fn (): mixed => $fixture124()->toArray()['promoted_trunk_page_numbers'],
    'summary updated page numbers' => static fn (): mixed => $fixture124()->toArray()['updated_page_numbers'],
    'summary embeds reuse row' => static fn (): mixed => array_column($fixture124()->toArray()['btree_freelist_pointermap_reuse_current_source_next124'], 'page_number'),
    'root allocation pointer type' => static fn (): mixed => array_column($rows124($fixture124(null)), 'reuse_pointer_map_type'),
    'root allocation pointer parent' => static fn (): mixed => array_column($rows124($fixture124(null)), 'reuse_pointer_map_parent'),
    'secure delete does not clear promoted trunk image' => static fn (): mixed => $fixture124(125, true)->freePlan->clearedPageNumbers,
    'secure delete leaves promoted trunk out of cleared images' => static fn (): mixed => array_key_exists(130, $fixture124(125, true)->freePlan->clearedPageImages),
    'final integrity check reports ok' => static fn (): mixed => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $fixture124()->databaseAfterReuse)['rows'],
    'zero allocation rejected' => static fn () => $throwsMessage124(static fn () => SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan::fromFreedPages($databaseFixture124(), [130], 0, 125)),
    'already free page rejected' => static fn () => $throwsMessage124(static fn () => SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan::fromFreedPages($databaseFixture124(), [5], 1, 125)),
    'non allocated supplied image rejected' => static fn () => $throwsMessage124(static fn () => SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan::fromFreedPages($databaseFixture124(), [130], 1, 125, [131 => str_repeat("\0", 512)])),
];

$expected124 = [
    'action label' => 'btree-freelist-pointermap-reuse-current-source-next124',
    'free plan freed pages' => [130],
    'free plan promotes freed page to trunk' => [130],
    'free plan does not append leaf into full trunk' => [],
    'free plan first trunk is promoted page' => 130,
    'free plan count includes promoted trunk' => 122,
    'free plan updated freelist pages' => [130],
    'free plan updated pointer-map pages' => [105],
    'database after free first trunk' => 130,
    'database after free freelist count' => 122,
    'database after free allocation order first page' => [130],
    'promoted trunk next pointer targets old trunk' => 4,
    'promoted trunk has no leaves' => [],
    'promoted page pointer-map is free after free' => 'free-page',
    'allocation pages reuse promoted trunk' => [130],
    'allocation source is freelist trunk' => ['freelist-trunk'],
    'allocation step trunk page' => [130],
    'allocation next trunk before' => [4],
    'allocation next trunk after' => [4],
    'allocation count after step' => [121],
    'allocation pointer-map pages' => [105],
    'allocation pointer-map type' => ['btree-page'],
    'allocation pointer-map parent' => [125],
    'final first trunk returns to old trunk' => 4,
    'final freelist count returns to original' => 121,
    'final allocation order starts with old trunk leaf' => [5, 124],
    'final page pointer-map type is btree' => 'btree-page',
    'final page pointer-map parent' => 125,
    'final page type byte' => 13,
    'final page cell count' => 1,
    'reused promoted trunk pages' => [130],
    'reuse row page' => [130],
    'reuse row free state' => ['promoted-freelist-trunk'],
    'reuse row allocation source' => ['freelist-trunk'],
    'reuse row promoted next trunk' => [4],
    'reuse row promoted leaf count' => [0],
    'reuse row before pointer type' => ['btree-page'],
    'reuse row before pointer parent' => [125],
    'reuse row free pointer type' => ['free-page'],
    'reuse row free pointer parent' => [0],
    'reuse row reuse pointer type' => ['btree-page'],
    'reuse row reuse pointer parent' => [125],
    'reuse row supplied image flag' => [true],
    'reuse row promoted header overwritten' => [true],
    'reuse row page type byte' => [13],
    'summary promoted trunks' => [130],
    'summary updated page numbers' => [1, 105, 130],
    'summary embeds reuse row' => [130],
    'root allocation pointer type' => ['root-page'],
    'root allocation pointer parent' => [0],
    'secure delete does not clear promoted trunk image' => [],
    'secure delete leaves promoted trunk out of cleared images' => false,
    'final integrity check reports ok' => [['integrity_check' => 'ok']],
    'zero allocation rejected' => 'SQLite b-tree freelist pointer-map reuse allocation count must be positive',
    'already free page rejected' => 'SQLite page 5 is already on the freelist',
    'non allocated supplied image rejected' => 'SQLite allocated page image was not part of the allocation plan',
];

$tests = [];

foreach ($cases124 as $name => $callback) {
    $tests['btree freelist pointermap reuse current source next124 ' . $name] = static function (TestRunner $t) use ($callback, $expected124, $name): void {
        $t->same($expected124[$name], $callback());
    };
}

foreach (range(1, 24) as $index) {
    $tests['btree freelist pointermap reuse current source next124 invariant ' . $index] = static function (TestRunner $t) use ($fixture124, $rows124, $index): void {
        $parentPage = $index % 4 === 0 ? null : 125;
        $plan = $fixture124($parentPage);
        $rows = $rows124($plan);

        $t->same([130], $plan->promotedTrunkPageNumbers());
        $t->same([130], $plan->reusedPromotedTrunkPageNumbers());
        $t->same([130], $plan->allocationPlan->allocatedPageNumbers);
        $t->same(['freelist-trunk'], array_column($plan->allocationPlan->allocationSteps(), 'source'));
        $t->same(4, $plan->databaseAfterReuse->header->firstFreelistTrunkPage);
        $t->same(121, $plan->databaseAfterReuse->header->freelistPageCount);
        $t->same(['free-page'], array_column($rows, 'free_pointer_map_type'));
        $t->same([$parentPage === null ? 'root-page' : 'btree-page'], array_column($rows, 'reuse_pointer_map_type'));
        $t->same([$parentPage ?? 0], array_column($rows, 'reuse_pointer_map_parent'));
        $t->same([true], array_column($rows, 'promoted_trunk_header_overwritten'));
    };
}

return $tests;
