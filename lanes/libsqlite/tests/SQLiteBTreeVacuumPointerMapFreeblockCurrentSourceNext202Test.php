<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage202 = static function (int $pageCount): string {
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

$putPointerMapEntry202 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database202 = static function () use ($makeFirstPage202, $putPointerMapEntry202): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage202(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next202', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry202($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan202 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database202;

    $database = $database202();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext202(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next202-current-source-cursor-', 50),
        3,
        true,
        $batchSize,
    );
};

$message202 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases202 = [
    'action label' => static fn (): mixed => $plan202()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan202()->cursorSummary()['status'],
    'cursor row count' => static fn (): mixed => $plan202()->cursorSummary()['cursor_row_count'],
    'source writable pages' => static fn (): mixed => $plan202()->sourceWritablePages(),
    'summary source writable pages' => static fn (): mixed => $plan202()->cursorSummary()['source_writable_pages'],
    'base writable pages' => static fn (): mixed => $plan202()->cursorSummary()['base_next_writable_pages'],
    'cursor matches base' => static fn (): mixed => $plan202()->cursorSummary()['cursor_matches_base_writable_pages'],
    'newly visible batches' => static fn (): mixed => $plan202()->cursorSummary()['newly_visible_page_batches'],
    'pointer map guard batches' => static fn (): mixed => $plan202()->cursorSummary()['pointer_map_guard_batches'],
    'payload batches' => static fn (): mixed => $plan202()->cursorSummary()['payload_page_batches'],
    'cursor errors' => static fn (): mixed => $plan202()->cursorErrors(),
    'summary cursor errors' => static fn (): mixed => $plan202()->cursorSummary()['cursor_errors'],
    'all monotonic' => static fn (): mixed => $plan202()->cursorSummary()['all_monotonic'],
    'all pointer maps before payload' => static fn (): mixed => $plan202()->cursorSummary()['all_pointer_maps_before_payload'],
    'all freeblock receipts visible' => static fn (): mixed => $plan202()->cursorSummary()['all_freeblock_receipts_visible'],
    'all fenced tail pages guarded' => static fn (): mixed => $plan202()->cursorSummary()['all_fenced_tail_pages_guarded'],
    'resume token count' => static fn (): mixed => count($plan202()->resumeTokens()),
    'resume token lengths' => static fn (): mixed => array_map('strlen', $plan202()->resumeTokens()),
    'resume signature length' => static fn (): mixed => strlen($plan202()->cursorSummary()['resume_signature']),
    'current token length' => static fn (): mixed => strlen($plan202()->cursorSummary()['current_source_next202_token']),
    'first row writer visible pages' => static fn (): mixed => $plan202()->cursorRows()[0]['writer_visible_pages'],
    'first row newly visible pages' => static fn (): mixed => $plan202()->cursorRows()[0]['newly_visible_pages'],
    'first row pointer map guards' => static fn (): mixed => $plan202()->cursorRows()[0]['pointer_map_guard_pages'],
    'first row payload pages' => static fn (): mixed => $plan202()->cursorRows()[0]['payload_pages'],
    'first row previous token' => static fn (): mixed => $plan202()->cursorRows()[0]['previous_resume_token'],
    'second row writer visible pages' => static fn (): mixed => $plan202()->cursorRows()[1]['writer_visible_pages'],
    'second row newly visible pages' => static fn (): mixed => $plan202()->cursorRows()[1]['newly_visible_pages'],
    'second row pointer map guards' => static fn (): mixed => $plan202()->cursorRows()[1]['pointer_map_guard_pages'],
    'second row payload pages' => static fn (): mixed => $plan202()->cursorRows()[1]['payload_pages'],
    'second row previous token length' => static fn (): mixed => strlen((string) $plan202()->cursorRows()[1]['previous_resume_token']),
    'third row writer visible pages' => static fn (): mixed => $plan202()->cursorRows()[2]['writer_visible_pages'],
    'third row newly visible pages' => static fn (): mixed => $plan202()->cursorRows()[2]['newly_visible_pages'],
    'third row pointer map guards' => static fn (): mixed => $plan202()->cursorRows()[2]['pointer_map_guard_pages'],
    'third row payload pages' => static fn (): mixed => $plan202()->cursorRows()[2]['payload_pages'],
    'third row previous token length' => static fn (): mixed => strlen((string) $plan202()->cursorRows()[2]['previous_resume_token']),
    'row states' => static fn (): mixed => array_column($plan202()->cursorRows(), 'cursor_state'),
    'row monotonic flags' => static fn (): mixed => array_column($plan202()->cursorRows(), 'monotonic_writer_visibility'),
    'row pointer flags' => static fn (): mixed => array_column($plan202()->cursorRows(), 'pointer_map_precedes_payload'),
    'row freeblock flags' => static fn (): mixed => array_column($plan202()->cursorRows(), 'leaf_freeblock_cursor_valid'),
    'row fenced flags' => static fn (): mixed => array_column($plan202()->cursorRows(), 'fenced_tail_pages_guarded'),
    'row handoff flags' => static fn (): mixed => array_column($plan202()->cursorRows(), 'handoff_token_valid'),
    'batch size three row count' => static fn (): mixed => $plan202(3)->cursorSummary()['cursor_row_count'],
    'batch size three writable pages' => static fn (): mixed => $plan202(3)->sourceWritablePages(),
    'batch size three visible batches' => static fn (): mixed => array_column($plan202(3)->cursorRows(), 'writer_visible_pages'),
    'batch size three new batches' => static fn (): mixed => array_column($plan202(3)->cursorRows(), 'newly_visible_pages'),
    'batch size three resume token count' => static fn (): mixed => count($plan202(3)->resumeTokens()),
    'dependency closure' => static fn (): mixed => $plan202()->cursorSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan202()->cursorSummary()['non_overlap'], 'does not repeat next196'),
    'base action' => static fn (): mixed => $plan202()->basePlan->toArray()['action'],
    'base handoff rows' => static fn (): mixed => $plan202()->basePlan->sourceNextSummary()['handoff_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message202(static fn () => $plan202(0)),
];

$expected202 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next202',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next202-ready',
    'cursor row count' => 3,
    'source writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'summary source writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'base writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'cursor matches base' => true,
    'newly visible batches' => [[1, 2, 3], [105, 106], [107, 108]],
    'pointer map guard batches' => [[2], [105], [105]],
    'payload batches' => [[3], [106], [107, 108]],
    'cursor errors' => [],
    'summary cursor errors' => [],
    'all monotonic' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts visible' => true,
    'all fenced tail pages guarded' => true,
    'resume token count' => 3,
    'resume token lengths' => [64, 64, 64],
    'resume signature length' => 64,
    'current token length' => 64,
    'first row writer visible pages' => [1, 2, 3],
    'first row newly visible pages' => [1, 2, 3],
    'first row pointer map guards' => [2],
    'first row payload pages' => [3],
    'first row previous token' => null,
    'second row writer visible pages' => [1, 2, 3, 105, 106],
    'second row newly visible pages' => [105, 106],
    'second row pointer map guards' => [105],
    'second row payload pages' => [106],
    'second row previous token length' => 64,
    'third row writer visible pages' => [1, 2, 3, 105, 106, 107, 108],
    'third row newly visible pages' => [107, 108],
    'third row pointer map guards' => [105],
    'third row payload pages' => [107, 108],
    'third row previous token length' => 64,
    'row states' => ['current-source-cursor-ready', 'current-source-cursor-ready', 'current-source-cursor-ready'],
    'row monotonic flags' => [true, true, true],
    'row pointer flags' => [true, true, true],
    'row freeblock flags' => [true, true, true],
    'row fenced flags' => [true, true, true],
    'row handoff flags' => [true, true, true],
    'batch size three row count' => 2,
    'batch size three writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'batch size three visible batches' => [[1, 2, 3, 105], [1, 2, 3, 105, 106, 107, 108]],
    'batch size three new batches' => [[1, 2, 3, 105], [106, 107, 108]],
    'batch size three resume token count' => 2,
    'dependency closure' => 'no new support component needed; next202 reuses next196 source-next handoff rows, pointer-map carry-forward flags, leaf freeblock receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next196',
    'base handoff rows' => 3,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases202 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next202 ' . $name] = static function (TestRunner $t) use ($callback, $expected202, $name): void {
        $t->same($expected202[$name], $callback());
    };
}

foreach (range(1, 64) as $index) {
    $tests['btree vacuum pointermap freeblock current source next202 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan202): void {
        $plan = $plan202();
        $summary = $plan->cursorSummary();

        $t->same([], $plan->cursorErrors());
        $t->same([1, 2, 3, 105, 106, 107, 108], $plan->sourceWritablePages());
        $t->same([[1, 2, 3], [105, 106], [107, 108]], $summary['newly_visible_page_batches']);
        $t->same([[2], [105], [105]], $summary['pointer_map_guard_batches']);
        $t->same([[3], [106], [107, 108]], $summary['payload_page_batches']);
        $t->same([true, true, true], array_column($plan->cursorRows(), 'monotonic_writer_visibility'));
        $t->same([true, true, true], array_column($plan->cursorRows(), 'pointer_map_precedes_payload'));
        $t->same([true, true, true], array_column($plan->cursorRows(), 'leaf_freeblock_cursor_valid'));
        $t->same([true, true, true], array_column($plan->cursorRows(), 'fenced_tail_pages_guarded'));
        $t->same([64, 64, 64], array_map('strlen', $plan->resumeTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next202-ready', $summary['status']);
    };
}

return $tests;
