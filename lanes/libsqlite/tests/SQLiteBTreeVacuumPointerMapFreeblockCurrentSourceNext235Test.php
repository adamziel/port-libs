<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage235 = static function (int $pageCount): string {
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

$putPointerMapEntry235 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database235 = static function () use ($makeFirstPage235, $putPointerMapEntry235): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage235(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next235', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(84 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry235($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan235 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database235;

    $database = $database235();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext235(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next235-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message235 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases235 = [
    'action label' => static fn (): mixed => $plan235()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan235()->checkpointSummary()['status'],
    'checkpoint row count' => static fn (): mixed => $plan235()->checkpointSummary()['checkpoint_row_count'],
    'checkpoint pages' => static fn (): mixed => $plan235()->checkpointPages(),
    'summary checkpoint pages' => static fn (): mixed => $plan235()->checkpointSummary()['checkpoint_pages'],
    'summary handoff pages' => static fn (): mixed => $plan235()->checkpointSummary()['handoff_pages'],
    'checkpoint pages match handoff pages' => static fn (): mixed => $plan235()->checkpointSummary()['checkpoint_pages_match_handoff_pages'],
    'duplicate pointer map checkpoint pages' => static fn (): mixed => $plan235()->duplicatePointerMapCheckpointPages(),
    'summary duplicate pointer map checkpoint pages' => static fn (): mixed => $plan235()->checkpointSummary()['duplicate_pointer_map_checkpoint_pages'],
    'reusable payload pages' => static fn (): mixed => $plan235()->reusablePayloadPages(),
    'summary reusable payload pages' => static fn (): mixed => $plan235()->checkpointSummary()['reusable_payload_pages'],
    'checkpoint errors' => static fn (): mixed => $plan235()->checkpointErrors(),
    'summary checkpoint errors' => static fn (): mixed => $plan235()->checkpointSummary()['checkpoint_errors'],
    'all handoff tokens match' => static fn (): mixed => $plan235()->checkpointSummary()['all_handoff_tokens_match'],
    'all current source links closed' => static fn (): mixed => $plan235()->checkpointSummary()['all_current_source_links_closed'],
    'all pointer map generations preserved' => static fn (): mixed => $plan235()->checkpointSummary()['all_pointer_map_generations_preserved'],
    'all payload reuse waits for pointer map' => static fn (): mixed => $plan235()->checkpointSummary()['all_payload_reuse_waits_for_pointer_map'],
    'all freeblock receipts visible' => static fn (): mixed => $plan235()->checkpointSummary()['all_freeblock_receipts_visible'],
    'all tail pages remain fenced' => static fn (): mixed => $plan235()->checkpointSummary()['all_tail_pages_remain_fenced'],
    'checkpoint token count' => static fn (): mixed => count($plan235()->checkpointTokens()),
    'checkpoint token lengths' => static fn (): mixed => array_map('strlen', $plan235()->checkpointTokens()),
    'checkpoint signature length' => static fn (): mixed => strlen($plan235()->checkpointSummary()['checkpoint_signature']),
    'current source token length' => static fn (): mixed => strlen($plan235()->checkpointSummary()['current_source_next235_token']),
    'first checkpoint channel' => static fn (): mixed => $plan235()->checkpointRows()[0]['checkpoint_channel'],
    'first checkpoint page' => static fn (): mixed => $plan235()->checkpointRows()[0]['checkpoint_page'],
    'first visible pointer maps' => static fn (): mixed => $plan235()->checkpointRows()[0]['visible_pointer_map_pages'],
    'first visible freeblock pages' => static fn (): mixed => $plan235()->checkpointRows()[0]['visible_freeblock_receipt_pages'],
    'first payload reusable' => static fn (): mixed => $plan235()->checkpointRows()[0]['payload_reusable_after_checkpoint'],
    'second checkpoint channel' => static fn (): mixed => $plan235()->checkpointRows()[1]['checkpoint_channel'],
    'second checkpoint page' => static fn (): mixed => $plan235()->checkpointRows()[1]['checkpoint_page'],
    'second payload reusable' => static fn (): mixed => $plan235()->checkpointRows()[1]['payload_reusable_after_checkpoint'],
    'third pointer map generation' => static fn (): mixed => $plan235()->checkpointRows()[2]['pointer_map_generation'],
    'fifth pointer map generation' => static fn (): mixed => $plan235()->checkpointRows()[4]['pointer_map_generation'],
    'fifth duplicate pointer map checkpoint' => static fn (): mixed => $plan235()->checkpointRows()[4]['duplicate_pointer_map_checkpoint'],
    'last current source link closed' => static fn (): mixed => $plan235()->checkpointRows()[6]['current_source_link_closed'],
    'last checkpoint page' => static fn (): mixed => $plan235()->checkpointRows()[6]['checkpoint_page'],
    'checkpoint ordinals' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'checkpoint_ordinal'),
    'handoff ordinals' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'handoff_ordinal'),
    'row states' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'checkpoint_state'),
    'row handoff token flags' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'handoff_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'current_source_link_closed'),
    'row pointer generation flags' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'pointer_map_generation_preserved'),
    'row payload wait flags' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'payload_reuse_waits_for_pointer_map'),
    'row freeblock flags' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'freeblock_receipt_visible_at_checkpoint'),
    'row tail fence flags' => static fn (): mixed => array_column($plan235()->checkpointRows(), 'tail_pages_remain_fenced'),
    'batch size three row count' => static fn (): mixed => $plan235(3)->checkpointSummary()['checkpoint_row_count'],
    'batch size three pages' => static fn (): mixed => $plan235(3)->checkpointPages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan235(3)->reusablePayloadPages(),
    'batch size three duplicate pointer map pages' => static fn (): mixed => $plan235(3)->duplicatePointerMapCheckpointPages(),
    'dependency closure' => static fn (): mixed => $plan235()->checkpointSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan235()->checkpointSummary()['non_overlap'], 'does not repeat next232'),
    'handoff action' => static fn (): mixed => $plan235()->handoffPlan->toArray()['action'],
    'handoff row count' => static fn (): mixed => $plan235()->handoffPlan->handoffSummary()['handoff_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message235(static fn () => $plan235(0)),
];

