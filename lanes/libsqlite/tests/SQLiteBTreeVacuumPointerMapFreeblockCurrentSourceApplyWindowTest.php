<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPageapplyWindow = static function (int $pageCount): string {
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

$putPointerMapEntryapplyWindow = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$databaseapplyWindow = static function () use ($makeFirstPageapplyWindow, $putPointerMapEntryapplyWindow): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPageapplyWindow(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_applwin', str_repeat('cache:', 42)])),
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
        $putPointerMapEntryapplyWindow($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$planapplyWindow = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $databaseapplyWindow;

    $database = $databaseapplyWindow();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPublishFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('applywin-current-source-apply', 50),
        3,
        true,
        $batchSize,
    );
};

$messageapplyWindow = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$casesapplyWindow = [
    'action label' => static fn (): mixed => $planapplyWindow()->toArray()['action'],
    'summary status' => static fn (): mixed => $planapplyWindow()->applySummary()['status'],
    'apply row count' => static fn (): mixed => $planapplyWindow()->applySummary()['apply_row_count'],
    'apply pages' => static fn (): mixed => $planapplyWindow()->applyPages(),
    'summary apply pages' => static fn (): mixed => $planapplyWindow()->applySummary()['apply_pages'],
    'next apply pages' => static fn (): mixed => $planapplyWindow()->nextApplyPages(),
    'reuse pages' => static fn (): mixed => $planapplyWindow()->applySummary()['reuse_pages'],
    'apply pages match reuse pages' => static fn (): mixed => $planapplyWindow()->applySummary()['apply_pages_match_reuse_pages'],
    'pointer map apply pages' => static fn (): mixed => $planapplyWindow()->pointerMapApplyPages(),
    'payload apply pages' => static fn (): mixed => $planapplyWindow()->payloadApplyPages(),
    'duplicate pointer map apply pages' => static fn (): mixed => $planapplyWindow()->duplicatePointerMapApplyPages(),
    'committed freeblock pages' => static fn (): mixed => $planapplyWindow()->committedFreeblockPages(),
    'apply errors' => static fn (): mixed => $planapplyWindow()->applyErrors(),
    'summary apply errors' => static fn (): mixed => $planapplyWindow()->applySummary()['apply_errors'],
    'all reuse tokens match' => static fn (): mixed => $planapplyWindow()->applySummary()['all_reuse_tokens_match'],
    'all apply links valid' => static fn (): mixed => $planapplyWindow()->applySummary()['all_apply_links_valid'],
    'all payload apply waits for pointer map' => static fn (): mixed => $planapplyWindow()->applySummary()['all_payload_apply_waits_for_pointer_map'],
    'all duplicate pointer map generations applied' => static fn (): mixed => $planapplyWindow()->applySummary()['all_duplicate_pointer_map_generations_applied'],
    'all freeblock commits visible' => static fn (): mixed => $planapplyWindow()->applySummary()['all_freeblock_commits_visible'],
    'all tail pages remain fenced at apply' => static fn (): mixed => $planapplyWindow()->applySummary()['all_tail_pages_remain_fenced_at_apply'],
    'token count' => static fn (): mixed => count($planapplyWindow()->applyTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $planapplyWindow()->applyTokens()),
    'signature length' => static fn (): mixed => strlen($planapplyWindow()->applySummary()['apply_signature']),
    'current token length' => static fn (): mixed => strlen($planapplyWindow()->applySummary()['apply_window_token']),
    'first row state' => static fn (): mixed => $planapplyWindow()->applyRows()[0]['apply_state'],
    'second row state' => static fn (): mixed => $planapplyWindow()->applyRows()[1]['apply_state'],
    'first row channel' => static fn (): mixed => $planapplyWindow()->applyRows()[0]['apply_channel'],
    'second row channel' => static fn (): mixed => $planapplyWindow()->applyRows()[1]['apply_channel'],
    'first applied generations' => static fn (): mixed => $planapplyWindow()->applyRows()[0]['applied_pointer_map_generations'],
    'second committed freeblocks' => static fn (): mixed => $planapplyWindow()->applyRows()[1]['committed_freeblock_pages'],
    'fourth committed freeblocks' => static fn (): mixed => $planapplyWindow()->applyRows()[3]['committed_freeblock_pages'],
    'fifth duplicate pointer map' => static fn (): mixed => $planapplyWindow()->applyRows()[4]['duplicate_pointer_map_apply'],
    'fifth applied generations' => static fn (): mixed => $planapplyWindow()->applyRows()[4]['applied_pointer_map_generations'],
    'last row next page' => static fn (): mixed => $planapplyWindow()->applyRows()[6]['next_apply_page'],
    'ordinals' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'apply_ordinal'),
    'reuse ordinals' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'reuse_ordinal'),
    'row states' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'apply_state'),
    'row reuse token flags' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'reuse_token_matches'),
    'row link flags' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'apply_link_valid'),
    'row payload wait flags' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'payload_apply_waits_for_pointer_map'),
    'row duplicate flags' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'duplicate_pointer_map_generation_applied'),
    'row freeblock flags' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'freeblock_commit_visible'),
    'row tail fence flags' => static fn (): mixed => array_column($planapplyWindow()->applyRows(), 'tail_page_fenced_at_apply'),
    'batch size three row count' => static fn (): mixed => $planapplyWindow(3)->applySummary()['apply_row_count'],
    'batch size three pages' => static fn (): mixed => $planapplyWindow(3)->applyPages(),
    'batch size three next pages' => static fn (): mixed => $planapplyWindow(3)->nextApplyPages(),
    'batch size three committed freeblocks' => static fn (): mixed => $planapplyWindow(3)->committedFreeblockPages(),
    'dependency closure' => static fn (): mixed => $planapplyWindow()->applySummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($planapplyWindow()->applySummary()['non_overlap'], 'does not repeat reusable-page admission'),
    'reuse action' => static fn (): mixed => $planapplyWindow()->reusePlan->toArray()['action'],
    'reuse row count' => static fn (): mixed => $planapplyWindow()->reusePlan->reuseSummary()['reuse_row_count'],
    'bad batch size rejected' => static fn (): mixed => $messageapplyWindow(static fn () => $planapplyWindow(0)),
];

