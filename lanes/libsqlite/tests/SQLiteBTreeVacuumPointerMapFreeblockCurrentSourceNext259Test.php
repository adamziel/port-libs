<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage259 = static function (int $pageCount): string {
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

$putPointerMapEntry259 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database259 = static function () use ($makeFirstPage259, $putPointerMapEntry259): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage259(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next259', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry259($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan259 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database259;

    $database = $database259();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafSourceHandoffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next259-current-source-next-', 48),
        3,
        true,
        $batchSize,
    );
};

$message259 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases259 = [
    'action label' => static fn (): mixed => $plan259()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan259()->sourceNextSummary()['status'],
    'row count' => static fn (): mixed => $plan259()->sourceNextSummary()['source_next_row_count'],
    'source pages' => static fn (): mixed => $plan259()->sourcePages(),
    'summary source pages' => static fn (): mixed => $plan259()->sourceNextSummary()['source_pages'],
    'source next pages' => static fn (): mixed => $plan259()->sourceNextPages(),
    'published pages' => static fn (): mixed => $plan259()->sourceNextSummary()['published_pages'],
    'source pages match publication' => static fn (): mixed => $plan259()->sourceNextSummary()['source_pages_match_publication'],
    'source next pages match publication' => static fn (): mixed => $plan259()->sourceNextSummary()['source_next_pages_match_publication'],
    'pointer map pages' => static fn (): mixed => $plan259()->pointerMapSourcePages(),
    'freeblock pages' => static fn (): mixed => $plan259()->freeblockSourcePages(),
    'payload pages' => static fn (): mixed => $plan259()->payloadSourcePages(),
    'duplicate pointer map pages' => static fn (): mixed => $plan259()->duplicatePointerMapSourcePages(),
    'errors' => static fn (): mixed => $plan259()->sourceNextErrors(),
    'summary errors' => static fn (): mixed => $plan259()->sourceNextSummary()['source_next_errors'],
    'all publication tokens match' => static fn (): mixed => $plan259()->sourceNextSummary()['all_publication_tokens_match'],
    'all links valid' => static fn (): mixed => $plan259()->sourceNextSummary()['all_source_next_links_valid'],
    'all freeblocks after pointer map' => static fn (): mixed => $plan259()->sourceNextSummary()['all_freeblocks_open_after_pointer_map'],
    'all payloads wait for freeblock' => static fn (): mixed => $plan259()->sourceNextSummary()['all_payloads_wait_for_freeblock_source'],
    'all tail pages fenced' => static fn (): mixed => $plan259()->sourceNextSummary()['all_tail_pages_fenced_for_source_next'],
    'token count' => static fn (): mixed => count($plan259()->sourceNextTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan259()->sourceNextTokens()),
    'signature length' => static fn (): mixed => strlen($plan259()->sourceNextSummary()['source_next_signature']),
    'current token length' => static fn (): mixed => strlen($plan259()->sourceNextSummary()['current_source_next259_token']),
    'first state' => static fn (): mixed => $plan259()->sourceNextRows()[0]['source_next_state'],
    'second state' => static fn (): mixed => $plan259()->sourceNextRows()[1]['source_next_state'],
    'first channel' => static fn (): mixed => $plan259()->sourceNextRows()[0]['source_channel'],
    'second channel' => static fn (): mixed => $plan259()->sourceNextRows()[1]['source_channel'],
    'last next page' => static fn (): mixed => $plan259()->sourceNextRows()[6]['source_next_page'],
    'third pointer generations' => static fn (): mixed => $plan259()->sourceNextRows()[2]['pointer_map_source_generations'],
    'fifth pointer generations' => static fn (): mixed => $plan259()->sourceNextRows()[4]['pointer_map_source_generations'],
    'row ordinals' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'source_next_ordinal'),
    'publication ordinals' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'publication_ordinal'),
    'row channels' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'source_channel'),
    'row states' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'source_next_state'),
    'publication token flags' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'publication_token_matches'),
    'link flags' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'source_next_link_valid'),
    'freeblock open flags' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'freeblock_opens_after_pointer_map_source'),
    'payload wait flags' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'payload_waits_for_freeblock_source'),
    'tail fence flags' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'tail_pages_fenced_for_source_next'),
    'duplicate flags' => static fn (): mixed => array_column($plan259()->sourceNextRows(), 'duplicate_pointer_map_source_generation'),
    'first previous token' => static fn (): mixed => $plan259()->sourceNextRows()[0]['previous_source_next_token'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan259()->sourceNextRows()[1]['previous_source_next_token']),
    'batch size three row count' => static fn (): mixed => $plan259(3)->sourceNextSummary()['source_next_row_count'],
    'batch size three pages' => static fn (): mixed => $plan259(3)->sourcePages(),
    'batch size three next pages' => static fn (): mixed => $plan259(3)->sourceNextPages(),
    'batch size three channels' => static fn (): mixed => array_column($plan259(3)->sourceNextRows(), 'source_channel'),
    'dependency closure' => static fn (): mixed => $plan259()->sourceNextSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan259()->sourceNextSummary()['non_overlap'], 'does not repeat publication'),
    'publication action' => static fn (): mixed => $plan259()->publicationPlan->toArray()['action'],
    'publication row count' => static fn (): mixed => $plan259()->publicationPlan->publicationSummary()['publication_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message259(static fn () => $plan259(0)),
];

