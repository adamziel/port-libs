<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage253 = static function (int $pageCount): string {
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

$putPointerMapEntry253 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database253 = static function () use ($makeFirstPage253, $putPointerMapEntry253): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage253(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next253', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry253($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan253 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database253;

    $database = $database253();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafApplyFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next253-grouped-apply-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$message253 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases253 = [
    'action label' => static fn (): mixed => $plan253()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan253()->applySummary()['status'],
    'row count' => static fn (): mixed => $plan253()->applySummary()['apply_row_count'],
    'apply pages' => static fn (): mixed => $plan253()->applyPages(),
    'summary apply pages' => static fn (): mixed => $plan253()->applySummary()['apply_pages'],
    'summary next source pages' => static fn (): mixed => $plan253()->applySummary()['next_source_pages'],
    'apply pages match' => static fn (): mixed => $plan253()->applySummary()['apply_pages_match_next_source'],
    'pointer map apply pages' => static fn (): mixed => $plan253()->pointerMapApplyPages(),
    'summary pointer map apply pages' => static fn (): mixed => $plan253()->applySummary()['pointer_map_apply_pages'],
    'reusable freeblock pages' => static fn (): mixed => $plan253()->reusableFreeblockPages(),
    'summary reusable freeblock pages' => static fn (): mixed => $plan253()->applySummary()['reusable_freeblock_pages'],
    'group numbers' => static fn (): mixed => $plan253()->applyGroupNumbers(),
    'summary group numbers' => static fn (): mixed => $plan253()->applySummary()['apply_group_numbers'],
    'pages by group' => static fn (): mixed => $plan253()->pagesByApplyGroup(),
    'summary pages by group' => static fn (): mixed => $plan253()->applySummary()['pages_by_apply_group'],
    'errors' => static fn (): mixed => $plan253()->applyErrors(),
    'summary errors' => static fn (): mixed => $plan253()->applySummary()['apply_errors'],
    'all tokens match' => static fn (): mixed => $plan253()->applySummary()['all_next_source_tokens_match'],
    'all groups opened' => static fn (): mixed => $plan253()->applySummary()['all_groups_opened_by_pointer_map'],
    'all reusable after group pointer map' => static fn (): mixed => $plan253()->applySummary()['all_reusable_pages_after_group_pointer_map'],
    'all receipts ready' => static fn (): mixed => $plan253()->applySummary()['all_leaf_receipts_ready_at_apply'],
    'all tail fenced' => static fn (): mixed => $plan253()->applySummary()['all_tail_pages_remain_fenced'],
    'all links valid' => static fn (): mixed => $plan253()->applySummary()['all_apply_links_valid'],
    'token count' => static fn (): mixed => count($plan253()->applyTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan253()->applyTokens()),
    'signature length' => static fn (): mixed => strlen($plan253()->applySummary()['apply_signature']),
    'next token length' => static fn (): mixed => strlen($plan253()->applySummary()['current_source_next253_token']),
    'first channel' => static fn (): mixed => $plan253()->applyRows()[0]['apply_channel'],
    'first group pointer page' => static fn (): mixed => $plan253()->applyRows()[0]['group_pointer_map_page'],
    'second channel' => static fn (): mixed => $plan253()->applyRows()[1]['apply_channel'],
    'second group' => static fn (): mixed => $plan253()->applyRows()[1]['apply_group'],
    'third channel' => static fn (): mixed => $plan253()->applyRows()[2]['apply_channel'],
    'third group pointer page' => static fn (): mixed => $plan253()->applyRows()[2]['group_pointer_map_page'],
    'fifth channel' => static fn (): mixed => $plan253()->applyRows()[4]['apply_channel'],
    'fifth group' => static fn (): mixed => $plan253()->applyRows()[4]['apply_group'],
    'last group' => static fn (): mixed => $plan253()->applyRows()[6]['apply_group'],
    'last page' => static fn (): mixed => $plan253()->applyRows()[6]['apply_page'],
    'ordinals' => static fn (): mixed => array_column($plan253()->applyRows(), 'apply_ordinal'),
    'source ordinals' => static fn (): mixed => array_column($plan253()->applyRows(), 'next_source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan253()->applyRows(), 'apply_state'),
    'token flags' => static fn (): mixed => array_column($plan253()->applyRows(), 'next_source_token_matches'),
    'group flags' => static fn (): mixed => array_column($plan253()->applyRows(), 'group_opened_by_pointer_map'),
    'reuse flags' => static fn (): mixed => array_column($plan253()->applyRows(), 'reusable_after_group_pointer_map'),
    'receipt flags' => static fn (): mixed => array_column($plan253()->applyRows(), 'leaf_receipt_ready_at_apply'),
    'tail flags' => static fn (): mixed => array_column($plan253()->applyRows(), 'tail_page_still_fenced_at_apply'),
    'link flags' => static fn (): mixed => array_column($plan253()->applyRows(), 'apply_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan253(3)->applySummary()['apply_row_count'],
    'batch size three pages' => static fn (): mixed => $plan253(3)->applyPages(),
    'batch size three groups' => static fn (): mixed => $plan253(3)->applyGroupNumbers(),
    'batch size three pages by group' => static fn (): mixed => $plan253(3)->pagesByApplyGroup(),
    'dependency closure' => static fn (): mixed => $plan253()->applySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan253()->applySummary()['non_overlap'], 'does not repeat allocation publication'),
    'next source action' => static fn (): mixed => $plan253()->nextSourcePlan->toArray()['action'],
    'next source row count' => static fn (): mixed => $plan253()->nextSourcePlan->nextSourceSummary()['next_source_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message253(static fn () => $plan253(0)),
];

$expected253 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next253',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next253-ready',
    'row count' => 7,
    'apply pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary apply pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary next source pages' => [2, 3, 105, 106, 105, 107, 108],
    'apply pages match' => true,
    'pointer map apply pages' => [2, 105],
    'summary pointer map apply pages' => [2, 105],
    'reusable freeblock pages' => [3, 106, 107, 108],
    'summary reusable freeblock pages' => [3, 106, 107, 108],
    'group numbers' => [1, 1, 2, 2, 3, 3, 3],
    'summary group numbers' => [1, 1, 2, 2, 3, 3, 3],
    'pages by group' => [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]],
    'summary pages by group' => [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]],
    'errors' => [],
    'summary errors' => [],
    'all tokens match' => true,
    'all groups opened' => true,
    'all reusable after group pointer map' => true,
    'all receipts ready' => true,
    'all tail fenced' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first channel' => 'pointer-map-apply',
    'first group pointer page' => 2,
    'second channel' => 'reusable-freeblock-apply',
    'second group' => 1,
    'third channel' => 'pointer-map-apply',
    'third group pointer page' => 105,
    'fifth channel' => 'pointer-map-apply',
    'fifth group' => 3,
    'last group' => 3,
    'last page' => 108,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-next253-grouped-vacuum-apply-ready', 'current-source-next253-grouped-vacuum-apply-ready', 'current-source-next253-grouped-vacuum-apply-ready', 'current-source-next253-grouped-vacuum-apply-ready', 'current-source-next253-grouped-vacuum-apply-ready', 'current-source-next253-grouped-vacuum-apply-ready', 'current-source-next253-grouped-vacuum-apply-ready'],
    'token flags' => [true, true, true, true, true, true, true],
    'group flags' => [true, true, true, true, true, true, true],
    'reuse flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'tail flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three groups' => [1, 1, 2, 2, 2, 2],
    'batch size three pages by group' => [1 => [2, 3], 2 => [105, 106, 107, 108]],
    'dependency closure' => 'no new support component needed; next253 reuses allocation publication rows and records grouped vacuum apply ordering only',
    'non overlap' => true,
    'next source action' => 'btree-vacuum-pointermap-freeblock-allocation-publication',
    'next source row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases253 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next253 ' . $name] = static function (TestRunner $t) use ($callback, $expected253, $name): void {
        $t->same($expected253[$name], $callback());
    };
}

foreach (range(1, 96) as $index) {
    $tests['btree vacuum pointermap freeblock current source next253 grouped apply invariant ' . $index] = static function (TestRunner $t) use ($plan253): void {
        $plan = $plan253();
        $summary = $plan->applySummary();

        $t->same([], $plan->applyErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->applyPages());
        $t->same([2, 105], $plan->pointerMapApplyPages());
        $t->same([3, 106, 107, 108], $plan->reusableFreeblockPages());
        $t->same([1, 1, 2, 2, 3, 3, 3], $plan->applyGroupNumbers());
        $t->same([1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]], $plan->pagesByApplyGroup());
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'next_source_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'group_opened_by_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'reusable_after_group_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'leaf_receipt_ready_at_apply'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'tail_page_still_fenced_at_apply'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->applyTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next253-ready', $summary['status']);
        $t->same(true, $summary['apply_pages_match_next_source']);
        $t->same(true, $summary['all_reusable_pages_after_group_pointer_map']);
    };
}

return $tests;
