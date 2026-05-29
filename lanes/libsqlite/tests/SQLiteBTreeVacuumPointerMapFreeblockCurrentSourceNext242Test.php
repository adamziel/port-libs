<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage242 = static function (int $pageCount): string {
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

$putPointerMapEntry242 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database242 = static function () use ($makeFirstPage242, $putPointerMapEntry242): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage242(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next242', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(91 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry242($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan242 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database242;

    $database = $database242();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCurrentSourceHandoffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next242-current-source-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$message242 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases242 = [
    'action label' => static fn (): mixed => $plan242()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan242()->currentSourceSummary()['status'],
    'current source row count' => static fn (): mixed => $plan242()->currentSourceSummary()['current_source_row_count'],
    'current source pages' => static fn (): mixed => $plan242()->currentSourcePages(),
    'summary current source pages' => static fn (): mixed => $plan242()->currentSourceSummary()['current_source_pages'],
    'summary freelist pages' => static fn (): mixed => $plan242()->currentSourceSummary()['freelist_pages'],
    'current source pages match freelist pages' => static fn (): mixed => $plan242()->currentSourceSummary()['current_source_pages_match_freelist_pages'],
    'pointer map source pages' => static fn (): mixed => $plan242()->pointerMapSourcePages(),
    'summary pointer map source pages' => static fn (): mixed => $plan242()->currentSourceSummary()['pointer_map_source_pages'],
    'reusable freeblock pages' => static fn (): mixed => $plan242()->reusableFreeblockPages(),
    'summary reusable freeblock pages' => static fn (): mixed => $plan242()->currentSourceSummary()['reusable_freeblock_pages'],
    'trunk candidate pages' => static fn (): mixed => $plan242()->trunkCandidatePages(),
    'summary trunk candidate pages' => static fn (): mixed => $plan242()->currentSourceSummary()['trunk_candidate_pages'],
    'current source errors' => static fn (): mixed => $plan242()->currentSourceErrors(),
    'summary current source errors' => static fn (): mixed => $plan242()->currentSourceSummary()['current_source_errors'],
    'all freelist tokens match' => static fn (): mixed => $plan242()->currentSourceSummary()['all_freelist_tokens_match'],
    'all pointer map barriers visible' => static fn (): mixed => $plan242()->currentSourceSummary()['all_pointer_map_barriers_visible'],
    'all freeblock sources have receipts' => static fn (): mixed => $plan242()->currentSourceSummary()['all_freeblock_sources_have_receipts'],
    'all trunk candidates stable' => static fn (): mixed => $plan242()->currentSourceSummary()['all_trunk_candidates_stable'],
    'all reusable pages monotonic' => static fn (): mixed => $plan242()->currentSourceSummary()['all_reusable_pages_monotonic'],
    'all tail pages excluded' => static fn (): mixed => $plan242()->currentSourceSummary()['all_tail_pages_excluded'],
    'all current source links valid' => static fn (): mixed => $plan242()->currentSourceSummary()['all_current_source_links_valid'],
    'token count' => static fn (): mixed => count($plan242()->currentSourceTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan242()->currentSourceTokens()),
    'signature length' => static fn (): mixed => strlen($plan242()->currentSourceSummary()['current_source_signature']),
    'current source token length' => static fn (): mixed => strlen($plan242()->currentSourceSummary()['current_source_next242_token']),
    'first row channel' => static fn (): mixed => $plan242()->currentSourceRows()[0]['source_channel'],
    'first row page' => static fn (): mixed => $plan242()->currentSourceRows()[0]['current_source_page'],
    'first visible pointer maps' => static fn (): mixed => $plan242()->currentSourceRows()[0]['visible_pointer_map_pages'],
    'first visible reusable pages' => static fn (): mixed => $plan242()->currentSourceRows()[0]['visible_reusable_pages'],
    'second row channel' => static fn (): mixed => $plan242()->currentSourceRows()[1]['source_channel'],
    'second stable trunk' => static fn (): mixed => $plan242()->currentSourceRows()[1]['stable_trunk_candidate_page'],
    'second trunk visible' => static fn (): mixed => $plan242()->currentSourceRows()[1]['trunk_candidate_visible'],
    'fifth visible pointer maps' => static fn (): mixed => $plan242()->currentSourceRows()[4]['visible_pointer_map_pages'],
    'last visible reusable pages' => static fn (): mixed => $plan242()->currentSourceRows()[6]['visible_reusable_pages'],
    'last row page' => static fn (): mixed => $plan242()->currentSourceRows()[6]['current_source_page'],
    'current source ordinals' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'current_source_ordinal'),
    'freelist ordinals' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'freelist_ordinal'),
    'row states' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'current_source_state'),
    'row freelist token flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'freelist_token_matches'),
    'row pointer barrier flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'pointer_map_barrier_visible_before_source'),
    'row freeblock receipt flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'freeblock_source_has_leaf_receipt'),
    'row trunk stable flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'trunk_candidate_stable'),
    'row monotonic flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'reusable_source_monotonic'),
    'row tail excluded flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'tail_page_excluded_from_current_source'),
    'row link flags' => static fn (): mixed => array_column($plan242()->currentSourceRows(), 'current_source_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan242(3)->currentSourceSummary()['current_source_row_count'],
    'batch size three pages' => static fn (): mixed => $plan242(3)->currentSourcePages(),
    'batch size three reusable freeblocks' => static fn (): mixed => $plan242(3)->reusableFreeblockPages(),
    'batch size three pointer map sources' => static fn (): mixed => $plan242(3)->pointerMapSourcePages(),
    'dependency closure' => static fn (): mixed => $plan242()->currentSourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan242()->currentSourceSummary()['non_overlap'], 'does not repeat next238'),
    'freelist action' => static fn (): mixed => $plan242()->freelistPlan->toArray()['action'],
    'freelist row count' => static fn (): mixed => $plan242()->freelistPlan->freelistSummary()['freelist_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message242(static fn () => $plan242(0)),
];

