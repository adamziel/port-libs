<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPageAllocationPublication = static function (int $pageCount): string {
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

$putPointerMapEntryAllocationPublication = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$databaseAllocationPublication = static function () use ($makeFirstPageAllocationPublication, $putPointerMapEntryAllocationPublication): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPageAllocationPublication(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_allocation_publication', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(94 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntryAllocationPublication($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$planAllocationPublication = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $databaseAllocationPublication;

    $database = $databaseAllocationPublication();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafAllocationPublicationFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('allocation-publication-freeblock-', 40),
        3,
        true,
        $batchSize,
    );
};

$messageAllocationPublication = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$casesAllocationPublication = [
    'action label' => static fn (): mixed => $planAllocationPublication()->toArray()['action'],
    'summary status' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['status'],
    'row count' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['next_source_row_count'],
    'next source pages' => static fn (): mixed => $planAllocationPublication()->nextSourcePages(),
    'summary next source pages' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['next_source_pages'],
    'summary cursor pages' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['cursor_pages'],
    'pages match cursor' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['next_source_pages_match_cursor'],
    'pointer map epoch pages' => static fn (): mixed => $planAllocationPublication()->pointerMapEpochPages(),
    'summary pointer map epoch pages' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['pointer_map_epoch_pages'],
    'reusable allocation pages' => static fn (): mixed => $planAllocationPublication()->reusableAllocationPages(),
    'summary reusable allocation pages' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['reusable_allocation_pages'],
    'allocation positions' => static fn (): mixed => $planAllocationPublication()->nextAllocationPositions(),
    'summary allocation positions' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['next_allocation_positions'],
    'errors' => static fn (): mixed => $planAllocationPublication()->nextSourceErrors(),
    'summary errors' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['next_source_errors'],
    'all cursor tokens match' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['all_cursor_tokens_match'],
    'all pointer epochs ready' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['all_pointer_map_epochs_ready'],
    'all reusable pages after epoch' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['all_reusable_pages_after_epoch'],
    'all receipts carried' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['all_leaf_receipts_carried_forward'],
    'all trunk carried' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['all_trunk_candidates_carried_forward'],
    'all links valid' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['all_next_source_links_valid'],
    'token count' => static fn (): mixed => count($planAllocationPublication()->nextSourceTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $planAllocationPublication()->nextSourceTokens()),
    'signature length' => static fn (): mixed => strlen($planAllocationPublication()->nextSourceSummary()['next_source_signature']),
    'next token length' => static fn (): mixed => strlen($planAllocationPublication()->nextSourceSummary()['allocation_publication_token']),
    'first row channel' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[0]['next_source_channel'],
    'first row epoch' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[0]['pointer_map_epoch'],
    'second row channel' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[1]['next_source_channel'],
    'second row allocation' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[1]['next_allocation_position'],
    'second row trunk' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[1]['trunk_candidate_page'],
    'third row channel' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[2]['next_source_channel'],
    'third row allocation' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[2]['next_allocation_position'],
    'fifth row channel' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[4]['next_source_channel'],
    'fifth row epoch' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[4]['pointer_map_epoch'],
    'last row page' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[6]['next_source_page'],
    'last row allocation' => static fn (): mixed => $planAllocationPublication()->nextSourceRows()[6]['next_allocation_position'],
    'ordinals' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'next_source_ordinal'),
    'cursor ordinals' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'cursor_ordinal'),
    'row states' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'next_source_state'),
    'token flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'cursor_token_matches'),
    'epoch flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'pointer_map_epoch_ready_for_next_source'),
    'reuse flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'reusable_page_after_pointer_map_epoch'),
    'receipt flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'leaf_receipt_carried_forward'),
    'trunk flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'trunk_candidate_carried_forward'),
    'tail flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'tail_page_still_fenced'),
    'link flags' => static fn (): mixed => array_column($planAllocationPublication()->nextSourceRows(), 'next_source_link_valid'),
    'batch size three row count' => static fn (): mixed => $planAllocationPublication(3)->nextSourceSummary()['next_source_row_count'],
    'batch size three pages' => static fn (): mixed => $planAllocationPublication(3)->nextSourcePages(),
    'batch size three allocations' => static fn (): mixed => $planAllocationPublication(3)->nextAllocationPositions(),
    'batch size three reusable pages' => static fn (): mixed => $planAllocationPublication(3)->reusableAllocationPages(),
    'dependency closure' => static fn (): mixed => $planAllocationPublication()->nextSourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($planAllocationPublication()->nextSourceSummary()['non_overlap'], 'does not repeat next245'),
    'cursor action' => static fn (): mixed => $planAllocationPublication()->cursorPlan->toArray()['action'],
    'cursor row count' => static fn (): mixed => $planAllocationPublication()->cursorPlan->cursorSummary()['cursor_row_count'],
    'bad batch size rejected' => static fn (): mixed => $messageAllocationPublication(static fn () => $planAllocationPublication(0)),
];

