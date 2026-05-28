<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage263 = static function (int $pageCount): string {
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

$putPointerMapEntry263 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database263 = static function () use ($makeFirstPage263, $putPointerMapEntry263): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage263(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next263', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(106 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry263($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan263 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Plan {
    global $database263;

    $database = $database263();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext263Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next263-current-source-freelist-splice-', 34),
        3,
        true,
        $batchSize,
    );
};

$message263 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases263 = [
    'action label' => static fn (): mixed => $plan263()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan263()->freelistSummary()['status'],
    'row count' => static fn (): mixed => $plan263()->freelistSummary()['freelist_row_count'],
    'freelist pages' => static fn (): mixed => $plan263()->freelistPages(),
    'summary freelist pages' => static fn (): mixed => $plan263()->freelistSummary()['freelist_pages'],
    'trunk anchor pages' => static fn (): mixed => $plan263()->trunkAnchorPages(),
    'summary trunk anchor pages' => static fn (): mixed => $plan263()->freelistSummary()['trunk_anchor_pages'],
    'leaf slot pages' => static fn (): mixed => $plan263()->leafSlotPages(),
    'summary leaf slot pages' => static fn (): mixed => $plan263()->freelistSummary()['leaf_slot_pages'],
    'leaf slots by trunk' => static fn (): mixed => $plan263()->leafSlotsByTrunk(),
    'summary leaf slots by trunk' => static fn (): mixed => $plan263()->freelistSummary()['leaf_slots_by_trunk'],
    'leaf slot ordinals' => static fn (): mixed => $plan263()->leafSlotOrdinals(),
    'summary leaf slot ordinals' => static fn (): mixed => $plan263()->freelistSummary()['leaf_slot_ordinals'],
    'freelist write offsets' => static fn (): mixed => $plan263()->freelistWriteOffsets(),
    'summary freelist write offsets' => static fn (): mixed => $plan263()->freelistSummary()['freelist_write_offsets'],
    'vacuum finalized pages' => static fn (): mixed => $plan263()->freelistSummary()['vacuum_finalized_pages'],
    'leaf pages match vacuum' => static fn (): mixed => $plan263()->freelistSummary()['freelist_leaf_pages_match_vacuum'],
    'errors' => static fn (): mixed => $plan263()->freelistErrors(),
    'summary errors' => static fn (): mixed => $plan263()->freelistSummary()['freelist_errors'],
    'all vacuum tokens preserved' => static fn (): mixed => $plan263()->freelistSummary()['all_vacuum_tokens_preserved'],
    'all trunks before leaves' => static fn (): mixed => $plan263()->freelistSummary()['all_trunks_seen_before_leaf_slots'],
    'all leaf slots ordered' => static fn (): mixed => $plan263()->freelistSummary()['all_leaf_slots_ordered'],
    'all offsets match' => static fn (): mixed => $plan263()->freelistSummary()['all_offsets_match_vacuum_finalization'],
    'all tail pages rejected' => static fn (): mixed => $plan263()->freelistSummary()['all_tail_pages_rejected_from_freelist'],
    'all links valid' => static fn (): mixed => $plan263()->freelistSummary()['all_freelist_links_valid'],
    'token count' => static fn (): mixed => count($plan263()->freelistTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan263()->freelistTokens()),
    'signature length' => static fn (): mixed => strlen($plan263()->freelistSummary()['freelist_signature']),
    'current token length' => static fn (): mixed => strlen($plan263()->freelistSummary()['current_source_next263_token']),
    'first row channel' => static fn (): mixed => $plan263()->freelistRows()[0]['freelist_channel'],
    'first active trunk' => static fn (): mixed => $plan263()->freelistRows()[0]['active_trunk_page'],
    'second row channel' => static fn (): mixed => $plan263()->freelistRows()[1]['freelist_channel'],
    'second active trunk' => static fn (): mixed => $plan263()->freelistRows()[1]['active_trunk_page'],
    'second slot ordinal' => static fn (): mixed => $plan263()->freelistRows()[1]['trunk_slot_ordinal'],
    'third row channel' => static fn (): mixed => $plan263()->freelistRows()[2]['freelist_channel'],
    'fourth active trunk' => static fn (): mixed => $plan263()->freelistRows()[3]['active_trunk_page'],
    'sixth slot ordinal' => static fn (): mixed => $plan263()->freelistRows()[5]['trunk_slot_ordinal'],
    'last slot ordinal' => static fn (): mixed => $plan263()->freelistRows()[6]['trunk_slot_ordinal'],
    'ordinals' => static fn (): mixed => array_column($plan263()->freelistRows(), 'freelist_ordinal'),
    'vacuum ordinals' => static fn (): mixed => array_column($plan263()->freelistRows(), 'vacuum_ordinal'),
    'row channels' => static fn (): mixed => array_column($plan263()->freelistRows(), 'freelist_channel'),
    'row states' => static fn (): mixed => array_column($plan263()->freelistRows(), 'freelist_state'),
    'vacuum token flags' => static fn (): mixed => array_column($plan263()->freelistRows(), 'vacuum_token_preserved'),
    'trunk flags' => static fn (): mixed => array_column($plan263()->freelistRows(), 'trunk_seen_before_leaf_slot'),
    'slot order flags' => static fn (): mixed => array_column($plan263()->freelistRows(), 'leaf_slot_ordered'),
    'offset flags' => static fn (): mixed => array_column($plan263()->freelistRows(), 'offset_matches_vacuum_finalization'),
    'tail reject flags' => static fn (): mixed => array_column($plan263()->freelistRows(), 'tail_page_rejected_from_freelist'),
    'link flags' => static fn (): mixed => array_column($plan263()->freelistRows(), 'freelist_link_valid'),
    'first previous token' => static fn (): mixed => $plan263()->freelistRows()[0]['previous_freelist_token'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan263()->freelistRows()[1]['previous_freelist_token']),
    'batch size three row count' => static fn (): mixed => $plan263(3)->freelistSummary()['freelist_row_count'],
    'batch size three pages' => static fn (): mixed => $plan263(3)->freelistPages(),
    'batch size three leaf pages' => static fn (): mixed => $plan263(3)->leafSlotPages(),
    'batch size three leaf slots by trunk' => static fn (): mixed => $plan263(3)->leafSlotsByTrunk(),
    'batch size three slot ordinals' => static fn (): mixed => $plan263(3)->leafSlotOrdinals(),
    'dependency closure' => static fn (): mixed => $plan263()->freelistSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan263()->freelistSummary()['non_overlap'], 'does not repeat next261'),
    'vacuum action' => static fn (): mixed => $plan263()->vacuumPlan->toArray()['action'],
    'vacuum status' => static fn (): mixed => $plan263()->vacuumPlan->vacuumSummary()['status'],
    'bad batch size rejected' => static fn (): mixed => $message263(static fn () => $plan263(0)),
];

