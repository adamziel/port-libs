<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage246 = static function (int $pageCount): string {
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

$putPointerMapEntry246 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database246 = static function () use ($makeFirstPage246, $putPointerMapEntry246): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage246(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next246', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry246($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan246 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Plan {
    global $database246;

    $database = $database246();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Plan::tableLeafFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next246-current-source-reuse-cursor-', 40),
        3,
        true,
        $batchSize,
    );
};

$message246 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases246 = [
    'action label' => static fn (): mixed => $plan246()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan246()->reuseSummary()['status'],
    'reuse row count' => static fn (): mixed => $plan246()->reuseSummary()['reuse_row_count'],
    'reuse pages' => static fn (): mixed => $plan246()->reusePages(),
    'summary reuse pages' => static fn (): mixed => $plan246()->reuseSummary()['reuse_pages'],
    'summary current source pages' => static fn (): mixed => $plan246()->reuseSummary()['current_source_pages'],
    'reuse pages match current source pages' => static fn (): mixed => $plan246()->reuseSummary()['reuse_pages_match_current_source_pages'],
    'pointer map barrier pages' => static fn (): mixed => $plan246()->pointerMapBarrierPages(),
    'summary pointer map barrier pages' => static fn (): mixed => $plan246()->reuseSummary()['pointer_map_barrier_pages'],
    'allocated freeblock pages' => static fn (): mixed => $plan246()->allocatedFreeblockPages(),
    'summary allocated freeblock pages' => static fn (): mixed => $plan246()->reuseSummary()['allocated_freeblock_pages'],
    'duplicate pointer map pages' => static fn (): mixed => $plan246()->duplicatePointerMapPages(),
    'summary duplicate pointer map pages' => static fn (): mixed => $plan246()->reuseSummary()['duplicate_pointer_map_pages'],
    'reuse errors' => static fn (): mixed => $plan246()->reuseErrors(),
    'summary reuse errors' => static fn (): mixed => $plan246()->reuseSummary()['reuse_errors'],
    'all current source tokens match' => static fn (): mixed => $plan246()->reuseSummary()['all_current_source_tokens_match'],
    'all pointer map generations current' => static fn (): mixed => $plan246()->reuseSummary()['all_pointer_map_generations_current'],
    'all freeblock reuse waits for pointer map' => static fn (): mixed => $plan246()->reuseSummary()['all_freeblock_reuse_waits_for_pointer_map'],
    'all leaf receipts current at reuse' => static fn (): mixed => $plan246()->reuseSummary()['all_leaf_receipts_current_at_reuse'],
    'all trunk lease stable' => static fn (): mixed => $plan246()->reuseSummary()['all_trunk_lease_stable'],
    'all tail pages remain excluded' => static fn (): mixed => $plan246()->reuseSummary()['all_tail_pages_remain_excluded'],
    'all reuse links valid' => static fn (): mixed => $plan246()->reuseSummary()['all_reuse_links_valid'],
    'token count' => static fn (): mixed => count($plan246()->reuseTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan246()->reuseTokens()),
    'signature length' => static fn (): mixed => strlen($plan246()->reuseSummary()['reuse_signature']),
    'current source token length' => static fn (): mixed => strlen($plan246()->reuseSummary()['current_source_next246_token']),
    'first row channel' => static fn (): mixed => $plan246()->reuseRows()[0]['reuse_channel'],
    'first row page' => static fn (): mixed => $plan246()->reuseRows()[0]['reuse_page'],
    'first pointer map generations' => static fn (): mixed => $plan246()->reuseRows()[0]['pointer_map_generations'],
    'second row channel' => static fn (): mixed => $plan246()->reuseRows()[1]['reuse_channel'],
    'second allocated freeblocks' => static fn (): mixed => $plan246()->reuseRows()[1]['allocated_freeblock_pages'],
    'second trunk lease page' => static fn (): mixed => $plan246()->reuseRows()[1]['trunk_lease_page'],
    'fifth duplicate pointer map generation' => static fn (): mixed => $plan246()->reuseRows()[4]['duplicate_pointer_map_generation'],
    'fifth pointer map generations' => static fn (): mixed => $plan246()->reuseRows()[4]['pointer_map_generations'],
    'last allocated freeblocks' => static fn (): mixed => $plan246()->reuseRows()[6]['allocated_freeblock_pages'],
    'reuse ordinals' => static fn (): mixed => array_column($plan246()->reuseRows(), 'reuse_ordinal'),
    'current source ordinals' => static fn (): mixed => array_column($plan246()->reuseRows(), 'current_source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan246()->reuseRows(), 'reuse_state'),
    'row token flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'current_source_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'reuse_link_valid'),
    'row pointer current flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'pointer_map_generation_current'),
    'row freeblock wait flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'freeblock_reuse_waits_for_pointer_map'),
    'row leaf receipt flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'leaf_receipt_current_at_reuse'),
    'row trunk lease flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'trunk_lease_stable'),
    'row tail excluded flags' => static fn (): mixed => array_column($plan246()->reuseRows(), 'tail_pages_remain_excluded'),
    'batch size three row count' => static fn (): mixed => $plan246(3)->reuseSummary()['reuse_row_count'],
    'batch size three pages' => static fn (): mixed => $plan246(3)->reusePages(),
    'batch size three allocated freeblocks' => static fn (): mixed => $plan246(3)->allocatedFreeblockPages(),
    'batch size three pointer barriers' => static fn (): mixed => $plan246(3)->pointerMapBarrierPages(),
    'dependency closure' => static fn (): mixed => $plan246()->reuseSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan246()->reuseSummary()['non_overlap'], 'does not repeat next242'),
    'current source action' => static fn (): mixed => $plan246()->currentSourcePlan->toArray()['action'],
    'current source row count' => static fn (): mixed => $plan246()->currentSourcePlan->currentSourceSummary()['current_source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message246(static fn () => $plan246(0)),
];

