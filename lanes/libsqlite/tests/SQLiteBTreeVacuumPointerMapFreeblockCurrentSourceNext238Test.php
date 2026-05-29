<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage238 = static function (int $pageCount): string {
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

$putPointerMapEntry238 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database238 = static function () use ($makeFirstPage238, $putPointerMapEntry238): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage238(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next238', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(87 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry238($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan238 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database238;

    $database = $database238();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext238(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next238-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message238 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases238 = [
    'action label' => static fn (): mixed => $plan238()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan238()->freelistSummary()['status'],
    'freelist row count' => static fn (): mixed => $plan238()->freelistSummary()['freelist_row_count'],
    'freelist pages' => static fn (): mixed => $plan238()->freelistPages(),
    'summary freelist pages' => static fn (): mixed => $plan238()->freelistSummary()['freelist_pages'],
    'summary checkpoint pages' => static fn (): mixed => $plan238()->freelistSummary()['checkpoint_pages'],
    'freelist pages match checkpoint pages' => static fn (): mixed => $plan238()->freelistSummary()['freelist_pages_match_checkpoint_pages'],
    'pointer map barrier pages' => static fn (): mixed => $plan238()->pointerMapBarrierPages(),
    'summary pointer map barrier pages' => static fn (): mixed => $plan238()->freelistSummary()['pointer_map_barrier_pages'],
    'reusable payload pages' => static fn (): mixed => $plan238()->reusablePayloadPages(),
    'summary reusable payload pages' => static fn (): mixed => $plan238()->freelistSummary()['reusable_payload_pages'],
    'freelist trunk candidates' => static fn (): mixed => $plan238()->freelistTrunkCandidatePages(),
    'summary freelist trunk candidates' => static fn (): mixed => $plan238()->freelistSummary()['freelist_trunk_candidate_pages'],
    'freelist errors' => static fn (): mixed => $plan238()->freelistErrors(),
    'summary freelist errors' => static fn (): mixed => $plan238()->freelistSummary()['freelist_errors'],
    'all checkpoint tokens match' => static fn (): mixed => $plan238()->freelistSummary()['all_checkpoint_tokens_match'],
    'all pointer barriers seen before reuse' => static fn (): mixed => $plan238()->freelistSummary()['all_pointer_map_barriers_seen_before_reuse'],
    'all freeblock receipts admitted' => static fn (): mixed => $plan238()->freelistSummary()['all_freeblock_receipts_admitted_to_freelist'],
    'all payload pages linked monotonically' => static fn (): mixed => $plan238()->freelistSummary()['all_reusable_payload_pages_linked_monotonically'],
    'all duplicate pointer maps preserve generation' => static fn (): mixed => $plan238()->freelistSummary()['all_duplicate_pointer_maps_preserve_generation'],
    'all tail pages blocked' => static fn (): mixed => $plan238()->freelistSummary()['all_tail_pages_blocked_from_freelist'],
    'freelist token count' => static fn (): mixed => count($plan238()->freelistTokens()),
    'freelist token lengths' => static fn (): mixed => array_map('strlen', $plan238()->freelistTokens()),
    'freelist signature length' => static fn (): mixed => strlen($plan238()->freelistSummary()['freelist_signature']),
    'current source token length' => static fn (): mixed => strlen($plan238()->freelistSummary()['current_source_next238_token']),
    'first row channel' => static fn (): mixed => $plan238()->freelistRows()[0]['freelist_channel'],
    'first row page' => static fn (): mixed => $plan238()->freelistRows()[0]['freelist_page'],
    'first visible pointer barriers' => static fn (): mixed => $plan238()->freelistRows()[0]['visible_pointer_map_barrier_pages'],
    'first admitted payload pages' => static fn (): mixed => $plan238()->freelistRows()[0]['admitted_reusable_payload_pages'],
    'second row channel' => static fn (): mixed => $plan238()->freelistRows()[1]['freelist_channel'],
    'second admitted payload pages' => static fn (): mixed => $plan238()->freelistRows()[1]['admitted_reusable_payload_pages'],
    'second trunk candidate' => static fn (): mixed => $plan238()->freelistRows()[1]['freelist_trunk_candidate'],
    'fifth visible pointer barriers' => static fn (): mixed => $plan238()->freelistRows()[4]['visible_pointer_map_barrier_pages'],
    'fifth duplicate generation flag' => static fn (): mixed => $plan238()->freelistRows()[4]['duplicate_pointer_map_preserves_generation'],
    'last admitted payload pages' => static fn (): mixed => $plan238()->freelistRows()[6]['admitted_reusable_payload_pages'],
    'last row page' => static fn (): mixed => $plan238()->freelistRows()[6]['freelist_page'],
    'freelist ordinals' => static fn (): mixed => array_column($plan238()->freelistRows(), 'freelist_ordinal'),
    'checkpoint ordinals' => static fn (): mixed => array_column($plan238()->freelistRows(), 'checkpoint_ordinal'),
    'row states' => static fn (): mixed => array_column($plan238()->freelistRows(), 'freelist_state'),
    'row checkpoint token flags' => static fn (): mixed => array_column($plan238()->freelistRows(), 'checkpoint_token_matches'),
    'row barrier flags' => static fn (): mixed => array_column($plan238()->freelistRows(), 'pointer_map_barrier_seen_before_reuse'),
    'row freeblock flags' => static fn (): mixed => array_column($plan238()->freelistRows(), 'freeblock_receipt_admitted_to_freelist'),
    'row monotonic flags' => static fn (): mixed => array_column($plan238()->freelistRows(), 'reusable_payload_page_linked_monotonically'),
    'row duplicate flags' => static fn (): mixed => array_column($plan238()->freelistRows(), 'duplicate_pointer_map_preserves_generation'),
    'row tail block flags' => static fn (): mixed => array_column($plan238()->freelistRows(), 'tail_page_blocked_from_freelist'),
    'batch size three row count' => static fn (): mixed => $plan238(3)->freelistSummary()['freelist_row_count'],
    'batch size three pages' => static fn (): mixed => $plan238(3)->freelistPages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan238(3)->reusablePayloadPages(),
    'batch size three pointer barriers' => static fn (): mixed => $plan238(3)->pointerMapBarrierPages(),
    'dependency closure' => static fn (): mixed => $plan238()->freelistSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan238()->freelistSummary()['non_overlap'], 'does not repeat next235'),
    'checkpoint action' => static fn (): mixed => $plan238()->checkpointPlan->toArray()['action'],
    'checkpoint row count' => static fn (): mixed => $plan238()->checkpointPlan->checkpointSummary()['checkpoint_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message238(static fn () => $plan238(0)),
];

$expected238 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next238',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next238-ready',
    'freelist row count' => 7,
    'freelist pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary freelist pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'freelist pages match checkpoint pages' => true,
    'pointer map barrier pages' => [2, 105],
    'summary pointer map barrier pages' => [2, 105],
    'reusable payload pages' => [3, 106, 107, 108],
    'summary reusable payload pages' => [3, 106, 107, 108],
    'freelist trunk candidates' => [3],
    'summary freelist trunk candidates' => [3],
    'freelist errors' => [],
    'summary freelist errors' => [],
    'all checkpoint tokens match' => true,
    'all pointer barriers seen before reuse' => true,
    'all freeblock receipts admitted' => true,
    'all payload pages linked monotonically' => true,
    'all duplicate pointer maps preserve generation' => true,
    'all tail pages blocked' => true,
    'freelist token count' => 7,
    'freelist token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'freelist signature length' => 64,
    'current source token length' => 64,
    'first row channel' => 'pointer-map',
    'first row page' => 2,
    'first visible pointer barriers' => [2],
    'first admitted payload pages' => [],
    'second row channel' => 'payload',
    'second admitted payload pages' => [3],
    'second trunk candidate' => true,
    'fifth visible pointer barriers' => [2, 105],
    'fifth duplicate generation flag' => true,
    'last admitted payload pages' => [3, 106, 107, 108],
    'last row page' => 108,
    'freelist ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'checkpoint ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next238-freelist-link-admitted', 'current-source-next238-freelist-link-admitted', 'current-source-next238-freelist-link-admitted', 'current-source-next238-freelist-link-admitted', 'current-source-next238-freelist-link-admitted', 'current-source-next238-freelist-link-admitted', 'current-source-next238-freelist-link-admitted'],
    'row checkpoint token flags' => [true, true, true, true, true, true, true],
    'row barrier flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row monotonic flags' => [true, true, true, true, true, true, true],
    'row duplicate flags' => [true, true, true, true, true, true, true],
    'row tail block flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'batch size three pointer barriers' => [2, 105],
    'dependency closure' => 'no new support component needed; next238 reuses next235 checkpoint rows and adds freelist-link admission after pointer-map/freeblock visibility',
    'non overlap' => true,
    'checkpoint action' => 'btree-vacuum-pointermap-freeblock-current-source-next235',
    'checkpoint row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases238 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next238 ' . $name] = static function (TestRunner $t) use ($callback, $expected238, $name): void {
        $t->same($expected238[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next238 freelist invariant ' . $index] = static function (TestRunner $t) use ($plan238): void {
        $plan = $plan238();
        $summary = $plan->freelistSummary();

        $t->same([], $plan->freelistErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->freelistPages());
        $t->same([2, 105], $plan->pointerMapBarrierPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([3], $plan->freelistTrunkCandidatePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->freelistRows(), 'freelist_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->freelistRows(), 'checkpoint_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->freelistRows(), 'pointer_map_barrier_seen_before_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->freelistRows(), 'freeblock_receipt_admitted_to_freelist'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->freelistRows(), 'reusable_payload_page_linked_monotonically'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->freelistRows(), 'tail_page_blocked_from_freelist'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->freelistTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next238-ready', $summary['status']);
        $t->same(true, $summary['freelist_pages_match_checkpoint_pages']);
        $t->same(true, $summary['all_pointer_map_barriers_seen_before_reuse']);
    };
}

return $tests;
