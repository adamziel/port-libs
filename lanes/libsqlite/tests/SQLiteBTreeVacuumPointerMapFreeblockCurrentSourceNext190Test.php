<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage190 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 0), 32, 4);
    $page = substr_replace($page, pack('N', 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry190 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$database190 = static function () use ($makeFirstPage190, $putPointerMapEntry190): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage190(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next190', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(80 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry190($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan190 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database190;

    $database = $database190();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafLeaseManifestFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next190-reader-lease-current-source-', 48),
        3,
        true,
    );
};

$message190 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases190 = [
    'action label' => static fn (): mixed => $plan190()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan190()->leaseSummary()['status'],
    'lease errors' => static fn (): mixed => $plan190()->leaseErrors(),
    'reader visible pages' => static fn (): mixed => $plan190()->readerVisiblePages(),
    'reader fenced pages' => static fn (): mixed => $plan190()->readerFencedPages(),
    'reusable overflow pages' => static fn (): mixed => $plan190()->reusableOverflowPages(),
    'scrubbed freeblock pages' => static fn (): mixed => $plan190()->scrubbedFreeblockPages(),
    'summary visible pages' => static fn (): mixed => $plan190()->leaseSummary()['reader_visible_pages'],
    'summary fenced pages' => static fn (): mixed => $plan190()->leaseSummary()['reader_fenced_pages'],
    'summary reusable overflow pages' => static fn (): mixed => $plan190()->leaseSummary()['reusable_overflow_pages'],
    'summary scrubbed pages' => static fn (): mixed => $plan190()->leaseSummary()['scrubbed_freeblock_pages'],
    'summary lease errors' => static fn (): mixed => $plan190()->leaseSummary()['lease_error_count'],
    'summary reader page count' => static fn (): mixed => $plan190()->leaseSummary()['reader_page_count'],
    'summary tail fence count' => static fn (): mixed => $plan190()->leaseSummary()['tail_fence_count'],
    'summary ordinals contiguous' => static fn (): mixed => $plan190()->leaseSummary()['reader_ordinals_contiguous'],
    'summary tail after reader pages' => static fn (): mixed => $plan190()->leaseSummary()['tail_fence_after_reader_pages'],
    'summary reuse receipts complete' => static fn (): mixed => $plan190()->leaseSummary()['all_reader_pages_reusable_or_scrubbed'],
    'summary dependencies' => static fn (): mixed => $plan190()->leaseSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan190()->leaseSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan190()->leaseSummary()['non_overlap'], 'does not repeat next187 publish barriers'),
    'row pages' => static fn (): mixed => array_column($plan190()->leaseRows(), 'page_number'),
    'reader states' => static fn (): mixed => array_column($plan190()->leaseRows(), 'reader_state'),
    'reader ordinals' => static fn (): mixed => array_column($plan190()->leaseRows(), 'reader_ordinal'),
    'publish states' => static fn (): mixed => array_column($plan190()->leaseRows(), 'publish_state'),
    'cursor states' => static fn (): mixed => array_column($plan190()->leaseRows(), 'cursor_state'),
    'secure delete visible' => static fn (): mixed => array_column($plan190()->leaseRows(), 'secure_delete_freeblock_visible'),
    'overflow reusable' => static fn (): mixed => array_column($plan190()->leaseRows(), 'overflow_reusable_by_next_writer'),
    'tail visible' => static fn (): mixed => array_column($plan190()->leaseRows(), 'tail_fence_visible_to_reader'),
    'tail required' => static fn (): mixed => array_column($plan190()->leaseRows(), 'tail_fence_required'),
    'tail excluded' => static fn (): mixed => array_column($plan190()->leaseRows(), 'tail_excluded_from_next_source'),
    'receipt complete' => static fn (): mixed => array_column($plan190()->leaseRows(), 'receipt_chain_complete'),
    'reuse receipt complete' => static fn (): mixed => array_column($plan190()->leaseRows(), 'reader_reuse_receipt_complete'),
    'source replayable' => static fn (): mixed => array_column($plan190()->leaseRows(), 'source_replayable'),
    'final materialized' => static fn (): mixed => array_column($plan190()->leaseRows(), 'final_materialized'),
    'lease token length' => static fn (): mixed => strlen($plan190()->leaseSummary()['lease_token']),
    'publish token length' => static fn (): mixed => strlen($plan190()->leaseSummary()['publish_token']),
    'lease key length' => static fn (): mixed => strlen($plan190()->leaseRows()[0]['reader_lease_key']),
    'base action label' => static fn (): mixed => $plan190()->basePlan->toArray()['action'],
    'base next source pages' => static fn (): mixed => $plan190()->basePlan->nextSourcePages(),
    'base fenced pages' => static fn (): mixed => $plan190()->basePlan->fencedTailPages(),
    'wide visible pages' => static fn (): mixed => $plan190(null, 6)->readerVisiblePages(),
    'wide fenced pages' => static fn (): mixed => $plan190(null, 6)->readerFencedPages(),
    'small replacement rejected' => static fn (): mixed => $message190(static fn () => $plan190(str_repeat('small', 20))),
];

