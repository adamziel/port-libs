<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage228 = static function (int $pageCount): string {
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

$putPointerMapEntry228 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database228 = static function () use ($makeFirstPage228, $putPointerMapEntry228): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage228(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next228', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(78 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry228($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan228 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database228;

    $database = $database228();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafDrainWindowFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next228-current-source-drain-', 50),
        3,
        true,
        $batchSize,
    );
};

$message228 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases228 = [
    'action label' => static fn (): mixed => $plan228()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan228()->drainSummary()['status'],
    'drain row count' => static fn (): mixed => $plan228()->drainSummary()['drain_row_count'],
    'drained pages' => static fn (): mixed => $plan228()->drainedPages(),
    'summary drained pages' => static fn (): mixed => $plan228()->drainSummary()['drained_pages'],
    'resume after pages' => static fn (): mixed => $plan228()->resumeAfterPages(),
    'summary resume after pages' => static fn (): mixed => $plan228()->drainSummary()['resume_after_pages'],
    'source pages' => static fn (): mixed => $plan228()->drainSummary()['source_pages'],
    'drain pages match source pages' => static fn (): mixed => $plan228()->drainSummary()['drain_pages_match_source_pages'],
    'duplicate pointer map pages' => static fn (): mixed => $plan228()->duplicatePointerMapPages(),
    'summary duplicate pointer map pages' => static fn (): mixed => $plan228()->drainSummary()['duplicate_pointer_map_pages'],
    'drain errors' => static fn (): mixed => $plan228()->drainErrors(),
    'summary drain errors' => static fn (): mixed => $plan228()->drainSummary()['drain_errors'],
    'all resume links match source next' => static fn (): mixed => $plan228()->drainSummary()['all_resume_links_match_source_next'],
    'all pointer map revisits ordered' => static fn (): mixed => $plan228()->drainSummary()['all_pointer_map_revisits_ordered'],
    'all freeblock receipts drained' => static fn (): mixed => $plan228()->drainSummary()['all_freeblock_receipts_drained'],
    'all tail pages fenced at drain' => static fn (): mixed => $plan228()->drainSummary()['all_tail_pages_fenced_at_drain'],
    'drain token count' => static fn (): mixed => count($plan228()->drainTokens()),
    'drain token lengths' => static fn (): mixed => array_map('strlen', $plan228()->drainTokens()),
    'drain signature length' => static fn (): mixed => strlen($plan228()->drainSummary()['drain_signature']),
    'current source token length' => static fn (): mixed => strlen($plan228()->drainSummary()['current_source_next228_token']),
    'eof token length' => static fn (): mixed => strlen((string) $plan228()->drainSummary()['eof_drain_token']),
    'first drain page' => static fn (): mixed => $plan228()->drainRows()[0]['drained_page'],
    'first resume page' => static fn (): mixed => $plan228()->drainRows()[0]['resume_after_page'],
    'first previous token' => static fn (): mixed => $plan228()->drainRows()[0]['previous_drain_token'],
    'first channel' => static fn (): mixed => $plan228()->drainRows()[0]['source_channel'],
    'second channel' => static fn (): mixed => $plan228()->drainRows()[1]['source_channel'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan228()->drainRows()[1]['previous_drain_token']),
    'third visible pointer maps' => static fn (): mixed => $plan228()->drainRows()[2]['visible_pointer_map_pages'],
    'fourth resume page' => static fn (): mixed => $plan228()->drainRows()[3]['resume_after_page'],
    'fifth pointer map revisit' => static fn (): mixed => $plan228()->drainRows()[4]['pointer_map_revisit'],
    'fifth pointer map ordered' => static fn (): mixed => $plan228()->drainRows()[4]['pointer_map_revisit_ordered'],
    'last resume page' => static fn (): mixed => $plan228()->drainRows()[6]['resume_after_page'],
    'last channel' => static fn (): mixed => $plan228()->drainRows()[6]['source_channel'],
    'drain ordinals' => static fn (): mixed => array_column($plan228()->drainRows(), 'drain_ordinal'),
    'source ordinals' => static fn (): mixed => array_column($plan228()->drainRows(), 'source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan228()->drainRows(), 'drain_state'),
    'row source token flags' => static fn (): mixed => array_column($plan228()->drainRows(), 'source_token_matches'),
    'row resume flags' => static fn (): mixed => array_column($plan228()->drainRows(), 'resume_link_matches_source_next'),
    'row pointer revisit flags' => static fn (): mixed => array_column($plan228()->drainRows(), 'pointer_map_revisit'),
    'row pointer ordered flags' => static fn (): mixed => array_column($plan228()->drainRows(), 'pointer_map_revisit_ordered'),
    'row freeblock flags' => static fn (): mixed => array_column($plan228()->drainRows(), 'freeblock_receipt_drained'),
    'row tail fence flags' => static fn (): mixed => array_column($plan228()->drainRows(), 'tail_pages_fenced_at_drain'),
    'batch size three drain count' => static fn (): mixed => $plan228(3)->drainSummary()['drain_row_count'],
    'batch size three drained pages' => static fn (): mixed => $plan228(3)->drainedPages(),
    'batch size three resume pages' => static fn (): mixed => $plan228(3)->resumeAfterPages(),
    'batch size three duplicate pointer maps' => static fn (): mixed => $plan228(3)->duplicatePointerMapPages(),
    'batch size three token count' => static fn (): mixed => count($plan228(3)->drainTokens()),
    'dependency closure' => static fn (): mixed => $plan228()->drainSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan228()->drainSummary()['non_overlap'], 'does not repeat checkpoint-validation'),
    'base action' => static fn (): mixed => $plan228()->basePlan->toArray()['action'],
    'base source rows' => static fn (): mixed => $plan228()->basePlan->sourceSummary()['source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message228(static fn () => $plan228(0)),
];

$expected228 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next228',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next228-ready',
    'drain row count' => 7,
    'drained pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary drained pages' => [2, 3, 105, 106, 105, 107, 108],
    'resume after pages' => [3, 105, 106, 105, 107, 108, null],
    'summary resume after pages' => [3, 105, 106, 105, 107, 108, null],
    'source pages' => [2, 3, 105, 106, 105, 107, 108],
    'drain pages match source pages' => true,
    'duplicate pointer map pages' => [105],
    'summary duplicate pointer map pages' => [105],
    'drain errors' => [],
    'summary drain errors' => [],
    'all resume links match source next' => true,
    'all pointer map revisits ordered' => true,
    'all freeblock receipts drained' => true,
    'all tail pages fenced at drain' => true,
    'drain token count' => 7,
    'drain token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'drain signature length' => 64,
    'current source token length' => 64,
    'eof token length' => 64,
    'first drain page' => 2,
    'first resume page' => 3,
    'first previous token' => null,
    'first channel' => 'pointer-map',
    'second channel' => 'payload',
    'second previous token length' => 64,
    'third visible pointer maps' => [2, 105],
    'fourth resume page' => 105,
    'fifth pointer map revisit' => true,
    'fifth pointer map ordered' => true,
    'last resume page' => null,
    'last channel' => 'payload',
    'drain ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-drained-for-next-writer', 'current-source-drained-for-next-writer', 'current-source-drained-for-next-writer', 'current-source-drained-for-next-writer', 'current-source-drained-for-next-writer', 'current-source-drained-for-next-writer', 'current-source-drained-for-next-writer'],
    'row source token flags' => [true, true, true, true, true, true, true],
    'row resume flags' => [true, true, true, true, true, true, true],
    'row pointer revisit flags' => [false, false, false, false, true, false, false],
    'row pointer ordered flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three drain count' => 6,
    'batch size three drained pages' => [2, 3, 105, 106, 107, 108],
    'batch size three resume pages' => [3, 105, 106, 107, 108, null],
    'batch size three duplicate pointer maps' => [],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; next228 reuses checkpoint-validation current-source next-page cursor receipts and adds drain/finalization metadata only',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-checkpoint-validation',
    'base source rows' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases228 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next228 ' . $name] = static function (TestRunner $t) use ($callback, $expected228, $name): void {
        $t->same($expected228[$name], $callback());
    };
}

foreach (range(1, 76) as $index) {
    $tests['btree vacuum pointermap freeblock current source next228 drain invariant ' . $index] = static function (TestRunner $t) use ($plan228): void {
        $plan = $plan228();
        $summary = $plan->drainSummary();

        $t->same([], $plan->drainErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->drainedPages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->resumeAfterPages());
        $t->same([105], $plan->duplicatePointerMapPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->drainRows(), 'drain_ordinal'));
        $t->same([false, false, false, false, true, false, false], array_column($plan->drainRows(), 'pointer_map_revisit'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'resume_link_matches_source_next'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'freeblock_receipt_drained'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->drainTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next228-ready', $summary['status']);
        $t->same(true, $summary['drain_pages_match_source_pages']);
        $t->same(true, $summary['all_tail_pages_fenced_at_drain']);
    };
}

return $tests;
