<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage257 = static function (int $pageCount): string {
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

$putPointerMapEntry257 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database257 = static function () use ($makeFirstPage257, $putPointerMapEntry257): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage257(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next257', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry257($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan257 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database257;

    $database = $database257();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafAdvanceFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next257-source-advance-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$message257 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases257 = [
    'action label' => static fn (): mixed => $plan257()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan257()->advanceSummary()['status'],
    'row count' => static fn (): mixed => $plan257()->advanceSummary()['advance_row_count'],
    'advanced pages' => static fn (): mixed => $plan257()->advancedPages(),
    'summary advanced pages' => static fn (): mixed => $plan257()->advanceSummary()['advanced_pages'],
    'summary apply pages' => static fn (): mixed => $plan257()->advanceSummary()['apply_pages'],
    'pages match apply' => static fn (): mixed => $plan257()->advanceSummary()['advanced_pages_match_apply'],
    'pointer map pages' => static fn (): mixed => $plan257()->committedPointerMapPages(),
    'summary pointer map pages' => static fn (): mixed => $plan257()->advanceSummary()['committed_pointer_map_pages'],
    'freeblock pages' => static fn (): mixed => $plan257()->committedFreeblockPages(),
    'summary freeblock pages' => static fn (): mixed => $plan257()->advanceSummary()['committed_freeblock_pages'],
    'pages by group' => static fn (): mixed => $plan257()->committedPagesByGroup(),
    'summary pages by group' => static fn (): mixed => $plan257()->advanceSummary()['committed_pages_by_group'],
    'errors' => static fn (): mixed => $plan257()->advanceErrors(),
    'summary errors' => static fn (): mixed => $plan257()->advanceSummary()['advance_errors'],
    'all apply tokens match' => static fn (): mixed => $plan257()->advanceSummary()['all_apply_tokens_match'],
    'all groups have pointer map opener' => static fn (): mixed => $plan257()->advanceSummary()['all_groups_have_pointer_map_opener'],
    'all freeblocks wait' => static fn (): mixed => $plan257()->advanceSummary()['all_freeblocks_wait_for_group_pointer_map'],
    'all leaf receipts committed' => static fn (): mixed => $plan257()->advanceSummary()['all_leaf_receipts_committed'],
    'all tail fenced' => static fn (): mixed => $plan257()->advanceSummary()['all_tail_pages_fenced_until_after_advance'],
    'all epochs monotonic' => static fn (): mixed => $plan257()->advanceSummary()['all_source_epochs_monotonic'],
    'all links valid' => static fn (): mixed => $plan257()->advanceSummary()['all_advance_links_valid'],
    'token count' => static fn (): mixed => count($plan257()->advanceTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan257()->advanceTokens()),
    'signature length' => static fn (): mixed => strlen($plan257()->advanceSummary()['advance_signature']),
    'next token length' => static fn (): mixed => strlen($plan257()->advanceSummary()['current_source_next257_token']),
    'first channel' => static fn (): mixed => $plan257()->advanceRows()[0]['advance_channel'],
    'first group pointer page' => static fn (): mixed => $plan257()->advanceRows()[0]['group_pointer_map_page'],
    'second channel' => static fn (): mixed => $plan257()->advanceRows()[1]['advance_channel'],
    'second source epoch' => static fn (): mixed => $plan257()->advanceRows()[1]['source_epoch'],
    'third channel' => static fn (): mixed => $plan257()->advanceRows()[2]['advance_channel'],
    'third group pointer page' => static fn (): mixed => $plan257()->advanceRows()[2]['group_pointer_map_page'],
    'fifth channel' => static fn (): mixed => $plan257()->advanceRows()[4]['advance_channel'],
    'fifth group' => static fn (): mixed => $plan257()->advanceRows()[4]['advance_group'],
    'last epoch' => static fn (): mixed => $plan257()->advanceRows()[6]['source_epoch'],
    'last page' => static fn (): mixed => $plan257()->advanceRows()[6]['advanced_page'],
    'ordinals' => static fn (): mixed => array_column($plan257()->advanceRows(), 'advance_ordinal'),
    'apply ordinals' => static fn (): mixed => array_column($plan257()->advanceRows(), 'apply_ordinal'),
    'source epochs' => static fn (): mixed => array_column($plan257()->advanceRows(), 'source_epoch'),
    'previous source epochs' => static fn (): mixed => array_column($plan257()->advanceRows(), 'previous_source_epoch'),
    'row states' => static fn (): mixed => array_column($plan257()->advanceRows(), 'advance_state'),
    'token flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'apply_token_matches'),
    'group flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'group_has_pointer_map_opener'),
    'wait flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'freeblock_waited_for_pointer_map'),
    'receipt flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'leaf_receipt_committed'),
    'tail flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'tail_page_fenced_until_after_advance'),
    'epoch flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'source_epoch_monotonic'),
    'link flags' => static fn (): mixed => array_column($plan257()->advanceRows(), 'advance_link_valid'),
    'batch size three row count' => static fn (): mixed => $plan257(3)->advanceSummary()['advance_row_count'],
    'batch size three pages' => static fn (): mixed => $plan257(3)->advancedPages(),
    'batch size three groups' => static fn (): mixed => array_column($plan257(3)->advanceRows(), 'advance_group'),
    'batch size three pages by group' => static fn (): mixed => $plan257(3)->committedPagesByGroup(),
    'dependency closure' => static fn (): mixed => $plan257()->advanceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan257()->advanceSummary()['non_overlap'], 'does not repeat next253'),
    'apply action' => static fn (): mixed => $plan257()->applyPlan->toArray()['action'],
    'apply row count' => static fn (): mixed => $plan257()->applyPlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message257(static fn () => $plan257(0)),
];

$expected257 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next257',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next257-ready',
    'row count' => 7,
    'advanced pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary advanced pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary apply pages' => [2, 3, 105, 106, 105, 107, 108],
    'pages match apply' => true,
    'pointer map pages' => [2, 105],
    'summary pointer map pages' => [2, 105],
    'freeblock pages' => [3, 106, 107, 108],
    'summary freeblock pages' => [3, 106, 107, 108],
    'pages by group' => [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]],
    'summary pages by group' => [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]],
    'errors' => [],
    'summary errors' => [],
    'all apply tokens match' => true,
    'all groups have pointer map opener' => true,
    'all freeblocks wait' => true,
    'all leaf receipts committed' => true,
    'all tail fenced' => true,
    'all epochs monotonic' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first channel' => 'pointer-map-source-advance',
    'first group pointer page' => 2,
    'second channel' => 'freeblock-source-advance',
    'second source epoch' => 3,
    'third channel' => 'pointer-map-source-advance',
    'third group pointer page' => 105,
    'fifth channel' => 'pointer-map-source-advance',
    'fifth group' => 3,
    'last epoch' => 10,
    'last page' => 108,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'apply ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source epochs' => [2, 3, 5, 6, 8, 9, 10],
    'previous source epochs' => [0, 2, 3, 5, 6, 8, 9],
    'row states' => ['current-source-next257-advance-ready', 'current-source-next257-advance-ready', 'current-source-next257-advance-ready', 'current-source-next257-advance-ready', 'current-source-next257-advance-ready', 'current-source-next257-advance-ready', 'current-source-next257-advance-ready'],
    'token flags' => [true, true, true, true, true, true, true],
    'group flags' => [true, true, true, true, true, true, true],
    'wait flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'tail flags' => [true, true, true, true, true, true, true],
    'epoch flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three groups' => [1, 1, 2, 2, 2, 2],
    'batch size three pages by group' => [1 => [2, 3], 2 => [105, 106, 107, 108]],
    'dependency closure' => 'no new support component needed; next257 reuses next253 grouped apply rows and records the current-source advance fence after each pointer-map/freeblock group is durable',
    'non overlap' => true,
    'apply action' => 'btree-vacuum-pointermap-freeblock-current-source-next253',
    'apply row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases257 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next257 ' . $name] = static function (TestRunner $t) use ($callback, $expected257, $name): void {
        $t->same($expected257[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next257 source advance invariant ' . $index] = static function (TestRunner $t) use ($plan257): void {
        $plan = $plan257();
        $summary = $plan->advanceSummary();

        $t->same([], $plan->advanceErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->advancedPages());
        $t->same([2, 105], $plan->committedPointerMapPages());
        $t->same([3, 106, 107, 108], $plan->committedFreeblockPages());
        $t->same([1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]], $plan->committedPagesByGroup());
        $t->same([2, 3, 5, 6, 8, 9, 10], array_column($plan->advanceRows(), 'source_epoch'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->advanceRows(), 'apply_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->advanceRows(), 'group_has_pointer_map_opener'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->advanceRows(), 'freeblock_waited_for_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->advanceRows(), 'leaf_receipt_committed'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->advanceRows(), 'tail_page_fenced_until_after_advance'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->advanceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next257-ready', $summary['status']);
        $t->same(true, $summary['advanced_pages_match_apply']);
        $t->same(true, $summary['all_source_epochs_monotonic']);
    };
}

return $tests;
