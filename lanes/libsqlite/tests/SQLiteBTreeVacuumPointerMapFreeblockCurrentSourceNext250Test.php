<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage250 = static function (int $pageCount): string {
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

$putPointerMapEntry250 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database250 = static function () use ($makeFirstPage250, $putPointerMapEntry250): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage250(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next250', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(90 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry250($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan250 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database250;

    $database = $database250();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext250(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next250-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message250 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases250 = [
    'action label' => static fn (): mixed => $plan250()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan250()->handoffSummary()['status'],
    'handoff row count' => static fn (): mixed => $plan250()->handoffSummary()['handoff_row_count'],
    'handoff pages' => static fn (): mixed => $plan250()->handoffPages(),
    'summary handoff pages' => static fn (): mixed => $plan250()->handoffSummary()['handoff_pages'],
    'checkpoint pages' => static fn (): mixed => $plan250()->handoffSummary()['checkpoint_pages'],
    'handoff pages match checkpoint pages' => static fn (): mixed => $plan250()->handoffSummary()['handoff_pages_match_checkpoint_pages'],
    'pointer map barrier pages' => static fn (): mixed => $plan250()->pointerMapBarrierPages(),
    'summary pointer map barrier pages' => static fn (): mixed => $plan250()->handoffSummary()['pointer_map_barrier_pages'],
    'freeblock source pages' => static fn (): mixed => $plan250()->freeblockSourcePages(),
    'summary freeblock source pages' => static fn (): mixed => $plan250()->handoffSummary()['freeblock_source_pages'],
    'payload source pages' => static fn (): mixed => $plan250()->payloadSourcePages(),
    'summary payload source pages' => static fn (): mixed => $plan250()->handoffSummary()['payload_source_pages'],
    'duplicate pointer map barrier pages' => static fn (): mixed => $plan250()->duplicatePointerMapBarrierPages(),
    'summary duplicate pointer map barrier pages' => static fn (): mixed => $plan250()->handoffSummary()['duplicate_pointer_map_barrier_pages'],
    'handoff errors' => static fn (): mixed => $plan250()->handoffErrors(),
    'summary handoff errors' => static fn (): mixed => $plan250()->handoffSummary()['handoff_errors'],
    'all checkpoint tokens match' => static fn (): mixed => $plan250()->handoffSummary()['all_checkpoint_tokens_match'],
    'all handoff links current' => static fn (): mixed => $plan250()->handoffSummary()['all_handoff_links_current'],
    'all pointer map barriers before sources' => static fn (): mixed => $plan250()->handoffSummary()['all_pointer_map_barriers_before_sources'],
    'all freeblock sources open before payload' => static fn (): mixed => $plan250()->handoffSummary()['all_freeblock_sources_open_before_payload'],
    'all payload sources checkpoint ready' => static fn (): mixed => $plan250()->handoffSummary()['all_payload_sources_checkpoint_ready'],
    'all duplicate pointer maps keep generation' => static fn (): mixed => $plan250()->handoffSummary()['all_duplicate_pointer_maps_keep_generation'],
    'all tail pages excluded from handoff' => static fn (): mixed => $plan250()->handoffSummary()['all_tail_pages_excluded_from_handoff'],
    'token count' => static fn (): mixed => count($plan250()->handoffTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan250()->handoffTokens()),
    'signature length' => static fn (): mixed => strlen($plan250()->handoffSummary()['handoff_signature']),
    'current token length' => static fn (): mixed => strlen($plan250()->handoffSummary()['current_source_next250_token']),
    'first row state' => static fn (): mixed => $plan250()->handoffRows()[0]['handoff_state'],
    'second row state' => static fn (): mixed => $plan250()->handoffRows()[1]['handoff_state'],
    'first row channel' => static fn (): mixed => $plan250()->handoffRows()[0]['handoff_channel'],
    'second row channel' => static fn (): mixed => $plan250()->handoffRows()[1]['handoff_channel'],
    'third row channel' => static fn (): mixed => $plan250()->handoffRows()[2]['handoff_channel'],
    'fourth row channel' => static fn (): mixed => $plan250()->handoffRows()[3]['handoff_channel'],
    'first pointer generations' => static fn (): mixed => $plan250()->handoffRows()[0]['pointer_map_generations'],
    'second barrier pages' => static fn (): mixed => $plan250()->handoffRows()[1]['pointer_map_barrier_pages'],
    'fourth payload sources' => static fn (): mixed => $plan250()->handoffRows()[3]['payload_source_pages'],
    'fifth duplicate pointer map' => static fn (): mixed => $plan250()->handoffRows()[4]['duplicate_pointer_map_barrier'],
    'fifth pointer generations' => static fn (): mixed => $plan250()->handoffRows()[4]['pointer_map_generations'],
    'sixth freeblock source open' => static fn (): mixed => $plan250()->handoffRows()[5]['freeblock_source_open'],
    'last row next page' => static fn (): mixed => $plan250()->handoffRows()[6]['next_handoff_page'],
    'ordinals' => static fn (): mixed => array_column($plan250()->handoffRows(), 'handoff_ordinal'),
    'checkpoint ordinals' => static fn (): mixed => array_column($plan250()->handoffRows(), 'checkpoint_ordinal'),
    'row states' => static fn (): mixed => array_column($plan250()->handoffRows(), 'handoff_state'),
    'row checkpoint token flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'checkpoint_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'handoff_link_current'),
    'row pointer barrier flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'pointer_map_barrier_before_source'),
    'row freeblock before payload flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'freeblock_source_open_before_payload'),
    'row payload ready flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'payload_source_checkpoint_ready'),
    'row duplicate generation flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'duplicate_pointer_map_keeps_generation'),
    'row tail exclusion flags' => static fn (): mixed => array_column($plan250()->handoffRows(), 'tail_page_excluded_from_handoff'),
    'batch size three row count' => static fn (): mixed => $plan250(3)->handoffSummary()['handoff_row_count'],
    'batch size three pages' => static fn (): mixed => $plan250(3)->handoffPages(),
    'batch size three duplicate pointer maps' => static fn (): mixed => $plan250(3)->duplicatePointerMapBarrierPages(),
    'dependency closure' => static fn (): mixed => $plan250()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan250()->handoffSummary()['non_overlap'], 'does not repeat next247'),
    'checkpoint action' => static fn (): mixed => $plan250()->checkpointPlan->toArray()['action'],
    'checkpoint row count' => static fn (): mixed => $plan250()->checkpointPlan->checkpointSummary()['checkpoint_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message250(static fn () => $plan250(0)),
];

$expected250 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next250',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next250-ready',
    'handoff row count' => 7,
    'handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'handoff pages match checkpoint pages' => true,
    'pointer map barrier pages' => [2, 105],
    'summary pointer map barrier pages' => [2, 105],
    'freeblock source pages' => [3],
    'summary freeblock source pages' => [3],
    'payload source pages' => [106, 107, 108],
    'summary payload source pages' => [106, 107, 108],
    'duplicate pointer map barrier pages' => [105],
    'summary duplicate pointer map barrier pages' => [105],
    'handoff errors' => [],
    'summary handoff errors' => [],
    'all checkpoint tokens match' => true,
    'all handoff links current' => true,
    'all pointer map barriers before sources' => true,
    'all freeblock sources open before payload' => true,
    'all payload sources checkpoint ready' => true,
    'all duplicate pointer maps keep generation' => true,
    'all tail pages excluded from handoff' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'current-source-next250-freeblock-handoff-admitted',
    'second row state' => 'current-source-next250-freeblock-handoff-admitted',
    'first row channel' => 'pointer-map-barrier',
    'second row channel' => 'freeblock-source',
    'third row channel' => 'pointer-map-barrier',
    'fourth row channel' => 'payload-source',
    'first pointer generations' => ['2:1'],
    'second barrier pages' => [2],
    'fourth payload sources' => [106],
    'fifth duplicate pointer map' => true,
    'fifth pointer generations' => ['2:1', '105:2'],
    'sixth freeblock source open' => true,
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'checkpoint ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => array_fill(0, 7, 'current-source-next250-freeblock-handoff-admitted'),
    'row checkpoint token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer barrier flags' => [true, true, true, true, true, true, true],
    'row freeblock before payload flags' => [true, true, true, true, true, true, true],
    'row payload ready flags' => [true, true, true, true, true, true, true],
    'row duplicate generation flags' => [true, true, true, true, true, true, true],
    'row tail exclusion flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three duplicate pointer maps' => [],
    'dependency closure' => 'no new support component needed; next250 reuses next247 checkpoint rows and validates the next current-source freeblock/payload handoff barriers',
    'non overlap' => true,
    'checkpoint action' => 'btree-vacuum-pointermap-freeblock-current-source-next247',
    'checkpoint row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases250 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next250 ' . $name] = static function (TestRunner $t) use ($callback, $expected250, $name): void {
        $t->same($expected250[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next250 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan250): void {
        $plan = $plan250();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->handoffPages());
        $t->same([2, 105], $plan->pointerMapBarrierPages());
        $t->same([3], $plan->freeblockSourcePages());
        $t->same([106, 107, 108], $plan->payloadSourcePages());
        $t->same([105], $plan->duplicatePointerMapBarrierPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->handoffRows(), 'handoff_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'checkpoint_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'handoff_link_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'pointer_map_barrier_before_source'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'freeblock_source_open_before_payload'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'payload_source_checkpoint_ready'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'duplicate_pointer_map_keeps_generation'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'tail_page_excluded_from_handoff'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->handoffTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next250-ready', $summary['status']);
        $t->same(true, $summary['handoff_pages_match_checkpoint_pages']);
        $t->same(true, $summary['all_handoff_links_current']);
        $t->same(true, $summary['all_tail_pages_excluded_from_handoff']);
    };
}

return $tests;
