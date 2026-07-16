<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage194 = static function (int $pageCount): string {
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

$putPointerMapEntry194 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database194 = static function () use ($makeFirstPage194, $putPointerMapEntry194): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage194(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next194', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry194($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan194 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database194;

    $database = $database194();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafWriterLeaseFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next194-writer-admission-current-source-', 48),
        3,
        true,
    );
};

$message194 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases194 = [
    'action label' => static fn (): mixed => $plan194()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan194()->writerSummary()['status'],
    'writer errors' => static fn (): mixed => $plan194()->writerErrors(),
    'admitted leaf freeblock pages' => static fn (): mixed => $plan194()->admittedLeafFreeblockPages(),
    'admitted overflow freelist pages' => static fn (): mixed => $plan194()->admittedOverflowFreelistPages(),
    'fenced tail pages' => static fn (): mixed => $plan194()->fencedTailPages(),
    'summary admitted writer pages' => static fn (): mixed => $plan194()->writerSummary()['admitted_writer_pages'],
    'summary fenced tail not admitted' => static fn (): mixed => $plan194()->writerSummary()['fenced_tail_not_admitted'],
    'summary admitted pages reader visible' => static fn (): mixed => $plan194()->writerSummary()['all_admitted_pages_reader_visible'],
    'summary admitted pages pointer map safe' => static fn (): mixed => $plan194()->writerSummary()['all_admitted_pages_pointer_map_safe'],
    'summary leaf before overflow' => static fn (): mixed => $plan194()->writerSummary()['leaf_freeblock_before_overflow_freelist'],
    'summary writer error count' => static fn (): mixed => $plan194()->writerSummary()['writer_error_count'],
    'summary dependencies' => static fn (): mixed => $plan194()->writerSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan194()->writerSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan194()->writerSummary()['non_overlap'], 'does not repeat next190 reader visibility'),
    'row pages' => static fn (): mixed => array_column($plan194()->writerRows(), 'page_number'),
    'reuse channels' => static fn (): mixed => array_column($plan194()->writerRows(), 'reuse_channel'),
    'receipt kinds' => static fn (): mixed => array_column($plan194()->writerRows(), 'receipt_kind'),
    'next writer admitted' => static fn (): mixed => array_column($plan194()->writerRows(), 'next_writer_admitted'),
    'writer ordinals' => static fn (): mixed => array_column($plan194()->writerRows(), 'writer_ordinal'),
    'reader visible before writer' => static fn (): mixed => array_column($plan194()->writerRows(), 'reader_visible_before_writer'),
    'pointer map safe for writer' => static fn (): mixed => array_column($plan194()->writerRows(), 'pointer_map_safe_for_writer'),
    'secure delete visible' => static fn (): mixed => array_column($plan194()->writerRows(), 'secure_delete_freeblock_visible'),
    'overflow reusable' => static fn (): mixed => array_column($plan194()->writerRows(), 'overflow_reusable_by_next_writer'),
    'tail required' => static fn (): mixed => array_column($plan194()->writerRows(), 'tail_fence_required'),
    'tail excluded' => static fn (): mixed => array_column($plan194()->writerRows(), 'tail_excluded_from_next_source'),
    'tail visible' => static fn (): mixed => array_column($plan194()->writerRows(), 'tail_fence_visible_to_reader'),
    'reader reuse receipts' => static fn (): mixed => array_column($plan194()->writerRows(), 'reader_reuse_receipt_complete'),
    'source replayable' => static fn (): mixed => array_column($plan194()->writerRows(), 'source_replayable'),
    'final materialized' => static fn (): mixed => array_column($plan194()->writerRows(), 'final_materialized'),
    'writer token length' => static fn (): mixed => strlen($plan194()->writerSummary()['writer_admission_token']),
    'reader token length' => static fn (): mixed => strlen($plan194()->writerSummary()['reader_lease_token']),
    'writer key length' => static fn (): mixed => strlen($plan194()->writerRows()[0]['writer_admission_key']),
    'base action label' => static fn (): mixed => $plan194()->basePlan->toArray()['action'],
    'base reader pages' => static fn (): mixed => $plan194()->basePlan->readerVisiblePages(),
    'base fenced pages' => static fn (): mixed => $plan194()->basePlan->readerFencedPages(),
    'wide admitted overflow freelist pages' => static fn (): mixed => $plan194(null, 6)->admittedOverflowFreelistPages(),
    'wide fenced tail pages' => static fn (): mixed => $plan194(null, 6)->fencedTailPages(),
    'small replacement rejected' => static fn (): mixed => $message194(static fn () => $plan194(str_repeat('small', 20))),
];

