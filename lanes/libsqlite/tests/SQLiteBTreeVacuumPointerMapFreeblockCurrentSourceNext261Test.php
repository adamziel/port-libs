<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage261 = static function (int $pageCount): string {
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

$putPointerMapEntry261 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database261 = static function () use ($makeFirstPage261, $putPointerMapEntry261): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage261(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next261', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry261($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan261 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database261;

    $database = $database261();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafVacuumFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next261-current-source-next-handoff-', 40),
        3,
        true,
        $batchSize,
    );
};

$message261 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases261 = [
    'action label' => static fn (): mixed => $plan261()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan261()->vacuumSummary()['status'],
    'row count' => static fn (): mixed => $plan261()->vacuumSummary()['vacuum_row_count'],
    'fence pages' => static fn (): mixed => $plan261()->fencePages(),
    'summary fence pages' => static fn (): mixed => $plan261()->vacuumSummary()['fence_pages'],
    'finalized pages' => static fn (): mixed => $plan261()->finalizedReusablePages(),
    'summary finalized pages' => static fn (): mixed => $plan261()->vacuumSummary()['finalized_reusable_pages'],
    'pages by pointer map' => static fn (): mixed => $plan261()->reusablePagesByPointerMap(),
    'summary pages by pointer map' => static fn (): mixed => $plan261()->vacuumSummary()['reusable_pages_by_pointer_map'],
    'finalized offsets' => static fn (): mixed => $plan261()->finalizedWriteOffsets(),
    'summary finalized offsets' => static fn (): mixed => $plan261()->vacuumSummary()['finalized_write_offsets'],
    'handoff pages preserved' => static fn (): mixed => $plan261()->vacuumSummary()['handoff_pages'],
    'errors' => static fn (): mixed => $plan261()->vacuumErrors(),
    'summary errors' => static fn (): mixed => $plan261()->vacuumSummary()['vacuum_errors'],
    'handoff tokens preserved' => static fn (): mixed => $plan261()->vacuumSummary()['all_handoff_tokens_preserved'],
    'pointer map batches fenced' => static fn (): mixed => $plan261()->vacuumSummary()['all_pointer_map_batches_fenced'],
    'reusable slots finalized' => static fn (): mixed => $plan261()->vacuumSummary()['all_reusable_slots_finalized'],
    'offsets safe' => static fn (): mixed => $plan261()->vacuumSummary()['all_offsets_current_source_safe'],
    'links valid' => static fn (): mixed => $plan261()->vacuumSummary()['all_vacuum_links_valid'],
    'token count' => static fn (): mixed => count($plan261()->vacuumTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan261()->vacuumTokens()),
    'signature length' => static fn (): mixed => strlen($plan261()->vacuumSummary()['vacuum_signature']),
    'next token length' => static fn (): mixed => strlen($plan261()->vacuumSummary()['current_source_next261_token']),
    'first row channel' => static fn (): mixed => $plan261()->vacuumRows()[0]['vacuum_channel'],
    'first row pointer map' => static fn (): mixed => $plan261()->vacuumRows()[0]['active_pointer_map_page'],
    'second row channel' => static fn (): mixed => $plan261()->vacuumRows()[1]['vacuum_channel'],
    'second row batch ordinal' => static fn (): mixed => $plan261()->vacuumRows()[1]['pointer_map_batch_ordinal'],
    'second row offset' => static fn (): mixed => $plan261()->vacuumRows()[1]['vacuum_write_offset'],
    'third row channel' => static fn (): mixed => $plan261()->vacuumRows()[2]['vacuum_channel'],
    'third row pointer map' => static fn (): mixed => $plan261()->vacuumRows()[2]['active_pointer_map_page'],
    'fourth row batch ordinal' => static fn (): mixed => $plan261()->vacuumRows()[3]['pointer_map_batch_ordinal'],
    'sixth row batch ordinal' => static fn (): mixed => $plan261()->vacuumRows()[5]['pointer_map_batch_ordinal'],
    'last row batch ordinal' => static fn (): mixed => $plan261()->vacuumRows()[6]['pointer_map_batch_ordinal'],
    'ordinals' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'vacuum_ordinal'),
    'handoff ordinals' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'handoff_ordinal'),
    'row states' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'vacuum_state'),
    'preserved flags' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'handoff_token_preserved'),
    'fenced flags' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'pointer_map_batch_fenced'),
    'finalized flags' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'reusable_slot_finalized'),
    'offset flags' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'offset_current_source_safe'),
    'link flags' => static fn (): mixed => array_column($plan261()->vacuumRows(), 'vacuum_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan261(3)->vacuumSummary()['vacuum_row_count'],
    'batch size three finalized pages' => static fn (): mixed => $plan261(3)->finalizedReusablePages(),
    'batch size three offsets' => static fn (): mixed => $plan261(3)->finalizedWriteOffsets(),
    'batch size three pointer map batches' => static fn (): mixed => $plan261(3)->reusablePagesByPointerMap(),
    'dependency closure' => static fn (): mixed => $plan261()->vacuumSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan261()->vacuumSummary()['non_overlap'], 'does not repeat next258'),
    'handoff action' => static fn (): mixed => $plan261()->handoffPlan->toArray()['action'],
    'handoff status' => static fn (): mixed => $plan261()->handoffPlan->handoffSummary()['status'],
    'bad batch size rejected' => static fn (): mixed => $message261(static fn () => $plan261(0)),
];

