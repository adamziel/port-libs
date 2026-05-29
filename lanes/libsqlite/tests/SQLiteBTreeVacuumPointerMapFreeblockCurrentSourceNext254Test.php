<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage254 = static function (int $pageCount): string {
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

$putPointerMapEntry254 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database254 = static function () use ($makeFirstPage254, $putPointerMapEntry254): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage254(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next254', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry254($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan254 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database254;

    $database = $database254();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCurrentSourceFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next254-current-source-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$message254 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases254 = [
    'action label' => static fn (): mixed => $plan254()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan254()->currentSourceSummary()['status'],
    'row count' => static fn (): mixed => $plan254()->currentSourceSummary()['current_source_row_count'],
    'current source pages' => static fn (): mixed => $plan254()->currentSourcePages(),
    'summary current source pages' => static fn (): mixed => $plan254()->currentSourceSummary()['current_source_pages'],
    'summary next source pages' => static fn (): mixed => $plan254()->currentSourceSummary()['next_source_pages'],
    'pages match next source' => static fn (): mixed => $plan254()->currentSourceSummary()['current_source_pages_match_next_source'],
    'freeblock write pages' => static fn (): mixed => $plan254()->freeblockWritePages(),
    'summary freeblock write pages' => static fn (): mixed => $plan254()->currentSourceSummary()['freeblock_write_pages'],
    'pointer map anchor pages' => static fn (): mixed => $plan254()->pointerMapAnchorPages(),
    'summary pointer map anchor pages' => static fn (): mixed => $plan254()->currentSourceSummary()['pointer_map_anchor_pages'],
    'write offsets' => static fn (): mixed => $plan254()->currentSourceWriteOffsets(),
    'summary write offsets' => static fn (): mixed => $plan254()->currentSourceSummary()['current_source_write_offsets'],
    'errors' => static fn (): mixed => $plan254()->currentSourceErrors(),
    'summary errors' => static fn (): mixed => $plan254()->currentSourceSummary()['current_source_errors'],
    'all next source tokens match' => static fn (): mixed => $plan254()->currentSourceSummary()['all_next_source_tokens_match'],
    'all freeblock writes after pointer map' => static fn (): mixed => $plan254()->currentSourceSummary()['all_freeblock_writes_after_pointer_map'],
    'all write offsets page local' => static fn (): mixed => $plan254()->currentSourceSummary()['all_write_offsets_page_local'],
    'all reusable receipts current' => static fn (): mixed => $plan254()->currentSourceSummary()['all_reusable_receipts_current'],
    'all allocation sequences monotonic' => static fn (): mixed => $plan254()->currentSourceSummary()['all_allocation_sequences_monotonic'],
    'all links valid' => static fn (): mixed => $plan254()->currentSourceSummary()['all_current_source_links_valid'],
    'token count' => static fn (): mixed => count($plan254()->currentSourceTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan254()->currentSourceTokens()),
    'signature length' => static fn (): mixed => strlen($plan254()->currentSourceSummary()['current_source_signature']),
    'next token length' => static fn (): mixed => strlen($plan254()->currentSourceSummary()['current_source_next254_token']),
    'first row channel' => static fn (): mixed => $plan254()->currentSourceRows()[0]['current_source_channel'],
    'first row active pointer map' => static fn (): mixed => $plan254()->currentSourceRows()[0]['active_pointer_map_page'],
    'second row channel' => static fn (): mixed => $plan254()->currentSourceRows()[1]['current_source_channel'],
    'second row offset' => static fn (): mixed => $plan254()->currentSourceRows()[1]['current_source_write_offset'],
    'second row active pointer map' => static fn (): mixed => $plan254()->currentSourceRows()[1]['active_pointer_map_page'],
    'third row channel' => static fn (): mixed => $plan254()->currentSourceRows()[2]['current_source_channel'],
    'third row active pointer map' => static fn (): mixed => $plan254()->currentSourceRows()[2]['active_pointer_map_page'],
    'fourth row offset' => static fn (): mixed => $plan254()->currentSourceRows()[3]['current_source_write_offset'],
    'fifth row channel' => static fn (): mixed => $plan254()->currentSourceRows()[4]['current_source_channel'],
    'fifth row active pointer map' => static fn (): mixed => $plan254()->currentSourceRows()[4]['active_pointer_map_page'],
    'last row page' => static fn (): mixed => $plan254()->currentSourceRows()[6]['current_source_page'],
    'last row offset' => static fn (): mixed => $plan254()->currentSourceRows()[6]['current_source_write_offset'],
    'ordinals' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'current_source_ordinal'),
    'next source ordinals' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'next_source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'current_source_state'),
    'token flags' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'next_source_token_matches'),
    'write order flags' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'freeblock_write_after_pointer_map'),
    'offset flags' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'write_offset_page_local'),
    'receipt flags' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'reusable_receipt_current'),
    'sequence flags' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'allocation_sequence_monotonic'),
    'link flags' => static fn (): mixed => array_column($plan254()->currentSourceRows(), 'current_source_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan254(3)->currentSourceSummary()['current_source_row_count'],
    'batch size three pages' => static fn (): mixed => $plan254(3)->currentSourcePages(),
    'batch size three offsets' => static fn (): mixed => $plan254(3)->currentSourceWriteOffsets(),
    'batch size three freeblock pages' => static fn (): mixed => $plan254(3)->freeblockWritePages(),
    'dependency closure' => static fn (): mixed => $plan254()->currentSourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan254()->currentSourceSummary()['non_overlap'], 'does not repeat next249'),
    'next source action' => static fn (): mixed => $plan254()->nextSourcePlan->toArray()['action'],
    'next source row count' => static fn (): mixed => $plan254()->nextSourcePlan->nextSourceSummary()['next_source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message254(static fn () => $plan254(0)),
];

