<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage195 = static function (int $pageCount): string {
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

$putPointerMapEntry195 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database195 = static function () use ($makeFirstPage195, $putPointerMapEntry195): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage195(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next195', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(72 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry195($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan195 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database195;

    $database = $database195();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafReplayCursorFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next195-current-source-replay-', 50),
        3,
        true,
        $batchSize,
    );
};

$message195 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases195 = [
    'action label' => static fn (): mixed => $plan195()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan195()->replaySummary()['status'],
    'replay errors' => static fn (): mixed => $plan195()->replayErrors(),
    'replayable pages' => static fn (): mixed => $plan195()->replayablePages(),
    'omitted tail pages' => static fn (): mixed => $plan195()->omittedTailPages(),
    'pointer map replay pages' => static fn (): mixed => $plan195()->pointerMapReplayPages(),
    'overflow replay pages' => static fn (): mixed => $plan195()->overflowReplayPages(),
    'summary replayable pages' => static fn (): mixed => $plan195()->replaySummary()['replayable_pages'],
    'summary omitted tail pages' => static fn (): mixed => $plan195()->replaySummary()['omitted_tail_pages'],
    'summary pointer pages' => static fn (): mixed => $plan195()->replaySummary()['pointer_map_replay_pages'],
    'summary overflow pages' => static fn (): mixed => $plan195()->replaySummary()['overflow_replay_pages'],
    'summary error count' => static fn (): mixed => $plan195()->replaySummary()['replay_error_count'],
    'summary byte ranges' => static fn (): mixed => $plan195()->replaySummary()['byte_ranges_contiguous'],
    'summary pointer before overflow' => static fn (): mixed => $plan195()->replaySummary()['pointer_map_replayed_before_overflow'],
    'summary freeblock replayed' => static fn (): mixed => $plan195()->replaySummary()['freeblock_receipt_replayed'],
    'summary tail matches' => static fn (): mixed => $plan195()->replaySummary()['tail_omission_matches_handoff'],
    'summary published count' => static fn (): mixed => $plan195()->replaySummary()['published_page_count'],
    'summary final page count' => static fn (): mixed => $plan195()->replaySummary()['final_database_page_count'],
    'dependencies' => static fn (): mixed => $plan195()->replaySummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan195()->replaySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan195()->replaySummary()['non_overlap'], 'does not repeat next191 manifest construction'),
    'row pages' => static fn (): mixed => array_column($plan195()->replayRows(), 'page_number'),
    'replay orders' => static fn (): mixed => array_column($plan195()->replayRows(), 'replay_order'),
    'replay ordinals' => static fn (): mixed => array_column($plan195()->replayRows(), 'replay_ordinal'),
    'page fences' => static fn (): mixed => array_column($plan195()->replayRows(), 'page_count_fence'),
    'byte starts' => static fn (): mixed => array_column($plan195()->replayRows(), 'byte_start'),
    'byte ends' => static fn (): mixed => array_column($plan195()->replayRows(), 'byte_end'),
    'states' => static fn (): mixed => array_column($plan195()->replayRows(), 'replay_state'),
    'actions' => static fn (): mixed => array_column($plan195()->replayRows(), 'replay_action'),
    'roles' => static fn (): mixed => array_column($plan195()->replayRows(), 'replay_role'),
    'receipt available' => static fn (): mixed => array_column($plan195()->replayRows(), 'receipt_available_to_replay'),
    'freeblock replay' => static fn (): mixed => array_column($plan195()->replayRows(), 'secure_delete_freeblock_replay'),
    'pointer required' => static fn (): mixed => array_column($plan195()->replayRows(), 'pointer_map_replay_required'),
    'overflow required' => static fn (): mixed => array_column($plan195()->replayRows(), 'overflow_replay_required'),
    'tail required' => static fn (): mixed => array_column($plan195()->replayRows(), 'tail_omission_required'),
    'hash required' => static fn (): mixed => array_column($plan195()->replayRows(), 'source_hash_required'),
    'hash available' => static fn (): mixed => array_column($plan195()->replayRows(), 'source_hash_available'),
    'resume required' => static fn (): mixed => array_column($plan195()->replayRows(), 'resume_token_required'),
    'resume available' => static fn (): mixed => array_column($plan195()->replayRows(), 'resume_token_available'),
    'pointer types' => static fn (): mixed => array_column($plan195()->replayRows(), 'pointer_map_type'),
    'pointer parents' => static fn (): mixed => array_column($plan195()->replayRows(), 'pointer_map_parent'),
    'replay token length' => static fn (): mixed => strlen($plan195()->replaySummary()['replay_token']),
    'manifest token length' => static fn (): mixed => strlen($plan195()->replaySummary()['handoff_manifest_token']),
    'replay key length' => static fn (): mixed => strlen($plan195()->replayRows()[0]['current_source_replay_key']),
    'base action label' => static fn (): mixed => $plan195()->basePlan->toArray()['action'],
    'base manifest pages' => static fn (): mixed => $plan195()->basePlan->manifestPages(),
    'base fenced pages' => static fn (): mixed => $plan195()->basePlan->fencedTailPages(),
    'batch size three replay pages' => static fn (): mixed => $plan195(3)->replayablePages(),
    'batch size three omitted pages' => static fn (): mixed => $plan195(3)->omittedTailPages(),
    'bad batch size rejected' => static fn (): mixed => $message195(static fn () => $plan195(0)),
];