$expected263 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next263',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next263-ready',
    'row count' => 7,
    'freelist pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary freelist pages' => [2, 3, 105, 106, 105, 107, 108],
    'trunk anchor pages' => [2, 105],
    'summary trunk anchor pages' => [2, 105],
    'leaf slot pages' => [3, 106, 107, 108],
    'summary leaf slot pages' => [3, 106, 107, 108],
    'leaf slots by trunk' => [2 => [3], 105 => [106, 107, 108]],
    'summary leaf slots by trunk' => [2 => [3], 105 => [106, 107, 108]],
    'leaf slot ordinals' => [1, 1, 2, 3],
    'summary leaf slot ordinals' => [1, 1, 2, 3],
    'freelist write offsets' => [40, 128, 144, 160],
    'summary freelist write offsets' => [40, 128, 144, 160],
    'vacuum finalized pages' => [3, 106, 107, 108],
    'leaf pages match vacuum' => true,
    'errors' => [],
    'summary errors' => [],
    'all vacuum tokens preserved' => true,
    'all trunks before leaves' => true,
    'all leaf slots ordered' => true,
    'all offsets match' => true,
    'all tail pages rejected' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row channel' => 'freelist-trunk-anchor',
    'first active trunk' => 2,
    'second row channel' => 'freelist-leaf-slot',
    'second active trunk' => 2,
    'second slot ordinal' => 1,
    'third row channel' => 'freelist-trunk-anchor',
    'fourth active trunk' => 105,
    'sixth slot ordinal' => 2,
    'last slot ordinal' => 3,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'vacuum ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row channels' => ['freelist-trunk-anchor', 'freelist-leaf-slot', 'freelist-trunk-anchor', 'freelist-leaf-slot', 'freelist-trunk-anchor', 'freelist-leaf-slot', 'freelist-leaf-slot'],
    'row states' => array_fill(0, 7, 'current-source-next263-freelist-splice-ready'),
    'vacuum token flags' => array_fill(0, 7, true),
    'trunk flags' => array_fill(0, 7, true),
    'slot order flags' => array_fill(0, 7, true),
    'offset flags' => array_fill(0, 7, true),
    'tail reject flags' => array_fill(0, 7, true),
    'link flags' => array_fill(0, 7, true),
    'first previous token' => null,
    'second previous token length' => 64,
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three leaf pages' => [3, 106, 107, 108],
    'batch size three leaf slots by trunk' => [2 => [3], 105 => [106, 107, 108]],
    'batch size three slot ordinals' => [1, 1, 2, 3],
    'dependency closure' => 'no new support component needed; next263 reuses next261 vacuum finalization rows and seals reusable pages into pointer-map-scoped freelist splice receipts',
    'non overlap' => true,
    'vacuum action' => 'btree-vacuum-pointermap-freeblock-current-source-next261',
    'vacuum status' => 'btree-vacuum-pointermap-freeblock-current-source-next261-ready',
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases263 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next263 ' . $name] = static function (TestRunner $t) use ($callback, $expected263, $name): void {
        $t->same($expected263[$name], $callback());
    };
}