$expected261 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next261',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next261-ready',
    'row count' => 7,
    'fence pages' => [2, 105],
    'summary fence pages' => [2, 105],
    'finalized pages' => [3, 106, 107, 108],
    'summary finalized pages' => [3, 106, 107, 108],
    'pages by pointer map' => [2 => [3], 105 => [106, 107, 108]],
    'summary pages by pointer map' => [2 => [3], 105 => [106, 107, 108]],
    'finalized offsets' => [40, 128, 144, 160],
    'summary finalized offsets' => [40, 128, 144, 160],
    'handoff pages preserved' => [2, 3, 105, 106, 105, 107, 108],
    'errors' => [],
    'summary errors' => [],
    'handoff tokens preserved' => true,
    'pointer map batches fenced' => true,
    'reusable slots finalized' => true,
    'offsets safe' => true,
    'links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first row channel' => 'pointer-map-batch-fence',
    'first row pointer map' => 2,
    'second row channel' => 'finalized-reusable-freeblock',
    'second row batch ordinal' => 1,
    'second row offset' => 40,
    'third row channel' => 'pointer-map-batch-fence',
    'third row pointer map' => 105,
    'fourth row batch ordinal' => 1,
    'sixth row batch ordinal' => 2,
    'last row batch ordinal' => 3,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => array_fill(0, 7, 'current-source-next261-vacuum-freeblock-finalized'),
    'preserved flags' => array_fill(0, 7, true),
    'fenced flags' => array_fill(0, 7, true),
    'finalized flags' => array_fill(0, 7, true),
    'offset flags' => array_fill(0, 7, true),
    'link flags' => array_fill(0, 7, true),
    'batch size three row count' => 6,
    'batch size three finalized pages' => [3, 106, 107, 108],
    'batch size three offsets' => [40, 128, 144, 160],
    'batch size three pointer map batches' => [2 => [3], 105 => [106, 107, 108]],
    'dependency closure' => 'no new support component needed; next261 reuses next258 current-source handoff rows and finalizes pointer-map-scoped reusable freeblock batches',
    'non overlap' => true,
    'handoff action' => 'btree-vacuum-pointermap-freeblock-current-source-next258',
    'handoff status' => 'btree-vacuum-pointermap-freeblock-current-source-next258-ready',
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases261 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next261 ' . $name] = static function (TestRunner $t) use ($callback, $expected261, $name): void {
        $t->same($expected261[$name], $callback());
    };
}

foreach (range(1, 96) as $index) {
    $tests['btree vacuum pointermap freeblock current source next261 finalization invariant ' . $index] = static function (TestRunner $t) use ($plan261): void {
        $plan = $plan261();
        $summary = $plan->vacuumSummary();

        $t->same([], $plan->vacuumErrors());
        $t->same([2, 105], $plan->fencePages());
        $t->same([3, 106, 107, 108], $plan->finalizedReusablePages());
        $t->same([2 => [3], 105 => [106, 107, 108]], $plan->reusablePagesByPointerMap());
        $t->same([40, 128, 144, 160], $plan->finalizedWriteOffsets());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->vacuumRows(), 'vacuum_ordinal'));
        $t->same(array_fill(0, 7, true), array_column($plan->vacuumRows(), 'pointer_map_batch_fenced'));
        $t->same(array_fill(0, 7, true), array_column($plan->vacuumRows(), 'reusable_slot_finalized'));
        $t->same(array_fill(0, 7, true), array_column($plan->vacuumRows(), 'offset_current_source_safe'));
        $t->same(array_fill(0, 7, true), array_column($plan->vacuumRows(), 'vacuum_link_valid'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->vacuumTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next261-ready', $summary['status']);
        $t->same(true, $summary['all_reusable_slots_finalized']);
        $t->same(true, $summary['all_pointer_map_batches_fenced']);
    };
}

return $tests;
