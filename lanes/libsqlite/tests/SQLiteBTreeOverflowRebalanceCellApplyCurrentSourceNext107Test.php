<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (int $pageCount, int $firstTrunk = 0, int $freelistCount = 0): string {
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

$setPointerMap = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$tableFixture = static function () use ($firstPage, $setPointerMap): array {
    $pages = array_fill(1, 14, str_repeat("\0", 512));
    $pages[1] = $firstPage(14);
    $pages[2] = str_repeat("\0", 512);
    $kept = SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']);
    $old = SQLiteRecord::encode([null, '_transient_rebalance_old107', str_repeat('current-source-overflow:', 70), 'no']);
    $new = SQLiteRecord::encode([null, '_transient_rebalance_new107', 'fresh-local', 'no']);
    $local = SQLiteTableLeafCell::localPayloadLength(strlen($old), 512);
    $overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($old, $local), [8, 5, 12], 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, $kept),
        SQLiteTableLeafCell::encode(20, $old, 512, 8),
        SQLiteTableLeafCell::encode(30, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
    ]);
    foreach ($overflowPages as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $setPointerMap($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $setPointerMap($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $setPointerMap($pages, 5, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $setPointerMap($pages, 12, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);
    ksort($pages);
    $database = SQLiteDatabase::fromBytes(implode('', $pages));

    return [$database, $new];
};

$indexFixture = static function () use ($firstPage, $setPointerMap): array {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage(12);
    $pages[2] = str_repeat("\0", 512);
    $oldKey = str_repeat('_site_transient_update_plugins_', 40);
    $oldValues = [$oldKey, 'autoload', 77];
    $oldEncoded = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($oldValues), 6, 512);
    $pages[4] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'yes', 1])),
        $oldEncoded['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['theme_mods', 'no', 9])),
    ]);
    $overflowPageNumbers = array_slice(range(6, 12), 0, count($oldEncoded['overflowPages']));
    foreach (array_combine($overflowPageNumbers, $oldEncoded['overflowPages']) as $pageNumber => $page) {
        $pages[$pageNumber] = $page;
    }
    $setPointerMap($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $setPointerMap($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
    foreach (array_values($overflowPageNumbers) as $index => $pageNumber) {
        if ($index === 0) {
            continue;
        }
        $setPointerMap($pages, $pageNumber, SQLitePointerMapEntry::OVERFLOW_PAGE, $overflowPageNumbers[$index - 1]);
    }
    ksort($pages);
    $database = SQLiteDatabase::fromBytes(implode('', $pages));
    $overflowReader = static function (int $firstPageNumber, int $byteCount) use ($database): string {
        return $database->readOverflowPayloadForBtreePlan($firstPageNumber, $byteCount);
    };

    return [$database, $oldValues, ['option_name', 'autoload', 78], $overflowReader];
};

$tests = [];

$tests['btree overflow rebalance cell apply current source next107 table leaf applies replacement on current page'] = static function (TestRunner $t) use ($tableFixture): void {
    [$database, $newPayload] = $tableFixture();
    $plan = SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource(
        $database,
        3,
        20,
        21,
        $newPayload,
        static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstPage, $byteCount),
        true,
    );
    $post = $plan->databaseAfter;
    $header = SQLiteBTreePageHeader::parsePage($post->page(3), 512);
    $cells = SQLiteTableLeafCell::parsePageCells($post->page(3), $header, 512);
    $summary = $plan->toArray();

    $t->same('btree-overflow-rebalance-cell-apply-current-source-next107', $summary['action']);
    $t->same('btree-overflow-cell-reuse-delete-apply', $summary['cell_apply']['action']);
    $t->same([1, 21, 30], array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, $cells));
    $t->same([8, 5, 12], $plan->releasedOverflowPageNumbers());
    $t->same([1, 2, 3, 5, 8, 12], $plan->materializedPageNumbers());
    $t->same(8, $post->header->firstFreelistTrunkPage);
    $t->same(3, $post->header->freelistPageCount);
    $t->same([8, 5, 12], $post->freelistPageNumbers());
    $t->same('free-page', $post->pointerMapEntryForPage(8)->typeName());
    $t->same('free-page', $post->pointerMapEntryForPage(5)->typeName());
    $t->same('free-page', $post->pointerMapEntryForPage(12)->typeName());
    $t->same([false, false, false], array_column($summary['released_page_rows'], 'before_freelist_member'));
    $t->same([true, true, true], array_column($summary['released_page_rows'], 'after_freelist_member'));
    $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], array_column($summary['released_page_rows'], 'before_pointer_map_type'));
    $t->same(['free-page', 'free-page', 'free-page'], array_column($summary['released_page_rows'], 'after_pointer_map_type'));
    $t->same([false, true, true], array_column($summary['released_page_rows'], 'secure_delete_cleared'));
    $t->true($summary['cell_apply']['remaining_freeblock_bytes'] > 0);
    $t->same(14, $summary['database_page_count_after']);
};

