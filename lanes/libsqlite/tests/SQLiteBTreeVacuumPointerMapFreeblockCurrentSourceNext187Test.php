<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage187 = static function (int $pageCount): string {
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

$putPointerMapEntry187 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage187 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database187 = static function () use ($makeFirstPage187, $putPointerMapEntry187, $overflowPage187): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage187(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next187', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage187(107, 'A');
    $pages[107] = $overflowPage187(108, 'B');
    $pages[108] = $overflowPage187(109, 'C');
    $pages[109] = $overflowPage187(110, 'D');
    $pages[110] = $overflowPage187(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry187($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan187 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database187;

    $database = $database187();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafPublishBarrierFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next187-final-current-source-barrier-receipt-', 44),
        3,
        true,
    );
};

$message187 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases187 = [
    'action label' => static fn (): mixed => $plan187()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan187()->barrierSummary()['status'],
    'barrier errors' => static fn (): mixed => $plan187()->barrierErrors(),
    'next source pages' => static fn (): mixed => $plan187()->nextSourcePages(),
    'fenced tail pages' => static fn (): mixed => $plan187()->fencedTailPages(),
    'scrubbed leaf pages' => static fn (): mixed => $plan187()->scrubbedLeafPages(),
    'terminal overflow pages' => static fn (): mixed => $plan187()->terminalOverflowPages(),
    'summary next source pages' => static fn (): mixed => $plan187()->barrierSummary()['next_source_pages'],
    'summary fenced tail pages' => static fn (): mixed => $plan187()->barrierSummary()['fenced_tail_pages'],
    'summary scrubbed pages' => static fn (): mixed => $plan187()->barrierSummary()['scrubbed_leaf_pages'],
    'summary terminal pages' => static fn (): mixed => $plan187()->barrierSummary()['terminal_overflow_pages'],
    'summary error count' => static fn (): mixed => $plan187()->barrierSummary()['barrier_error_count'],
    'all tail excluded' => static fn (): mixed => $plan187()->barrierSummary()['all_tail_pages_excluded_from_next_source'],
    'all receipts complete' => static fn (): mixed => $plan187()->barrierSummary()['all_materialized_pages_have_receipts'],
    'dependencies' => static fn (): mixed => $plan187()->barrierSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan187()->barrierSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan187()->barrierSummary()['non_overlap'], 'does not repeat next184 cursor'),
    'row pages' => static fn (): mixed => array_column($plan187()->barrierRows(), 'page_number'),
    'publish states' => static fn (): mixed => array_column($plan187()->barrierRows(), 'publish_state'),
    'next source ordinals' => static fn (): mixed => array_column($plan187()->barrierRows(), 'next_source_ordinal'),
    'cursor states' => static fn (): mixed => array_column($plan187()->barrierRows(), 'cursor_state'),
    'snapshot kinds' => static fn (): mixed => array_column($plan187()->barrierRows(), 'snapshot_kind'),
    'freeblock required' => static fn (): mixed => array_column($plan187()->barrierRows(), 'freeblock_scrub_receipt_required'),
    'freeblock carried' => static fn (): mixed => array_column($plan187()->barrierRows(), 'freeblock_scrub_receipt_carried'),
    'terminal required' => static fn (): mixed => array_column($plan187()->barrierRows(), 'terminal_overflow_receipt_required'),
    'terminal zero' => static fn (): mixed => array_column($plan187()->barrierRows(), 'terminal_next_pointer_zero'),
    'terminal carried' => static fn (): mixed => array_column($plan187()->barrierRows(), 'terminal_next_pointer_receipt_carried'),
    'tail required' => static fn (): mixed => array_column($plan187()->barrierRows(), 'tail_fence_required'),
    'tail excluded' => static fn (): mixed => array_column($plan187()->barrierRows(), 'tail_excluded_from_next_source'),
    'tail pointer receipt' => static fn (): mixed => array_column($plan187()->barrierRows(), 'pointer_map_tail_fence_receipt_carried'),
    'receipt complete' => static fn (): mixed => array_column($plan187()->barrierRows(), 'receipt_chain_complete'),
    'source replayable' => static fn (): mixed => array_column($plan187()->barrierRows(), 'source_replayable'),
    'final materialized' => static fn (): mixed => array_column($plan187()->barrierRows(), 'final_materialized'),
    'publish token length' => static fn (): mixed => strlen($plan187()->barrierSummary()['publish_token']),
    'cursor token length' => static fn (): mixed => strlen($plan187()->barrierSummary()['cursor_token']),
    'receipt key length' => static fn (): mixed => strlen($plan187()->barrierRows()[0]['publish_receipt_key']),
    'base action label' => static fn (): mixed => $plan187()->basePlan->toArray()['action'],
    'base materialized pages' => static fn (): mixed => $plan187()->basePlan->materializedSourcePages(),
    'base excluded pages' => static fn (): mixed => $plan187()->basePlan->excludedTruncatedPages(),
    'wide next source pages' => static fn (): mixed => $plan187(null, 6)->nextSourcePages(),
    'wide fenced pages' => static fn (): mixed => $plan187(null, 6)->fencedTailPages(),
    'small replacement rejected' => static fn (): mixed => $message187(static fn () => $plan187(str_repeat('small', 20))),
];

$expected187 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next187',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next187-ready',
    'barrier errors' => [],
    'next source pages' => [3, 106, 107, 108, 109],
    'fenced tail pages' => [110],
    'scrubbed leaf pages' => [3],
    'terminal overflow pages' => [109],
    'summary next source pages' => [3, 106, 107, 108, 109],
    'summary fenced tail pages' => [110],
    'summary scrubbed pages' => [3],
    'summary terminal pages' => [109],
    'summary error count' => 0,
    'all tail excluded' => true,
    'all receipts complete' => true,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next184', 'sqlite-current-source-next187'],
    'dependency closure' => 'no new support component needed; next187 reuses next184 current-source cursor rows, secure-delete freeblock receipts, overflow terminal next-pointer receipts, and pointer-map tail fences',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'publish states' => ['publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'fence-truncated-tail-page'],
    'next source ordinals' => [1, 2, 3, 4, 5, null],
    'cursor states' => ['materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'excluded-truncated-tail'],
    'snapshot kinds' => ['leaf-freeblock-current-source', 'overflow-current-source', 'overflow-current-source', 'overflow-current-source', 'overflow-tail-current-source', 'quarantined-truncated-tail'],
    'freeblock required' => [true, false, false, false, false, false],
    'freeblock carried' => [true, false, false, false, false, false],
    'terminal required' => [false, false, false, false, true, false],
    'terminal zero' => [null, null, null, null, true, null],
    'terminal carried' => [false, false, false, false, true, true],
    'tail required' => [false, false, false, false, false, true],
    'tail excluded' => [true, true, true, true, true, true],
    'tail pointer receipt' => [null, null, null, null, null, true],
    'receipt complete' => [true, true, true, true, true, true],
    'source replayable' => [true, true, true, true, true, false],
    'final materialized' => [true, true, true, true, true, false],
    'publish token length' => 64,
    'cursor token length' => 64,
    'receipt key length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next184',
    'base materialized pages' => [3, 106, 107, 108, 109],
    'base excluded pages' => [110],
    'wide next source pages' => [3, 106, 107, 108, 109],
    'wide fenced pages' => [110],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
];

$tests = [];

foreach ($cases187 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next187 ' . $name] = static function (TestRunner $t) use ($callback, $expected187, $name): void {
        $t->same($expected187[$name], $callback());
    };
}

foreach (range(1, 70) as $index) {
    $tests['btree vacuum pointermap freeblock current source next187 barrier invariant ' . $index] = static function (TestRunner $t) use ($plan187): void {
        $plan = $plan187();
        $rows = $plan->barrierRows();
        $summary = $plan->barrierSummary();

        $t->same([], $plan->barrierErrors());
        $t->same([3, 106, 107, 108, 109], $plan->nextSourcePages());
        $t->same([110], $plan->fencedTailPages());
        $t->same([3], $plan->scrubbedLeafPages());
        $t->same([109], $plan->terminalOverflowPages());
        $t->same([1, 2, 3, 4, 5, null], array_column($rows, 'next_source_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($rows, 'receipt_chain_complete'));
        $t->same([true, true, true, true, true, true], array_column($rows, 'tail_excluded_from_next_source'));
        $t->same(true, $rows[0]['freeblock_scrub_receipt_required']);
        $t->same(true, $rows[0]['freeblock_scrub_receipt_carried']);
        $t->same(true, $rows[4]['terminal_overflow_receipt_required']);
        $t->same(true, $rows[4]['terminal_next_pointer_zero']);
        $t->same(true, $rows[4]['terminal_next_pointer_receipt_carried']);
        $t->same(true, $rows[5]['tail_fence_required']);
        $t->same(true, $rows[5]['pointer_map_tail_fence_receipt_carried']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next187-ready', $summary['status']);
        $t->same(true, $summary['all_tail_pages_excluded_from_next_source']);
        $t->same(true, $summary['all_materialized_pages_have_receipts']);
    };
}

return $tests;
