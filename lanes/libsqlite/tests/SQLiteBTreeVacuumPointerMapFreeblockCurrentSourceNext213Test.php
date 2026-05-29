<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage213 = static function (int $pageCount): string {
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

$putPointerMapEntry213 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database213 = static function () use ($makeFirstPage213, $putPointerMapEntry213): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage213(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next213', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry213($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan213 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database213;

    $database = $database213();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext213(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next213-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message213 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases213 = [
    'action label' => static fn (): mixed => $plan213()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan213()->receiptSummary()['status'],
    'receipt row count' => static fn (): mixed => $plan213()->receiptSummary()['receipt_row_count'],
    'receipt pages' => static fn (): mixed => $plan213()->receiptPages(),
    'summary receipt pages' => static fn (): mixed => $plan213()->receiptSummary()['receipt_pages'],
    'pointer map receipt pages' => static fn (): mixed => $plan213()->pointerMapReceiptPages(),
    'payload receipt pages' => static fn (): mixed => $plan213()->payloadReceiptPages(),
    'apply pages' => static fn (): mixed => $plan213()->receiptSummary()['apply_pages'],
    'receipt matches apply pages' => static fn (): mixed => $plan213()->receiptSummary()['receipt_matches_apply_pages'],
    'receipt errors' => static fn (): mixed => $plan213()->receiptErrors(),
    'summary receipt errors' => static fn (): mixed => $plan213()->receiptSummary()['receipt_errors'],
    'all apply tokens match' => static fn (): mixed => $plan213()->receiptSummary()['all_apply_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan213()->receiptSummary()['all_pointer_maps_receipted_before_payload'],
    'all freeblock receipts preserved' => static fn (): mixed => $plan213()->receiptSummary()['all_freeblock_receipts_preserved'],
    'all tail pages fenced' => static fn (): mixed => $plan213()->receiptSummary()['all_tail_pages_fenced_after_receipt'],
    'all receipt chains valid' => static fn (): mixed => $plan213()->receiptSummary()['all_receipt_chains_valid'],
    'receipt token count' => static fn (): mixed => count($plan213()->receiptTokens()),
    'receipt token lengths' => static fn (): mixed => array_map('strlen', $plan213()->receiptTokens()),
    'receipt signature length' => static fn (): mixed => strlen($plan213()->receiptSummary()['receipt_signature']),
    'next writer token length' => static fn (): mixed => strlen($plan213()->receiptSummary()['next_writer_receipt_token']),
    'receipt page classes' => static fn (): mixed => $plan213()->receiptSummary()['receipt_page_classes'],
    'first receipt channel' => static fn (): mixed => $plan213()->receiptRows()[0]['receipt_channel'],
    'first receipt class' => static fn (): mixed => $plan213()->receiptRows()[0]['receipt_page_class'],
    'first receipt pages' => static fn (): mixed => $plan213()->receiptRows()[0]['receipt_pages'],
    'first visible pages' => static fn (): mixed => $plan213()->receiptRows()[0]['receipted_visible_pages'],
    'first previous token' => static fn (): mixed => $plan213()->receiptRows()[0]['previous_receipt_token'],
    'second receipt channel' => static fn (): mixed => $plan213()->receiptRows()[1]['receipt_channel'],
    'second receipt class' => static fn (): mixed => $plan213()->receiptRows()[1]['receipt_page_class'],
    'second receipt pages' => static fn (): mixed => $plan213()->receiptRows()[1]['receipt_pages'],
    'second visible pages' => static fn (): mixed => $plan213()->receiptRows()[1]['receipted_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan213()->receiptRows()[1]['previous_receipt_token']),
    'third receipt channel' => static fn (): mixed => $plan213()->receiptRows()[2]['receipt_channel'],
    'third receipt class' => static fn (): mixed => $plan213()->receiptRows()[2]['receipt_page_class'],
    'third receipt pages' => static fn (): mixed => $plan213()->receiptRows()[2]['receipt_pages'],
    'third visible pages' => static fn (): mixed => $plan213()->receiptRows()[2]['receipted_visible_pages'],
    'fourth receipt channel' => static fn (): mixed => $plan213()->receiptRows()[3]['receipt_channel'],
    'fourth receipt class' => static fn (): mixed => $plan213()->receiptRows()[3]['receipt_page_class'],
    'fourth receipt pages' => static fn (): mixed => $plan213()->receiptRows()[3]['receipt_pages'],
    'fourth visible pages' => static fn (): mixed => $plan213()->receiptRows()[3]['receipted_visible_pages'],
    'fifth receipt channel' => static fn (): mixed => $plan213()->receiptRows()[4]['receipt_channel'],
    'fifth receipt class' => static fn (): mixed => $plan213()->receiptRows()[4]['receipt_page_class'],
    'fifth receipt pages' => static fn (): mixed => $plan213()->receiptRows()[4]['receipt_pages'],
    'fifth visible pages' => static fn (): mixed => $plan213()->receiptRows()[4]['receipted_visible_pages'],
    'sixth receipt channel' => static fn (): mixed => $plan213()->receiptRows()[5]['receipt_channel'],
    'sixth receipt class' => static fn (): mixed => $plan213()->receiptRows()[5]['receipt_page_class'],
    'sixth receipt pages' => static fn (): mixed => $plan213()->receiptRows()[5]['receipt_pages'],
    'sixth visible pages' => static fn (): mixed => $plan213()->receiptRows()[5]['receipted_visible_pages'],
    'receipt ordinals' => static fn (): mixed => array_column($plan213()->receiptRows(), 'receipt_ordinal'),
    'row states' => static fn (): mixed => array_column($plan213()->receiptRows(), 'receipt_state'),
    'row apply token flags' => static fn (): mixed => array_column($plan213()->receiptRows(), 'apply_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan213()->receiptRows(), 'freeblock_receipt_preserved'),
    'row tail fence flags' => static fn (): mixed => array_column($plan213()->receiptRows(), 'tail_pages_fenced_after_receipt'),
    'row chain flags' => static fn (): mixed => array_column($plan213()->receiptRows(), 'receipt_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan213()->receiptRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan213(3)->receiptSummary()['receipt_row_count'],
    'batch size three pages' => static fn (): mixed => $plan213(3)->receiptPages(),
    'batch size three receipt batches' => static fn (): mixed => array_column($plan213(3)->receiptRows(), 'receipt_pages'),
    'batch size three token count' => static fn (): mixed => count($plan213(3)->receiptTokens()),
    'dependency closure' => static fn (): mixed => $plan213()->receiptSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan213()->receiptSummary()['non_overlap'], 'does not repeat next212'),
    'base action' => static fn (): mixed => $plan213()->basePlan->toArray()['action'],
    'base apply rows' => static fn (): mixed => $plan213()->basePlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message213(static fn () => $plan213(0)),
];

$expected213 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next213',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next213-ready',
    'receipt row count' => 6,
    'receipt pages' => [2, 3, 105, 106, 107, 108],
    'summary receipt pages' => [2, 3, 105, 106, 107, 108],
    'pointer map receipt pages' => [2, 105],
    'payload receipt pages' => [3, 106, 107, 108],
    'apply pages' => [2, 3, 105, 106, 107, 108],
    'receipt matches apply pages' => true,
    'receipt errors' => [],
    'summary receipt errors' => [],
    'all apply tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts preserved' => true,
    'all tail pages fenced' => true,
    'all receipt chains valid' => true,
    'receipt token count' => 6,
    'receipt token lengths' => [64, 64, 64, 64, 64, 64],
    'receipt signature length' => 64,
    'next writer token length' => 64,
    'receipt page classes' => ['pointer-map-page', 'leaf-freeblock-page', 'overflow-payload-page'],
    'first receipt channel' => 'pointer-map',
    'first receipt class' => 'pointer-map-page',
    'first receipt pages' => [2],
    'first visible pages' => [2],
    'first previous token' => null,
    'second receipt channel' => 'payload',
    'second receipt class' => 'leaf-freeblock-page',
    'second receipt pages' => [3],
    'second visible pages' => [2, 3],
    'second previous token length' => 64,
    'third receipt channel' => 'pointer-map',
    'third receipt class' => 'pointer-map-page',
    'third receipt pages' => [105],
    'third visible pages' => [2, 3, 105],
    'fourth receipt channel' => 'payload',
    'fourth receipt class' => 'overflow-payload-page',
    'fourth receipt pages' => [106],
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth receipt channel' => 'pointer-map',
    'fifth receipt class' => 'pointer-map-page',
    'fifth receipt pages' => [105],
    'fifth visible pages' => [2, 3, 105, 106],
    'sixth receipt channel' => 'payload',
    'sixth receipt class' => 'overflow-payload-page',
    'sixth receipt pages' => [107, 108],
    'sixth visible pages' => [2, 3, 105, 106, 107, 108],
    'receipt ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['current-source-page-receipt-ready', 'current-source-page-receipt-ready', 'current-source-page-receipt-ready', 'current-source-page-receipt-ready', 'current-source-page-receipt-ready', 'current-source-page-receipt-ready'],
    'row apply token flags' => [true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108],
    'batch size three row count' => 4,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three receipt batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next213 reuses next212 current-source apply rows, pointer-map/payload page classes, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
    'base apply rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases213 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next213 ' . $name] = static function (TestRunner $t) use ($callback, $expected213, $name): void {
        $t->same($expected213[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next213 receipt invariant ' . $index] = static function (TestRunner $t) use ($plan213): void {
        $plan = $plan213();
        $summary = $plan->receiptSummary();

        $t->same([], $plan->receiptErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->receiptPages());
        $t->same([2, 105], $plan->pointerMapReceiptPages());
        $t->same([3, 106, 107, 108], $plan->payloadReceiptPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->receiptRows(), 'receipt_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->receiptRows(), 'apply_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->receiptRows(), 'freeblock_receipt_preserved'));
        $t->same([true, true, true, true, true, true], array_column($plan->receiptRows(), 'tail_pages_fenced_after_receipt'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->receiptTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next213-ready', $summary['status']);
        $t->same(true, $summary['receipt_matches_apply_pages']);
    };
}

return $tests;
