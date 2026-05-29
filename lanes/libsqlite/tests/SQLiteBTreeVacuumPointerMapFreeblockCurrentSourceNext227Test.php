<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage227 = static function (int $pageCount): string {
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

$putPointerMapEntry227 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database227 = static function () use ($makeFirstPage227, $putPointerMapEntry227): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage227(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next227', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry227($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan227 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database227;

    $database = $database227();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafSealAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next227-current-seal-', 50),
        3,
        true,
        $batchSize,
    );
};

$message227 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases227 = [
    'action label' => static fn (): mixed => $plan227()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan227()->sealSummary()['status'],
    'seal row count' => static fn (): mixed => $plan227()->sealSummary()['seal_row_count'],
    'seal pages' => static fn (): mixed => $plan227()->sealPages(),
    'summary seal pages' => static fn (): mixed => $plan227()->sealSummary()['seal_pages'],
    'unique seal pages' => static fn (): mixed => $plan227()->sealSummary()['unique_seal_pages'],
    'pointer map seal pages' => static fn (): mixed => $plan227()->pointerMapSealPages(),
    'payload seal pages' => static fn (): mixed => $plan227()->payloadSealPages(),
    'duplicate rewrite seal pages' => static fn (): mixed => $plan227()->duplicateRewriteSealPages(),
    'seal pages match reads' => static fn (): mixed => $plan227()->sealSummary()['seal_pages_match_read_pages'],
    'unique pages match reads' => static fn (): mixed => $plan227()->sealSummary()['unique_seal_pages_match_read_pages'],
    'pointer map seals match reads' => static fn (): mixed => $plan227()->sealSummary()['pointer_map_seals_match_reads'],
    'payload seals match reads' => static fn (): mixed => $plan227()->sealSummary()['payload_seals_match_reads'],
    'duplicate rewrites match reads' => static fn (): mixed => $plan227()->sealSummary()['duplicate_rewrites_match_reads'],
    'seal errors' => static fn (): mixed => $plan227()->sealErrors(),
    'summary seal errors' => static fn (): mixed => $plan227()->sealSummary()['seal_errors'],
    'all read tokens match' => static fn (): mixed => $plan227()->sealSummary()['all_read_tokens_match'],
    'all current source tokens match' => static fn (): mixed => $plan227()->sealSummary()['all_current_source_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan227()->sealSummary()['all_pointer_maps_sealed_before_payload'],
    'all tail pages excluded' => static fn (): mixed => $plan227()->sealSummary()['all_tail_pages_excluded_from_seal'],
    'all freeblock receipts sealed' => static fn (): mixed => $plan227()->sealSummary()['all_freeblock_receipts_sealed'],
    'all leaf freeblock receipts sealed' => static fn (): mixed => $plan227()->sealSummary()['all_leaf_freeblock_receipts_sealed'],
    'all seal offsets contiguous' => static fn (): mixed => $plan227()->sealSummary()['all_seal_offsets_contiguous'],
    'seal token count' => static fn (): mixed => count($plan227()->sealTokens()),
    'seal token lengths' => static fn (): mixed => array_map('strlen', $plan227()->sealTokens()),
    'seal signature length' => static fn (): mixed => strlen($plan227()->sealSummary()['seal_signature']),
    'current source token length' => static fn (): mixed => strlen($plan227()->sealSummary()['current_source_next227_token']),
    'seal ordinals' => static fn (): mixed => array_column($plan227()->sealRows(), 'seal_ordinal'),
    'source read ordinals' => static fn (): mixed => array_column($plan227()->sealRows(), 'source_read_ordinal'),
    'seal channels' => static fn (): mixed => array_column($plan227()->sealRows(), 'seal_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan227()->sealRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan227()->sealRows(), 'byte_length'),
    'duplicate rewrite flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'duplicate_rewrite_sealed'),
    'tail exclusion flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'tail_page_excluded_from_seal'),
    'freeblock receipt flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'freeblock_receipt_sealed'),
    'leaf freeblock receipt flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'leaf_freeblock_receipt_sealed'),
    'overflow payload flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'overflow_payload_sealed'),
    'read token flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'read_token_matches'),
    'current source token flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'current_source_token_matches'),
    'seal chain flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'seal_chain_valid'),
    'seal offset flags' => static fn (): mixed => array_column($plan227()->sealRows(), 'seal_offset_contiguous'),
    'seal states' => static fn (): mixed => array_column($plan227()->sealRows(), 'seal_state'),
    'first seal visible pages' => static fn (): mixed => $plan227()->sealRows()[0]['sealed_visible_pages'],
    'third seal visible pages' => static fn (): mixed => $plan227()->sealRows()[2]['sealed_visible_pages'],
    'last seal visible pages' => static fn (): mixed => $plan227()->sealRows()[6]['sealed_visible_pages'],
    'first previous seal token' => static fn (): mixed => $plan227()->sealRows()[0]['previous_seal_token'],
    'second previous seal token length' => static fn (): mixed => strlen((string) $plan227()->sealRows()[1]['previous_seal_token']),
    'batch size three seal row count' => static fn (): mixed => $plan227(3)->sealSummary()['seal_row_count'],
    'batch size three seal pages' => static fn (): mixed => $plan227(3)->sealPages(),
    'batch size three source read ordinals' => static fn (): mixed => array_column($plan227(3)->sealRows(), 'source_read_ordinal'),
    'dependency closure' => static fn (): mixed => $plan227()->sealSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan227()->sealSummary()['non_overlap'], 'does not repeat next219'),
    'base action' => static fn (): mixed => $plan227()->basePlan->toArray()['action'],
    'base read row count' => static fn (): mixed => $plan227()->basePlan->readSummary()['read_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message227(static fn () => $plan227(0)),
];

$expected227 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next227',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next227-ready',
    'seal row count' => 7,
    'seal pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary seal pages' => [2, 105, 105, 3, 106, 107, 108],
    'unique seal pages' => [2, 3, 105, 106, 107, 108],
    'pointer map seal pages' => [2, 105, 105],
    'payload seal pages' => [3, 106, 107, 108],
    'duplicate rewrite seal pages' => [105],
    'seal pages match reads' => true,
    'unique pages match reads' => true,
    'pointer map seals match reads' => true,
    'payload seals match reads' => true,
    'duplicate rewrites match reads' => true,
    'seal errors' => [],
    'summary seal errors' => [],
    'all read tokens match' => true,
    'all current source tokens match' => true,
    'all pointer maps before payload' => true,
    'all tail pages excluded' => true,
    'all freeblock receipts sealed' => true,
    'all leaf freeblock receipts sealed' => true,
    'all seal offsets contiguous' => true,
    'seal token count' => 7,
    'seal token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'seal signature length' => 64,
    'current source token length' => 64,
    'seal ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source read ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'seal channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'duplicate rewrite flags' => [false, false, true, false, false, false, false],
    'tail exclusion flags' => [true, true, true, true, true, true, true],
    'freeblock receipt flags' => [true, true, true, true, true, true, true],
    'leaf freeblock receipt flags' => [false, false, false, true, false, false, false],
    'overflow payload flags' => [false, false, false, false, true, true, true],
    'read token flags' => [true, true, true, true, true, true, true],
    'current source token flags' => [true, true, true, true, true, true, true],
    'seal chain flags' => [true, true, true, true, true, true, true],
    'seal offset flags' => [true, true, true, true, true, true, true],
    'seal states' => ['current-source-page-publication-sealed', 'current-source-page-publication-sealed', 'current-source-page-publication-sealed', 'current-source-page-publication-sealed', 'current-source-page-publication-sealed', 'current-source-page-publication-sealed', 'current-source-page-publication-sealed'],
    'first seal visible pages' => [2],
    'third seal visible pages' => [2, 105],
    'last seal visible pages' => [2, 3, 105, 106, 107, 108],
    'first previous seal token' => null,
    'second previous seal token length' => 64,
    'batch size three seal row count' => 6,
    'batch size three seal pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source read ordinals' => [1, 2, 3, 4, 5, 6],
    'dependency closure' => 'no new support component needed; next227 reuses next219 readback rows, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next219',
    'base read row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases227 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next227 ' . $name] = static function (TestRunner $t) use ($callback, $expected227, $name): void {
        $t->same($expected227[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next227 seal invariant ' . $index] = static function (TestRunner $t) use ($plan227): void {
        $plan = $plan227();
        $summary = $plan->sealSummary();

        $t->same([], $plan->sealErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->sealPages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['unique_seal_pages']);
        $t->same([2, 105, 105], $plan->pointerMapSealPages());
        $t->same([3, 106, 107, 108], $plan->payloadSealPages());
        $t->same([105], $plan->duplicateRewriteSealPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'read_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'tail_page_excluded_from_seal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->sealRows(), 'freeblock_receipt_sealed'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sealTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next227-ready', $summary['status']);
        $t->same(true, $summary['seal_pages_match_read_pages']);
        $t->same(true, $summary['all_pointer_maps_sealed_before_payload']);
        $t->same(true, $summary['all_leaf_freeblock_receipts_sealed']);
    };
}

return $tests;