$tests['btree overflow rebalance cell apply current source next107 index leaf applies replacement on current page'] = static function (TestRunner $t) use ($indexFixture): void {
    [$database, $oldValues, $newValues, $overflowReader] = $indexFixture();
    $plan = SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::indexLeafCurrentSource(
        $database,
        4,
        $oldValues,
        $newValues,
        static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstPage, $byteCount),
        true,
        $overflowReader,
    );
    $post = $plan->databaseAfter;
    $header = SQLiteBTreePageHeader::parsePage($post->page(4), 512);
    $records = array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, SQLiteIndexCell::parsePageCells($post->page(4), $header, 512));
    $summary = $plan->toArray();

    $t->same('index-leaf', $summary['cell_apply']['leaf_page_type']);
    $t->same($newValues, $records[1]);
    $t->same([6, 7, 8], $plan->releasedOverflowPageNumbers());
    $t->same([1, 2, 4, 6, 7, 8], $plan->materializedPageNumbers());
    $t->same(6, $post->header->firstFreelistTrunkPage);
    $t->same(3, $post->header->freelistPageCount);
    $t->same([6, 7, 8], $post->freelistPageNumbers());
    $t->same([6, 7, 8], $summary['cell_apply']['freed_pages']);
    $t->same([7, 8], $summary['cell_apply']['secure_delete_cleared_pages']);
    $t->same(['first-overflow-page', 'overflow-page', 'overflow-page'], array_column($summary['released_page_rows'], 'before_pointer_map_type'));
    $t->same(['free-page', 'free-page', 'free-page'], array_column($summary['released_page_rows'], 'after_pointer_map_type'));
    $t->same([true, true, true], array_column($summary['released_page_rows'], 'after_freelist_member'));
    $t->same(12, $summary['database_page_count_before']);
    $t->same(12, $summary['database_page_count_after']);
};

$tests['btree overflow rebalance cell apply current source next107 rejects corrupt inputs'] = static function (TestRunner $t) use ($tableFixture): void {
    [$database, $newPayload] = $tableFixture();

    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource($database, 99, 20, 21, $newPayload, static fn (): array => [8]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource($database, 3, 999, 21, $newPayload, static fn (): array => [8]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource($database, 3, 20, 1, $newPayload, static fn (): array => [8]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource($database, 3, 20, 21, str_repeat('x', 470), static fn (): array => [8]));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource($database, 3, 20, 21, $newPayload, static fn (): array => [8, 8]));
};

$tablePlan = static function () use ($tableFixture): SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan {
    [$database, $newPayload] = $tableFixture();

    return SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource(
        $database,
        3,
        20,
        21,
        $newPayload,
        static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstPage, $byteCount),
        true,
    );
};

$indexPlan = static function () use ($indexFixture): SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan {
    [$database, $oldValues, $newValues, $overflowReader] = $indexFixture();

    return SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::indexLeafCurrentSource(
        $database,
        4,
        $oldValues,
        $newValues,
        static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstPage, $byteCount),
        true,
        $overflowReader,
    );
};

$caseRows = [
    'table summary action' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->toArray()['action'], 'btree-overflow-rebalance-cell-apply-current-source-next107'],
    'table nested action' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->toArray()['cell_apply']['action'], 'btree-overflow-cell-reuse-delete-apply'],
    'table leaf type' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->cellApplyPlan->leafPageType, 'table-leaf'],
    'table released pages' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPageNumbers(), [8, 5, 12]],
    'table materialized pages' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->materializedPageNumbers(), [1, 2, 3, 5, 8, 12]],
    'table freelist after' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter->freelistPageNumbers(), [8, 5, 12]],
    'table pointer types before' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => array_column($plan->releasedPageRows(), 'before_pointer_map_type'), ['first-overflow-page', 'overflow-page', 'overflow-page']],
    'table pointer types after' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => array_column($plan->releasedPageRows(), 'after_pointer_map_type'), ['free-page', 'free-page', 'free-page']],
    'table secure delete rows' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => array_column($plan->releasedPageRows(), 'secure_delete_cleared'), [false, true, true]],
    'table rows after' => [$tablePlan, static function (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed {
        $header = SQLiteBTreePageHeader::parsePage($plan->databaseAfter->page(3), 512);
        return array_map(static fn (SQLiteTableLeafCell $cell): int => $cell->rowId, SQLiteTableLeafCell::parsePageCells($plan->databaseAfter->page(3), $header, 512));
    }, [1, 21, 30]],
    'table first trunk after' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter->header->firstFreelistTrunkPage, 8],
    'table freelist count after' => [$tablePlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter->header->freelistPageCount, 3],
    'index leaf type' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->cellApplyPlan->leafPageType, 'index-leaf'],
    'index released pages' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->releasedOverflowPageNumbers(), [6, 7, 8]],
    'index materialized pages' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->materializedPageNumbers(), [1, 2, 4, 6, 7, 8]],
    'index freelist after' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter->freelistPageNumbers(), [6, 7, 8]],
    'index secure delete pages' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->toArray()['cell_apply']['secure_delete_cleared_pages'], [7, 8]],
    'index pointer types before' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => array_column($plan->releasedPageRows(), 'before_pointer_map_type'), ['first-overflow-page', 'overflow-page', 'overflow-page']],
    'index pointer types after' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => array_column($plan->releasedPageRows(), 'after_pointer_map_type'), ['free-page', 'free-page', 'free-page']],
    'index records after' => [$indexPlan, static function (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed {
        $header = SQLiteBTreePageHeader::parsePage($plan->databaseAfter->page(4), 512);
        return array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, SQLiteIndexCell::parsePageCells($plan->databaseAfter->page(4), $header, 512))[1];
    }, ['option_name', 'autoload', 78]],
    'index first trunk after' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter->header->firstFreelistTrunkPage, 6],
    'index freelist count after' => [$indexPlan, static fn (SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan $plan): mixed => $plan->databaseAfter->header->freelistPageCount, 3],
];

foreach ($caseRows as $name => [$fixture, $callback, $expected]) {
    $tests['btree overflow rebalance cell apply current source next107 ' . $name] = static function (TestRunner $t) use ($fixture, $callback, $expected): void {
        $t->same($expected, $callback($fixture()));
    };
}

return $tests;
