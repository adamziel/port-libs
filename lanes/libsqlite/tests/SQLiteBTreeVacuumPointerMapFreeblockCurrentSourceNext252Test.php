<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage252 = static function (int $pageCount): string {
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

$putPointerMapEntry252 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database252 = static function () use ($makeFirstPage252, $putPointerMapEntry252): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage252(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next252', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry252($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan252 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database252;

    $database = $database252();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext252(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next252-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message252 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases252 = [
    'action label' => static fn (): mixed => $plan252()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan252()->handoffSummary()['status'],
    'handoff row count' => static fn (): mixed => $plan252()->handoffSummary()['handoff_row_count'],
    'admissible pages' => static fn (): mixed => $plan252()->admissiblePages(),
    'summary admissible pages' => static fn (): mixed => $plan252()->handoffSummary()['admissible_pages'],
    'summary seal pages' => static fn (): mixed => $plan252()->handoffSummary()['seal_pages'],
    'admissible pages match seal' => static fn (): mixed => $plan252()->handoffSummary()['admissible_pages_match_seal'],
    'pointer map pages' => static fn (): mixed => $plan252()->pointerMapPages(),
    'summary pointer map pages' => static fn (): mixed => $plan252()->handoffSummary()['pointer_map_pages'],
    'published freeblock pages' => static fn (): mixed => $plan252()->publishedFreeblockPages(),
    'summary published freeblock pages' => static fn (): mixed => $plan252()->handoffSummary()['published_freeblock_pages'],
    'reusable payload pages' => static fn (): mixed => $plan252()->reusablePayloadPages(),
    'summary reusable payload pages' => static fn (): mixed => $plan252()->handoffSummary()['reusable_payload_pages'],
    'handoff errors' => static fn (): mixed => $plan252()->handoffErrors(),
    'summary handoff errors' => static fn (): mixed => $plan252()->handoffSummary()['handoff_errors'],
    'all seal tokens match' => static fn (): mixed => $plan252()->handoffSummary()['all_seal_tokens_match'],
    'all pointer maps admitted before payload reuse' => static fn (): mixed => $plan252()->handoffSummary()['all_pointer_maps_admitted_before_payload_reuse'],
    'all freeblocks admitted before payload reuse' => static fn (): mixed => $plan252()->handoffSummary()['all_freeblocks_admitted_before_payload_reuse'],
    'all tail pages fenced at handoff' => static fn (): mixed => $plan252()->handoffSummary()['all_tail_pages_fenced_at_handoff'],
    'handoff token count' => static fn (): mixed => count($plan252()->handoffTokens()),
    'handoff token lengths' => static fn (): mixed => array_map('strlen', $plan252()->handoffTokens()),
    'handoff signature length' => static fn (): mixed => strlen($plan252()->handoffSummary()['handoff_signature']),
    'current source token length' => static fn (): mixed => strlen($plan252()->handoffSummary()['current_source_next252_token']),
    'first handoff channel' => static fn (): mixed => $plan252()->handoffRows()[0]['handoff_channel'],
    'first admissible page' => static fn (): mixed => $plan252()->handoffRows()[0]['admissible_page'],
    'first admitted pointer maps' => static fn (): mixed => $plan252()->handoffRows()[0]['admitted_pointer_map_pages'],
    'first admitted freeblocks' => static fn (): mixed => $plan252()->handoffRows()[0]['admitted_freeblock_pages'],
    'first payload reuse admitted' => static fn (): mixed => $plan252()->handoffRows()[0]['payload_reuse_admitted'],
    'second handoff channel' => static fn (): mixed => $plan252()->handoffRows()[1]['handoff_channel'],
    'second admissible page' => static fn (): mixed => $plan252()->handoffRows()[1]['admissible_page'],
    'second payload reuse admitted' => static fn (): mixed => $plan252()->handoffRows()[1]['payload_reuse_admitted'],
    'third pointer map pages' => static fn (): mixed => $plan252()->handoffRows()[2]['admitted_pointer_map_pages'],
    'fifth duplicate pointer map pages' => static fn (): mixed => $plan252()->handoffRows()[4]['admitted_pointer_map_pages'],
    'last admissible page' => static fn (): mixed => $plan252()->handoffRows()[6]['admissible_page'],
    'handoff ordinals' => static fn (): mixed => array_column($plan252()->handoffRows(), 'handoff_ordinal'),
    'seal ordinals' => static fn (): mixed => array_column($plan252()->handoffRows(), 'seal_ordinal'),
    'row states' => static fn (): mixed => array_column($plan252()->handoffRows(), 'handoff_state'),
    'row seal token flags' => static fn (): mixed => array_column($plan252()->handoffRows(), 'seal_token_matches'),
    'row freeblock publication flags' => static fn (): mixed => array_column($plan252()->handoffRows(), 'freeblock_publication_admitted'),
    'row pointer admission flags' => static fn (): mixed => array_column($plan252()->handoffRows(), 'pointer_maps_admitted_before_payload_reuse'),
    'row freeblock admission flags' => static fn (): mixed => array_column($plan252()->handoffRows(), 'freeblocks_admitted_before_payload_reuse'),
    'row tail fence flags' => static fn (): mixed => array_column($plan252()->handoffRows(), 'tail_page_fenced_at_handoff'),
    'batch size three row count' => static fn (): mixed => $plan252(3)->handoffSummary()['handoff_row_count'],
    'batch size three pages' => static fn (): mixed => $plan252(3)->admissiblePages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan252(3)->reusablePayloadPages(),
    'batch size three pointer map pages' => static fn (): mixed => $plan252(3)->pointerMapPages(),
    'dependency closure' => static fn (): mixed => $plan252()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan252()->handoffSummary()['non_overlap'], 'does not repeat next248'),
    'seal action' => static fn (): mixed => $plan252()->sealPlan->toArray()['action'],
    'seal row count' => static fn (): mixed => $plan252()->sealPlan->sealSummary()['seal_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message252(static fn () => $plan252(0)),
];

$expected252 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next252',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next252-ready',
    'handoff row count' => 7,
    'admissible pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary admissible pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary seal pages' => [2, 3, 105, 106, 105, 107, 108],
    'admissible pages match seal' => true,
    'pointer map pages' => [2, 105],
    'summary pointer map pages' => [2, 105],
    'published freeblock pages' => [2, 3, 105, 106, 107, 108],
    'summary published freeblock pages' => [2, 3, 105, 106, 107, 108],
    'reusable payload pages' => [3, 106, 107, 108],
    'summary reusable payload pages' => [3, 106, 107, 108],
    'handoff errors' => [],
    'summary handoff errors' => [],
    'all seal tokens match' => true,
    'all pointer maps admitted before payload reuse' => true,
    'all freeblocks admitted before payload reuse' => true,
    'all tail pages fenced at handoff' => true,
    'handoff token count' => 7,
    'handoff token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'handoff signature length' => 64,
    'current source token length' => 64,
    'first handoff channel' => 'pointer-map',
    'first admissible page' => 2,
    'first admitted pointer maps' => [2],
    'first admitted freeblocks' => [2],
    'first payload reuse admitted' => false,
    'second handoff channel' => 'payload',
    'second admissible page' => 3,
    'second payload reuse admitted' => true,
    'third pointer map pages' => [2, 105],
    'fifth duplicate pointer map pages' => [2, 105],
    'last admissible page' => 108,
    'handoff ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'seal ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next252-vacuum-freeblock-admitted', 'current-source-next252-vacuum-freeblock-admitted', 'current-source-next252-vacuum-freeblock-admitted', 'current-source-next252-vacuum-freeblock-admitted', 'current-source-next252-vacuum-freeblock-admitted', 'current-source-next252-vacuum-freeblock-admitted', 'current-source-next252-vacuum-freeblock-admitted'],
    'row seal token flags' => [true, true, true, true, true, true, true],
    'row freeblock publication flags' => [true, true, true, true, true, true, true],
    'row pointer admission flags' => [true, true, true, true, true, true, true],
    'row freeblock admission flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'batch size three pointer map pages' => [2, 105],
    'dependency closure' => 'no new support component needed; next252 reuses next248 publication seals and adds current-source admission checks before vacuum freeblock reuse is exposed',
    'non overlap' => true,
    'seal action' => 'btree-vacuum-pointermap-freeblock-current-source-next248',
    'seal row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases252 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next252 ' . $name] = static function (TestRunner $t) use ($callback, $expected252, $name): void {
        $t->same($expected252[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next252 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan252): void {
        $plan = $plan252();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->admissiblePages());
        $t->same([2, 105], $plan->pointerMapPages());
        $t->same([2, 3, 105, 106, 107, 108], $plan->publishedFreeblockPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->handoffRows(), 'handoff_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'seal_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'freeblock_publication_admitted'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'pointer_maps_admitted_before_payload_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'freeblocks_admitted_before_payload_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->handoffRows(), 'tail_page_fenced_at_handoff'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->handoffTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next252-ready', $summary['status']);
        $t->same(true, $summary['admissible_pages_match_seal']);
        $t->same(true, $summary['all_freeblocks_admitted_before_payload_reuse']);
    };
}

return $tests;