$expected194 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next194',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next194-ready',
    'writer errors' => [],
    'admitted leaf freeblock pages' => [3],
    'admitted overflow freelist pages' => [109],
    'fenced tail pages' => [110],
    'summary admitted writer pages' => [3, 109],
    'summary fenced tail not admitted' => true,
    'summary admitted pages reader visible' => true,
    'summary admitted pages pointer map safe' => true,
    'summary leaf before overflow' => true,
    'summary writer error count' => 0,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next190', 'sqlite-current-source-next194'],
    'dependency closure' => 'no new support component needed; next194 reuses next190 reader leases, secure-delete freeblock visibility, terminal overflow receipts, and tail-fence exclusion',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'reuse channels' => ['leaf-freeblock', 'current-source-page', 'current-source-page', 'current-source-page', 'overflow-freelist', 'tail-fence'],
    'receipt kinds' => ['secure-delete-freeblock', 'reader-visible-page', 'reader-visible-page', 'reader-visible-page', 'terminal-overflow-freelist', 'truncated-tail-fence'],
    'next writer admitted' => [true, false, false, false, true, false],
    'writer ordinals' => [1, null, null, null, 2, null],
    'reader visible before writer' => [true, true, true, true, true, false],
    'pointer map safe for writer' => [true, true, true, true, true, false],
    'secure delete visible' => [true, false, false, false, false, false],
    'overflow reusable' => [false, false, false, false, true, false],
    'tail required' => [false, false, false, false, false, true],
    'tail excluded' => [true, true, true, true, true, true],
    'tail visible' => [false, false, false, false, false, false],
    'reader reuse receipts' => [true, true, true, true, true, true],
    'source replayable' => [true, true, true, true, true, false],
    'final materialized' => [true, true, true, true, true, false],
    'writer token length' => 64,
    'reader token length' => 64,
    'writer key length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next190',
    'base reader pages' => [3, 106, 107, 108, 109],
    'base fenced pages' => [110],
    'wide admitted overflow freelist pages' => [109],
    'wide fenced tail pages' => [110],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
];

$tests = [];

foreach ($cases194 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next194 ' . $name] = static function (TestRunner $t) use ($callback, $expected194, $name): void {
        $t->same($expected194[$name], $callback());
    };
}

foreach (range(1, 76) as $index) {
    $tests['btree vacuum pointermap freeblock current source next194 writer admission invariant ' . $index] = static function (TestRunner $t) use ($plan194): void {
        $plan = $plan194();
        $rows = $plan->writerRows();
        $summary = $plan->writerSummary();

        $t->same([], $plan->writerErrors());
        $t->same([3], $plan->admittedLeafFreeblockPages());
        $t->same([109], $plan->admittedOverflowFreelistPages());
        $t->same([110], $plan->fencedTailPages());
        $t->same([true, false, false, false, true, false], array_column($rows, 'next_writer_admitted'));
        $t->same([1, null, null, null, 2, null], array_column($rows, 'writer_ordinal'));
        $t->same('leaf-freeblock', $rows[0]['reuse_channel']);
        $t->same('overflow-freelist', $rows[4]['reuse_channel']);
        $t->same('tail-fence', $rows[5]['reuse_channel']);
        $t->same(false, $rows[5]['next_writer_admitted']);
        $t->same(false, $rows[5]['tail_fence_visible_to_reader']);
        $t->same(true, $summary['fenced_tail_not_admitted']);
        $t->same(true, $summary['all_admitted_pages_reader_visible']);
        $t->same(true, $summary['all_admitted_pages_pointer_map_safe']);
        $t->same(true, $summary['leaf_freeblock_before_overflow_freelist']);
    };
}

return $tests;