foreach (range(1, 88) as $index) {
    $tests['btree vacuum pointermap freeblock current source next263 freelist splice invariant ' . $index] = static function (TestRunner $t) use ($plan263): void {
        $plan = $plan263();
        $summary = $plan->freelistSummary();

        $t->same([], $plan->freelistErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->freelistPages());
        $t->same([2, 105], $plan->trunkAnchorPages());
        $t->same([3, 106, 107, 108], $plan->leafSlotPages());
        $t->same([2 => [3], 105 => [106, 107, 108]], $plan->leafSlotsByTrunk());
        $t->same([1, 1, 2, 3], $plan->leafSlotOrdinals());
        $t->same([40, 128, 144, 160], $plan->freelistWriteOffsets());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->freelistRows(), 'freelist_ordinal'));
        $t->same(array_fill(0, 7, true), array_column($plan->freelistRows(), 'trunk_seen_before_leaf_slot'));
        $t->same(array_fill(0, 7, true), array_column($plan->freelistRows(), 'leaf_slot_ordered'));
        $t->same(array_fill(0, 7, true), array_column($plan->freelistRows(), 'offset_matches_vacuum_finalization'));
        $t->same(array_fill(0, 7, true), array_column($plan->freelistRows(), 'tail_page_rejected_from_freelist'));
        $t->same(array_fill(0, 7, true), array_column($plan->freelistRows(), 'freelist_link_valid'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->freelistTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next263-ready', $summary['status']);
        $t->same(true, $summary['freelist_leaf_pages_match_vacuum']);
        $t->same(true, $summary['all_tail_pages_rejected_from_freelist']);
    };
}

return $tests;
