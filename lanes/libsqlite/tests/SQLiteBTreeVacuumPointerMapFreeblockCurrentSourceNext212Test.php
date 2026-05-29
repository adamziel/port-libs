<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage212 = static function (int $pageCount): string {
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

$putPointerMapEntry212 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database212 = static function () use ($makeFirstPage212, $putPointerMapEntry212): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage212(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next212', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(78 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry212($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan212 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database212;

    $database = $database212();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext212(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next212-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message212 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases212 = [
    'action label' => static fn (): mixed => $plan212()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan212()->applySummary()['status'],
    'apply row count' => static fn (): mixed => $plan212()->applySummary()['apply_row_count'],
    'apply pages' => static fn (): mixed => $plan212()->applyPages(),
    'summary apply pages' => static fn (): mixed => $plan212()->applySummary()['apply_pages'],
    'pointer map apply pages' => static fn (): mixed => $plan212()->pointerMapApplyPages(),
    'payload apply pages' => static fn (): mixed => $plan212()->payloadApplyPages(),
    'writer source pages' => static fn (): mixed => $plan212()->applySummary()['writer_source_pages'],
    'apply matches writer source pages' => static fn (): mixed => $plan212()->applySummary()['apply_matches_writer_source_pages'],
    'apply errors' => static fn (): mixed => $plan212()->applyErrors(),
    'summary apply errors' => static fn (): mixed => $plan212()->applySummary()['apply_errors'],
    'all source tokens match' => static fn (): mixed => $plan212()->applySummary()['all_source_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan212()->applySummary()['all_pointer_maps_applied_before_payload'],
    'all freeblock receipts carried' => static fn (): mixed => $plan212()->applySummary()['all_freeblock_receipts_carried'],
    'all tail pages fenced' => static fn (): mixed => $plan212()->applySummary()['all_tail_pages_fenced_for_apply'],
    'all apply chains valid' => static fn (): mixed => $plan212()->applySummary()['all_apply_chains_valid'],
    'apply token count' => static fn (): mixed => count($plan212()->applyTokens()),
    'apply token lengths' => static fn (): mixed => array_map('strlen', $plan212()->applyTokens()),
    'apply signature length' => static fn (): mixed => strlen($plan212()->applySummary()['apply_signature']),
    'next writer token length' => static fn (): mixed => strlen($plan212()->applySummary()['next_writer_apply_token']),
    'first apply channel' => static fn (): mixed => $plan212()->applyRows()[0]['apply_channel'],
    'first apply pages' => static fn (): mixed => $plan212()->applyRows()[0]['apply_pages'],
    'first visible pages' => static fn (): mixed => $plan212()->applyRows()[0]['applied_visible_pages'],
    'first previous token' => static fn (): mixed => $plan212()->applyRows()[0]['previous_apply_token'],
    'second apply channel' => static fn (): mixed => $plan212()->applyRows()[1]['apply_channel'],
    'second apply pages' => static fn (): mixed => $plan212()->applyRows()[1]['apply_pages'],
    'second visible pages' => static fn (): mixed => $plan212()->applyRows()[1]['applied_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan212()->applyRows()[1]['previous_apply_token']),
    'third apply channel' => static fn (): mixed => $plan212()->applyRows()[2]['apply_channel'],
    'third apply pages' => static fn (): mixed => $plan212()->applyRows()[2]['apply_pages'],
    'third visible pages' => static fn (): mixed => $plan212()->applyRows()[2]['applied_visible_pages'],
    'fourth apply channel' => static fn (): mixed => $plan212()->applyRows()[3]['apply_channel'],
    'fourth apply pages' => static fn (): mixed => $plan212()->applyRows()[3]['apply_pages'],
    'fourth visible pages' => static fn (): mixed => $plan212()->applyRows()[3]['applied_visible_pages'],
    'fifth apply channel' => static fn (): mixed => $plan212()->applyRows()[4]['apply_channel'],
    'fifth apply pages' => static fn (): mixed => $plan212()->applyRows()[4]['apply_pages'],
    'fifth visible pages' => static fn (): mixed => $plan212()->applyRows()[4]['applied_visible_pages'],
    'sixth apply channel' => static fn (): mixed => $plan212()->applyRows()[5]['apply_channel'],
    'sixth apply pages' => static fn (): mixed => $plan212()->applyRows()[5]['apply_pages'],
    'sixth visible pages' => static fn (): mixed => $plan212()->applyRows()[5]['applied_visible_pages'],
    'apply ordinals' => static fn (): mixed => array_column($plan212()->applyRows(), 'apply_ordinal'),
    'row states' => static fn (): mixed => array_column($plan212()->applyRows(), 'apply_state'),
    'row source token flags' => static fn (): mixed => array_column($plan212()->applyRows(), 'source_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan212()->applyRows(), 'freeblock_receipt_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan212()->applyRows(), 'tail_pages_fenced_for_apply'),
    'row chain flags' => static fn (): mixed => array_column($plan212()->applyRows(), 'apply_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan212()->applyRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan212(3)->applySummary()['apply_row_count'],
    'batch size three pages' => static fn (): mixed => $plan212(3)->applyPages(),
    'batch size three apply batches' => static fn (): mixed => array_column($plan212(3)->applyRows(), 'apply_pages'),
    'batch size three token count' => static fn (): mixed => count($plan212(3)->applyTokens()),
    'dependency closure' => static fn (): mixed => $plan212()->applySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan212()->applySummary()['non_overlap'], 'does not repeat next209'),
    'base action' => static fn (): mixed => $plan212()->basePlan->toArray()['action'],
    'base source rows' => static fn (): mixed => $plan212()->basePlan->writerSourceSummary()['source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message212(static fn () => $plan212(0)),
];

$expected212 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next212-ready',
    'apply row count' => 6,
    'apply pages' => [2, 3, 105, 106, 107, 108],
    'summary apply pages' => [2, 3, 105, 106, 107, 108],
    'pointer map apply pages' => [2, 105],
    'payload apply pages' => [3, 106, 107, 108],
    'writer source pages' => [2, 3, 105, 106, 107, 108],
    'apply matches writer source pages' => true,
    'apply errors' => [],
    'summary apply errors' => [],
    'all source tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts carried' => true,
    'all tail pages fenced' => true,
    'all apply chains valid' => true,
    'apply token count' => 6,
    'apply token lengths' => [64, 64, 64, 64, 64, 64],
    'apply signature length' => 64,
    'next writer token length' => 64,
    'first apply channel' => 'pointer-map',
    'first apply pages' => [2],
    'first visible pages' => [2],
    'first previous token' => null,
    'second apply channel' => 'payload',
    'second apply pages' => [3],
    'second visible pages' => [2, 3],
    'second previous token length' => 64,
    'third apply channel' => 'pointer-map',
    'third apply pages' => [105],
    'third visible pages' => [2, 3, 105],
    'fourth apply channel' => 'payload',
    'fourth apply pages' => [106],
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth apply channel' => 'pointer-map',
    'fifth apply pages' => [105],
    'fifth visible pages' => [2, 3, 105, 106],
    'sixth apply channel' => 'payload',
    'sixth apply pages' => [107, 108],
    'sixth visible pages' => [2, 3, 105, 106, 107, 108],
    'apply ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['current-source-page-apply-ready', 'current-source-page-apply-ready', 'current-source-page-apply-ready', 'current-source-page-apply-ready', 'current-source-page-apply-ready', 'current-source-page-apply-ready'],
    'row source token flags' => [true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108],
    'batch size three row count' => 4,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three apply batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next212 reuses next209 writer-source latch rows, pointer-map source pages, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next209',
    'base source rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases212 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next212 ' . $name] = static function (TestRunner $t) use ($callback, $expected212, $name): void {
        $t->same($expected212[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next212 apply invariant ' . $index] = static function (TestRunner $t) use ($plan212): void {
        $plan = $plan212();
        $summary = $plan->applySummary();

        $t->same([], $plan->applyErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->applyPages());
        $t->same([2, 105], $plan->pointerMapApplyPages());
        $t->same([3, 106, 107, 108], $plan->payloadApplyPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->applyRows(), 'apply_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'source_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'freeblock_receipt_carried'));
        $t->same([true, true, true, true, true, true], array_column($plan->applyRows(), 'tail_pages_fenced_for_apply'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->applyTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next212-ready', $summary['status']);
        $t->same(true, $summary['apply_matches_writer_source_pages']);
    };
}

return $tests;
