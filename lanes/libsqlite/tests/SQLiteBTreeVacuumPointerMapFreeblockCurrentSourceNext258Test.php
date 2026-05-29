<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage258 = static function (int $pageCount): string {
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

$putPointerMapEntry258 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database258 = static function () use ($makeFirstPage258, $putPointerMapEntry258): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage258(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next258', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(98 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry258($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan258 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database258;

    $database = $database258();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext258(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next258-current-source-next-handoff-', 40),
        3,
        true,
        $batchSize,
    );
};

$message258 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases258 = [
    'action label' => static fn (): mixed => $plan258()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan258()->handoffSummary()['status'],
    'row count' => static fn (): mixed => $plan258()->handoffSummary()['handoff_row_count'],
    'handoff pages' => static fn (): mixed => $plan258()->handoffPages(),
    'summary handoff pages' => static fn (): mixed => $plan258()->handoffSummary()['handoff_pages'],
    'summary current source pages' => static fn (): mixed => $plan258()->handoffSummary()['current_source_pages'],
    'pages match current source' => static fn (): mixed => $plan258()->handoffSummary()['handoff_pages_match_current_source'],
    'next reusable pages' => static fn (): mixed => $plan258()->nextReusablePages(),
    'summary next reusable pages' => static fn (): mixed => $plan258()->handoffSummary()['next_reusable_pages'],
    'pointer map fence pages' => static fn (): mixed => $plan258()->pointerMapFencePages(),
    'summary pointer map fence pages' => static fn (): mixed => $plan258()->handoffSummary()['pointer_map_fence_pages'],
    'stale slot fenced pages' => static fn (): mixed => $plan258()->staleSlotFencedPages(),
    'summary stale slot fenced pages' => static fn (): mixed => $plan258()->handoffSummary()['stale_slot_fenced_pages'],
    'handoff write offsets' => static fn (): mixed => $plan258()->handoffWriteOffsets(),
    'summary handoff write offsets' => static fn (): mixed => $plan258()->handoffSummary()['handoff_write_offsets'],
    'errors' => static fn (): mixed => $plan258()->handoffErrors(),
    'summary errors' => static fn (): mixed => $plan258()->handoffSummary()['handoff_errors'],
    'all current source tokens match' => static fn (): mixed => $plan258()->handoffSummary()['all_current_source_tokens_match'],
    'all pointer map fences before reuse' => static fn (): mixed => $plan258()->handoffSummary()['all_pointer_map_fences_before_reuse'],
    'all next reuse has current slot' => static fn (): mixed => $plan258()->handoffSummary()['all_next_reuse_has_current_slot'],
    'all stale slots fenced' => static fn (): mixed => $plan258()->handoffSummary()['all_stale_slots_fenced_before_next_reuse'],
    'all leaf receipts preserved' => static fn (): mixed => $plan258()->handoffSummary()['all_leaf_receipts_preserved'],
    'all links valid' => static fn (): mixed => $plan258()->handoffSummary()['all_handoff_links_valid'],
    'token count' => static fn (): mixed => count($plan258()->handoffTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan258()->handoffTokens()),
    'signature length' => static fn (): mixed => strlen($plan258()->handoffSummary()['handoff_signature']),
    'next token length' => static fn (): mixed => strlen($plan258()->handoffSummary()['current_source_next258_token']),
    'first row channel' => static fn (): mixed => $plan258()->handoffRows()[0]['handoff_channel'],
    'first row active pointer map' => static fn (): mixed => $plan258()->handoffRows()[0]['active_pointer_map_page'],
    'second row channel' => static fn (): mixed => $plan258()->handoffRows()[1]['handoff_channel'],
    'second row previous slot' => static fn (): mixed => $plan258()->handoffRows()[1]['previous_reusable_slot_page'],
    'second row offset' => static fn (): mixed => $plan258()->handoffRows()[1]['handoff_write_offset'],
    'third row channel' => static fn (): mixed => $plan258()->handoffRows()[2]['handoff_channel'],
    'third row previous slot' => static fn (): mixed => $plan258()->handoffRows()[2]['previous_reusable_slot_page'],
    'fourth row previous slot' => static fn (): mixed => $plan258()->handoffRows()[3]['previous_reusable_slot_page'],
    'fifth row channel' => static fn (): mixed => $plan258()->handoffRows()[4]['handoff_channel'],
    'fifth row previous slot' => static fn (): mixed => $plan258()->handoffRows()[4]['previous_reusable_slot_page'],
    'last row page' => static fn (): mixed => $plan258()->handoffRows()[6]['handoff_page'],
    'last row previous slot' => static fn (): mixed => $plan258()->handoffRows()[6]['previous_reusable_slot_page'],
    'last row offset' => static fn (): mixed => $plan258()->handoffRows()[6]['handoff_write_offset'],
    'ordinals' => static fn (): mixed => array_column($plan258()->handoffRows(), 'handoff_ordinal'),
    'current source ordinals' => static fn (): mixed => array_column($plan258()->handoffRows(), 'current_source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan258()->handoffRows(), 'handoff_state'),
    'token flags' => static fn (): mixed => array_column($plan258()->handoffRows(), 'current_source_token_matches'),
    'pointer map fence flags' => static fn (): mixed => array_column($plan258()->handoffRows(), 'pointer_map_fence_before_reuse'),
    'current slot flags' => static fn (): mixed => array_column($plan258()->handoffRows(), 'next_reuse_has_current_slot'),
    'stale fence flags' => static fn (): mixed => array_column($plan258()->handoffRows(), 'stale_freeblock_slot_fenced'),
    'receipt flags' => static fn (): mixed => array_column($plan258()->handoffRows(), 'leaf_receipt_preserved_for_next_source'),
    'link flags' => static fn (): mixed => array_column($plan258()->handoffRows(), 'handoff_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan258(3)->handoffSummary()['handoff_row_count'],
    'batch size three pages' => static fn (): mixed => $plan258(3)->handoffPages(),
    'batch size three offsets' => static fn (): mixed => $plan258(3)->handoffWriteOffsets(),
    'batch size three reusable pages' => static fn (): mixed => $plan258(3)->nextReusablePages(),
    'dependency closure' => static fn (): mixed => $plan258()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan258()->handoffSummary()['non_overlap'], 'does not repeat next254'),
    'current source action' => static fn (): mixed => $plan258()->currentSourcePlan->toArray()['action'],
    'current source row count' => static fn (): mixed => $plan258()->currentSourcePlan->currentSourceSummary()['current_source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message258(static fn () => $plan258(0)),
];

$expected258 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next258',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next258-ready',
    'row count' => 7,
    'handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'pages match current source' => true,
    'next reusable pages' => [3, 106, 107, 108],
    'summary next reusable pages' => [3, 106, 107, 108],
    'pointer map fence pages' => [2, 105],
    'summary pointer map fence pages' => [2, 105],
    'stale slot fenced pages' => [2, 3, 105, 106, 107, 108],
    'summary stale slot fenced pages' => [2, 3, 105, 106, 107, 108],
    'handoff write offsets' => [0, 40, 0, 128, 0, 144, 160],
    'summary handoff write offsets' => [0, 40, 0, 128, 0, 144, 160],
    'errors' => [],
    'summary errors' => [],
    'all current source tokens match' => true,
    'all pointer map fences before reuse' => true,
    'all next reuse has current slot' => true,
    'all stale slots fenced' => true,
    'all leaf receipts preserved' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first row channel' => 'pointer-map-fence',
    'first row active pointer map' => 2,
    'second row channel' => 'next-source-reusable-page',
    'second row previous slot' => null,
    'second row offset' => 40,
    'third row channel' => 'pointer-map-fence',
    'third row previous slot' => 3,
    'fourth row previous slot' => 3,
    'fifth row channel' => 'pointer-map-fence',
    'fifth row previous slot' => 106,
    'last row page' => 108,
    'last row previous slot' => 107,
    'last row offset' => 160,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'current source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next258-next-source-reuse-handoff-ready', 'current-source-next258-next-source-reuse-handoff-ready', 'current-source-next258-next-source-reuse-handoff-ready', 'current-source-next258-next-source-reuse-handoff-ready', 'current-source-next258-next-source-reuse-handoff-ready', 'current-source-next258-next-source-reuse-handoff-ready', 'current-source-next258-next-source-reuse-handoff-ready'],
    'token flags' => [true, true, true, true, true, true, true],
    'pointer map fence flags' => [true, true, true, true, true, true, true],
    'current slot flags' => [true, true, true, true, true, true, true],
    'stale fence flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three offsets' => [0, 40, 0, 128, 144, 160],
    'batch size three reusable pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next258 reuses next254 page-local current-source write slots and adds the next-source stale-slot fence',
    'non overlap' => true,
    'current source action' => 'btree-vacuum-pointermap-freeblock-current-source-next254',
    'current source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases258 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next258 ' . $name] = static function (TestRunner $t) use ($callback, $expected258, $name): void {
        $t->same($expected258[$name], $callback());
    };
}

foreach (range(1, 96) as $index) {
    $tests['btree vacuum pointermap freeblock current source next258 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan258): void {
        $plan = $plan258();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->handoffPages());
        $t->same([2, 105], $plan->pointerMapFencePages());
        $t->same([3, 106, 107, 108], $plan->nextReusablePages());
        $t->same([0, 40, 0, 128, 0, 144, 160], $plan->handoffWriteOffsets());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->handoffRows(), 'handoff_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'current_source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'pointer_map_fence_before_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'next_reuse_has_current_slot'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'stale_freeblock_slot_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->handoffTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next258-ready', $summary['status']);
        $t->same(true, $summary['handoff_pages_match_current_source']);
        $t->same(true, $summary['all_stale_slots_fenced_before_next_reuse']);
    };
}

return $tests;
