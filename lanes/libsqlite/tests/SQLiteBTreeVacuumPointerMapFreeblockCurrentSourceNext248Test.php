<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage248 = static function (int $pageCount): string {
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

$putPointerMapEntry248 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database248 = static function () use ($makeFirstPage248, $putPointerMapEntry248): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage248(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next248', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry248($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan248 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database248;

    $database = $database248();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafSealFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next248-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message248 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases248 = [
    'action label' => static fn (): mixed => $plan248()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan248()->sealSummary()['status'],
    'seal row count' => static fn (): mixed => $plan248()->sealSummary()['seal_row_count'],
    'sealed pages' => static fn (): mixed => $plan248()->sealedPages(),
    'summary sealed pages' => static fn (): mixed => $plan248()->sealSummary()['sealed_pages'],
    'summary checkpoint pages' => static fn (): mixed => $plan248()->sealSummary()['checkpoint_pages'],
    'sealed pages match checkpoints' => static fn (): mixed => $plan248()->sealSummary()['sealed_pages_match_checkpoints'],
    'final pointer map pages' => static fn (): mixed => $plan248()->finalPointerMapPages(),
    'summary final pointer map pages' => static fn (): mixed => $plan248()->sealSummary()['final_pointer_map_pages'],
    'freeblock publication pages' => static fn (): mixed => $plan248()->freeblockPublicationPages(),
    'summary freeblock publication pages' => static fn (): mixed => $plan248()->sealSummary()['freeblock_publication_pages'],
    'reusable payload pages' => static fn (): mixed => $plan248()->reusablePayloadPages(),
    'summary reusable payload pages' => static fn (): mixed => $plan248()->sealSummary()['reusable_payload_pages'],
    'seal errors' => static fn (): mixed => $plan248()->sealErrors(),
    'summary seal errors' => static fn (): mixed => $plan248()->sealSummary()['seal_errors'],
    'all checkpoint tokens match' => static fn (): mixed => $plan248()->sealSummary()['all_checkpoint_tokens_match'],
    'all pointer maps visible before freeblock publication' => static fn (): mixed => $plan248()->sealSummary()['all_pointer_maps_visible_before_freeblock_publication'],
    'all freeblock publications have receipts' => static fn (): mixed => $plan248()->sealSummary()['all_freeblock_publications_have_receipts'],
    'all payload reuse waits for freeblock publication' => static fn (): mixed => $plan248()->sealSummary()['all_payload_reuse_waits_for_freeblock_publication'],
    'all tail pages remain fenced' => static fn (): mixed => $plan248()->sealSummary()['all_tail_pages_remain_fenced'],
    'seal token count' => static fn (): mixed => count($plan248()->sealTokens()),
    'seal token lengths' => static fn (): mixed => array_map('strlen', $plan248()->sealTokens()),
    'seal signature length' => static fn (): mixed => strlen($plan248()->sealSummary()['seal_signature']),
    'current source token length' => static fn (): mixed => strlen($plan248()->sealSummary()['current_source_next248_token']),
    'first seal channel' => static fn (): mixed => $plan248()->sealRows()[0]['seal_channel'],
    'first sealed page' => static fn (): mixed => $plan248()->sealRows()[0]['sealed_page'],
    'first visible pointer maps' => static fn (): mixed => $plan248()->sealRows()[0]['visible_pointer_map_pages'],
    'first publishes freeblock' => static fn (): mixed => $plan248()->sealRows()[0]['publishes_freeblock_after_pointer_map'],
    'second seal channel' => static fn (): mixed => $plan248()->sealRows()[1]['seal_channel'],
    'second sealed page' => static fn (): mixed => $plan248()->sealRows()[1]['sealed_page'],
    'second payload reusable after seal' => static fn (): mixed => $plan248()->sealRows()[1]['payload_reusable_after_seal'],
    'third pointer map visible pages' => static fn (): mixed => $plan248()->sealRows()[2]['visible_pointer_map_pages'],
    'fifth duplicate pointer map visible pages' => static fn (): mixed => $plan248()->sealRows()[4]['visible_pointer_map_pages'],
    'last sealed page' => static fn (): mixed => $plan248()->sealRows()[6]['sealed_page'],
    'seal ordinals' => static fn (): mixed => array_column($plan248()->sealRows(), 'seal_ordinal'),
    'checkpoint ordinals' => static fn (): mixed => array_column($plan248()->sealRows(), 'checkpoint_ordinal'),
    'row states' => static fn (): mixed => array_column($plan248()->sealRows(), 'seal_state'),
    'row checkpoint token flags' => static fn (): mixed => array_column($plan248()->sealRows(), 'checkpoint_token_matches'),
    'row pointer visibility flags' => static fn (): mixed => array_column($plan248()->sealRows(), 'pointer_maps_visible_before_freeblock_publication'),
    'row freeblock publication flags' => static fn (): mixed => array_column($plan248()->sealRows(), 'publishes_freeblock_after_pointer_map'),
    'row payload wait flags' => static fn (): mixed => array_column($plan248()->sealRows(), 'payload_reuse_waits_for_freeblock_publication'),
    'row tail fence flags' => static fn (): mixed => array_column($plan248()->sealRows(), 'tail_pages_remain_fenced'),
    'batch size three row count' => static fn (): mixed => $plan248(3)->sealSummary()['seal_row_count'],
    'batch size three pages' => static fn (): mixed => $plan248(3)->sealedPages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan248(3)->reusablePayloadPages(),
    'batch size three final pointer map pages' => static fn (): mixed => $plan248(3)->finalPointerMapPages(),
    'dependency closure' => static fn (): mixed => $plan248()->sealSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan248()->sealSummary()['non_overlap'], 'does not repeat next235'),
    'checkpoint action' => static fn (): mixed => $plan248()->checkpointPlan->toArray()['action'],
    'checkpoint row count' => static fn (): mixed => $plan248()->checkpointPlan->checkpointSummary()['checkpoint_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message248(static fn () => $plan248(0)),
];

$expected248 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next248',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next248-ready',
    'seal row count' => 7,
    'sealed pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary sealed pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'sealed pages match checkpoints' => true,
    'final pointer map pages' => [2, 105],
    'summary final pointer map pages' => [2, 105],
    'freeblock publication pages' => [2, 3, 105, 106, 107, 108],
    'summary freeblock publication pages' => [2, 3, 105, 106, 107, 108],
    'reusable payload pages' => [3, 106, 107, 108],
    'summary reusable payload pages' => [3, 106, 107, 108],
    'seal errors' => [],
    'summary seal errors' => [],
    'all checkpoint tokens match' => true,
    'all pointer maps visible before freeblock publication' => true,
    'all freeblock publications have receipts' => true,
    'all payload reuse waits for freeblock publication' => true,
    'all tail pages remain fenced' => true,
    'seal token count' => 7,
    'seal token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'seal signature length' => 64,
    'current source token length' => 64,
    'first seal channel' => 'pointer-map',
    'first sealed page' => 2,
    'first visible pointer maps' => [2],
    'first publishes freeblock' => true,
    'second seal channel' => 'payload',
    'second sealed page' => 3,
    'second payload reusable after seal' => true,
    'third pointer map visible pages' => [2, 105],
    'fifth duplicate pointer map visible pages' => [2, 105],
    'last sealed page' => 108,
    'seal ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'checkpoint ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next248-freeblock-publication-sealed', 'current-source-next248-freeblock-publication-sealed', 'current-source-next248-freeblock-publication-sealed', 'current-source-next248-freeblock-publication-sealed', 'current-source-next248-freeblock-publication-sealed', 'current-source-next248-freeblock-publication-sealed', 'current-source-next248-freeblock-publication-sealed'],
    'row checkpoint token flags' => [true, true, true, true, true, true, true],
    'row pointer visibility flags' => [true, true, true, true, true, true, true],
    'row freeblock publication flags' => [true, true, true, true, true, true, true],
    'row payload wait flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'batch size three final pointer map pages' => [2, 105],
    'dependency closure' => 'no new support component needed; next248 reuses next235 checkpoint rows and seals current-source freeblock publication after pointer-map visibility',
    'non overlap' => true,
    'checkpoint action' => 'btree-vacuum-pointermap-freeblock-current-source-next235',
    'checkpoint row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases248 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next248 ' . $name] = static function (TestRunner $t) use ($callback, $expected248, $name): void {
        $t->same($expected248[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next248 seal invariant ' . $index] = static function (TestRunner $t) use ($plan248): void {
        $plan = $plan248();
        $summary = $plan->sealSummary();

        $t->same([], $plan->sealErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->sealedPages());
        $t->same([2, 105], $plan->finalPointerMapPages());
        $t->same([2, 3, 105, 106, 107, 108], $plan->freeblockPublicationPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->sealRows(), 'seal_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'checkpoint_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'pointer_maps_visible_before_freeblock_publication'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'publishes_freeblock_after_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'payload_reuse_waits_for_freeblock_publication'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'tail_pages_remain_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sealTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next248-ready', $summary['status']);
        $t->same(true, $summary['sealed_pages_match_checkpoints']);
        $t->same(true, $summary['all_payload_reuse_waits_for_freeblock_publication']);
    };
}

return $tests;
