<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext223Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage223 = static function (int $pageCount): string {
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

$putPointerMapEntry223 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database223 = static function () use ($makeFirstPage223, $putPointerMapEntry223): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage223(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next223', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry223($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan223 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext223Plan {
    global $database223;

    $database = $database223();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext223Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next218-current-source-write-', 50),
        3,
        true,
        $batchSize,
    );
};

$message223 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases223 = [
    'action label' => static fn (): mixed => $plan223()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan223()->sourceSummary()['status'],
    'source row count' => static fn (): mixed => $plan223()->sourceSummary()['source_row_count'],
    'source pages' => static fn (): mixed => $plan223()->sourcePages(),
    'summary source pages' => static fn (): mixed => $plan223()->sourceSummary()['source_pages'],
    'pointer map source pages' => static fn (): mixed => $plan223()->pointerMapSourcePages(),
    'payload source pages' => static fn (): mixed => $plan223()->payloadSourcePages(),
    'summary write pages' => static fn (): mixed => $plan223()->sourceSummary()['write_pages'],
    'source matches write pages' => static fn (): mixed => $plan223()->sourceSummary()['source_matches_write_pages'],
    'source errors' => static fn (): mixed => $plan223()->sourceErrors(),
    'summary source errors' => static fn (): mixed => $plan223()->sourceSummary()['source_errors'],
    'all write tokens match' => static fn (): mixed => $plan223()->sourceSummary()['all_write_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan223()->sourceSummary()['all_pointer_maps_sourced_before_payload'],
    'all freeblock receipts published' => static fn (): mixed => $plan223()->sourceSummary()['all_freeblock_receipts_published'],
    'all tail pages excluded' => static fn (): mixed => $plan223()->sourceSummary()['all_tail_pages_excluded_from_source'],
    'all source chains valid' => static fn (): mixed => $plan223()->sourceSummary()['all_source_chains_valid'],
    'source token count' => static fn (): mixed => count($plan223()->sourceTokens()),
    'source token lengths' => static fn (): mixed => array_map('strlen', $plan223()->sourceTokens()),
    'source signature length' => static fn (): mixed => strlen($plan223()->sourceSummary()['source_signature']),
    'current source token length' => static fn (): mixed => strlen($plan223()->sourceSummary()['current_source_next223_token']),
    'first source channel' => static fn (): mixed => $plan223()->sourceRows()[0]['source_channel'],
    'first source page' => static fn (): mixed => $plan223()->sourceRows()[0]['page_number'],
    'first visible pages' => static fn (): mixed => $plan223()->sourceRows()[0]['source_visible_pages'],
    'first previous token' => static fn (): mixed => $plan223()->sourceRows()[0]['previous_source_token'],
    'second source channel' => static fn (): mixed => $plan223()->sourceRows()[1]['source_channel'],
    'second source page' => static fn (): mixed => $plan223()->sourceRows()[1]['page_number'],
    'second visible pages' => static fn (): mixed => $plan223()->sourceRows()[1]['source_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan223()->sourceRows()[1]['previous_source_token']),
    'third source channel' => static fn (): mixed => $plan223()->sourceRows()[2]['source_channel'],
    'third source page' => static fn (): mixed => $plan223()->sourceRows()[2]['page_number'],
    'third visible pages' => static fn (): mixed => $plan223()->sourceRows()[2]['source_visible_pages'],
    'fourth source channel' => static fn (): mixed => $plan223()->sourceRows()[3]['source_channel'],
    'fourth source page' => static fn (): mixed => $plan223()->sourceRows()[3]['page_number'],
    'fourth visible pages' => static fn (): mixed => $plan223()->sourceRows()[3]['source_visible_pages'],
    'fifth source channel' => static fn (): mixed => $plan223()->sourceRows()[4]['source_channel'],
    'fifth source page' => static fn (): mixed => $plan223()->sourceRows()[4]['page_number'],
    'fifth visible pages' => static fn (): mixed => $plan223()->sourceRows()[4]['source_visible_pages'],
    'sixth source channel' => static fn (): mixed => $plan223()->sourceRows()[5]['source_channel'],
    'sixth source page' => static fn (): mixed => $plan223()->sourceRows()[5]['page_number'],
    'sixth visible pages' => static fn (): mixed => $plan223()->sourceRows()[5]['source_visible_pages'],
    'source ordinals' => static fn (): mixed => array_column($plan223()->sourceRows(), 'source_ordinal'),
    'write ordinals' => static fn (): mixed => array_column($plan223()->sourceRows(), 'write_ordinal'),
    'apply ordinals' => static fn (): mixed => array_column($plan223()->sourceRows(), 'apply_ordinal'),
    'row states' => static fn (): mixed => array_column($plan223()->sourceRows(), 'source_state'),
    'row write token flags' => static fn (): mixed => array_column($plan223()->sourceRows(), 'write_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan223()->sourceRows(), 'freeblock_receipt_published'),
    'row tail fence flags' => static fn (): mixed => array_column($plan223()->sourceRows(), 'tail_pages_excluded_from_source'),
    'row chain flags' => static fn (): mixed => array_column($plan223()->sourceRows(), 'source_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan223()->sourceRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan223(3)->sourceSummary()['source_row_count'],
    'batch size three pages' => static fn (): mixed => $plan223(3)->sourcePages(),
    'batch size three source write ordinals' => static fn (): mixed => array_column($plan223(3)->sourceRows(), 'write_ordinal'),
    'batch size three token count' => static fn (): mixed => count($plan223(3)->sourceTokens()),
    'dependency closure' => static fn (): mixed => $plan223()->sourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan223()->sourceSummary()['non_overlap'], 'does not repeat next218'),
    'base action' => static fn (): mixed => $plan223()->basePlan->toArray()['action'],
    'base write rows' => static fn (): mixed => $plan223()->basePlan->writeSummary()['write_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message223(static fn () => $plan223(0)),
];

$expected223 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next223',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next223-ready',
    'source row count' => 7,
    'source pages' => [2, 3, 105, 106, 107, 108],
    'summary source pages' => [2, 3, 105, 106, 107, 108],
    'pointer map source pages' => [2, 105],
    'payload source pages' => [3, 106, 107, 108],
    'summary write pages' => [2, 3, 105, 106, 107, 108],
    'source matches write pages' => true,
    'source errors' => [],
    'summary source errors' => [],
    'all write tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts published' => true,
    'all tail pages excluded' => true,
    'all source chains valid' => true,
    'source token count' => 7,
    'source token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'source signature length' => 64,
    'current source token length' => 64,
    'first source channel' => 'pointer-map',
    'first source page' => 2,
    'first visible pages' => [2],
    'first previous token' => null,
    'second source channel' => 'payload',
    'second source page' => 3,
    'second visible pages' => [2, 3],
    'second previous token length' => 64,
    'third source channel' => 'pointer-map',
    'third source page' => 105,
    'third visible pages' => [2, 3, 105],
    'fourth source channel' => 'payload',
    'fourth source page' => 106,
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth source channel' => 'pointer-map',
    'fifth source page' => 105,
    'fifth visible pages' => [2, 3, 105, 106],
    'sixth source channel' => 'payload',
    'sixth source page' => 107,
    'sixth visible pages' => [2, 3, 105, 106, 107],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'write ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'apply ordinals' => [1, 2, 3, 4, 5, 6, 6],
    'row states' => ['current-source-publication-receipted', 'current-source-publication-receipted', 'current-source-publication-receipted', 'current-source-publication-receipted', 'current-source-publication-receipted', 'current-source-publication-receipted', 'current-source-publication-receipted'],
    'row write token flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108, 108],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three source write ordinals' => [1, 2, 3, 4, 5, 6],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; next223 reuses next218 per-page write receipts and publishes a current-source source fence only',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next218',
    'base write rows' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases223 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next223 ' . $name] = static function (TestRunner $t) use ($callback, $expected223, $name): void {
        $t->same($expected223[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next223 source invariant ' . $index] = static function (TestRunner $t) use ($plan223): void {
        $plan = $plan223();
        $summary = $plan->sourceSummary();

        $t->same([], $plan->sourceErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->sourcePages());
        $t->same([2, 105], $plan->pointerMapSourcePages());
        $t->same([3, 106, 107, 108], $plan->payloadSourcePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->sourceRows(), 'source_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'write_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'freeblock_receipt_published'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'tail_pages_excluded_from_source'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next223-ready', $summary['status']);
        $t->same(true, $summary['source_matches_write_pages']);
        $t->same(true, $summary['all_pointer_maps_sourced_before_payload']);
    };
}

return $tests;
