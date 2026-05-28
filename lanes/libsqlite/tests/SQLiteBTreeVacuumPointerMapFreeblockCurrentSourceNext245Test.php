<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage245 = static function (int $pageCount): string {
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

$putPointerMapEntry245 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database245 = static function () use ($makeFirstPage245, $putPointerMapEntry245): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage245(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next245', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(94 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry245($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan245 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan {
    global $database245;

    $database = $database245();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next245-source-cursor-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$message245 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases245 = [
    'action label' => static fn (): mixed => $plan245()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan245()->cursorSummary()['status'],
    'cursor row count' => static fn (): mixed => $plan245()->cursorSummary()['cursor_row_count'],
    'admitted pages' => static fn (): mixed => $plan245()->admittedPages(),
    'summary admitted pages' => static fn (): mixed => $plan245()->cursorSummary()['admitted_pages'],
    'summary current source pages' => static fn (): mixed => $plan245()->cursorSummary()['current_source_pages'],
    'admitted pages match source' => static fn (): mixed => $plan245()->cursorSummary()['admitted_pages_match_current_source'],
    'pointer map barrier pages' => static fn (): mixed => $plan245()->pointerMapBarrierPages(),
    'summary pointer map barrier pages' => static fn (): mixed => $plan245()->cursorSummary()['pointer_map_barrier_pages'],
    'reusable freeblock pages' => static fn (): mixed => $plan245()->reusableFreeblockPages(),
    'summary reusable freeblock pages' => static fn (): mixed => $plan245()->cursorSummary()['reusable_freeblock_pages'],
    'cursor epochs' => static fn (): mixed => $plan245()->cursorEpochs(),
    'summary cursor epochs' => static fn (): mixed => $plan245()->cursorSummary()['cursor_epochs'],
    'cursor errors' => static fn (): mixed => $plan245()->cursorErrors(),
    'summary cursor errors' => static fn (): mixed => $plan245()->cursorSummary()['cursor_errors'],
    'all source tokens match' => static fn (): mixed => $plan245()->cursorSummary()['all_current_source_tokens_match'],
    'all pointer epochs open' => static fn (): mixed => $plan245()->cursorSummary()['all_pointer_map_epochs_open_before_reuse'],
    'all leaf receipts visible' => static fn (): mixed => $plan245()->cursorSummary()['all_reusable_pages_have_leaf_receipts'],
    'all trunk candidates preserved' => static fn (): mixed => $plan245()->cursorSummary()['all_trunk_candidates_preserved'],
    'all cursor links valid' => static fn (): mixed => $plan245()->cursorSummary()['all_cursor_links_valid'],
    'token count' => static fn (): mixed => count($plan245()->cursorTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan245()->cursorTokens()),
    'signature length' => static fn (): mixed => strlen($plan245()->cursorSummary()['cursor_signature']),
    'next token length' => static fn (): mixed => strlen($plan245()->cursorSummary()['current_source_next245_token']),
    'first row kind' => static fn (): mixed => $plan245()->cursorRows()[0]['admission_kind'],
    'first row epoch' => static fn (): mixed => $plan245()->cursorRows()[0]['pointer_map_epoch'],
    'second row kind' => static fn (): mixed => $plan245()->cursorRows()[1]['admission_kind'],
    'second row trunk' => static fn (): mixed => $plan245()->cursorRows()[1]['trunk_candidate_page'],
    'second row epoch opened' => static fn (): mixed => $plan245()->cursorRows()[1]['pointer_map_epoch_open_before_reuse'],
    'fifth row kind' => static fn (): mixed => $plan245()->cursorRows()[4]['admission_kind'],
    'fifth row epoch' => static fn (): mixed => $plan245()->cursorRows()[4]['pointer_map_epoch'],
    'last row page' => static fn (): mixed => $plan245()->cursorRows()[6]['admitted_page'],
    'last row epoch' => static fn (): mixed => $plan245()->cursorRows()[6]['pointer_map_epoch'],
    'cursor ordinals' => static fn (): mixed => array_column($plan245()->cursorRows(), 'cursor_ordinal'),
    'source ordinals' => static fn (): mixed => array_column($plan245()->cursorRows(), 'current_source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan245()->cursorRows(), 'cursor_state'),
    'row token flags' => static fn (): mixed => array_column($plan245()->cursorRows(), 'current_source_token_matches'),
    'row epoch flags' => static fn (): mixed => array_column($plan245()->cursorRows(), 'pointer_map_epoch_open_before_reuse'),
    'row receipt flags' => static fn (): mixed => array_column($plan245()->cursorRows(), 'leaf_receipt_visible_before_admission'),
    'row trunk flags' => static fn (): mixed => array_column($plan245()->cursorRows(), 'trunk_candidate_preserved'),
    'row tail flags' => static fn (): mixed => array_column($plan245()->cursorRows(), 'tail_page_still_fenced'),
    'row link flags' => static fn (): mixed => array_column($plan245()->cursorRows(), 'cursor_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan245(3)->cursorSummary()['cursor_row_count'],
    'batch size three pages' => static fn (): mixed => $plan245(3)->admittedPages(),
    'batch size three epochs' => static fn (): mixed => $plan245(3)->cursorEpochs(),
    'batch size three reusable freeblocks' => static fn (): mixed => $plan245(3)->reusableFreeblockPages(),
    'dependency closure' => static fn (): mixed => $plan245()->cursorSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan245()->cursorSummary()['non_overlap'], 'does not repeat next242'),
    'current source action' => static fn (): mixed => $plan245()->currentSourcePlan->toArray()['action'],
    'current source row count' => static fn (): mixed => $plan245()->currentSourcePlan->currentSourceSummary()['current_source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message245(static fn () => $plan245(0)),
];

$expected245 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next245',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next245-ready',
    'cursor row count' => 7,
    'admitted pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary admitted pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'admitted pages match source' => true,
    'pointer map barrier pages' => [2, 105],
    'summary pointer map barrier pages' => [2, 105],
    'reusable freeblock pages' => [3, 106, 107, 108],
    'summary reusable freeblock pages' => [3, 106, 107, 108],
    'cursor epochs' => [1, 1, 2, 2, 3, 3, 3],
    'summary cursor epochs' => [1, 1, 2, 2, 3, 3, 3],
    'cursor errors' => [],
    'summary cursor errors' => [],
    'all source tokens match' => true,
    'all pointer epochs open' => true,
    'all leaf receipts visible' => true,
    'all trunk candidates preserved' => true,
    'all cursor links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first row kind' => 'pointer-map-barrier',
    'first row epoch' => 1,
    'second row kind' => 'reusable-freeblock',
    'second row trunk' => 3,
    'second row epoch opened' => true,
    'fifth row kind' => 'pointer-map-barrier',
    'fifth row epoch' => 3,
    'last row page' => 108,
    'last row epoch' => 3,
    'cursor ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next245-source-cursor-admitted', 'current-source-next245-source-cursor-admitted', 'current-source-next245-source-cursor-admitted', 'current-source-next245-source-cursor-admitted', 'current-source-next245-source-cursor-admitted', 'current-source-next245-source-cursor-admitted', 'current-source-next245-source-cursor-admitted'],
    'row token flags' => [true, true, true, true, true, true, true],
    'row epoch flags' => [true, true, true, true, true, true, true],
    'row receipt flags' => [true, true, true, true, true, true, true],
    'row trunk flags' => [true, true, true, true, true, true, true],
    'row tail flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three epochs' => [1, 1, 2, 2, 2, 2],
    'batch size three reusable freeblocks' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next245 reuses next242 current-source rows and verifies cursor admission ordering only',
    'non overlap' => true,
    'current source action' => 'btree-vacuum-pointermap-freeblock-current-source-next242',
    'current source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases245 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next245 ' . $name] = static function (TestRunner $t) use ($callback, $expected245, $name): void {
        $t->same($expected245[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next245 cursor invariant ' . $index] = static function (TestRunner $t) use ($plan245): void {
        $plan = $plan245();
        $summary = $plan->cursorSummary();

        $t->same([], $plan->cursorErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->admittedPages());
        $t->same([2, 105], $plan->pointerMapBarrierPages());
        $t->same([3, 106, 107, 108], $plan->reusableFreeblockPages());
        $t->same([1, 1, 2, 2, 3, 3, 3], $plan->cursorEpochs());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->cursorRows(), 'cursor_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'current_source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'pointer_map_epoch_open_before_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'leaf_receipt_visible_before_admission'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'trunk_candidate_preserved'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->cursorRows(), 'tail_page_still_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->cursorTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next245-ready', $summary['status']);
        $t->same(true, $summary['admitted_pages_match_current_source']);
        $t->same(true, $summary['all_pointer_map_epochs_open_before_reuse']);
    };
}

return $tests;
