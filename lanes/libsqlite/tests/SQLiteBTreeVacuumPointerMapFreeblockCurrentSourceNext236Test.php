<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage236 = static function (int $pageCount): string {
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

$putPointerMapEntry236 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database236 = static function () use ($makeFirstPage236, $putPointerMapEntry236): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage236(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next236', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry236($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan236 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database236;

    $database = $database236();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext236(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next236-current-source-next-', 50),
        3,
        true,
        $batchSize,
    );
};

$message236 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases236 = [
    'action label' => static fn (): mixed => $plan236()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan236()->sourceNextSummary()['status'],
    'source next row count' => static fn (): mixed => $plan236()->sourceNextSummary()['source_next_row_count'],
    'source next pages' => static fn (): mixed => $plan236()->sourceNextPages(),
    'summary source next pages' => static fn (): mixed => $plan236()->sourceNextSummary()['source_next_pages'],
    'next source pages' => static fn (): mixed => $plan236()->nextSourcePages(),
    'checkpoint pages' => static fn (): mixed => $plan236()->sourceNextSummary()['checkpoint_pages'],
    'source pages match checkpoints' => static fn (): mixed => $plan236()->sourceNextSummary()['source_next_pages_match_checkpoint_pages'],
    'pointer map source pages' => static fn (): mixed => $plan236()->pointerMapSourcePages(),
    'payload source pages' => static fn (): mixed => $plan236()->payloadSourcePages(),
    'source next errors' => static fn (): mixed => $plan236()->sourceNextErrors(),
    'summary source next errors' => static fn (): mixed => $plan236()->sourceNextSummary()['source_next_errors'],
    'all checkpoint tokens match' => static fn (): mixed => $plan236()->sourceNextSummary()['all_checkpoint_tokens_match'],
    'all source next links valid' => static fn (): mixed => $plan236()->sourceNextSummary()['all_source_next_links_valid'],
    'all pointer generations visible before payload' => static fn (): mixed => $plan236()->sourceNextSummary()['all_pointer_map_generations_visible_before_payload'],
    'all duplicate pointer map sources preserved' => static fn (): mixed => $plan236()->sourceNextSummary()['all_duplicate_pointer_map_sources_preserved'],
    'all freeblock receipts current' => static fn (): mixed => $plan236()->sourceNextSummary()['all_freeblock_receipts_current'],
    'all tail pages fenced' => static fn (): mixed => $plan236()->sourceNextSummary()['all_tail_pages_fenced_for_source_next'],
    'token count' => static fn (): mixed => count($plan236()->sourceNextTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan236()->sourceNextTokens()),
    'signature length' => static fn (): mixed => strlen($plan236()->sourceNextSummary()['source_next_signature']),
    'current token length' => static fn (): mixed => strlen($plan236()->sourceNextSummary()['current_source_next236_token']),
    'first row channel' => static fn (): mixed => $plan236()->sourceNextRows()[0]['source_next_channel'],
    'first row page' => static fn (): mixed => $plan236()->sourceNextRows()[0]['source_next_page'],
    'first row next page' => static fn (): mixed => $plan236()->sourceNextRows()[0]['next_source_page'],
    'first visible generations' => static fn (): mixed => $plan236()->sourceNextRows()[0]['visible_pointer_map_generations'],
    'second row channel' => static fn (): mixed => $plan236()->sourceNextRows()[1]['source_next_channel'],
    'second row page' => static fn (): mixed => $plan236()->sourceNextRows()[1]['source_next_page'],
    'second visible generations' => static fn (): mixed => $plan236()->sourceNextRows()[1]['visible_pointer_map_generations'],
    'third row page' => static fn (): mixed => $plan236()->sourceNextRows()[2]['source_next_page'],
    'third visible generations' => static fn (): mixed => $plan236()->sourceNextRows()[2]['visible_pointer_map_generations'],
    'fifth row page' => static fn (): mixed => $plan236()->sourceNextRows()[4]['source_next_page'],
    'fifth visible generations' => static fn (): mixed => $plan236()->sourceNextRows()[4]['visible_pointer_map_generations'],
    'last row next page' => static fn (): mixed => $plan236()->sourceNextRows()[6]['next_source_page'],
    'ordinals' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'source_next_ordinal'),
    'checkpoint ordinals' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'checkpoint_ordinal'),
    'row states' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'source_next_state'),
    'row checkpoint token flags' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'checkpoint_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'source_next_link_valid'),
    'row pointer visibility flags' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'pointer_map_generation_visible_before_payload'),
    'row duplicate source flags' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'duplicate_pointer_map_source_preserved'),
    'row freeblock flags' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'freeblock_source_next_receipt_current'),
    'row tail fence flags' => static fn (): mixed => array_column($plan236()->sourceNextRows(), 'tail_pages_fenced_for_source_next'),
    'batch size three row count' => static fn (): mixed => $plan236(3)->sourceNextSummary()['source_next_row_count'],
    'batch size three pages' => static fn (): mixed => $plan236(3)->sourceNextPages(),
    'batch size three next pages' => static fn (): mixed => $plan236(3)->nextSourcePages(),
    'batch size three token count' => static fn (): mixed => count($plan236(3)->sourceNextTokens()),
    'dependency closure' => static fn (): mixed => $plan236()->sourceNextSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan236()->sourceNextSummary()['non_overlap'], 'does not repeat next233'),
    'checkpoint action' => static fn (): mixed => $plan236()->checkpointPlan->toArray()['action'],
    'checkpoint row count' => static fn (): mixed => $plan236()->checkpointPlan->checkpointSummary()['checkpoint_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message236(static fn () => $plan236(0)),
];