$expected235 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next235',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next235-ready',
    'checkpoint row count' => 7,
    'checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary handoff pages' => [2, 3, 105, 106, 105, 107, 108],
    'checkpoint pages match handoff pages' => true,
    'duplicate pointer map checkpoint pages' => [105],
    'summary duplicate pointer map checkpoint pages' => [105],
    'reusable payload pages' => [3, 106, 107, 108],
    'summary reusable payload pages' => [3, 106, 107, 108],
    'checkpoint errors' => [],
    'summary checkpoint errors' => [],
    'all handoff tokens match' => true,
    'all current source links closed' => true,
    'all pointer map generations preserved' => true,
    'all payload reuse waits for pointer map' => true,
    'all freeblock receipts visible' => true,
    'all tail pages remain fenced' => true,
    'checkpoint token count' => 7,
    'checkpoint token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'checkpoint signature length' => 64,
    'current source token length' => 64,
    'first checkpoint channel' => 'pointer-map',
    'first checkpoint page' => 2,
    'first visible pointer maps' => [2],
    'first visible freeblock pages' => [2],
    'first payload reusable' => false,
    'second checkpoint channel' => 'payload',
    'second checkpoint page' => 3,
    'second payload reusable' => true,
    'third pointer map generation' => 1,
    'fifth pointer map generation' => 2,
    'fifth duplicate pointer map checkpoint' => true,
    'last current source link closed' => true,
    'last checkpoint page' => 108,
    'checkpoint ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next235-reusable-payload-checkpointed', 'current-source-next235-reusable-payload-checkpointed', 'current-source-next235-reusable-payload-checkpointed', 'current-source-next235-reusable-payload-checkpointed', 'current-source-next235-reusable-payload-checkpointed', 'current-source-next235-reusable-payload-checkpointed', 'current-source-next235-reusable-payload-checkpointed'],
    'row handoff token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer generation flags' => [true, true, true, true, true, true, true],
    'row payload wait flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'batch size three duplicate pointer map pages' => [],
    'dependency closure' => 'no new support component needed; next235 reuses next232 handoff rows and adds reusable-payload checkpoint admission only',
    'non overlap' => true,
    'handoff action' => 'btree-vacuum-pointermap-freeblock-current-source-next232',
    'handoff row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases235 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next235 ' . $name] = static function (TestRunner $t) use ($callback, $expected235, $name): void {
        $t->same($expected235[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next235 checkpoint invariant ' . $index] = static function (TestRunner $t) use ($plan235): void {
        $plan = $plan235();
        $summary = $plan->checkpointSummary();

        $t->same([], $plan->checkpointErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->checkpointPages());
        $t->same([105], $plan->duplicatePointerMapCheckpointPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->checkpointRows(), 'checkpoint_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'handoff_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'current_source_link_closed'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'pointer_map_generation_preserved'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'payload_reuse_waits_for_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'freeblock_receipt_visible_at_checkpoint'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'tail_pages_remain_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->checkpointTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next235-ready', $summary['status']);
        $t->same(true, $summary['checkpoint_pages_match_handoff_pages']);
        $t->same(true, $summary['all_payload_reuse_waits_for_pointer_map']);
    };
}

return $tests;