$expected195 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next195',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next195-ready',
    'replay errors' => [],
    'replayable pages' => [1, 3, 105, 106, 107, 108],
    'omitted tail pages' => [109, 110],
    'pointer map replay pages' => [105],
    'overflow replay pages' => [106, 107, 108],
    'summary replayable pages' => [1, 3, 105, 106, 107, 108],
    'summary omitted tail pages' => [109, 110],
    'summary pointer pages' => [105],
    'summary overflow pages' => [106, 107, 108],
    'summary error count' => 0,
    'summary byte ranges' => true,
    'summary pointer before overflow' => true,
    'summary freeblock replayed' => true,
    'summary tail matches' => true,
    'summary published count' => 6,
    'summary final page count' => 108,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next191', 'sqlite-current-source-next195'],
    'dependency closure' => 'no new support component needed; next195 reuses next191 current-source handoff rows, pointer-map ordering, secure-delete freeblock receipts, overflow replay hashes, and page-count tail fences',
    'non overlap' => true,
    'row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'replay orders' => [0, 1, 2, 3, 4, 5, 6, 7],
    'replay ordinals' => [1, 2, 3, 4, 5, 6, null, null],
    'page fences' => [108, 108, 108, 108, 108, 108, 108, 108],
    'byte starts' => [0, 512, 1024, 1536, 2048, 2560, null, null],
    'byte ends' => [512, 1024, 1536, 2048, 2560, 3072, null, null],
    'states' => ['replay-current-source-page', 'replay-current-source-page', 'replay-current-source-page', 'replay-current-source-page', 'replay-current-source-page', 'replay-current-source-page', 'omit-truncated-tail', 'omit-truncated-tail'],
    'actions' => ['stream-page-from-current-source', 'stream-page-from-current-source', 'stream-page-from-current-source', 'stream-page-from-current-source', 'stream-page-from-current-source', 'stream-page-from-current-source', 'skip-page-beyond-current-source-eof', 'skip-page-beyond-current-source-eof'],
    'roles' => ['database-header', 'table-leaf-freeblock', 'pointer-map', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail', 'truncated-tail'],
    'receipt available' => [true, true, true, true, true, true, false, false],
    'freeblock replay' => [false, true, false, false, false, false, false, false],
    'pointer required' => [false, false, true, false, false, false, false, false],
    'overflow required' => [false, false, false, true, true, true, false, false],
    'tail required' => [false, false, false, false, false, false, true, true],
    'hash required' => [true, true, true, true, true, true, false, false],
    'hash available' => [true, true, true, true, true, true, false, false],
    'resume required' => [true, true, true, true, true, true, false, false],
    'resume available' => [true, true, true, true, true, true, false, false],
    'pointer types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'pointer parents' => [null, 0, null, 108, 3, 107, null, null],
    'replay token length' => 64,
    'manifest token length' => 64,
    'replay key length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next191',
    'base manifest pages' => [1, 3, 105, 106, 107, 108],
    'base fenced pages' => [109, 110],
    'batch size three replay pages' => [1, 3, 105, 106, 107, 108],
    'batch size three omitted pages' => [109, 110],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases195 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next195 ' . $name] = static function (TestRunner $t) use ($callback, $expected195, $name): void {
        $t->same($expected195[$name], $callback());
    };
}

foreach (range(1, 50) as $index) {
    $tests['btree vacuum pointermap freeblock current source next195 replay invariant ' . $index] = static function (TestRunner $t) use ($plan195): void {
        $plan = $plan195();
        $rows = $plan->replayRows();
        $summary = $plan->replaySummary();

        $t->same([], $plan->replayErrors());
        $t->same([1, 3, 105, 106, 107, 108], $plan->replayablePages());
        $t->same([109, 110], $plan->omittedTailPages());
        $t->same([105], $plan->pointerMapReplayPages());
        $t->same([106, 107, 108], $plan->overflowReplayPages());
        $t->same([1, 2, 3, 4, 5, 6, null, null], array_column($rows, 'replay_ordinal'));
        $t->same([0, 512, 1024, 1536, 2048, 2560, null, null], array_column($rows, 'byte_start'));
        $t->same([512, 1024, 1536, 2048, 2560, 3072, null, null], array_column($rows, 'byte_end'));
        $t->same(true, $summary['byte_ranges_contiguous']);
        $t->same(true, $summary['pointer_map_replayed_before_overflow']);
        $t->same(true, $summary['freeblock_receipt_replayed']);
        $t->same(true, $summary['tail_omission_matches_handoff']);
        $t->same(true, $rows[1]['secure_delete_freeblock_replay']);
        $t->same(true, $rows[2]['pointer_map_replay_required']);
        $t->same([true, true, true], array_slice(array_column($rows, 'overflow_replay_required'), 3, 3));
        $t->same([true, true], array_slice(array_column($rows, 'tail_omission_required'), 6, 2));
        $t->same(6, $summary['published_page_count']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next195-ready', $summary['status']);
    };
}

return $tests;