$expected236 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next236',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next236-ready',
    'source next row count' => 7,
    'source next pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary source next pages' => [2, 3, 105, 106, 105, 107, 108],
    'next source pages' => [3, 105, 106, 105, 107, 108, null],
    'checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'source pages match checkpoints' => true,
    'pointer map source pages' => [2, 105],
    'payload source pages' => [3, 106, 107, 108],
    'source next errors' => [],
    'summary source next errors' => [],
    'all checkpoint tokens match' => true,
    'all source next links valid' => true,
    'all pointer generations visible before payload' => true,
    'all duplicate pointer map sources preserved' => true,
    'all freeblock receipts current' => true,
    'all tail pages fenced' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row channel' => 'pointer-map',
    'first row page' => 2,
    'first row next page' => 3,
    'first visible generations' => ['2:1'],
    'second row channel' => 'payload',
    'second row page' => 3,
    'second visible generations' => ['2:1'],
    'third row page' => 105,
    'third visible generations' => ['2:1', '105:1'],
    'fifth row page' => 105,
    'fifth visible generations' => ['2:1', '105:2'],
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'checkpoint ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next-page-visible', 'current-source-next-page-visible', 'current-source-next-page-visible', 'current-source-next-page-visible', 'current-source-next-page-visible', 'current-source-next-page-visible', 'current-source-next-page-visible'],
    'row checkpoint token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer visibility flags' => [true, true, true, true, true, true, true],
    'row duplicate source flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; next236 reuses next233 checkpoint rows and records source-next cursor visibility only',
    'non overlap' => true,
    'checkpoint action' => 'btree-vacuum-pointermap-freeblock-current-source-next233',
    'checkpoint row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases236 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next236 ' . $name] = static function (TestRunner $t) use ($callback, $expected236, $name): void {
        $t->same($expected236[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next236 source-next invariant ' . $index] = static function (TestRunner $t) use ($plan236): void {
        $plan = $plan236();
        $summary = $plan->sourceNextSummary();

        $t->same([], $plan->sourceNextErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->sourceNextPages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextSourcePages());
        $t->same([2, 105], $plan->pointerMapSourcePages());
        $t->same([3, 106, 107, 108], $plan->payloadSourcePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->sourceNextRows(), 'source_next_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'checkpoint_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'source_next_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'pointer_map_generation_visible_before_payload'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'duplicate_pointer_map_source_preserved'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'freeblock_source_next_receipt_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'tail_pages_fenced_for_source_next'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sourceNextTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next236-ready', $summary['status']);
        $t->same(true, $summary['source_next_pages_match_checkpoint_pages']);
        $t->same(true, $summary['all_pointer_map_generations_visible_before_payload']);
    };
}

return $tests;
