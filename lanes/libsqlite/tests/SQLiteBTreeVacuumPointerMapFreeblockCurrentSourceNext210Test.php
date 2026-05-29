<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage210 = static function (int $pageCount): string {
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

$putPointerMapEntry210 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database210 = static function () use ($makeFirstPage210, $putPointerMapEntry210): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage210(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next210', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(76 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry210($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan210 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database210;

    $database = $database210();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext210(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next210-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message210 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases210 = [
    'action label' => static fn (): mixed => $plan210()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan210()->applySummary()['status'],
    'apply row count' => static fn (): mixed => $plan210()->applySummary()['apply_row_count'],
    'applied pages' => static fn (): mixed => $plan210()->appliedPages(),
    'summary applied pages' => static fn (): mixed => $plan210()->applySummary()['applied_pages'],
    'applied pointer map pages' => static fn (): mixed => $plan210()->appliedPointerMapPages(),
    'applied payload pages' => static fn (): mixed => $plan210()->appliedPayloadPages(),
    'writer source pages' => static fn (): mixed => $plan210()->applySummary()['writer_source_pages'],
    'matches writer source pages' => static fn (): mixed => $plan210()->applySummary()['apply_matches_writer_source_pages'],
    'apply errors' => static fn (): mixed => $plan210()->applyErrors(),
    'summary errors' => static fn (): mixed => $plan210()->applySummary()['apply_errors'],
    'all writer tokens match' => static fn (): mixed => $plan210()->applySummary()['all_writer_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan210()->applySummary()['all_pointer_maps_applied_before_payload'],
    'all tail pages fenced' => static fn (): mixed => $plan210()->applySummary()['all_tail_pages_remain_fenced'],
    'all apply chains valid' => static fn (): mixed => $plan210()->applySummary()['all_apply_chains_valid'],
    'all epochs ready' => static fn (): mixed => $plan210()->applySummary()['all_current_source_epochs_ready'],
    'token count' => static fn (): mixed => count($plan210()->applyTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan210()->applyTokens()),
    'apply signature length' => static fn (): mixed => strlen($plan210()->applySummary()['apply_signature']),
    'next apply token length' => static fn (): mixed => strlen($plan210()->applySummary()['next_current_source_apply_token']),
    'first channel' => static fn (): mixed => $plan210()->applyRows()[0]['apply_channel'],
    'first pages' => static fn (): mixed => $plan210()->applyRows()[0]['apply_pages'],
    'first visible pages' => static fn (): mixed => $plan210()->applyRows()[0]['applied_visible_pages'],
    'first pointer pages' => static fn (): mixed => $plan210()->applyRows()[0]['applied_pointer_map_pages'],
    'first previous token' => static fn (): mixed => $plan210()->applyRows()[0]['previous_apply_token'],
    'second channel' => static fn (): mixed => $plan210()->applyRows()[1]['apply_channel'],
    'second pages' => static fn (): mixed => $plan210()->applyRows()[1]['apply_pages'],
    'second visible pages' => static fn (): mixed => $plan210()->applyRows()[1]['applied_visible_pages'],
    'second pointer pages' => static fn (): mixed => $plan210()->applyRows()[1]['applied_pointer_map_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan210()->applyRows()[1]['previous_apply_token']),
    'third channel' => static fn (): mixed => $plan210()->applyRows()[2]['apply_channel'],
    'third pages' => static fn (): mixed => $plan210()->applyRows()[2]['apply_pages'],
    'third pointer pages' => static fn (): mixed => $plan210()->applyRows()[2]['applied_pointer_map_pages'],
    'fourth channel' => static fn (): mixed => $plan210()->applyRows()[3]['apply_channel'],
    'fourth pages' => static fn (): mixed => $plan210()->applyRows()[3]['apply_pages'],
    'fourth visible pages' => static fn (): mixed => $plan210()->applyRows()[3]['applied_visible_pages'],
    'fifth channel' => static fn (): mixed => $plan210()->applyRows()[4]['apply_channel'],
    'fifth pages' => static fn (): mixed => $plan210()->applyRows()[4]['apply_pages'],
    'sixth channel' => static fn (): mixed => $plan210()->applyRows()[5]['apply_channel'],
    'sixth pages' => static fn (): mixed => $plan210()->applyRows()[5]['apply_pages'],
    'sixth visible pages' => static fn (): mixed => $plan210()->applyRows()[5]['applied_visible_pages'],
    'ordinals' => static fn (): mixed => array_column($plan210()->applyRows(), 'apply_ordinal'),
    'row states' => static fn (): mixed => array_column($plan210()->applyRows(), 'apply_state'),
    'writer token flags' => static fn (): mixed => array_column($plan210()->applyRows(), 'writer_token_matches'),
    'dependency flags' => static fn (): mixed => array_column($plan210()->applyRows(), 'pointer_map_dependency_satisfied'),
    'tail fence flags' => static fn (): mixed => array_column($plan210()->applyRows(), 'tail_pages_remain_fenced'),
    'chain flags' => static fn (): mixed => array_column($plan210()->applyRows(), 'apply_chain_valid'),
    'epoch flags' => static fn (): mixed => array_column($plan210()->applyRows(), 'current_source_epoch_ready'),
    'high water pages' => static fn (): mixed => array_column($plan210()->applyRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan210(3)->applySummary()['apply_row_count'],
    'batch size three applied pages' => static fn (): mixed => $plan210(3)->appliedPages(),
    'batch size three apply batches' => static fn (): mixed => array_column($plan210(3)->applyRows(), 'apply_pages'),
    'batch size three token count' => static fn (): mixed => count($plan210(3)->applyTokens()),
    'dependency closure' => static fn (): mixed => $plan210()->applySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan210()->applySummary()['non_overlap'], 'does not repeat next209'),
    'base action' => static fn (): mixed => $plan210()->basePlan->toArray()['action'],
    'base writer rows' => static fn (): mixed => $plan210()->basePlan->writerSourceSummary()['source_row_count'],
    'base writer pages' => static fn (): mixed => $plan210()->basePlan->writerSourcePages(),
    'bad batch size rejected' => static fn (): mixed => $message210(static fn () => $plan210(0)),
];

$expected210 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next210',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next210-ready',
    'apply row count' => 6,
    'applied pages' => [2, 3, 105, 106, 107, 108],
    'summary applied pages' => [2, 3, 105, 106, 107, 108],
    'applied pointer map pages' => [2, 105],
    'applied payload pages' => [3, 106, 107, 108],
    'writer source pages' => [2, 3, 105, 106, 107, 108],
    'matches writer source pages' => true,
    'apply errors' => [],
    'summary errors' => [],
    'all writer tokens match' => true,
    'all pointer maps before payload' => true,
    'all tail pages fenced' => true,
    'all apply chains valid' => true,
    'all epochs ready' => true,
    'token count' => 6,
    'token lengths' => [64, 64, 64, 64, 64, 64],
    'apply signature length' => 64,
    'next apply token length' => 64,
    'first channel' => 'pointer-map-apply',
    'first pages' => [2],
    'first visible pages' => [2],
    'first pointer pages' => [2],
    'first previous token' => null,
    'second channel' => 'payload-apply',
    'second pages' => [3],
    'second visible pages' => [2, 3],
    'second pointer pages' => [2],
    'second previous token length' => 64,
    'third channel' => 'pointer-map-apply',
    'third pages' => [105],
    'third pointer pages' => [2, 105],
    'fourth channel' => 'payload-apply',
    'fourth pages' => [106],
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth channel' => 'pointer-map-apply',
    'fifth pages' => [105],
    'sixth channel' => 'payload-apply',
    'sixth pages' => [107, 108],
    'sixth visible pages' => [2, 3, 105, 106, 107, 108],
    'ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['current-source-writer-applied', 'current-source-writer-applied', 'current-source-writer-applied', 'current-source-writer-applied', 'current-source-writer-applied', 'current-source-writer-applied'],
    'writer token flags' => [true, true, true, true, true, true],
    'dependency flags' => [true, true, true, true, true, true],
    'tail fence flags' => [true, true, true, true, true, true],
    'chain flags' => [true, true, true, true, true, true],
    'epoch flags' => [true, true, true, true, true, true],
    'high water pages' => [3, 3, 106, 106, 108, 108],
    'batch size three row count' => 4,
    'batch size three applied pages' => [2, 3, 105, 106, 107, 108],
    'batch size three apply batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next210 reuses next209 writer-source rows, pointer-map ordering, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next209',
    'base writer rows' => 6,
    'base writer pages' => [2, 3, 105, 106, 107, 108],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases210 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next210 ' . $name] = static function (TestRunner $t) use ($callback, $expected210, $name): void {
        $t->same($expected210[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next210 apply invariant ' . $index] = static function (TestRunner $t) use ($plan210): void {
        $plan = $plan210();
        $summary = $plan->applySummary();

        $t->same([], $plan->applyErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->appliedPages());
        $t->same([2, 105], $plan->appliedPointerMapPages());
        $t->same([3, 106, 107, 108], $plan->appliedPayloadPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->applyRows(), 'apply_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'writer_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'pointer_map_dependency_satisfied'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'tail_pages_remain_fenced'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'current_source_epoch_ready'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->applyTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next210-ready', $summary['status']);
        $t->same(true, $summary['apply_matches_writer_source_pages']);
    };
}

return $tests;
