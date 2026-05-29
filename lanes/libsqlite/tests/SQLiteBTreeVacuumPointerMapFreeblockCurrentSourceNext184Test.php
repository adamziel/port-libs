<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage184 = static function (int $pageCount): string {
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

$putPointerMapEntry184 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage184 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database184 = static function () use ($makeFirstPage184, $putPointerMapEntry184, $overflowPage184): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage184(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next184', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage184(107, 'A');
    $pages[107] = $overflowPage184(108, 'B');
    $pages[108] = $overflowPage184(109, 'C');
    $pages[109] = $overflowPage184(110, 'D');
    $pages[110] = $overflowPage184(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry184($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan184 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database184;

    $database = $database184();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext184(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next184-current-source-cursor-scrub-receipt-', 44),
        3,
        true,
    );
};

$message184 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases184 = [
    'action label' => static fn (): mixed => $plan184()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan184()->cursorSummary()['status'],
    'cursor errors' => static fn (): mixed => $plan184()->cursorErrors(),
    'materialized pages' => static fn (): mixed => $plan184()->materializedSourcePages(),
    'excluded pages' => static fn (): mixed => $plan184()->excludedTruncatedPages(),
    'freeblock scrub pages' => static fn (): mixed => $plan184()->freeblockScrubPages(),
    'overflow terminal pages' => static fn (): mixed => $plan184()->overflowTerminalPages(),
    'summary materialized pages' => static fn (): mixed => $plan184()->cursorSummary()['materialized_source_pages'],
    'summary excluded pages' => static fn (): mixed => $plan184()->cursorSummary()['excluded_truncated_pages'],
    'summary freeblock pages' => static fn (): mixed => $plan184()->cursorSummary()['freeblock_scrub_pages'],
    'summary terminal pages' => static fn (): mixed => $plan184()->cursorSummary()['overflow_terminal_pages'],
    'summary error count' => static fn (): mixed => $plan184()->cursorSummary()['cursor_error_count'],
    'dependencies' => static fn (): mixed => $plan184()->cursorSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan184()->cursorSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan184()->cursorSummary()['non_overlap'], 'does not repeat next181 snapshot'),
    'row pages' => static fn (): mixed => array_column($plan184()->cursorRows(), 'page_number'),
    'row ordinals' => static fn (): mixed => array_column($plan184()->cursorRows(), 'source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan184()->cursorRows(), 'cursor_state'),
    'snapshot kinds' => static fn (): mixed => array_column($plan184()->cursorRows(), 'snapshot_kind'),
    'quarantine reasons' => static fn (): mixed => array_column($plan184()->cursorRows(), 'quarantine_reason'),
    'leaf scrub flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'leaf_freeblock_scrub_required'),
    'freeblock carried flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'freeblock_receipt_carried'),
    'terminal flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'overflow_terminal_page'),
    'next pointer carried flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'next_pointer_receipt_carried'),
    'pointer map carried flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'pointer_map_receipt_carried'),
    'final materialized flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'final_materialized'),
    'final next pages' => static fn (): mixed => array_column($plan184()->cursorRows(), 'final_next_page'),
    'source replayable flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'source_replayable'),
    'truncated fenced flags' => static fn (): mixed => array_column($plan184()->cursorRows(), 'truncated_tail_fenced'),
    'pointer map types' => static fn (): mixed => array_column($plan184()->cursorRows(), 'final_pointer_map_type'),
    'pointer map parents' => static fn (): mixed => array_column($plan184()->cursorRows(), 'final_pointer_map_parent'),
    'scrub key length' => static fn (): mixed => strlen($plan184()->cursorRows()[0]['scrub_receipt_key']),
    'cursor token length' => static fn (): mixed => strlen($plan184()->cursorSummary()['cursor_token']),
    'scrub token length' => static fn (): mixed => strlen($plan184()->cursorSummary()['scrub_token']),
    'base action label' => static fn (): mixed => $plan184()->basePlan->toArray()['action'],
    'base replayable pages' => static fn (): mixed => $plan184()->basePlan->replayablePages(),
    'base quarantined pages' => static fn (): mixed => $plan184()->basePlan->quarantinedPages(),
    'base pointer map receipt pages' => static fn (): mixed => $plan184()->basePlan->pointerMapReceiptPages(),
    'wide materialized pages' => static fn (): mixed => $plan184(null, 6)->materializedSourcePages(),
    'wide excluded pages' => static fn (): mixed => $plan184(null, 6)->excludedTruncatedPages(),
    'small replacement rejected' => static fn (): mixed => $message184(static fn () => $plan184(str_repeat('small', 20))),
];

$expected184 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next184',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next184-ready',
    'cursor errors' => [],
    'materialized pages' => [3, 106, 107, 108, 109],
    'excluded pages' => [110],
    'freeblock scrub pages' => [3],
    'overflow terminal pages' => [109],
    'summary materialized pages' => [3, 106, 107, 108, 109],
    'summary excluded pages' => [110],
    'summary freeblock pages' => [3],
    'summary terminal pages' => [109],
    'summary error count' => 0,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next181', 'sqlite-current-source-next184'],
    'dependency closure' => 'no new support component needed; next184 reuses next181 snapshot rows, secure-delete leaf freeblock receipts, overflow terminal next-pointer receipts, and auto-vacuum pointer-map metadata',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'row ordinals' => [1, 2, 3, 4, 5, null],
    'row states' => ['materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'materialized-current-source', 'excluded-truncated-tail'],
    'snapshot kinds' => ['leaf-freeblock-current-source', 'overflow-current-source', 'overflow-current-source', 'overflow-current-source', 'overflow-tail-current-source', 'quarantined-truncated-tail'],
    'quarantine reasons' => [null, null, null, null, null, 'truncated-tail-fenced-from-next-reader'],
    'leaf scrub flags' => [true, false, false, false, false, false],
    'freeblock carried flags' => [true, false, false, false, false, false],
    'terminal flags' => [false, false, false, false, true, false],
    'next pointer carried flags' => [false, false, false, false, true, true],
    'pointer map carried flags' => [false, false, false, false, false, true],
    'final materialized flags' => [true, true, true, true, true, false],
    'final next pages' => [null, 107, 108, 109, 0, null],
    'source replayable flags' => [true, true, true, true, true, false],
    'truncated fenced flags' => [false, false, false, false, false, true],
    'pointer map types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'pointer map parents' => [0, 3, 106, 107, 108, null],
    'scrub key length' => 64,
    'cursor token length' => 64,
    'scrub token length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next181',
    'base replayable pages' => [3, 106, 107, 108, 109],
    'base quarantined pages' => [110],
    'base pointer map receipt pages' => [110],
    'wide materialized pages' => [3, 106, 107, 108, 109],
    'wide excluded pages' => [110],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
];

$tests = [];

foreach ($cases184 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next184 ' . $name] = static function (TestRunner $t) use ($callback, $expected184, $name): void {
        $t->same($expected184[$name], $callback());
    };
}

foreach (range(1, 60) as $index) {
    $tests['btree vacuum pointermap freeblock current source next184 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan184): void {
        $plan = $plan184();
        $rows = $plan->cursorRows();
        $summary = $plan->cursorSummary();

        $t->same([], $plan->cursorErrors());
        $t->same([3, 106, 107, 108, 109], $plan->materializedSourcePages());
        $t->same([110], $plan->excludedTruncatedPages());
        $t->same([3], $plan->freeblockScrubPages());
        $t->same([109], $plan->overflowTerminalPages());
        $t->same([1, 2, 3, 4, 5, null], array_column($rows, 'source_ordinal'));
        $t->same([true, true, true, true, true, false], array_column($rows, 'source_replayable'));
        $t->same([false, false, false, false, false, true], array_column($rows, 'truncated_tail_fenced'));
        $t->same(true, $rows[0]['leaf_freeblock_scrub_required']);
        $t->same(true, $rows[0]['freeblock_receipt_carried']);
        $t->same(true, $rows[4]['overflow_terminal_page']);
        $t->same(0, $rows[4]['final_next_page']);
        $t->same(true, $rows[4]['next_pointer_receipt_carried']);
        $t->same(true, $rows[5]['pointer_map_receipt_carried']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next184-ready', $summary['status']);
    };
}

return $tests;