$expected246 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next246',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next246-ready',
    'reuse row count' => 7,
    'reuse pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary reuse pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary current source pages' => [2, 3, 105, 106, 105, 107, 108],
    'reuse pages match current source pages' => true,
    'pointer map barrier pages' => [2, 105],
    'summary pointer map barrier pages' => [2, 105],
    'allocated freeblock pages' => [3, 106, 107, 108],
    'summary allocated freeblock pages' => [3, 106, 107, 108],
    'duplicate pointer map pages' => [105],
    'summary duplicate pointer map pages' => [105],
    'reuse errors' => [],
    'summary reuse errors' => [],
    'all current source tokens match' => true,
    'all pointer map generations current' => true,
    'all freeblock reuse waits for pointer map' => true,
    'all leaf receipts current at reuse' => true,
    'all trunk lease stable' => true,
    'all tail pages remain excluded' => true,
    'all reuse links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current source token length' => 64,
    'first row channel' => 'pointer-map-barrier',
    'first row page' => 2,
    'first pointer map generations' => ['2:1'],
    'second row channel' => 'reusable-freeblock',
    'second allocated freeblocks' => [3],
    'second trunk lease page' => 3,
    'fifth duplicate pointer map generation' => true,
    'fifth pointer map generations' => ['2:1', '105:2'],
    'last allocated freeblocks' => [3, 106, 107, 108],
    'reuse ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'current source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next246-vacuum-reuse-cursor-published', 'current-source-next246-vacuum-reuse-cursor-published', 'current-source-next246-vacuum-reuse-cursor-published', 'current-source-next246-vacuum-reuse-cursor-published', 'current-source-next246-vacuum-reuse-cursor-published', 'current-source-next246-vacuum-reuse-cursor-published', 'current-source-next246-vacuum-reuse-cursor-published'],
    'row token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row pointer current flags' => [true, true, true, true, true, true, true],
    'row freeblock wait flags' => [true, true, true, true, true, true, true],
    'row leaf receipt flags' => [true, true, true, true, true, true, true],
    'row trunk lease flags' => [true, true, true, true, true, true, true],
    'row tail excluded flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three allocated freeblocks' => [3, 106, 107, 108],
    'batch size three pointer barriers' => [2, 105],
    'dependency closure' => 'no new support component needed; next246 reuses next242 current-source rows and validates vacuum reuse cursor publication',
    'non overlap' => true,
    'current source action' => 'btree-vacuum-pointermap-freeblock-current-source-next242',
    'current source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases246 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next246 ' . $name] = static function (TestRunner $t) use ($callback, $expected246, $name): void {
        $t->same($expected246[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next246 reuse invariant ' . $index] = static function (TestRunner $t) use ($plan246): void {
        $plan = $plan246();
        $summary = $plan->reuseSummary();

        $t->same([], $plan->reuseErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->reusePages());
        $t->same([2, 105], $plan->pointerMapBarrierPages());
        $t->same([3, 106, 107, 108], $plan->allocatedFreeblockPages());
        $t->same([105], $plan->duplicatePointerMapPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->reuseRows(), 'reuse_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'current_source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'reuse_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'freeblock_reuse_waits_for_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'leaf_receipt_current_at_reuse'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'trunk_lease_stable'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->reuseRows(), 'tail_pages_remain_excluded'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->reuseTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next246-ready', $summary['status']);
        $t->same(true, $summary['reuse_pages_match_current_source_pages']);
        $t->same(true, $summary['all_freeblock_reuse_waits_for_pointer_map']);
    };
}

return $tests;
