<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPagecurrentSourceCommitReceipt = static function (int $pageCount): string {
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

$putPointerMapEntrycurrentSourceCommitReceipt = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$databasecurrentSourceCommitReceipt = static function () use ($makeFirstPagecurrentSourceCommitReceipt, $putPointerMapEntrycurrentSourceCommitReceipt): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPagecurrentSourceCommitReceipt(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_current-source-commit-receipt', str_repeat('cache:', 42)])),
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
        $putPointerMapEntrycurrentSourceCommitReceipt($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plancurrentSourceCommitReceipt = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $databasecurrentSourceCommitReceipt;

    $database = $databasecurrentSourceCommitReceipt();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCommitReceiptAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('receipt-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$messagecurrentSourceCommitReceipt = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$casescurrentSourceCommitReceipt = [
    'action label' => static fn (): mixed => $plancurrentSourceCommitReceipt()->toArray()['action'],
    'summary status' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['status'],
    'receipt row count' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['receipt_row_count'],
    'receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptPages(),
    'summary receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['receipt_pages'],
    'pointer map receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->pointerMapReceiptPages(),
    'payload receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->payloadReceiptPages(),
    'apply pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['apply_pages'],
    'receipts match apply pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['receipts_match_apply_pages'],
    'receipt errors' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptErrors(),
    'summary receipt errors' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['receipt_errors'],
    'all apply tokens match' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['all_apply_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['all_pointer_maps_committed_before_payload'],
    'all freeblock receipts committed' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['all_freeblock_receipts_committed'],
    'all tail pages excluded' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['all_tail_pages_excluded_from_receipts'],
    'all receipt chains valid' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['all_receipt_chains_valid'],
    'receipt token count' => static fn (): mixed => count($plancurrentSourceCommitReceipt()->receiptTokens()),
    'receipt token lengths' => static fn (): mixed => array_map('strlen', $plancurrentSourceCommitReceipt()->receiptTokens()),
    'receipt signature length' => static fn (): mixed => strlen($plancurrentSourceCommitReceipt()->receiptSummary()['receipt_signature']),
    'next writer token length' => static fn (): mixed => strlen($plancurrentSourceCommitReceipt()->receiptSummary()['next_writer_commit_token']),
    'first receipt channel' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[0]['receipt_channel'],
    'first receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[0]['receipt_pages'],
    'first committed pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[0]['committed_visible_pages'],
    'first previous token' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[0]['previous_receipt_token'],
    'second receipt channel' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[1]['receipt_channel'],
    'second receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[1]['receipt_pages'],
    'second committed pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[1]['committed_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plancurrentSourceCommitReceipt()->receiptRows()[1]['previous_receipt_token']),
    'third receipt channel' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[2]['receipt_channel'],
    'third receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[2]['receipt_pages'],
    'third committed pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[2]['committed_visible_pages'],
    'fourth receipt channel' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[3]['receipt_channel'],
    'fourth receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[3]['receipt_pages'],
    'fourth committed pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[3]['committed_visible_pages'],
    'fifth receipt channel' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[4]['receipt_channel'],
    'fifth receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[4]['receipt_pages'],
    'fifth committed pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[4]['committed_visible_pages'],
    'sixth receipt channel' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[5]['receipt_channel'],
    'sixth receipt pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[5]['receipt_pages'],
    'sixth committed pages' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptRows()[5]['committed_visible_pages'],
    'receipt ordinals' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'receipt_ordinal'),
    'row states' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'receipt_state'),
    'row apply token flags' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'apply_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'freeblock_receipt_committed'),
    'row tail fence flags' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'tail_pages_excluded_from_receipt'),
    'row chain flags' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'receipt_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt()->receiptRows(), 'high_water_page'),
    'page hash lengths first row' => static fn (): mixed => array_values(array_map('strlen', $plancurrentSourceCommitReceipt()->receiptRows()[0]['page_hashes'])),
    'page hash keys sixth row' => static fn (): mixed => array_keys($plancurrentSourceCommitReceipt()->receiptRows()[5]['page_hashes']),
    'batch size three row count' => static fn (): mixed => $plancurrentSourceCommitReceipt(3)->receiptSummary()['receipt_row_count'],
    'batch size three pages' => static fn (): mixed => $plancurrentSourceCommitReceipt(3)->receiptPages(),
    'batch size three receipt batches' => static fn (): mixed => array_column($plancurrentSourceCommitReceipt(3)->receiptRows(), 'receipt_pages'),
    'batch size three token count' => static fn (): mixed => count($plancurrentSourceCommitReceipt(3)->receiptTokens()),
    'batch size three payload pages' => static fn (): mixed => $plancurrentSourceCommitReceipt(3)->payloadReceiptPages(),
    'dependency closure' => static fn (): mixed => $plancurrentSourceCommitReceipt()->receiptSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plancurrentSourceCommitReceipt()->receiptSummary()['non_overlap'], 'does not repeat page-apply'),
    'base action' => static fn (): mixed => $plancurrentSourceCommitReceipt()->basePlan->toArray()['action'],
    'base apply rows' => static fn (): mixed => $plancurrentSourceCommitReceipt()->basePlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $messagecurrentSourceCommitReceipt(static fn () => $plancurrentSourceCommitReceipt(0)),
];

