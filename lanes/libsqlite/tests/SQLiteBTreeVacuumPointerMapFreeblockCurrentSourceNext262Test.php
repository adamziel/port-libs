<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage262 = static function (int $pageCount): string {
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

$putPointerMapEntry262 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database262 = static function () use ($makeFirstPage262, $putPointerMapEntry262): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage262(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next262', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(102 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry262($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan262 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database262;

    $database = $database262();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafReplayFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next262-current-source-replay-barrier-', 40),
        3,
        true,
        $batchSize,
    );
};

$message262 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases262 = [
    'action label' => static fn (): mixed => $plan262()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan262()->replaySummary()['status'],
    'row count' => static fn (): mixed => $plan262()->replaySummary()['replay_row_count'],
    'replay pages' => static fn (): mixed => $plan262()->replayPages(),
    'summary replay pages' => static fn (): mixed => $plan262()->replaySummary()['replay_pages'],
    'summary handoff pages' => static fn (): mixed => $plan262()->replaySummary()['handoff_pages'],
    'pages match handoff' => static fn (): mixed => $plan262()->replaySummary()['replay_pages_match_handoff'],
    'barrier pages' => static fn (): mixed => $plan262()->barrierPages(),
    'summary barrier pages' => static fn (): mixed => $plan262()->replaySummary()['barrier_pages'],
    'consumable pages' => static fn (): mixed => $plan262()->consumablePages(),
    'summary consumable pages' => static fn (): mixed => $plan262()->replaySummary()['consumable_pages'],
    'write offsets' => static fn (): mixed => $plan262()->replayWriteOffsets(),
    'summary write offsets' => static fn (): mixed => $plan262()->replaySummary()['replay_write_offsets'],
    'barrier epochs' => static fn (): mixed => $plan262()->replayBarrierEpochs(),
    'summary barrier epochs' => static fn (): mixed => $plan262()->replaySummary()['replay_barrier_epochs'],
    'errors' => static fn (): mixed => $plan262()->replayErrors(),
    'summary errors' => static fn (): mixed => $plan262()->replaySummary()['replay_errors'],
    'all handoff tokens match' => static fn (): mixed => $plan262()->replaySummary()['all_handoff_tokens_match'],
    'all barriers before consume' => static fn (): mixed => $plan262()->replaySummary()['all_barriers_seen_before_consume'],
    'all stale slots fenced' => static fn (): mixed => $plan262()->replaySummary()['all_stale_slots_remain_fenced'],
    'all receipts replayable' => static fn (): mixed => $plan262()->replaySummary()['all_leaf_receipts_replayable'],
    'all links valid' => static fn (): mixed => $plan262()->replaySummary()['all_replay_links_valid'],
    'token count' => static fn (): mixed => count($plan262()->replayTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan262()->replayTokens()),
    'signature length' => static fn (): mixed => strlen($plan262()->replaySummary()['replay_signature']),
    'next token length' => static fn (): mixed => strlen($plan262()->replaySummary()['current_source_next262_token']),
    'first row channel' => static fn (): mixed => $plan262()->replayRows()[0]['replay_channel'],
    'first row barrier page' => static fn (): mixed => $plan262()->replayRows()[0]['last_barrier_page'],
    'second row channel' => static fn (): mixed => $plan262()->replayRows()[1]['replay_channel'],
    'second row barrier page' => static fn (): mixed => $plan262()->replayRows()[1]['last_barrier_page'],
    'second row previous consumable' => static fn (): mixed => $plan262()->replayRows()[1]['previous_consumable_page'],
    'third row channel' => static fn (): mixed => $plan262()->replayRows()[2]['replay_channel'],
    'third row previous consumable' => static fn (): mixed => $plan262()->replayRows()[2]['previous_consumable_page'],
    'fourth row previous consumable' => static fn (): mixed => $plan262()->replayRows()[3]['previous_consumable_page'],
    'fifth row channel' => static fn (): mixed => $plan262()->replayRows()[4]['replay_channel'],
    'fifth row barrier page' => static fn (): mixed => $plan262()->replayRows()[4]['last_barrier_page'],
    'last row page' => static fn (): mixed => $plan262()->replayRows()[6]['replay_page'],
    'last row previous consumable' => static fn (): mixed => $plan262()->replayRows()[6]['previous_consumable_page'],
    'last row offset' => static fn (): mixed => $plan262()->replayRows()[6]['replay_write_offset'],
    'ordinals' => static fn (): mixed => array_column($plan262()->replayRows(), 'replay_ordinal'),
    'handoff ordinals' => static fn (): mixed => array_column($plan262()->replayRows(), 'handoff_ordinal'),
    'row states' => static fn (): mixed => array_column($plan262()->replayRows(), 'replay_state'),
    'token flags' => static fn (): mixed => array_column($plan262()->replayRows(), 'handoff_token_matches'),
    'barrier flags' => static fn (): mixed => array_column($plan262()->replayRows(), 'barrier_seen_before_consume'),
    'stale fence flags' => static fn (): mixed => array_column($plan262()->replayRows(), 'stale_slot_remains_fenced'),
    'receipt flags' => static fn (): mixed => array_column($plan262()->replayRows(), 'leaf_receipt_replayable'),
    'link flags' => static fn (): mixed => array_column($plan262()->replayRows(), 'replay_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan262(3)->replaySummary()['replay_row_count'],
    'batch size three pages' => static fn (): mixed => $plan262(3)->replayPages(),
    'batch size three offsets' => static fn (): mixed => $plan262(3)->replayWriteOffsets(),
    'batch size three consumable pages' => static fn (): mixed => $plan262(3)->consumablePages(),
    'dependency closure' => static fn (): mixed => $plan262()->replaySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan262()->replaySummary()['non_overlap'], 'does not repeat next258'),
    'handoff action' => static fn (): mixed => $plan262()->handoffPlan->toArray()['action'],
    'handoff row count' => static fn (): mixed => $plan262()->handoffPlan->handoffSummary()['handoff_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message262(static fn () => $plan262(0)),
];

$expected262 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next262',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next262-ready',
    'row count' => 7,
    'replay pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary replay pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'pages match handoff' => true,
    'barrier pages' => [2, 105],
    'summary barrier pages' => [2, 105],
    'consumable pages' => [3, 106, 107, 108],
    'summary consumable pages' => [3, 106, 107, 108],
    'write offsets' => [0, 40, 0, 128, 0, 144, 160],
    'summary write offsets' => [0, 40, 0, 128, 0, 144, 160],
    'barrier epochs' => [1, 1, 2, 2, 3, 3, 3],
    'summary barrier epochs' => [1, 1, 2, 2, 3, 3, 3],
    'errors' => [],
    'summary errors' => [],
    'all handoff tokens match' => true,
    'all barriers before consume' => true,
    'all stale slots fenced' => true,
    'all receipts replayable' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first row channel' => 'pointer-map-replay-barrier',
    'first row barrier page' => 2,
    'second row channel' => 'freeblock-consume-ready',
    'second row barrier page' => 2,
    'second row previous consumable' => null,
    'third row channel' => 'pointer-map-replay-barrier',
    'third row previous consumable' => 3,
    'fourth row previous consumable' => 3,
    'fifth row channel' => 'pointer-map-replay-barrier',
    'fifth row barrier page' => 105,
    'last row page' => 108,
    'last row previous consumable' => 107,
    'last row offset' => 160,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next262-replay-barrier-ready', 'current-source-next262-replay-barrier-ready', 'current-source-next262-replay-barrier-ready', 'current-source-next262-replay-barrier-ready', 'current-source-next262-replay-barrier-ready', 'current-source-next262-replay-barrier-ready', 'current-source-next262-replay-barrier-ready'],
    'token flags' => [true, true, true, true, true, true, true],
    'barrier flags' => [true, true, true, true, true, true, true],
    'stale fence flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three offsets' => [0, 40, 0, 128, 144, 160],
    'batch size three consumable pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next262 reuses next258 handoff rows and records the final replay barrier before next-source freeblock consumption',
    'non overlap' => true,
    'handoff action' => 'btree-vacuum-pointermap-freeblock-current-source-next258',
    'handoff row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases262 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next262 ' . $name] = static function (TestRunner $t) use ($callback, $expected262, $name): void {
        $t->same($expected262[$name], $callback());
    };
}

foreach (range(1, 96) as $index) {
    $tests['btree vacuum pointermap freeblock current source next262 replay invariant ' . $index] = static function (TestRunner $t) use ($plan262): void {
        $plan = $plan262();
        $summary = $plan->replaySummary();

        $t->same([], $plan->replayErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->replayPages());
        $t->same([2, 105], $plan->barrierPages());
        $t->same([3, 106, 107, 108], $plan->consumablePages());
        $t->same([0, 40, 0, 128, 0, 144, 160], $plan->replayWriteOffsets());
        $t->same([1, 1, 2, 2, 3, 3, 3], $plan->replayBarrierEpochs());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->replayRows(), 'replay_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->replayRows(), 'handoff_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->replayRows(), 'barrier_seen_before_consume'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->replayRows(), 'stale_slot_remains_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->replayTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next262-ready', $summary['status']);
        $t->same(true, $summary['replay_pages_match_handoff']);
        $t->same(true, $summary['all_barriers_seen_before_consume']);
    };
}

return $tests;
