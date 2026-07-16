<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReusePlan;
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

$fixture = static function (bool $secureDelete = true, int $replacementCount = 5, bool $allowAppend = true) use (
    $makeFirstPage,
    $putPointerMapEntry,
): array {
    $pageSize = 512;
    $pageCount = 260;
    $releasedPages = [20, 21, 22, 106, 107];
    $existingLeaves = array_values(array_filter(
        range(130, 252),
        static fn (int $pageNumber): bool => $pageNumber !== 208,
    ));
    $pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
    $pages[1] = $makeFirstPage($pageSize, $pageCount, 8, 1 + count($existingLeaves));
    $pages[8] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

    foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 8 => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
    }
    foreach ($existingLeaves as $pageNumber) {
        $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0, $pageSize);
    }
    foreach ($releasedPages as $index => $pageNumber) {
        $putPointerMapEntry(
            $pages,
            $pageNumber,
            $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
            $index === 0 ? 4 : $releasedPages[$index - 1],
            $pageSize,
        );
        $pages[$pageNumber] = str_repeat(chr(65 + $index), $pageSize);
    }

    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $deleteResults = [
        [
            'source' => 'wp_options-transient-table-overflow-current-next51',
            'obsolete_overflow_page_numbers' => [20, 21, 22],
            'rowids' => [41],
        ],
        [
            'source' => 'wp_options-option-name-index-overflow-current-next51',
            'obsolete_overflow_page_numbers' => [106, 107],
            'record_values' => [['_transient_reuse_current_next51', 41]],
        ],
    ];
    $plan = SQLiteOverflowFreelistReusePlan::fromDeleteResults(
        $database,
        $deleteResults,
        $replacementCount,
        4,
        $secureDelete,
        $allowAppend,
    );
    foreach ($plan->pageImages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

    return [$database, $plan, $postDatabase, $releasedPages, $existingLeaves];
};

$tests = [];

