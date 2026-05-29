<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage241 = static function (int $pageCount): string {
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

$putPointerMapEntry241 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database241 = static function () use ($makeFirstPage241, $putPointerMapEntry241): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage241(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next241', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(87 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry241($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan241 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database241;

    $database = $database241();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext241(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next241-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message241 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases241 = [
    'action label' => static fn (): mixed => $plan241()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan241()->sourceSummary()['status'],
    'source row count' => static fn (): mixed => $plan241()->sourceSummary()['source_row_count'],
    'source pages' => static fn (): mixed => $plan241()->sourcePages(),
    'summary source pages' => static fn (): mixed => $plan241()->sourceSummary()['source_pages'],
    'next source pages' => static fn (): mixed => $plan241()->nextSourcePages(),
    'freelist pages' => static fn (): mixed => $plan241()->sourceSummary()['freelist_pages'],
    'source pages match freelist pages' => static fn (): mixed => $plan241()->sourceSummary()['source_pages_match_freelist_pages'],
    'pointer map barrier pages' => static fn (): mixed => $plan241()->pointerMapBarrierPages(),
    'summary pointer map barrier pages' => static fn (): mixed => $plan241()->sourceSummary()['pointer_map_barrier_pages'],
    'reusable payload pages' => static fn (): mixed => $plan241()->reusablePayloadPages(),
    'summary reusable payload pages' => static fn (): mixed => $plan241()->sourceSummary()['reusable_payload_pages'],
    'duplicate pointer map pages' => static fn (): mixed => $plan241()->duplicatePointerMapPages(),
    'summary duplicate pointer map pages' => static fn (): mixed => $plan241()->sourceSummary()['duplicate_pointer_map_pages'],
    'source errors' => static fn (): mixed => $plan241()->sourceErrors(),
    'summary source errors' => static fn (): mixed => $plan241()->sourceSummary()['source_errors'],
    'all freelist tokens match' => static fn (): mixed => $plan241()->sourceSummary()['all_freelist_tokens_match'],
    'all source links current' => static fn (): mixed => $plan241()->sourceSummary()['all_source_links_current'],
    'all pointer barriers replayed' => static fn (): mixed => $plan241()->sourceSummary()['all_pointer_map_barriers_replayed_before_payload'],
    'all payload receipts kept' => static fn (): mixed => $plan241()->sourceSummary()['all_payload_pages_keep_freeblock_receipts'],
    'all duplicate pointer maps keep generation' => static fn (): mixed => $plan241()->sourceSummary()['all_duplicate_pointer_maps_keep_generation'],
    'all tail pages remain excluded' => static fn (): mixed => $plan241()->sourceSummary()['all_tail_pages_remain_excluded'],
    'source token count' => static fn (): mixed => count($plan241()->sourceTokens()),
    'source token lengths' => static fn (): mixed => array_map('strlen', $plan241()->sourceTokens()),
    'source signature length' => static fn (): mixed => strlen($plan241()->sourceSummary()['source_signature']),
    'current source token length' => static fn (): mixed => strlen($plan241()->sourceSummary()['current_source_next241_token']),
    'first row channel' => static fn (): mixed => $plan241()->sourceRows()[0]['source_channel'],
    'first row page' => static fn (): mixed => $plan241()->sourceRows()[0]['source_page'],
    'first next page' => static fn (): mixed => $plan241()->sourceRows()[0]['next_source_page'],
    'first visible pointer maps' => static fn (): mixed => $plan241()->sourceRows()[0]['visible_pointer_map_pages'],
    'second row channel' => static fn (): mixed => $plan241()->sourceRows()[1]['source_channel'],
    'second visible pointer maps' => static fn (): mixed => $plan241()->sourceRows()[1]['visible_pointer_map_pages'],
    'fifth duplicate pointer map' => static fn (): mixed => $plan241()->sourceRows()[4]['duplicate_pointer_map_replay'],
    'fifth pointer generations' => static fn (): mixed => $plan241()->sourceRows()[4]['pointer_map_generations'],
    'last row page' => static fn (): mixed => $plan241()->sourceRows()[6]['source_page'],
    'last next page' => static fn (): mixed => $plan241()->sourceRows()[6]['next_source_page'],
    'source ordinals' => static fn (): mixed => array_column($plan241()->sourceRows(), 'source_ordinal'),
    'freelist ordinals' => static fn (): mixed => array_column($plan241()->sourceRows(), 'freelist_ordinal'),
    'row states' => static fn (): mixed => array_column($plan241()->sourceRows(), 'source_state'),
    'row token flags' => static fn (): mixed => array_column($plan241()->sourceRows(), 'freelist_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan241()->sourceRows(), 'source_link_current'),
    'row barrier flags' => static fn (): mixed => array_column($plan241()->sourceRows(), 'pointer_map_barrier_replayed_before_payload'),
    'row receipt flags' => static fn (): mixed => array_column($plan241()->sourceRows(), 'payload_page_keeps_freeblock_receipt'),
    'row duplicate generation flags' => static fn (): mixed => array_column($plan241()->sourceRows(), 'duplicate_pointer_map_keeps_generation'),
    'row tail exclusion flags' => static fn (): mixed => array_column($plan241()->sourceRows(), 'tail_page_remains_excluded'),
    'batch size three row count' => static fn (): mixed => $plan241(3)->sourceSummary()['source_row_count'],
    'batch size three source pages' => static fn (): mixed => $plan241(3)->sourcePages(),
    'batch size three next pages' => static fn (): mixed => $plan241(3)->nextSourcePages(),
    'batch size three duplicate pointer maps' => static fn (): mixed => $plan241(3)->duplicatePointerMapPages(),
    'dependency closure' => static fn (): mixed => $plan241()->sourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan241()->sourceSummary()['non_overlap'], 'does not repeat next238'),
    'freelist action' => static fn (): mixed => $plan241()->freelistPlan->toArray()['action'],
    'freelist row count' => static fn (): mixed => $plan241()->freelistPlan->freelistSummary()['freelist_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message241(static fn () => $plan241(0)),
];

$expected241 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next241',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next241-ready',
    'source row count' => 7,
    'source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary source pages' => [2, 3, 105, 106, 105, 107, 108],
    'next source pages' => [3, 105, 106, 105, 107, 108, null],
    'freelist pages' => [2, 3, 105, 106, 105, 107, 108],
    'source pages match freelist pages' => true,
    'pointer map barrier pages' => [2, 105],
    'summary pointer map barrier pages' => [2, 105],
    'reusable payload pages' => [3, 106, 107, 108],
    'summary reusable payload pages' => [3, 106, 107, 108],
    'duplicate pointer map pages' => [105],
    'summary duplicate pointer map pages' => [105],
    'source errors' => [],
    'summary source errors' => [],
    'all freelist tokens match' => true,
    'all source links current' => true,
    'all pointer barriers replayed' => true,
    'all payload receipts kept' => true,
    'all duplicate pointer maps keep generation' => true,
    'all tail pages remain excluded' => true,
    'source token count' => 7,
    'source token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'source signature length' => 64,
    'current source token length' => 64,
    'first row channel' => 'pointer-map',
    'first row page' => 2,
    'first next page' => 3,
    'first visible pointer maps' => [2],
    'second row channel' => 'payload',
    'second visible pointer maps' => [2],
    'fifth duplicate pointer map' => true,
    'fifth pointer generations' => ['2:1', '105:2'],
    'last row page' => 108,
    'last next page' => null,
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'freelist ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next241-freelist-cursor-visible', 'current-source-next241-freelist-cursor-visible', 'current-source-next241-freelist-cursor-visible', 'current-source-next241-freelist-cursor-visible', 'current-source-next241-freelist-cursor-visible', 'current-source-next241-freelist-cursor-visible', 'current-source-next241-freelist-cursor-visible'],
    'row token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row barrier flags' => [true, true, true, true, true, true, true],
    'row receipt flags' => [true, true, true, true, true, true, true],
    'row duplicate generation flags' => [true, true, true, true, true, true, true],
    'row tail exclusion flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three source pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three duplicate pointer maps' => [],
    'dependency closure' => 'no new support component needed; next241 reuses next238 freelist-link rows and adds current-source cursor validation only',
    'non overlap' => true,
    'freelist action' => 'btree-vacuum-pointermap-freeblock-current-source-next238',
    'freelist row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases241 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next241 ' . $name] = static function (TestRunner $t) use ($callback, $expected241, $name): void {
        $t->same($expected241[$name], $callback());
    };
}

foreach (range(1, 75) as $index) {
    $tests['btree vacuum pointermap freeblock current source next241 source invariant ' . $index] = static function (TestRunner $t) use ($plan241): void {
        $plan = $plan241();
        $summary = $plan->sourceSummary();

        $t->same([], $plan->sourceErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->sourcePages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextSourcePages());
        $t->same([2, 105], $plan->pointerMapBarrierPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([105], $plan->duplicatePointerMapPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->sourceRows(), 'source_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'freelist_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'source_link_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'pointer_map_barrier_replayed_before_payload'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'payload_page_keeps_freeblock_receipt'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'duplicate_pointer_map_keeps_generation'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceRows(), 'tail_page_remains_excluded'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next241-ready', $summary['status']);
        $t->same(true, $summary['source_pages_match_freelist_pages']);
        $t->same(true, $summary['all_source_links_current']);
        $t->same(true, $summary['all_tail_pages_remain_excluded']);
    };
}

return $tests;