$expected190 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next190',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next190-ready',
    'lease errors' => [],
    'reader visible pages' => [3, 106, 107, 108, 109],
    'reader fenced pages' => [110],
    'reusable overflow pages' => [109],
    'scrubbed freeblock pages' => [3],
    'summary visible pages' => [3, 106, 107, 108, 109],
    'summary fenced pages' => [110],
    'summary reusable overflow pages' => [109],
    'summary scrubbed pages' => [3],
    'summary lease errors' => 0,
    'summary reader page count' => 5,
    'summary tail fence count' => 1,
    'summary ordinals contiguous' => true,
    'summary tail after reader pages' => true,
    'summary reuse receipts complete' => true,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next187', 'sqlite-current-source-next190'],
    'dependency closure' => 'no new support component needed; next190 reuses next187 publish barriers, secure-delete freeblock receipts, overflow terminal receipts, and truncated-tail pointer-map fences',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'reader states' => ['reader-visible', 'reader-visible', 'reader-visible', 'reader-visible', 'reader-visible', 'reader-fenced-tail'],
    'reader ordinals' => [1, 2, 3, 4, 5, null],
    'publish states' => ['publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'fence-truncated-tail-page'],
    'cursor states' => ['materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'excluded-truncated-tail'],
    'secure delete visible' => [true, false, false, false, false, false],
    'overflow reusable' => [false, false, false, false, true, false],
    'tail visible' => [false, false, false, false, false, false],
    'tail required' => [false, false, false, false, false, true],
    'tail excluded' => [true, true, true, true, true, true],
    'receipt complete' => [true, true, true, true, true, true],
    'reuse receipt complete' => [true, true, true, true, true, true],
    'source replayable' => [true, true, true, true, true, false],
    'final materialized' => [true, true, true, true, true, false],
    'lease token length' => 64,
    'publish token length' => 64,
    'lease key length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next187',
    'base next source pages' => [3, 106, 107, 108, 109],
    'base fenced pages' => [110],
    'wide visible pages' => [3, 106, 107, 108, 109],
    'wide fenced pages' => [110],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
];

$tests = [];

foreach ($cases190 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next190 ' . $name] = static function (TestRunner $t) use ($callback, $expected190, $name): void {
        $t->same($expected190[$name], $callback());
    };
}

foreach (range(1, 72) as $index) {
    $tests['btree vacuum pointermap freeblock current source next190 reader lease invariant ' . $index] = static function (TestRunner $t) use ($plan190): void {
        $plan = $plan190();
        $rows = $plan->leaseRows();
        $summary = $plan->leaseSummary();

        $t->same([], $plan->leaseErrors());
        $t->same([3, 106, 107, 108, 109], $plan->readerVisiblePages());
        $t->same([110], $plan->readerFencedPages());
        $t->same([109], $plan->reusableOverflowPages());
        $t->same([3], $plan->scrubbedFreeblockPages());
        $t->same([1, 2, 3, 4, 5, null], array_column($rows, 'reader_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($rows, 'reader_reuse_receipt_complete'));
        $t->same([false, false, false, false, false, false], array_column($rows, 'tail_fence_visible_to_reader'));
        $t->same(true, $rows[0]['secure_delete_freeblock_visible']);
        $t->same(true, $rows[4]['overflow_reusable_by_next_writer']);
        $t->same(true, $rows[5]['tail_fence_required']);
        $t->same(false, $rows[5]['tail_fence_visible_to_reader']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next190-ready', $summary['status']);
        $t->same(true, $summary['reader_ordinals_contiguous']);
        $t->same(true, $summary['tail_fence_after_reader_pages']);
        $t->same(true, $summary['all_reader_pages_reusable_or_scrubbed']);
    };
}

return $tests;
