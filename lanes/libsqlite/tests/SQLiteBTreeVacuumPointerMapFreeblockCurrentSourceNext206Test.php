<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage206 = static function (int $pageCount): string {
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

$putPointerMapEntry206 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database206 = static function () use ($makeFirstPage206, $putPointerMapEntry206): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage206(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next206', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry206($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan206 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database206;

    $database = $database206();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPageSealAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next206-current-source-seal-', 50),
        3,
        true,
        $batchSize,
    );
};

$message206 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases206 = [
    'action label' => static fn (): mixed => $plan206()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['status'],
    'seal row count' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['seal_row_count'],
    'sealed pages' => static fn (): mixed => $plan206()->sealedPages(),
    'summary sealed pages' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['sealed_pages'],
    'sealed pointer map pages' => static fn (): mixed => $plan206()->sealedPointerMapPages(),
    'sealed payload pages' => static fn (): mixed => $plan206()->sealedPayloadPages(),
    'cursor row count' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['cursor_row_count'],
    'seal errors' => static fn (): mixed => $plan206()->sealErrors(),
    'summary seal errors' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['seal_errors'],
    'all cursor tokens match' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['all_cursor_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['all_pointer_maps_sealed_before_payload'],
    'all leaf freeblocks sealed' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['all_leaf_freeblocks_sealed'],
    'all tail pages fenced' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['all_tail_pages_fenced'],
    'all seal chains valid' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['all_seal_chains_valid'],
    'seal token count' => static fn (): mixed => count($plan206()->sealTokens()),
    'seal token lengths' => static fn (): mixed => array_map('strlen', $plan206()->sealTokens()),
    'seal signature length' => static fn (): mixed => strlen($plan206()->sealedCurrentSourceSummary()['seal_signature']),
    'next writer freeblock token length' => static fn (): mixed => strlen($plan206()->sealedCurrentSourceSummary()['next_writer_freeblock_source_token']),
    'first seal channel' => static fn (): mixed => $plan206()->sealRows()[0]['seal_channel'],
    'first seal pages' => static fn (): mixed => $plan206()->sealRows()[0]['sealed_pages'],
    'first seal previous token' => static fn (): mixed => $plan206()->sealRows()[0]['previous_seal_token'],
    'second seal channel' => static fn (): mixed => $plan206()->sealRows()[1]['seal_channel'],
    'second seal pages' => static fn (): mixed => $plan206()->sealRows()[1]['sealed_pages'],
    'second seal previous token length' => static fn (): mixed => strlen((string) $plan206()->sealRows()[1]['previous_seal_token']),
    'third seal channel' => static fn (): mixed => $plan206()->sealRows()[2]['seal_channel'],
    'third seal pages' => static fn (): mixed => $plan206()->sealRows()[2]['sealed_pages'],
    'fourth seal channel' => static fn (): mixed => $plan206()->sealRows()[3]['seal_channel'],
    'fourth seal pages' => static fn (): mixed => $plan206()->sealRows()[3]['sealed_pages'],
    'fifth seal channel' => static fn (): mixed => $plan206()->sealRows()[4]['seal_channel'],
    'fifth seal pages' => static fn (): mixed => $plan206()->sealRows()[4]['sealed_pages'],
    'sixth seal channel' => static fn (): mixed => $plan206()->sealRows()[5]['seal_channel'],
    'sixth seal pages' => static fn (): mixed => $plan206()->sealRows()[5]['sealed_pages'],
    'row ordinals' => static fn (): mixed => array_column($plan206()->sealRows(), 'seal_ordinal'),
    'row states' => static fn (): mixed => array_column($plan206()->sealRows(), 'seal_state'),
    'row cursor token flags' => static fn (): mixed => array_column($plan206()->sealRows(), 'cursor_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan206()->sealRows(), 'leaf_freeblock_sealed'),
    'row tail fence flags' => static fn (): mixed => array_column($plan206()->sealRows(), 'tail_pages_fenced'),
    'row chain flags' => static fn (): mixed => array_column($plan206()->sealRows(), 'seal_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan206()->sealRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan206(3)->sealedCurrentSourceSummary()['seal_row_count'],
    'batch size three sealed pages' => static fn (): mixed => $plan206(3)->sealedPages(),
    'batch size three seal batches' => static fn (): mixed => array_column($plan206(3)->sealRows(), 'sealed_pages'),
    'batch size three token count' => static fn (): mixed => count($plan206(3)->sealTokens()),
    'dependency closure' => static fn (): mixed => $plan206()->sealedCurrentSourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan206()->sealedCurrentSourceSummary()['non_overlap'], 'does not repeat next203'),
    'base action' => static fn (): mixed => $plan206()->basePlan->toArray()['action'],
    'base cursor rows' => static fn (): mixed => $plan206()->basePlan->currentSourceCursorSummary()['cursor_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message206(static fn () => $plan206(0)),
];

$expected206 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next206',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next206-ready',
    'seal row count' => 6,
    'sealed pages' => [2, 3, 105, 106, 107, 108],
    'summary sealed pages' => [2, 3, 105, 106, 107, 108],
    'sealed pointer map pages' => [2, 105],
    'sealed payload pages' => [3, 106, 107, 108],
    'cursor row count' => 3,
    'seal errors' => [],
    'summary seal errors' => [],
    'all cursor tokens match' => true,
    'all pointer maps before payload' => true,
    'all leaf freeblocks sealed' => true,
    'all tail pages fenced' => true,
    'all seal chains valid' => true,
    'seal token count' => 6,
    'seal token lengths' => [64, 64, 64, 64, 64, 64],
    'seal signature length' => 64,
    'next writer freeblock token length' => 64,
    'first seal channel' => 'pointer-map',
    'first seal pages' => [2],
    'first seal previous token' => null,
    'second seal channel' => 'payload',
    'second seal pages' => [3],
    'second seal previous token length' => 64,
    'third seal channel' => 'pointer-map',
    'third seal pages' => [105],
    'fourth seal channel' => 'payload',
    'fourth seal pages' => [106],
    'fifth seal channel' => 'pointer-map',
    'fifth seal pages' => [105],
    'sixth seal channel' => 'payload',
    'sixth seal pages' => [107, 108],
    'row ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['sealed-current-source', 'sealed-current-source', 'sealed-current-source', 'sealed-current-source', 'sealed-current-source', 'sealed-current-source'],
    'row cursor token flags' => [true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108],
    'batch size three row count' => 4,
    'batch size three sealed pages' => [2, 3, 105, 106, 107, 108],
    'batch size three seal batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next206 reuses next203 current-source cursor batches, pointer-map pages, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next203',
    'base cursor rows' => 3,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases206 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next206 ' . $name] = static function (TestRunner $t) use ($callback, $expected206, $name): void {
        $t->same($expected206[$name], $callback());
    };
}

foreach (range(1, 72) as $index) {
    $tests['btree vacuum pointermap freeblock current source next206 seal invariant ' . $index] = static function (TestRunner $t) use ($plan206): void {
        $plan = $plan206();
        $summary = $plan->sealedCurrentSourceSummary();

        $t->same([], $plan->sealErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->sealedPages());
        $t->same([2, 105], $plan->sealedPointerMapPages());
        $t->same([3, 106, 107, 108], $plan->sealedPayloadPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->sealRows(), 'seal_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->sealRows(), 'cursor_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->sealRows(), 'leaf_freeblock_sealed'));
        $t->same([true, true, true, true, true, true], array_column($plan->sealRows(), 'tail_pages_fenced'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sealTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next206-ready', $summary['status']);
    };
}

return $tests;
