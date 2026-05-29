<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage260 = static function (int $pageCount): string {
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

$putPointerMapEntry260 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database260 = static function () use ($makeFirstPage260, $putPointerMapEntry260): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage260(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next260', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry260($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan260 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database260;

    $database = $database260();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafReaderHandoffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next260-reader-handoff-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$message260 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases260 = [
    'action label' => static fn (): mixed => $plan260()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan260()->handoffSummary()['status'],
    'row count' => static fn (): mixed => $plan260()->handoffSummary()['handoff_row_count'],
    'handoff pages' => static fn (): mixed => $plan260()->handoffPages(),
    'summary handoff pages' => static fn (): mixed => $plan260()->handoffSummary()['handoff_pages'],
    'summary advanced pages' => static fn (): mixed => $plan260()->handoffSummary()['advanced_pages'],
    'pages match advance' => static fn (): mixed => $plan260()->handoffSummary()['handoff_pages_match_advanced_pages'],
    'reader visible pages' => static fn (): mixed => $plan260()->readerVisiblePages(),
    'summary reader visible pages' => static fn (): mixed => $plan260()->handoffSummary()['reader_visible_pages'],
    'pointer map snapshots' => static fn (): mixed => $plan260()->pointerMapSnapshotPages(),
    'summary pointer map snapshots' => static fn (): mixed => $plan260()->handoffSummary()['pointer_map_snapshot_pages'],
    'freeblock snapshots' => static fn (): mixed => $plan260()->reusableFreeblockSnapshotPages(),
    'summary freeblock snapshots' => static fn (): mixed => $plan260()->handoffSummary()['reusable_freeblock_snapshot_pages'],
    'visible pages by group' => static fn (): mixed => $plan260()->readerVisiblePagesByGroup(),
    'summary visible pages by group' => static fn (): mixed => $plan260()->handoffSummary()['reader_visible_pages_by_group'],
    'errors' => static fn (): mixed => $plan260()->handoffErrors(),
    'summary errors' => static fn (): mixed => $plan260()->handoffSummary()['handoff_errors'],
    'all advance tokens match' => static fn (): mixed => $plan260()->handoffSummary()['all_advance_tokens_match'],
    'all snapshots have pointer map' => static fn (): mixed => $plan260()->handoffSummary()['all_group_snapshots_have_pointer_map'],
    'all reader visibility ordered' => static fn (): mixed => $plan260()->handoffSummary()['all_reader_visibility_after_pointer_map'],
    'all freeblock receipts visible' => static fn (): mixed => $plan260()->handoffSummary()['all_freeblock_receipts_reader_visible'],
    'all tail pages blocked' => static fn (): mixed => $plan260()->handoffSummary()['all_tail_pages_blocked_from_reader'],
    'all source epochs preserved' => static fn (): mixed => $plan260()->handoffSummary()['all_source_epochs_preserved'],
    'all links valid' => static fn (): mixed => $plan260()->handoffSummary()['all_handoff_links_valid'],
    'token count' => static fn (): mixed => count($plan260()->handoffTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan260()->handoffTokens()),
    'signature length' => static fn (): mixed => strlen($plan260()->handoffSummary()['handoff_signature']),
    'next token length' => static fn (): mixed => strlen($plan260()->handoffSummary()['current_source_next260_token']),
    'first channel' => static fn (): mixed => $plan260()->handoffRows()[0]['handoff_channel'],
    'first group pointer page' => static fn (): mixed => $plan260()->handoffRows()[0]['group_pointer_map_page'],
    'second channel' => static fn (): mixed => $plan260()->handoffRows()[1]['handoff_channel'],
    'second reader epoch' => static fn (): mixed => $plan260()->handoffRows()[1]['reader_source_epoch'],
    'third reader visible pages' => static fn (): mixed => $plan260()->handoffRows()[2]['reader_visible_pages'],
    'fifth channel' => static fn (): mixed => $plan260()->handoffRows()[4]['handoff_channel'],
    'fifth reader visible pages' => static fn (): mixed => $plan260()->handoffRows()[4]['reader_visible_pages'],
    'last reader epoch' => static fn (): mixed => $plan260()->handoffRows()[6]['reader_source_epoch'],
    'last reader visible pages' => static fn (): mixed => $plan260()->handoffRows()[6]['reader_visible_pages'],
    'ordinals' => static fn (): mixed => array_column($plan260()->handoffRows(), 'handoff_ordinal'),
    'advance ordinals' => static fn (): mixed => array_column($plan260()->handoffRows(), 'advance_ordinal'),
    'handoff groups' => static fn (): mixed => array_column($plan260()->handoffRows(), 'handoff_group'),
    'source epochs' => static fn (): mixed => array_column($plan260()->handoffRows(), 'source_epoch'),
    'reader epochs' => static fn (): mixed => array_column($plan260()->handoffRows(), 'reader_source_epoch'),
    'row states' => static fn (): mixed => array_column($plan260()->handoffRows(), 'handoff_state'),
    'token flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'advance_token_matches'),
    'snapshot flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'group_snapshot_has_pointer_map'),
    'visibility flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'reader_visibility_after_pointer_map'),
    'receipt flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'freeblock_receipt_reader_visible'),
    'tail flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'tail_page_blocked_from_reader'),
    'epoch flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'source_epoch_preserved'),
    'link flags' => static fn (): mixed => array_column($plan260()->handoffRows(), 'handoff_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan260(3)->handoffSummary()['handoff_row_count'],
    'batch size three pages' => static fn (): mixed => $plan260(3)->handoffPages(),
    'batch size three groups' => static fn (): mixed => array_column($plan260(3)->handoffRows(), 'handoff_group'),
    'batch size three visible by group' => static fn (): mixed => $plan260(3)->readerVisiblePagesByGroup(),
    'dependency closure' => static fn (): mixed => $plan260()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan260()->handoffSummary()['non_overlap'], 'does not repeat next257'),
    'advance action' => static fn (): mixed => $plan260()->advancePlan->toArray()['action'],
    'advance row count' => static fn (): mixed => $plan260()->advancePlan->advanceSummary()['advance_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message260(static fn () => $plan260(0)),
];

$expected260 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next260',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next260-ready',
    'row count' => 7,
    'handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary advanced pages' => [2, 3, 105, 106, 105, 107, 108],
    'pages match advance' => true,
    'reader visible pages' => [2, 3, 105, 106, 107, 108],
    'summary reader visible pages' => [2, 3, 105, 106, 107, 108],
    'pointer map snapshots' => [2, 105],
    'summary pointer map snapshots' => [2, 105],
    'freeblock snapshots' => [3, 106, 107, 108],
    'summary freeblock snapshots' => [3, 106, 107, 108],
    'visible pages by group' => [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]],
    'summary visible pages by group' => [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]],
    'errors' => [],
    'summary errors' => [],
    'all advance tokens match' => true,
    'all snapshots have pointer map' => true,
    'all reader visibility ordered' => true,
    'all freeblock receipts visible' => true,
    'all tail pages blocked' => true,
    'all source epochs preserved' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first channel' => 'pointer-map-snapshot',
    'first group pointer page' => 2,
    'second channel' => 'reusable-freeblock-snapshot',
    'second reader epoch' => 4,
    'third reader visible pages' => [2, 3, 105],
    'fifth channel' => 'pointer-map-snapshot',
    'fifth reader visible pages' => [2, 3, 105, 106],
    'last reader epoch' => 13,
    'last reader visible pages' => [2, 3, 105, 106, 107, 108],
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'advance ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'handoff groups' => [1, 1, 2, 2, 3, 3, 3],
    'source epochs' => [2, 3, 5, 6, 8, 9, 10],
    'reader epochs' => [3, 4, 7, 8, 11, 12, 13],
    'row states' => ['current-source-next260-reader-handoff-ready', 'current-source-next260-reader-handoff-ready', 'current-source-next260-reader-handoff-ready', 'current-source-next260-reader-handoff-ready', 'current-source-next260-reader-handoff-ready', 'current-source-next260-reader-handoff-ready', 'current-source-next260-reader-handoff-ready'],
    'token flags' => [true, true, true, true, true, true, true],
    'snapshot flags' => [true, true, true, true, true, true, true],
    'visibility flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'tail flags' => [true, true, true, true, true, true, true],
    'epoch flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three groups' => [1, 1, 2, 2, 2, 2],
    'batch size three visible by group' => [1 => [2, 3], 2 => [105, 106, 107, 108]],
    'dependency closure' => 'no new support component needed; next260 reuses next257 advance fences and publishes grouped reader-visible current-source snapshots',
    'non overlap' => true,
    'advance action' => 'btree-vacuum-pointermap-freeblock-current-source-next257',
    'advance row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases260 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next260 ' . $name] = static function (TestRunner $t) use ($callback, $expected260, $name): void {
        $t->same($expected260[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next260 reader handoff invariant ' . $index] = static function (TestRunner $t) use ($plan260): void {
        $plan = $plan260();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->handoffPages());
        $t->same([2, 3, 105, 106, 107, 108], $plan->readerVisiblePages());
        $t->same([2, 105], $plan->pointerMapSnapshotPages());
        $t->same([3, 106, 107, 108], $plan->reusableFreeblockSnapshotPages());
        $t->same([1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]], $plan->readerVisiblePagesByGroup());
        $t->same([3, 4, 7, 8, 11, 12, 13], array_column($plan->handoffRows(), 'reader_source_epoch'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'advance_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'reader_visibility_after_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'freeblock_receipt_reader_visible'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'tail_page_blocked_from_reader'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->handoffTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next260-ready', $summary['status']);
        $t->same(true, $summary['handoff_pages_match_advanced_pages']);
        $t->same(true, $summary['all_handoff_links_valid']);
    };
}

return $tests;