$expectedAllocationPublication = [
    'action label' => 'btree-vacuum-pointermap-freeblock-allocation-publication',
    'summary status' => 'btree-vacuum-pointermap-freeblock-allocation-publication-ready',
    'row count' => 7,
    'next source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary next source pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary cursor pages' => [2, 3, 105, 106, 105, 107, 108],
    'pages match cursor' => true,
    'pointer map epoch pages' => [2, 105],
    'summary pointer map epoch pages' => [2, 105],
    'reusable allocation pages' => [3, 106, 107, 108],
    'summary reusable allocation pages' => [3, 106, 107, 108],
    'allocation positions' => [0, 1, 1, 2, 2, 3, 4],
    'summary allocation positions' => [0, 1, 1, 2, 2, 3, 4],
    'errors' => [],
    'summary errors' => [],
    'all cursor tokens match' => true,
    'all pointer epochs ready' => true,
    'all reusable pages after epoch' => true,
    'all receipts carried' => true,
    'all trunk carried' => true,
    'all links valid' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'next token length' => 64,
    'first row channel' => 'pointer-map-epoch',
    'first row epoch' => 1,
    'second row channel' => 'reusable-allocation',
    'second row allocation' => 1,
    'second row trunk' => 3,
    'third row channel' => 'pointer-map-epoch',
    'third row allocation' => 1,
    'fifth row channel' => 'pointer-map-epoch',
    'fifth row epoch' => 3,
    'last row page' => 108,
    'last row allocation' => 4,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'cursor ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-allocation-publication-published', 'current-source-allocation-publication-published', 'current-source-allocation-publication-published', 'current-source-allocation-publication-published', 'current-source-allocation-publication-published', 'current-source-allocation-publication-published', 'current-source-allocation-publication-published'],
    'token flags' => [true, true, true, true, true, true, true],
    'epoch flags' => [true, true, true, true, true, true, true],
    'reuse flags' => [true, true, true, true, true, true, true],
    'receipt flags' => [true, true, true, true, true, true, true],
    'trunk flags' => [true, true, true, true, true, true, true],
    'tail flags' => [true, true, true, true, true, true, true],
    'link flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three allocations' => [0, 1, 1, 2, 3, 4],
    'batch size three reusable pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; allocation publication reuses cursor-admission admitted cursor rows and records next-source allocation ordering only',
    'non overlap' => true,
    'cursor action' => 'btree-vacuum-pointermap-freeblock-current-source-next245',
    'cursor row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($casesAllocationPublication as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source allocation-publication ' . $name] = static function (TestRunner $t) use ($callback, $expectedAllocationPublication, $name): void {
        $t->same($expectedAllocationPublication[$name], $callback());
    };
}

foreach (range(1, 96) as $index) {
    $tests['btree vacuum pointermap freeblock current source allocation publication invariant ' . $index] = static function (TestRunner $t) use ($planAllocationPublication): void {
        $plan = $planAllocationPublication();
        $summary = $plan->nextSourceSummary();

        $t->same([], $plan->nextSourceErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->nextSourcePages());
        $t->same([2, 105], $plan->pointerMapEpochPages());
        $t->same([3, 106, 107, 108], $plan->reusableAllocationPages());
        $t->same([0, 1, 1, 2, 2, 3, 4], $plan->nextAllocationPositions());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->nextSourceRows(), 'next_source_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->nextSourceRows(), 'cursor_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->nextSourceRows(), 'pointer_map_epoch_ready_for_next_source'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->nextSourceRows(), 'reusable_page_after_pointer_map_epoch'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->nextSourceRows(), 'leaf_receipt_carried_forward'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->nextSourceRows(), 'trunk_candidate_carried_forward'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->nextSourceRows(), 'tail_page_still_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->nextSourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-allocation-publication-ready', $summary['status']);
        $t->same(true, $summary['next_source_pages_match_cursor']);
        $t->same(true, $summary['all_reusable_pages_after_epoch']);
    };
}

return $tests;
