<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage230 = static function (int $pageCount): string {
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

$putPointerMapEntry230 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database230 = static function () use ($makeFirstPage230, $putPointerMapEntry230): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage230(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next230', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(81 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry230($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan230 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database230;

    $database = $database230();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext230(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next230-current-final-', 50),
        3,
        true,
        $batchSize,
    );
};

$message230 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases230 = [
    'action label' => static fn (): mixed => $plan230()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan230()->finalSummary()['status'],
    'final row count' => static fn (): mixed => $plan230()->finalSummary()['final_row_count'],
    'final pages' => static fn (): mixed => $plan230()->finalPages(),
    'summary final pages' => static fn (): mixed => $plan230()->finalSummary()['final_pages'],
    'unique final pages' => static fn (): mixed => $plan230()->finalSummary()['unique_final_pages'],
    'pointer map final pages' => static fn (): mixed => $plan230()->pointerMapFinalPages(),
    'payload final pages' => static fn (): mixed => $plan230()->payloadFinalPages(),
    'duplicate rewrite final pages' => static fn (): mixed => $plan230()->duplicateRewriteFinalPages(),
    'final pages match seals' => static fn (): mixed => $plan230()->finalSummary()['final_pages_match_seal_pages'],
    'unique pages match seals' => static fn (): mixed => $plan230()->finalSummary()['unique_final_pages_match_seal_pages'],
    'pointer map finals match seals' => static fn (): mixed => $plan230()->finalSummary()['pointer_map_final_matches_seals'],
    'payload finals match seals' => static fn (): mixed => $plan230()->finalSummary()['payload_final_matches_seals'],
    'duplicate rewrites match seals' => static fn (): mixed => $plan230()->finalSummary()['duplicate_rewrites_match_seals'],
    'final errors' => static fn (): mixed => $plan230()->finalErrors(),
    'summary final errors' => static fn (): mixed => $plan230()->finalSummary()['final_errors'],
    'all seal tokens match' => static fn (): mixed => $plan230()->finalSummary()['all_seal_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan230()->finalSummary()['all_pointer_maps_finalized_before_payload'],
    'all payload rows depend on pointer maps' => static fn (): mixed => $plan230()->finalSummary()['all_payload_rows_depend_on_pointer_maps'],
    'all tail pages excluded' => static fn (): mixed => $plan230()->finalSummary()['all_tail_pages_excluded_from_final'],
    'all freeblock receipts finalized' => static fn (): mixed => $plan230()->finalSummary()['all_freeblock_receipts_finalized'],
    'all leaf freeblock receipts finalized' => static fn (): mixed => $plan230()->finalSummary()['all_leaf_freeblock_receipts_finalized'],
    'all final offsets contiguous' => static fn (): mixed => $plan230()->finalSummary()['all_final_offsets_contiguous'],
    'final token count' => static fn (): mixed => count($plan230()->finalTokens()),
    'final token lengths' => static fn (): mixed => array_map('strlen', $plan230()->finalTokens()),
    'final signature length' => static fn (): mixed => strlen($plan230()->finalSummary()['final_signature']),
    'current source token length' => static fn (): mixed => strlen($plan230()->finalSummary()['current_source_next230_token']),
    'final ordinals' => static fn (): mixed => array_column($plan230()->finalRows(), 'final_ordinal'),
    'source seal ordinals' => static fn (): mixed => array_column($plan230()->finalRows(), 'source_seal_ordinal'),
    'final channels' => static fn (): mixed => array_column($plan230()->finalRows(), 'final_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan230()->finalRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan230()->finalRows(), 'byte_length'),
    'duplicate rewrite flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'duplicate_rewrite_finalized'),
    'tail exclusion flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'tail_page_excluded_from_final'),
    'freeblock receipt flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'freeblock_receipt_finalized'),
    'leaf freeblock receipt flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'leaf_freeblock_receipt_finalized'),
    'overflow payload flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'overflow_payload_finalized'),
    'seal token flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'seal_token_matches'),
    'final chain flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'final_chain_valid'),
    'payload dependency flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'payload_depends_on_pointer_maps'),
    'final offset flags' => static fn (): mixed => array_column($plan230()->finalRows(), 'final_offset_contiguous'),
    'final states' => static fn (): mixed => array_column($plan230()->finalRows(), 'final_state'),
    'first final visible pages' => static fn (): mixed => $plan230()->finalRows()[0]['finalized_visible_pages'],
    'third final visible pages' => static fn (): mixed => $plan230()->finalRows()[2]['finalized_visible_pages'],
    'last final visible pages' => static fn (): mixed => $plan230()->finalRows()[6]['finalized_visible_pages'],
    'first pointer map visible pages' => static fn (): mixed => $plan230()->finalRows()[0]['finalized_pointer_map_pages'],
    'first payload pointer map pages' => static fn (): mixed => $plan230()->finalRows()[3]['finalized_pointer_map_pages'],
    'first previous final token' => static fn (): mixed => $plan230()->finalRows()[0]['previous_final_token'],
    'second previous final token length' => static fn (): mixed => strlen((string) $plan230()->finalRows()[1]['previous_final_token']),
    'batch size three final row count' => static fn (): mixed => $plan230(3)->finalSummary()['final_row_count'],
    'batch size three final pages' => static fn (): mixed => $plan230(3)->finalPages(),
    'batch size three source seal ordinals' => static fn (): mixed => array_column($plan230(3)->finalRows(), 'source_seal_ordinal'),
    'dependency closure' => static fn (): mixed => $plan230()->finalSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan230()->finalSummary()['non_overlap'], 'does not repeat next227'),
    'base action' => static fn (): mixed => $plan230()->basePlan->toArray()['action'],
    'base seal row count' => static fn (): mixed => $plan230()->basePlan->sealSummary()['seal_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message230(static fn () => $plan230(0)),
];

$expected230 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next230',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next230-ready',
    'final row count' => 7,
    'final pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary final pages' => [2, 105, 105, 3, 106, 107, 108],
    'unique final pages' => [2, 3, 105, 106, 107, 108],
    'pointer map final pages' => [2, 105, 105],
    'payload final pages' => [3, 106, 107, 108],
    'duplicate rewrite final pages' => [105],
    'final pages match seals' => true,
    'unique pages match seals' => true,
    'pointer map finals match seals' => true,
    'payload finals match seals' => true,
    'duplicate rewrites match seals' => true,
    'final errors' => [],
    'summary final errors' => [],
    'all seal tokens match' => true,
    'all pointer maps before payload' => true,
    'all payload rows depend on pointer maps' => true,
    'all tail pages excluded' => true,
    'all freeblock receipts finalized' => true,
    'all leaf freeblock receipts finalized' => true,
    'all final offsets contiguous' => true,
    'final token count' => 7,
    'final token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'final signature length' => 64,
    'current source token length' => 64,
    'final ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source seal ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'final channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'duplicate rewrite flags' => [false, false, true, false, false, false, false],
    'tail exclusion flags' => [true, true, true, true, true, true, true],
    'freeblock receipt flags' => [true, true, true, true, true, true, true],
    'leaf freeblock receipt flags' => [false, false, false, true, false, false, false],
    'overflow payload flags' => [false, false, false, false, true, true, true],
    'seal token flags' => [true, true, true, true, true, true, true],
    'final chain flags' => [true, true, true, true, true, true, true],
    'payload dependency flags' => [true, true, true, true, true, true, true],
    'final offset flags' => [true, true, true, true, true, true, true],
    'final states' => ['current-source-page-finalized', 'current-source-page-finalized', 'current-source-page-finalized', 'current-source-page-finalized', 'current-source-page-finalized', 'current-source-page-finalized', 'current-source-page-finalized'],
    'first final visible pages' => [2],
    'third final visible pages' => [2, 105],
    'last final visible pages' => [2, 3, 105, 106, 107, 108],
    'first pointer map visible pages' => [2],
    'first payload pointer map pages' => [2, 105],
    'first previous final token' => null,
    'second previous final token length' => 64,
    'batch size three final row count' => 6,
    'batch size three final pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source seal ordinals' => [1, 2, 3, 4, 5, 6],
    'dependency closure' => 'no new support component needed; next230 reuses next227 publication seals, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next227',
    'base seal row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases230 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next230 ' . $name] = static function (TestRunner $t) use ($callback, $expected230, $name): void {
        $t->same($expected230[$name], $callback());
    };
}

foreach (range(1, 90) as $index) {
    $tests['btree vacuum pointermap freeblock current source next230 final invariant ' . $index] = static function (TestRunner $t) use ($plan230): void {
        $plan = $plan230();
        $summary = $plan->finalSummary();

        $t->same([], $plan->finalErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->finalPages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['unique_final_pages']);
        $t->same([2, 105, 105], $plan->pointerMapFinalPages());
        $t->same([3, 106, 107, 108], $plan->payloadFinalPages());
        $t->same([105], $plan->duplicateRewriteFinalPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->finalRows(), 'seal_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->finalRows(), 'payload_depends_on_pointer_maps'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->finalRows(), 'tail_page_excluded_from_final'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->finalRows(), 'freeblock_receipt_finalized'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->finalTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next230-ready', $summary['status']);
        $t->same(true, $summary['final_pages_match_seal_pages']);
        $t->same(true, $summary['all_pointer_maps_finalized_before_payload']);
        $t->same(true, $summary['all_payload_rows_depend_on_pointer_maps']);
        $t->same(true, $summary['all_leaf_freeblock_receipts_finalized']);
    };
}

return $tests;
