<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage203 = static function (int $pageCount): string {
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

$putPointerMapEntry203 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database203 = static function () use ($makeFirstPage203, $putPointerMapEntry203): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage203(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next203', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(75 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry203($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan203 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database203;

    $database = $database203();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext203(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next203-current-source-cursor-', 50),
        3,
        true,
        $batchSize,
    );
};

$message203 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases203 = [
    'action label' => static fn (): mixed => $plan203()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['status'],
    'cursor row count' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['cursor_row_count'],
    'current source pages' => static fn (): mixed => $plan203()->currentSourcePages(),
    'summary current source pages' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['current_source_pages'],
    'next writable pages' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['next_writable_pages'],
    'pointer map cursor pages' => static fn (): mixed => $plan203()->pointerMapCursorPages(),
    'payload cursor pages' => static fn (): mixed => $plan203()->payloadCursorPages(),
    'cursor errors' => static fn (): mixed => $plan203()->cursorErrors(),
    'summary cursor errors' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['cursor_errors'],
    'all source next tokens match' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['all_source_next_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['all_pointer_maps_before_payload'],
    'all freeblock cursors ready' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['all_freeblock_cursors_ready'],
    'all fenced tail pages absent' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['all_fenced_tail_pages_absent'],
    'all cursor chains valid' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['all_cursor_chains_valid'],
    'cursor token count' => static fn (): mixed => count($plan203()->cursorTokens()),
    'cursor token lengths' => static fn (): mixed => array_map('strlen', $plan203()->cursorTokens()),
    'cursor signature length' => static fn (): mixed => strlen($plan203()->currentSourceCursorSummary()['cursor_signature']),
    'next writer cursor token length' => static fn (): mixed => strlen($plan203()->currentSourceCursorSummary()['next_writer_cursor_token']),
    'first row pages' => static fn (): mixed => $plan203()->cursorRows()[0]['current_source_pages'],
    'first row pointer maps' => static fn (): mixed => $plan203()->cursorRows()[0]['pointer_map_cursor_pages'],
    'first row payload' => static fn (): mixed => $plan203()->cursorRows()[0]['payload_cursor_pages'],
    'first row previous token' => static fn (): mixed => $plan203()->cursorRows()[0]['previous_cursor_token'],
    'second row pages' => static fn (): mixed => $plan203()->cursorRows()[1]['current_source_pages'],
    'second row pointer maps' => static fn (): mixed => $plan203()->cursorRows()[1]['pointer_map_cursor_pages'],
    'second row payload' => static fn (): mixed => $plan203()->cursorRows()[1]['payload_cursor_pages'],
    'second row previous token length' => static fn (): mixed => strlen((string) $plan203()->cursorRows()[1]['previous_cursor_token']),
    'third row pages' => static fn (): mixed => $plan203()->cursorRows()[2]['current_source_pages'],
    'third row pointer maps' => static fn (): mixed => $plan203()->cursorRows()[2]['pointer_map_cursor_pages'],
    'third row payload' => static fn (): mixed => $plan203()->cursorRows()[2]['payload_cursor_pages'],
    'third row previous token length' => static fn (): mixed => strlen((string) $plan203()->cursorRows()[2]['previous_cursor_token']),
    'row states' => static fn (): mixed => array_column($plan203()->cursorRows(), 'cursor_state'),
    'row source token flags' => static fn (): mixed => array_column($plan203()->cursorRows(), 'source_next_token_matches'),
    'row pointer order flags' => static fn (): mixed => array_column($plan203()->cursorRows(), 'pointer_maps_before_payload'),
    'row freeblock flags' => static fn (): mixed => array_column($plan203()->cursorRows(), 'leaf_freeblock_cursor_ready'),
    'row fenced flags' => static fn (): mixed => array_column($plan203()->cursorRows(), 'fenced_tail_pages_absent'),
    'row chain flags' => static fn (): mixed => array_column($plan203()->cursorRows(), 'cursor_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan203()->cursorRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan203(3)->currentSourceCursorSummary()['cursor_row_count'],
    'batch size three current source pages' => static fn (): mixed => $plan203(3)->currentSourcePages(),
    'batch size three cursor batches' => static fn (): mixed => array_column($plan203(3)->cursorRows(), 'current_source_pages'),
    'batch size three cursor token count' => static fn (): mixed => count($plan203(3)->cursorTokens()),
    'dependency closure' => static fn (): mixed => $plan203()->currentSourceCursorSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan203()->currentSourceCursorSummary()['non_overlap'], 'does not repeat next196'),
    'base action' => static fn (): mixed => $plan203()->basePlan->toArray()['action'],
    'base handoff rows' => static fn (): mixed => $plan203()->basePlan->sourceNextSummary()['handoff_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message203(static fn () => $plan203(0)),
];

$expected203 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next203',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next203-ready',
    'cursor row count' => 3,
    'current source pages' => [2, 3, 105, 106, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 107, 108],
    'next writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'pointer map cursor pages' => [2, 105],
    'payload cursor pages' => [3, 106, 107, 108],
    'cursor errors' => [],
    'summary cursor errors' => [],
    'all source next tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock cursors ready' => true,
    'all fenced tail pages absent' => true,
    'all cursor chains valid' => true,
    'cursor token count' => 3,
    'cursor token lengths' => [64, 64, 64],
    'cursor signature length' => 64,
    'next writer cursor token length' => 64,
    'first row pages' => [2, 3],
    'first row pointer maps' => [2],
    'first row payload' => [3],
    'first row previous token' => null,
    'second row pages' => [105, 106],
    'second row pointer maps' => [105],
    'second row payload' => [106],
    'second row previous token length' => 64,
    'third row pages' => [105, 107, 108],
    'third row pointer maps' => [105],
    'third row payload' => [107, 108],
    'third row previous token length' => 64,
    'row states' => ['current-source-cursor-ready', 'current-source-cursor-ready', 'current-source-cursor-ready'],
    'row source token flags' => [true, true, true],
    'row pointer order flags' => [true, true, true],
    'row freeblock flags' => [true, true, true],
    'row fenced flags' => [true, true, true],
    'row chain flags' => [true, true, true],
    'row high water pages' => [3, 106, 108],
    'batch size three row count' => 2,
    'batch size three current source pages' => [2, 3, 105, 106, 107, 108],
    'batch size three cursor batches' => [[2, 3], [105, 106, 107, 108]],
    'batch size three cursor token count' => 2,
    'dependency closure' => 'no new support component needed; next203 reuses next196 source-next handoff tokens, pointer-map pages, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next196',
    'base handoff rows' => 3,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases203 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next203 ' . $name] = static function (TestRunner $t) use ($callback, $expected203, $name): void {
        $t->same($expected203[$name], $callback());
    };
}

foreach (range(1, 66) as $index) {
    $tests['btree vacuum pointermap freeblock current source next203 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan203): void {
        $plan = $plan203();
        $summary = $plan->currentSourceCursorSummary();

        $t->same([], $plan->cursorErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->currentSourcePages());
        $t->same([2, 105], $plan->pointerMapCursorPages());
        $t->same([3, 106, 107, 108], $plan->payloadCursorPages());
        $t->same([true, true, true], array_column($plan->cursorRows(), 'source_next_token_matches'));
        $t->same([true, true, true], array_column($plan->cursorRows(), 'pointer_maps_before_payload'));
        $t->same([true, true, true], array_column($plan->cursorRows(), 'leaf_freeblock_cursor_ready'));
        $t->same([true, true, true], array_column($plan->cursorRows(), 'fenced_tail_pages_absent'));
        $t->same([64, 64, 64], array_map('strlen', $plan->cursorTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next203-ready', $summary['status']);
    };
}

return $tests;
