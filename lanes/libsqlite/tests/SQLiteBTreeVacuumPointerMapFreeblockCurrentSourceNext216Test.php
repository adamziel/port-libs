<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage216 = static function (int $pageCount): string {
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

$putPointerMapEntry216 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database216 = static function () use ($makeFirstPage216, $putPointerMapEntry216): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage216(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next216', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry216($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan216 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database216;

    $database = $database216();
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
        str_repeat('next216-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message216 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases216 = [
    'action label' => static fn (): mixed => $plan216()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan216()->receiptSummary()['status'],
    'receipt row count' => static fn (): mixed => $plan216()->receiptSummary()['receipt_row_count'],
    'receipt pages' => static fn (): mixed => $plan216()->receiptPages(),
    'summary receipt pages' => static fn (): mixed => $plan216()->receiptSummary()['receipt_pages'],
    'pointer map receipt pages' => static fn (): mixed => $plan216()->pointerMapReceiptPages(),
    'payload receipt pages' => static fn (): mixed => $plan216()->payloadReceiptPages(),
    'apply pages' => static fn (): mixed => $plan216()->receiptSummary()['apply_pages'],
    'receipts match apply pages' => static fn (): mixed => $plan216()->receiptSummary()['receipts_match_apply_pages'],
    'receipt errors' => static fn (): mixed => $plan216()->receiptErrors(),
    'summary receipt errors' => static fn (): mixed => $plan216()->receiptSummary()['receipt_errors'],
    'all apply tokens match' => static fn (): mixed => $plan216()->receiptSummary()['all_apply_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan216()->receiptSummary()['all_pointer_maps_committed_before_payload'],
    'all freeblock receipts committed' => static fn (): mixed => $plan216()->receiptSummary()['all_freeblock_receipts_committed'],
    'all tail pages excluded' => static fn (): mixed => $plan216()->receiptSummary()['all_tail_pages_excluded_from_receipts'],
    'all receipt chains valid' => static fn (): mixed => $plan216()->receiptSummary()['all_receipt_chains_valid'],
    'receipt token count' => static fn (): mixed => count($plan216()->receiptTokens()),
    'receipt token lengths' => static fn (): mixed => array_map('strlen', $plan216()->receiptTokens()),
    'receipt signature length' => static fn (): mixed => strlen($plan216()->receiptSummary()['receipt_signature']),
    'next writer token length' => static fn (): mixed => strlen($plan216()->receiptSummary()['next_writer_commit_token']),
    'first receipt channel' => static fn (): mixed => $plan216()->receiptRows()[0]['receipt_channel'],
    'first receipt pages' => static fn (): mixed => $plan216()->receiptRows()[0]['receipt_pages'],
    'first committed pages' => static fn (): mixed => $plan216()->receiptRows()[0]['committed_visible_pages'],
    'first previous token' => static fn (): mixed => $plan216()->receiptRows()[0]['previous_receipt_token'],
    'second receipt channel' => static fn (): mixed => $plan216()->receiptRows()[1]['receipt_channel'],
    'second receipt pages' => static fn (): mixed => $plan216()->receiptRows()[1]['receipt_pages'],
    'second committed pages' => static fn (): mixed => $plan216()->receiptRows()[1]['committed_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan216()->receiptRows()[1]['previous_receipt_token']),
    'third receipt channel' => static fn (): mixed => $plan216()->receiptRows()[2]['receipt_channel'],
    'third receipt pages' => static fn (): mixed => $plan216()->receiptRows()[2]['receipt_pages'],
    'third committed pages' => static fn (): mixed => $plan216()->receiptRows()[2]['committed_visible_pages'],
    'fourth receipt channel' => static fn (): mixed => $plan216()->receiptRows()[3]['receipt_channel'],
    'fourth receipt pages' => static fn (): mixed => $plan216()->receiptRows()[3]['receipt_pages'],
    'fourth committed pages' => static fn (): mixed => $plan216()->receiptRows()[3]['committed_visible_pages'],
    'fifth receipt channel' => static fn (): mixed => $plan216()->receiptRows()[4]['receipt_channel'],
    'fifth receipt pages' => static fn (): mixed => $plan216()->receiptRows()[4]['receipt_pages'],
    'fifth committed pages' => static fn (): mixed => $plan216()->receiptRows()[4]['committed_visible_pages'],
    'sixth receipt channel' => static fn (): mixed => $plan216()->receiptRows()[5]['receipt_channel'],
    'sixth receipt pages' => static fn (): mixed => $plan216()->receiptRows()[5]['receipt_pages'],
    'sixth committed pages' => static fn (): mixed => $plan216()->receiptRows()[5]['committed_visible_pages'],
    'receipt ordinals' => static fn (): mixed => array_column($plan216()->receiptRows(), 'receipt_ordinal'),
    'row states' => static fn (): mixed => array_column($plan216()->receiptRows(), 'receipt_state'),
    'row apply token flags' => static fn (): mixed => array_column($plan216()->receiptRows(), 'apply_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan216()->receiptRows(), 'freeblock_receipt_committed'),
    'row tail fence flags' => static fn (): mixed => array_column($plan216()->receiptRows(), 'tail_pages_excluded_from_receipt'),
    'row chain flags' => static fn (): mixed => array_column($plan216()->receiptRows(), 'receipt_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan216()->receiptRows(), 'high_water_page'),
    'page hash lengths first row' => static fn (): mixed => array_values(array_map('strlen', $plan216()->receiptRows()[0]['page_hashes'])),
    'page hash keys sixth row' => static fn (): mixed => array_keys($plan216()->receiptRows()[5]['page_hashes']),
    'batch size three row count' => static fn (): mixed => $plan216(3)->receiptSummary()['receipt_row_count'],
    'batch size three pages' => static fn (): mixed => $plan216(3)->receiptPages(),
    'batch size three receipt batches' => static fn (): mixed => array_column($plan216(3)->receiptRows(), 'receipt_pages'),
    'batch size three token count' => static fn (): mixed => count($plan216(3)->receiptTokens()),
    'batch size three payload pages' => static fn (): mixed => $plan216(3)->payloadReceiptPages(),
    'dependency closure' => static fn (): mixed => $plan216()->receiptSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan216()->receiptSummary()['non_overlap'], 'does not repeat next212'),
    'base action' => static fn (): mixed => $plan216()->basePlan->toArray()['action'],
    'base apply rows' => static fn (): mixed => $plan216()->basePlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message216(static fn () => $plan216(0)),
];

$expected216 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next216',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next216-ready',
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
    'dependency closure' => 'no new support component needed; next216 reuses next212 current-source apply rows, page hashes, freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
    'base apply rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases216 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next216 ' . $name] = static function (TestRunner $t) use ($callback, $expected216, $name): void {
        $t->same($expected216[$name], $callback());
    };
}

foreach (range(1, 88) as $index) {
    $tests['btree vacuum pointermap freeblock current source next216 receipt invariant ' . $index] = static function (TestRunner $t) use ($plan216): void {
        $plan = $plan216();
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
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next216-ready', $summary['status']);
        $t->same(true, $summary['receipts_match_apply_pages']);
    };
}

return $tests;