$expected259 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next259',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next259-ready',
    'row count' => 7,
    'source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary source pages' => [2, 3, 105, 106, 105, 107, 108],
    'source next pages' => [3, 105, 106, 105, 107, 108, null],
    'published pages' => [2, 3, 105, 106, 105, 107, 108],
    'source pages match publication' => true,
    'source next pages match publication' => true,
    'pointer map pages' => [2, 105],
    'freeblock pages' => [3],
    'payload pages' => [106, 107, 108],
    'duplicate pointer map pages' => [105],
    'errors' => [],
    'summary errors' => [],
    'all publication tokens match' => true,
    'all links valid' => true,
    'all freeblocks after pointer map' => true,
    'all payloads wait for freeblock' => true,
    'all tail pages fenced' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first state' => 'current-source-next259-pointer-map-linked',
    'second state' => 'current-source-next259-freeblock-linked',
    'first channel' => 'pointer-map-source',
    'second channel' => 'freeblock-source',
    'last next page' => null,
    'third pointer generations' => ['2:1', '105:1'],
    'fifth pointer generations' => ['2:1', '105:2'],
    'row ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'publication ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row channels' => ['pointer-map-source', 'freeblock-source', 'pointer-map-source', 'payload-source', 'pointer-map-source', 'payload-source', 'payload-source'],
    'row states' => ['current-source-next259-pointer-map-linked', 'current-source-next259-freeblock-linked', 'current-source-next259-pointer-map-linked', 'current-source-next259-freeblock-linked', 'current-source-next259-pointer-map-linked', 'current-source-next259-freeblock-linked', 'current-source-next259-freeblock-linked'],
    'publication token flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'freeblock open flags' => [true, true, true, true, true, true, true],
    'payload wait flags' => [true, true, true, true, true, true, true],
    'tail fence flags' => [true, true, true, true, true, true, true],
    'duplicate flags' => [false, false, false, false, true, false, false],
    'first previous token' => null,
    'second previous token length' => 64,
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three channels' => ['pointer-map-source', 'freeblock-source', 'pointer-map-source', 'payload-source', 'payload-source', 'payload-source'],
    'dependency closure' => 'no new support component needed; current-source next-link validation reuses publication rows and validates current-source next links before freeblock/payload reuse',
    'non overlap' => true,
    'publication action' => 'btree-vacuum-pointermap-freeblock-current-source-publication',
    'publication row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases259 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next259 ' . $name] = static function (TestRunner $t) use ($callback, $expected259, $name): void {
        $t->same($expected259[$name], $callback());
    };
}

foreach (range(1, 84) as $index) {
    $tests['btree vacuum pointermap freeblock current source next259 source-next invariant ' . $index] = static function (TestRunner $t) use ($plan259): void {
        $plan = $plan259();
        $summary = $plan->sourceNextSummary();

        $t->same([], $plan->sourceNextErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->sourcePages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->sourceNextPages());
        $t->same([2, 105], $plan->pointerMapSourcePages());
        $t->same([3], $plan->freeblockSourcePages());
        $t->same([106, 107, 108], $plan->payloadSourcePages());
        $t->same([105], $plan->duplicatePointerMapSourcePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->sourceNextRows(), 'source_next_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'publication_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'source_next_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'payload_waits_for_freeblock_source'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sourceNextRows(), 'tail_pages_fenced_for_source_next'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sourceNextTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next259-ready', $summary['status']);
        $t->same(true, $summary['source_pages_match_publication']);
        $t->same(true, $summary['source_next_pages_match_publication']);
        $t->same(true, $summary['all_freeblocks_open_after_pointer_map']);
        $t->same(true, $summary['all_payloads_wait_for_freeblock_source']);
    };
}

return $tests;
