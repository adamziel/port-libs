<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage239 = static function (int $pageCount): string {
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

$putPointerMapEntry239 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database239 = static function () use ($makeFirstPage239, $putPointerMapEntry239): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage239(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next239', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry239($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan239 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database239;

    $database = $database239();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext239(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next239-current-source-drain-', 50),
        3,
        true,
        $batchSize,
    );
};

$message239 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases239 = [
    'action label' => static fn (): mixed => $plan239()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan239()->drainSummary()['status'],
    'drain row count' => static fn (): mixed => $plan239()->drainSummary()['drain_row_count'],
    'drain pages' => static fn (): mixed => $plan239()->drainPages(),
    'summary drain pages' => static fn (): mixed => $plan239()->drainSummary()['drain_pages'],
    'summary source next pages' => static fn (): mixed => $plan239()->drainSummary()['source_next_pages'],
    'drain pages match source next pages' => static fn (): mixed => $plan239()->drainSummary()['drain_pages_match_source_next_pages'],
    'pointer map drain pages' => static fn (): mixed => $plan239()->pointerMapDrainPages(),
    'summary pointer map drain pages' => static fn (): mixed => $plan239()->drainSummary()['pointer_map_drain_pages'],
    'payload drain pages' => static fn (): mixed => $plan239()->payloadDrainPages(),
    'summary payload drain pages' => static fn (): mixed => $plan239()->drainSummary()['payload_drain_pages'],
    'duplicate pointer map drain pages' => static fn (): mixed => $plan239()->duplicatePointerMapDrainPages(),
    'summary duplicate pointer map drain pages' => static fn (): mixed => $plan239()->drainSummary()['duplicate_pointer_map_drain_pages'],
    'drain errors' => static fn (): mixed => $plan239()->drainErrors(),
    'summary drain errors' => static fn (): mixed => $plan239()->drainSummary()['drain_errors'],
    'all source next tokens match' => static fn (): mixed => $plan239()->drainSummary()['all_source_next_tokens_match'],
    'all source next links drained' => static fn (): mixed => $plan239()->drainSummary()['all_source_next_links_drained'],
    'all pointer maps drained before payload' => static fn (): mixed => $plan239()->drainSummary()['all_pointer_maps_drained_before_payload'],
    'all duplicate pointer map generations drained' => static fn (): mixed => $plan239()->drainSummary()['all_duplicate_pointer_map_generations_drained'],
    'all freeblock receipts drained' => static fn (): mixed => $plan239()->drainSummary()['all_freeblock_receipts_drained'],
    'all tail pages fenced after drain' => static fn (): mixed => $plan239()->drainSummary()['all_tail_pages_remain_fenced_after_drain'],
    'drain token count' => static fn (): mixed => count($plan239()->drainTokens()),
    'drain token lengths' => static fn (): mixed => array_map('strlen', $plan239()->drainTokens()),
    'drain signature length' => static fn (): mixed => strlen($plan239()->drainSummary()['drain_signature']),
    'current source token length' => static fn (): mixed => strlen($plan239()->drainSummary()['current_source_next239_token']),
    'first drain channel' => static fn (): mixed => $plan239()->drainRows()[0]['drain_channel'],
    'first drain page' => static fn (): mixed => $plan239()->drainRows()[0]['drain_page'],
    'first next drain page' => static fn (): mixed => $plan239()->drainRows()[0]['next_drain_page'],
    'first drained generations' => static fn (): mixed => $plan239()->drainRows()[0]['drained_pointer_map_generations'],
    'first drained freeblocks' => static fn (): mixed => $plan239()->drainRows()[0]['drained_freeblock_pages'],
    'second drain channel' => static fn (): mixed => $plan239()->drainRows()[1]['drain_channel'],
    'second drain page' => static fn (): mixed => $plan239()->drainRows()[1]['drain_page'],
    'second pointer maps before payload' => static fn (): mixed => $plan239()->drainRows()[1]['pointer_maps_drained_before_payload'],
    'third drain page' => static fn (): mixed => $plan239()->drainRows()[2]['drain_page'],
    'third drained generations' => static fn (): mixed => $plan239()->drainRows()[2]['drained_pointer_map_generations'],
    'fifth drain page' => static fn (): mixed => $plan239()->drainRows()[4]['drain_page'],
    'fifth duplicate generation drained' => static fn (): mixed => $plan239()->drainRows()[4]['duplicate_pointer_map_generation_drained'],
    'fifth drained generations' => static fn (): mixed => $plan239()->drainRows()[4]['drained_pointer_map_generations'],
    'last next drain page' => static fn (): mixed => $plan239()->drainRows()[6]['next_drain_page'],
    'ordinals' => static fn (): mixed => array_column($plan239()->drainRows(), 'drain_ordinal'),
    'source next ordinals' => static fn (): mixed => array_column($plan239()->drainRows(), 'source_next_ordinal'),
    'row states' => static fn (): mixed => array_column($plan239()->drainRows(), 'drain_state'),
    'row source token flags' => static fn (): mixed => array_column($plan239()->drainRows(), 'source_next_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan239()->drainRows(), 'source_next_link_drained'),
    'row pointer drain flags' => static fn (): mixed => array_column($plan239()->drainRows(), 'pointer_maps_drained_before_payload'),
    'row duplicate preserve flags' => static fn (): mixed => array_column($plan239()->drainRows(), 'duplicate_pointer_map_generation_preserved'),
    'row freeblock flags' => static fn (): mixed => array_column($plan239()->drainRows(), 'freeblock_receipt_drained'),
    'row tail fence flags' => static fn (): mixed => array_column($plan239()->drainRows(), 'tail_pages_remain_fenced_after_drain'),
    'batch size three row count' => static fn (): mixed => $plan239(3)->drainSummary()['drain_row_count'],
    'batch size three pages' => static fn (): mixed => $plan239(3)->drainPages(),
    'batch size three pointer map pages' => static fn (): mixed => $plan239(3)->pointerMapDrainPages(),
    'batch size three payload pages' => static fn (): mixed => $plan239(3)->payloadDrainPages(),
    'batch size three duplicate pointer pages' => static fn (): mixed => $plan239(3)->duplicatePointerMapDrainPages(),
    'dependency closure' => static fn (): mixed => $plan239()->drainSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan239()->drainSummary()['non_overlap'], 'does not repeat next236'),
    'source next action' => static fn (): mixed => $plan239()->sourceNextPlan->toArray()['action'],
    'source next row count' => static fn (): mixed => $plan239()->sourceNextPlan->sourceNextSummary()['source_next_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message239(static fn () => $plan239(0)),
];

$expected239 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next239',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next239-ready',
    'drain row count' => 7,
    'drain pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary drain pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary source next pages' => [2, 3, 105, 106, 105, 107, 108],
    'drain pages match source next pages' => true,
    'pointer map drain pages' => [2, 105],
    'summary pointer map drain pages' => [2, 105],
    'payload drain pages' => [3, 106, 107, 108],
    'summary payload drain pages' => [3, 106, 107, 108],
    'duplicate pointer map drain pages' => [105],
    'summary duplicate pointer map drain pages' => [105],
    'drain errors' => [],
    'summary drain errors' => [],
    'all source next tokens match' => true,
    'all source next links drained' => true,
    'all pointer maps drained before payload' => true,
    'all duplicate pointer map generations drained' => true,
    'all freeblock receipts drained' => true,
    'all tail pages fenced after drain' => true,
    'drain token count' => 7,
    'drain token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'drain signature length' => 64,
    'current source token length' => 64,
    'first drain channel' => 'pointer-map',
    'first drain page' => 2,
    'first next drain page' => 3,
    'first drained generations' => ['2:1'],
    'first drained freeblocks' => [2],
    'second drain channel' => 'payload',
    'second drain page' => 3,
    'second pointer maps before payload' => true,
    'third drain page' => 105,
    'third drained generations' => ['2:1', '105:1'],
    'fifth drain page' => 105,
    'fifth duplicate generation drained' => true,
    'fifth drained generations' => ['2:1', '105:2'],
    'last next drain page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source next ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next239-drained', 'current-source-next239-drained', 'current-source-next239-drained', 'current-source-next239-drained', 'current-source-next239-drained', 'current-source-next239-drained', 'current-source-next239-drained'],
    'row source token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer drain flags' => [true, true, true, true, true, true, true],
    'row duplicate preserve flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three pointer map pages' => [2, 105],
    'batch size three payload pages' => [3, 106, 107, 108],
    'batch size three duplicate pointer pages' => [],
    'dependency closure' => 'no new support component needed; next239 reuses next236 source-next cursor rows and adds final drain admission for pointer-map/freeblock reuse',
    'non overlap' => true,
    'source next action' => 'btree-vacuum-pointermap-freeblock-current-source-next236',
    'source next row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases239 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next239 ' . $name] = static function (TestRunner $t) use ($callback, $expected239, $name): void {
        $t->same($expected239[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next239 drain invariant ' . $index] = static function (TestRunner $t) use ($plan239): void {
        $plan = $plan239();
        $summary = $plan->drainSummary();

        $t->same([], $plan->drainErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->drainPages());
        $t->same([2, 105], $plan->pointerMapDrainPages());
        $t->same([3, 106, 107, 108], $plan->payloadDrainPages());
        $t->same([105], $plan->duplicatePointerMapDrainPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->drainRows(), 'drain_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'source_next_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'source_next_link_drained'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'pointer_maps_drained_before_payload'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'duplicate_pointer_map_generation_preserved'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'freeblock_receipt_drained'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->drainRows(), 'tail_pages_remain_fenced_after_drain'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->drainTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next239-ready', $summary['status']);
        $t->same(true, $summary['drain_pages_match_source_next_pages']);
        $t->same(true, $summary['all_freeblock_receipts_drained']);
    };
}

return $tests;