$expected254 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next254',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next254-ready',
    'row count' => 7,
    'current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary next source pages' => [2, 3, 105, 106, 105, 107, 108],
    'pages match next source' => true,
    'freeblock write pages' => [3, 106, 107, 108],
    'summary freeblock write pages' => [3, 106, 107, 108],
    'pointer map anchor pages' => [2, 105],
    'summary pointer map anchor pages' => [2, 105],
    'write offsets' => [0, 40, 0, 128, 0, 144, 160],
    'summary write offsets' => [0, 40, 0, 128, 0, 144, 160],
    'errors' => [],
    'summary errors' => [],
    'all next source tokens match' => true,
    'all freeblock writes after pointer map' => true,
    'all write offsets page local' => true,
    'all reusable receipts current' => true,
    'all allocation sequences monotonic' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first row channel' => 'pointer-map-anchor',
    'first row active pointer map' => 2,
    'second row channel' => 'freeblock-write-slot',
    'second row offset' => 40,
    'second row active pointer map' => 2,
    'third row channel' => 'pointer-map-anchor',
    'third row active pointer map' => 105,
    'fourth row offset' => 128,
    'fifth row channel' => 'pointer-map-anchor',
    'fifth row active pointer map' => 105,
    'last row page' => 108,
    'last row offset' => 160,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'next source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next254-freeblock-write-slot-published', 'current-source-next254-freeblock-write-slot-published', 'current-source-next254-freeblock-write-slot-published', 'current-source-next254-freeblock-write-slot-published', 'current-source-next254-freeblock-write-slot-published', 'current-source-next254-freeblock-write-slot-published', 'current-source-next254-freeblock-write-slot-published'],
    'token flags' => [true, true, true, true, true, true, true],
    'write order flags' => [true, true, true, true, true, true, true],
    'offset flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'sequence flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three offsets' => [0, 40, 0, 128, 144, 160],
    'batch size three freeblock pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next254 reuses next249 next-source rows and records page-local current-source freeblock write slots',
    'non overlap' => true,
    'next source action' => 'btree-vacuum-pointermap-freeblock-current-source-next249',
    'next source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases254 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next254 ' . $name] = static function (TestRunner $t) use ($callback, $expected254, $name): void {
        $t->same($expected254[$name], $callback());
    };
}

foreach (range(1, 96) as $index) {
    $tests['btree vacuum pointermap freeblock current source next254 current-source invariant ' . $index] = static function (TestRunner $t) use ($plan254): void {
        $plan = $plan254();
        $summary = $plan->currentSourceSummary();

        $t->same([], $plan->currentSourceErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->currentSourcePages());
        $t->same([2, 105], $plan->pointerMapAnchorPages());
        $t->same([3, 106, 107, 108], $plan->freeblockWritePages());
        $t->same([0, 40, 0, 128, 0, 144, 160], $plan->currentSourceWriteOffsets());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->currentSourceRows(), 'current_source_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'next_source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'freeblock_write_after_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'write_offset_page_local'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'reusable_receipt_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'allocation_sequence_monotonic'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->currentSourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next254-ready', $summary['status']);
        $t->same(true, $summary['current_source_pages_match_next_source']);
        $t->same(true, $summary['all_freeblock_writes_after_pointer_map']);
    };
}

return $tests;