$tests['overflow freelist reuse current next51 reuses freshly released pages'] = static function (TestRunner $t) use ($fixture): void {
    [$database, $plan, $postDatabase, $releasedPages, $existingLeaves] = $fixture();
    $summary = $plan->toArray();

    $t->same('overflow-freelist-reuse', $summary['action']);
    $t->same($releasedPages, $plan->releasePlan->releasedOverflowPages);
    $t->same([20, 21, 22], $plan->releasePlan->sources[0]['pages']);
    $t->same([106, 107], $plan->releasePlan->sources[1]['pages']);
    $t->same('wp_options-transient-table-overflow-current-next51', $plan->releasePlan->sources[0]['source']);
    $t->same('wp_options-option-name-index-overflow-current-next51', $plan->releasePlan->sources[1]['source']);
    $t->same(5, $plan->releasePlan->sources[0]['count'] + $plan->releasePlan->sources[1]['count']);
    $t->same([21, 107, 106, 22, 20], $plan->replacementOverflowPageNumbers());
    $t->same([21, 107, 106, 22, 20], $plan->reusedReleasedPageNumbers);
    $t->same([], $plan->appendedPageNumbers());
    $t->same([21, 107, 106, 22, 20], $summary['replacement_overflow_pages']);
    $t->same([21, 107, 106, 22, 20], $summary['reused_released_pages']);
    $t->same([], $summary['appended_page_numbers']);
    $t->same([21, 107, 106, 22, 20], $plan->allocationPlan->allocatedPageNumbers);
    $t->same([2, 105], array_keys($plan->allocationPlan->updatedPointerMapPages));
    $t->same([2, 105], $summary['allocation']['updated_pointer_map_page_numbers']);
    $t->same([1, 2, 20, 105], array_values(array_intersect([1, 2, 8, 20, 105], array_keys($plan->pageImages))));
    $t->same(260, $plan->allocationPlan->databasePageCount);
    $t->same(8, $plan->allocationPlan->firstFreelistTrunkPage);
    $t->same(count($existingLeaves) + 6, $plan->releasePlan->freePlan->freelistPageCount);
    $t->same(count($existingLeaves) + 1, $plan->allocationPlan->freelistPageCount);
    $t->same(count($existingLeaves) + 1, $postDatabase->header->freelistPageCount);
    $t->same(8, $postDatabase->header->firstFreelistTrunkPage);
    $t->same($existingLeaves, $postDatabase->freelistTrunkPages()[0]->leafPageNumbers);
    $t->same([130, 252, 251, 250], $postDatabase->freelistAllocationOrder(4));
    $t->same('first-overflow-page', $postDatabase->pointerMapEntryForPage(21)->typeName());
    $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(22)->typeName());
    $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(106)->typeName());
    $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(107)->typeName());
    $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(20)->typeName());
    $t->same(4, $postDatabase->pointerMapEntryForPage(21)->parentPageNumber);
    $t->same(106, $postDatabase->pointerMapEntryForPage(22)->parentPageNumber);
    $t->same(107, $postDatabase->pointerMapEntryForPage(106)->parentPageNumber);
    $t->same(21, $postDatabase->pointerMapEntryForPage(107)->parentPageNumber);
    $t->same(22, $postDatabase->pointerMapEntryForPage(20)->parentPageNumber);
    $t->same([21, 107, 106, 22, 20], array_column($plan->allocationPlan->allocatedPointerMapEntries, 'page_number'));
    $t->same(['first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', 'overflow-page'], array_column($plan->allocationPlan->allocatedPointerMapEntries, 'type_name'));
    $t->same([4, 21, 107, 106, 22], array_column($plan->allocationPlan->allocatedPointerMapEntries, 'parent_page_number'));
    $t->same([2, 105, 105, 2, 2], array_column($plan->allocationPlan->allocatedPointerMapEntries, 'pointer_map_page'));
    $t->same(['freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-leaf', 'freelist-trunk'], array_column($plan->allocationPlan->allocationSteps(), 'source'));
    $t->same([21, 107, 106, 22, 20], array_column($plan->allocationPlan->allocationSteps(), 'allocated_page'));
    $t->same([20, 20, 20, 20, 20], array_column($plan->allocationPlan->allocationSteps(), 'trunk_page'));
    $t->same([3, 2, 1, 0], array_slice(array_column($plan->allocationPlan->allocationSteps(), 'leaf_count_after'), 0, 4));
    $t->same(8, $plan->allocationPlan->allocationSteps()[4]['next_trunk_page_after']);
    $t->same([21, 22, 106, 107], $plan->releasePlan->freePlan->clearedPageNumbers);
    $t->same(str_repeat("\0", 512), $database->page(21) === str_repeat("\0", 512) ? '' : $plan->releasePlan->freePlan->clearedPageImages[21]);
    $t->same(false, $postDatabase->page(20) === str_repeat("\0", 512));
    $t->same(true, $postDatabase->page(21) === str_repeat("\0", 512));
    $t->same(true, $postDatabase->page(22) === str_repeat("\0", 512));
    $t->same(true, $postDatabase->page(106) === str_repeat("\0", 512));
    $t->same(true, $postDatabase->page(107) === str_repeat("\0", 512));
    $t->same([20], $plan->releasePlan->freePlan->newTrunkPageNumbers);
    $t->same([21, 22, 106, 107], $plan->releasePlan->freePlan->leafPageNumbers);
    $t->same([20, 21, 22, 106, 107], $summary['released_overflow_pages']);
    $t->same([1, 2, 20, 105], array_values(array_intersect([1, 2, 8, 20, 105], $summary['updated_page_numbers'])));
    $t->same(20, $plan->releasePlan->freePlan->firstFreelistTrunkPage);
    $t->same(8, $plan->allocationPlan->firstFreelistTrunkPage);
    $t->same(count($existingLeaves) + 6, $summary['release']['free_plan']['freelist_page_count']);
    $t->same(count($existingLeaves) + 1, $summary['allocation']['freelist_page_count']);
};

$tests['overflow freelist reuse current next51 can append after reusing released pages'] = static function (TestRunner $t) use ($fixture): void {
    [, $plan, $postDatabase, , $existingLeaves] = $fixture(false, 129);

    $t->same([21, 107, 106, 22, 20], array_slice($plan->replacementOverflowPageNumbers(), 0, 5));
    $t->same([21, 107, 106, 22, 20], $plan->reusedReleasedPageNumbers);
    $t->same([261], $plan->appendedPageNumbers());
    $t->same(261, $postDatabase->header->databaseSizePages);
    $t->same('overflow-page', $postDatabase->pointerMapEntryForPage(261)->typeName());
    $t->same(8, $postDatabase->pointerMapEntryForPage(261)->parentPageNumber);
    $t->same(0, $postDatabase->header->freelistPageCount);
    $t->same([], $plan->releasePlan->freePlan->clearedPageNumbers);
};

$tests['overflow freelist reuse current next51 rejects replacement without append capacity'] = static function (TestRunner $t) use ($fixture): void {
    $t->throws(InvalidArgumentException::class, static fn () => $fixture(false, 130, false));
};

$tests['overflow freelist reuse current next51 validates replacement count'] = static function (TestRunner $t) use ($fixture): void {
    $t->throws(InvalidArgumentException::class, static fn () => $fixture(false, 0));
};

return $tests;