$expectedcurrentSourceCommitReceipt = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-commit-receipt',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-commit-receipt-ready',
    'receipt row count' => 6,
    'receipt pages' => [2, 3, 105, 106, 107, 108],
    'summary receipt pages' => [2, 3, 105, 106, 107, 108],
    'pointer map receipt pages' => [2, 105],
    'payload receipt pages' => [3, 106, 107, 108],
    'apply pages' => [2, 3, 105, 106, 107, 108],
    'receipts match apply pages' => true,
    'receipt errors' => [],
    'summary receipt errors' => [],
    'all apply tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts committed' => true,
    'all tail pages excluded' => true,
    'all receipt chains valid' => true,
    'receipt token count' => 6,
    'receipt token lengths' => [64, 64, 64, 64, 64, 64],
    'receipt signature length' => 64,
    'next writer token length' => 64,
    'first receipt channel' => 'pointer-map-receipt',
    'first receipt pages' => [2],
    'first committed pages' => [2],
    'first previous token' => null,
    'second receipt channel' => 'payload-receipt',
    'second receipt pages' => [3],
    'second committed pages' => [2, 3],
    'second previous token length' => 64,
    'third receipt channel' => 'pointer-map-receipt',
    'third receipt pages' => [105],
    'third committed pages' => [2, 3, 105],
    'fourth receipt channel' => 'payload-receipt',
    'fourth receipt pages' => [106],
    'fourth committed pages' => [2, 3, 105, 106],
    'fifth receipt channel' => 'pointer-map-receipt',
    'fifth receipt pages' => [105],
    'fifth committed pages' => [2, 3, 105, 106],
    'sixth receipt channel' => 'payload-receipt',
    'sixth receipt pages' => [107, 108],
    'sixth committed pages' => [2, 3, 105, 106, 107, 108],
    'receipt ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['current-source-page-commit-receipted', 'current-source-page-commit-receipted', 'current-source-page-commit-receipted', 'current-source-page-commit-receipted', 'current-source-page-commit-receipted', 'current-source-page-commit-receipted'],
    'row apply token flags' => [true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108],
    'page hash lengths first row' => [64],
    'page hash keys sixth row' => [107, 108],
    'batch size three row count' => 4,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three receipt batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'batch size three payload pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; current-source-commit-receipt reuses page-apply current-source apply rows, page hashes, freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-page-apply',
    'base apply rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($casescurrentSourceCommitReceipt as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source current-source-commit-receipt ' . $name] = static function (TestRunner $t) use ($callback, $expectedcurrentSourceCommitReceipt, $name): void {
        $t->same($expectedcurrentSourceCommitReceipt[$name], $callback());
    };
}

foreach (range(1, 88) as $index) {
    $tests['btree vacuum pointermap freeblock current source current-source-commit-receipt receipt invariant ' . $index] = static function (TestRunner $t) use ($plancurrentSourceCommitReceipt): void {
        $plan = $plancurrentSourceCommitReceipt();
        $summary = $plan->receiptSummary();

        $t->same([], $plan->receiptErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->receiptPages());
        $t->same([2, 105], $plan->pointerMapReceiptPages());
        $t->same([3, 106, 107, 108], $plan->payloadReceiptPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->receiptRows(), 'receipt_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->receiptRows(), 'apply_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->receiptRows(), 'freeblock_receipt_committed'));
        $t->same([true, true, true, true, true, true], array_column($plan->receiptRows(), 'tail_pages_excluded_from_receipt'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->receiptTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-commit-receipt-ready', $summary['status']);
        $t->same(true, $summary['receipts_match_apply_pages']);
    };
}

return $tests;
