<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage240 = static function (int $pageCount): string {
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

$putPointerMapEntry240 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database240 = static function () use ($makeFirstPage240, $putPointerMapEntry240): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage240(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next240', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(82 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry240($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan240 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database240;

    $database = $database240();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafReuseAdmissionFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next240-current-source-reuse-', 50),
        3,
        true,
        $batchSize,
    );
};

$message240 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases240 = [
    'action label' => static fn (): mixed => $plan240()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan240()->reuseSummary()['status'],
    'reuse row count' => static fn (): mixed => $plan240()->reuseSummary()['reuse_row_count'],
    'reuse pages' => static fn (): mixed => $plan240()->reusePages(),
    'summary reuse pages' => static fn (): mixed => $plan240()->reuseSummary()['reuse_pages'],
    'next reuse pages' => static fn (): mixed => $plan240()->nextReusePages(),
    'source next pages' => static fn (): mixed => $plan240()->reuseSummary()['source_next_pages'],
    'reuse pages match source next pages' => static fn (): mixed => $plan240()->reuseSummary()['reuse_pages_match_source_next_pages'],
    'pointer map reuse pages' => static fn (): mixed => $plan240()->pointerMapReusePages(),
    'duplicate pointer map reuse pages' => static fn (): mixed => $plan240()->duplicatePointerMapReusePages(),
    'reusable payload pages' => static fn (): mixed => $plan240()->reusablePayloadPages(),
    'reuse errors' => static fn (): mixed => $plan240()->reuseErrors(),
    'summary reuse errors' => static fn (): mixed => $plan240()->reuseSummary()['reuse_errors'],
    'all source next tokens match' => static fn (): mixed => $plan240()->reuseSummary()['all_source_next_tokens_match'],
    'all reuse links valid' => static fn (): mixed => $plan240()->reuseSummary()['all_reuse_links_valid'],
    'all payload reuse waits for pointer map' => static fn (): mixed => $plan240()->reuseSummary()['all_payload_reuse_waits_for_pointer_map'],
    'all duplicate pointer map reuse current' => static fn (): mixed => $plan240()->reuseSummary()['all_duplicate_pointer_map_reuse_current'],
    'all freeblock receipts current at reuse' => static fn (): mixed => $plan240()->reuseSummary()['all_freeblock_receipts_current_at_reuse'],
    'all tail pages fenced until reuse' => static fn (): mixed => $plan240()->reuseSummary()['all_tail_pages_fenced_until_reuse'],
    'token count' => static fn (): mixed => count($plan240()->reuseTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan240()->reuseTokens()),
    'signature length' => static fn (): mixed => strlen($plan240()->reuseSummary()['reuse_signature']),
    'current token length' => static fn (): mixed => strlen($plan240()->reuseSummary()['current_source_next240_token']),
    'first row state' => static fn (): mixed => $plan240()->reuseRows()[0]['reuse_state'],
    'second row state' => static fn (): mixed => $plan240()->reuseRows()[1]['reuse_state'],
    'first row channel' => static fn (): mixed => $plan240()->reuseRows()[0]['reuse_channel'],
    'second row channel' => static fn (): mixed => $plan240()->reuseRows()[1]['reuse_channel'],
    'first visible generations' => static fn (): mixed => $plan240()->reuseRows()[0]['visible_pointer_map_generations'],
    'second visible generations' => static fn (): mixed => $plan240()->reuseRows()[1]['visible_pointer_map_generations'],
    'fourth payload reuse pages' => static fn (): mixed => $plan240()->reuseRows()[3]['visible_payload_reuse_pages'],
    'fifth duplicate pointer map' => static fn (): mixed => $plan240()->reuseRows()[4]['duplicate_pointer_map_reuse'],
    'fifth visible generations' => static fn (): mixed => $plan240()->reuseRows()[4]['visible_pointer_map_generations'],
    'last row next page' => static fn (): mixed => $plan240()->reuseRows()[6]['next_reuse_page'],
    'ordinals' => static fn (): mixed => array_column($plan240()->reuseRows(), 'reuse_ordinal'),
    'source ordinals' => static fn (): mixed => array_column($plan240()->reuseRows(), 'source_next_ordinal'),
    'row states' => static fn (): mixed => array_column($plan240()->reuseRows(), 'reuse_state'),
    'row source token flags' => static fn (): mixed => array_column($plan240()->reuseRows(), 'source_next_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan240()->reuseRows(), 'reuse_link_valid'),
    'row payload wait flags' => static fn (): mixed => array_column($plan240()->reuseRows(), 'payload_reuse_waits_for_pointer_map'),
    'row duplicate current flags' => static fn (): mixed => array_column($plan240()->reuseRows(), 'duplicate_pointer_map_reuse_current'),
    'row freeblock flags' => static fn (): mixed => array_column($plan240()->reuseRows(), 'freeblock_receipt_current_at_reuse'),
    'row tail fence flags' => static fn (): mixed => array_column($plan240()->reuseRows(), 'tail_page_fenced_until_reuse'),
    'batch size three row count' => static fn (): mixed => $plan240(3)->reuseSummary()['reuse_row_count'],
    'batch size three pages' => static fn (): mixed => $plan240(3)->reusePages(),
    'batch size three next pages' => static fn (): mixed => $plan240(3)->nextReusePages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan240(3)->reusablePayloadPages(),
    'dependency closure' => static fn (): mixed => $plan240()->reuseSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan240()->reuseSummary()['non_overlap'], 'does not repeat next236'),
    'source next action' => static fn (): mixed => $plan240()->sourceNextPlan->toArray()['action'],
    'source next row count' => static fn (): mixed => $plan240()->sourceNextPlan->sourceNextSummary()['source_next_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message240(static fn () => $plan240(0)),
];

$expected240 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next240',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next240-ready',
    'reuse row count' => 7,
    'reuse pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary reuse pages' => [2, 3, 105, 106, 105, 107, 108],
    'next reuse pages' => [3, 105, 106, 105, 107, 108, null],
    'source next pages' => [2, 3, 105, 106, 105, 107, 108],
    'reuse pages match source next pages' => true,
    'pointer map reuse pages' => [2, 105],
    'duplicate pointer map reuse pages' => [105],
    'reusable payload pages' => [3, 106, 107, 108],
    'reuse errors' => [],
    'summary reuse errors' => [],
    'all source next tokens match' => true,
    'all reuse links valid' => true,
    'all payload reuse waits for pointer map' => true,
    'all duplicate pointer map reuse current' => true,
    'all freeblock receipts current at reuse' => true,
    'all tail pages fenced until reuse' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'pointer-map-reuse-gate',
    'second row state' => 'payload-freeblock-reusable',
    'first row channel' => 'pointer-map',
    'second row channel' => 'payload',
    'first visible generations' => ['2:1'],
    'second visible generations' => ['2:1'],
    'fourth payload reuse pages' => [3, 106],
    'fifth duplicate pointer map' => true,
    'fifth visible generations' => ['2:1', '105:2'],
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['pointer-map-reuse-gate', 'payload-freeblock-reusable', 'pointer-map-reuse-gate', 'payload-freeblock-reusable', 'pointer-map-reuse-gate', 'payload-freeblock-reusable', 'payload-freeblock-reusable'],
    'row source token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row payload wait flags' => [true, true, true, true, true, true, true],
    'row duplicate current flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next240 reuses next236 source-next rows and validates freeblock reuse admission ordering',
    'non overlap' => true,
    'source next action' => 'btree-vacuum-pointermap-freeblock-current-source-next236',
    'source next row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases240 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next240 ' . $name] = static function (TestRunner $t) use ($callback, $expected240, $name): void {
        $t->same($expected240[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next240 reuse invariant ' . $index] = static function (TestRunner $t) use ($plan240): void {
        $plan = $plan240();
        $summary = $plan->reuseSummary();

        $t->same([], $plan->reuseErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->reusePages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextReusePages());
        $t->same([2, 105], $plan->pointerMapReusePages());
        $t->same([105], $plan->duplicatePointerMapReusePages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->reuseRows(), 'reuse_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'source_next_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'reuse_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'payload_reuse_waits_for_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'duplicate_pointer_map_reuse_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'freeblock_receipt_current_at_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'tail_page_fenced_until_reuse'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->reuseTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next240-ready', $summary['status']);
        $t->same(true, $summary['reuse_pages_match_source_next_pages']);
        $t->same(true, $summary['all_payload_reuse_waits_for_pointer_map']);
    };
}

return $tests;