$expected242 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next242',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next242-ready',
    'current source row count' => 7,
    'current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary freelist pages' => [2, 3, 105, 106, 105, 107, 108],
    'current source pages match freelist pages' => true,
    'pointer map source pages' => [2, 105],
    'summary pointer map source pages' => [2, 105],
    'reusable freeblock pages' => [3, 106, 107, 108],
    'summary reusable freeblock pages' => [3, 106, 107, 108],
    'trunk candidate pages' => [3],
    'summary trunk candidate pages' => [3],
    'current source errors' => [],
    'summary current source errors' => [],
    'all freelist tokens match' => true,
    'all pointer map barriers visible' => true,
    'all freeblock sources have receipts' => true,
    'all trunk candidates stable' => true,
    'all reusable pages monotonic' => true,
    'all tail pages excluded' => true,
    'all current source links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current source token length' => 64,
    'first row channel' => 'pointer-map-barrier',
    'first row page' => 2,
    'first visible pointer maps' => [2],
    'first visible reusable pages' => [],
    'second row channel' => 'reusable-freeblock',
    'second stable trunk' => 3,
    'second trunk visible' => true,
    'fifth visible pointer maps' => [2, 105],
    'last visible reusable pages' => [3, 106, 107, 108],
    'last row page' => 108,
    'current source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'freelist ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next242-freeblock-handoff-visible', 'current-source-next242-freeblock-handoff-visible', 'current-source-next242-freeblock-handoff-visible', 'current-source-next242-freeblock-handoff-visible', 'current-source-next242-freeblock-handoff-visible', 'current-source-next242-freeblock-handoff-visible', 'current-source-next242-freeblock-handoff-visible'],
    'row freelist token flags' => [true, true, true, true, true, true, true],
    'row pointer barrier flags' => [true, true, true, true, true, true, true],
    'row freeblock receipt flags' => [true, true, true, true, true, true, true],
    'row trunk stable flags' => [true, true, true, true, true, true, true],
    'row monotonic flags' => [true, true, true, true, true, true, true],
    'row tail excluded flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable freeblocks' => [3, 106, 107, 108],
    'batch size three pointer map sources' => [2, 105],
    'dependency closure' => 'no new support component needed; next242 reuses next238 freelist rows and records current-source freeblock handoff visibility only',
    'non overlap' => true,
    'freelist action' => 'btree-vacuum-pointermap-freeblock-current-source-next238',
    'freelist row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases242 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next242 ' . $name] = static function (TestRunner $t) use ($callback, $expected242, $name): void {
        $t->same($expected242[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next242 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan242): void {
        $plan = $plan242();
        $summary = $plan->currentSourceSummary();

        $t->same([], $plan->currentSourceErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->currentSourcePages());
        $t->same([2, 105], $plan->pointerMapSourcePages());
        $t->same([3, 106, 107, 108], $plan->reusableFreeblockPages());
        $t->same([3], $plan->trunkCandidatePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->currentSourceRows(), 'current_source_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'freelist_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'pointer_map_barrier_visible_before_source'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'freeblock_source_has_leaf_receipt'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'trunk_candidate_stable'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->currentSourceRows(), 'tail_page_excluded_from_current_source'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->currentSourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next242-ready', $summary['status']);
        $t->same(true, $summary['current_source_pages_match_freelist_pages']);
        $t->same(true, $summary['all_freeblock_sources_have_receipts']);
    };
}

return $tests;