$expectedapplyWindow = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-apply-window',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-apply-window-ready',
    'apply row count' => 7,
    'apply pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary apply pages' => [2, 3, 105, 106, 105, 107, 108],
    'next apply pages' => [3, 105, 106, 105, 107, 108, null],
    'reuse pages' => [2, 3, 105, 106, 105, 107, 108],
    'apply pages match reuse pages' => true,
    'pointer map apply pages' => [2, 105],
    'payload apply pages' => [3, 106, 107, 108],
    'duplicate pointer map apply pages' => [105],
    'committed freeblock pages' => [2, 3, 105, 106, 107, 108],
    'apply errors' => [],
    'summary apply errors' => [],
    'all reuse tokens match' => true,
    'all apply links valid' => true,
    'all payload apply waits for pointer map' => true,
    'all duplicate pointer map generations applied' => true,
    'all freeblock commits visible' => true,
    'all tail pages remain fenced at apply' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'pointer-map-apply-gate',
    'second row state' => 'payload-freeblock-apply-visible',
    'first row channel' => 'pointer-map',
    'second row channel' => 'payload',
    'first applied generations' => ['2:1'],
    'second committed freeblocks' => [3],
    'fourth committed freeblocks' => [3, 106],
    'fifth duplicate pointer map' => true,
    'fifth applied generations' => ['2:1', '105:2'],
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'reuse ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['pointer-map-apply-gate', 'payload-freeblock-apply-visible', 'pointer-map-apply-gate', 'payload-freeblock-apply-visible', 'pointer-map-apply-gate', 'payload-freeblock-apply-visible', 'payload-freeblock-apply-visible'],
    'row reuse token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row payload wait flags' => [true, true, true, true, true, true, true],
    'row duplicate flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three next pages' => [3, 105, 106, 107, 108, null],
    'batch size three committed freeblocks' => [2, 3, 105, 106, 107, 108],
    'dependency closure' => 'no new support component needed; apply-window reuses reusable-page rows and records apply-window ordering for pointer-map/freeblock current-source pages',
    'non overlap' => true,
    'reuse action' => 'btree-vacuum-pointermap-freeblock-current-source-next240',
    'reuse row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($casesapplyWindow as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source apply-window ' . $name] = static function (TestRunner $t) use ($callback, $expectedapplyWindow, $name): void {
        $t->same($expectedapplyWindow[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source apply-window apply invariant ' . $index] = static function (TestRunner $t) use ($planapplyWindow): void {
        $plan = $planapplyWindow();
        $summary = $plan->applySummary();

        $t->same([], $plan->applyErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->applyPages());
        $t->same([3, 105, 106, 105, 107, 108, null], $plan->nextApplyPages());
        $t->same([2, 105], $plan->pointerMapApplyPages());
        $t->same([3, 106, 107, 108], $plan->payloadApplyPages());
        $t->same([105], $plan->duplicatePointerMapApplyPages());
        $t->same([2, 3, 105, 106, 107, 108], $plan->committedFreeblockPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->applyRows(), 'apply_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'reuse_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'apply_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'payload_apply_waits_for_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'duplicate_pointer_map_generation_applied'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'freeblock_commit_visible'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->applyRows(), 'tail_page_fenced_at_apply'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->applyTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-apply-window-ready', $summary['status']);
        $t->same(true, $summary['apply_pages_match_reuse_pages']);
        $t->same(true, $summary['all_payload_apply_waits_for_pointer_map']);
    };
}

return $tests;
