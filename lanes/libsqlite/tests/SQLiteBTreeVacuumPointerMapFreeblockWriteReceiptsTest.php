<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage218 = static function (int $pageCount): string {
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

$putPointerMapEntry218 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database218 = static function () use ($makeFirstPage218, $putPointerMapEntry218): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage218(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_receipt', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry218($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan218 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database218;

    $database = $database218();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafWriteReceiptAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('receipt-current-source-write-', 50),
        3,
        true,
        $batchSize,
    );
};

$message218 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases218 = [
    'action label' => static fn (): mixed => $plan218()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan218()->writeSummary()['status'],
    'write row count' => static fn (): mixed => $plan218()->writeSummary()['write_row_count'],
    'write pages' => static fn (): mixed => $plan218()->writePages(),
    'summary write pages' => static fn (): mixed => $plan218()->writeSummary()['write_pages'],
    'pointer map write pages' => static fn (): mixed => $plan218()->pointerMapWritePages(),
    'payload write pages' => static fn (): mixed => $plan218()->payloadWritePages(),
    'summary apply pages' => static fn (): mixed => $plan218()->writeSummary()['apply_pages'],
    'writes match apply pages' => static fn (): mixed => $plan218()->writeSummary()['writes_match_apply_pages'],
    'write errors' => static fn (): mixed => $plan218()->writeErrors(),
    'summary write errors' => static fn (): mixed => $plan218()->writeSummary()['write_errors'],
    'all apply tokens match' => static fn (): mixed => $plan218()->writeSummary()['all_apply_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan218()->writeSummary()['all_pointer_maps_written_before_payload'],
    'all freeblock receipts carried' => static fn (): mixed => $plan218()->writeSummary()['all_freeblock_receipts_carried'],
    'all tail pages fenced' => static fn (): mixed => $plan218()->writeSummary()['all_tail_pages_fenced_for_write'],
    'all write chains valid' => static fn (): mixed => $plan218()->writeSummary()['all_write_chains_valid'],
    'write token count' => static fn (): mixed => count($plan218()->writeTokens()),
    'write token lengths' => static fn (): mixed => array_map('strlen', $plan218()->writeTokens()),
    'write signature length' => static fn (): mixed => strlen($plan218()->writeSummary()['write_signature']),
    'current source token length' => static fn (): mixed => strlen($plan218()->writeSummary()['current_source_write_receipt_token']),
    'first write channel' => static fn (): mixed => $plan218()->writeRows()[0]['write_channel'],
    'first write page' => static fn (): mixed => $plan218()->writeRows()[0]['page_number'],
    'first visible pages' => static fn (): mixed => $plan218()->writeRows()[0]['written_visible_pages'],
    'first previous token' => static fn (): mixed => $plan218()->writeRows()[0]['previous_write_token'],
    'second write channel' => static fn (): mixed => $plan218()->writeRows()[1]['write_channel'],
    'second write page' => static fn (): mixed => $plan218()->writeRows()[1]['page_number'],
    'second visible pages' => static fn (): mixed => $plan218()->writeRows()[1]['written_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan218()->writeRows()[1]['previous_write_token']),
    'third write channel' => static fn (): mixed => $plan218()->writeRows()[2]['write_channel'],
    'third write page' => static fn (): mixed => $plan218()->writeRows()[2]['page_number'],
    'third visible pages' => static fn (): mixed => $plan218()->writeRows()[2]['written_visible_pages'],
    'fourth write channel' => static fn (): mixed => $plan218()->writeRows()[3]['write_channel'],
    'fourth write page' => static fn (): mixed => $plan218()->writeRows()[3]['page_number'],
    'fourth visible pages' => static fn (): mixed => $plan218()->writeRows()[3]['written_visible_pages'],
    'fifth write channel' => static fn (): mixed => $plan218()->writeRows()[4]['write_channel'],
    'fifth write page' => static fn (): mixed => $plan218()->writeRows()[4]['page_number'],
    'fifth visible pages' => static fn (): mixed => $plan218()->writeRows()[4]['written_visible_pages'],
    'sixth write channel' => static fn (): mixed => $plan218()->writeRows()[5]['write_channel'],
    'sixth write page' => static fn (): mixed => $plan218()->writeRows()[5]['page_number'],
    'sixth visible pages' => static fn (): mixed => $plan218()->writeRows()[5]['written_visible_pages'],
    'write ordinals' => static fn (): mixed => array_column($plan218()->writeRows(), 'write_ordinal'),
    'apply ordinals' => static fn (): mixed => array_column($plan218()->writeRows(), 'apply_ordinal'),
    'row states' => static fn (): mixed => array_column($plan218()->writeRows(), 'write_state'),
    'row apply token flags' => static fn (): mixed => array_column($plan218()->writeRows(), 'apply_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan218()->writeRows(), 'freeblock_receipt_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan218()->writeRows(), 'tail_pages_fenced_for_write'),
    'row chain flags' => static fn (): mixed => array_column($plan218()->writeRows(), 'write_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan218()->writeRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan218(3)->writeSummary()['write_row_count'],
    'batch size three pages' => static fn (): mixed => $plan218(3)->writePages(),
    'batch size three write apply ordinals' => static fn (): mixed => array_column($plan218(3)->writeRows(), 'apply_ordinal'),
    'batch size three token count' => static fn (): mixed => count($plan218(3)->writeTokens()),
    'dependency closure' => static fn (): mixed => $plan218()->writeSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan218()->writeSummary()['non_overlap'], 'does not repeat next212'),
    'base action' => static fn (): mixed => $plan218()->basePlan->toArray()['action'],
    'base apply rows' => static fn (): mixed => $plan218()->basePlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message218(static fn () => $plan218(0)),
];

$expected218 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-write-receipts',
    'summary status' => 'btree-vacuum-pointermap-freeblock-write-receipts-ready',
    'write row count' => 7,
    'write pages' => [2, 3, 105, 106, 107, 108],
    'summary write pages' => [2, 3, 105, 106, 107, 108],
    'pointer map write pages' => [2, 105],
    'payload write pages' => [3, 106, 107, 108],
    'summary apply pages' => [2, 3, 105, 106, 107, 108],
    'writes match apply pages' => true,
    'write errors' => [],
    'summary write errors' => [],
    'all apply tokens match' => true,
    'all pointer maps before payload' => true,
    'all freeblock receipts carried' => true,
    'all tail pages fenced' => true,
    'all write chains valid' => true,
    'write token count' => 7,
    'write token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'write signature length' => 64,
    'current source token length' => 64,
    'first write channel' => 'pointer-map',
    'first write page' => 2,
    'first visible pages' => [2],
    'first previous token' => null,
    'second write channel' => 'payload',
    'second write page' => 3,
    'second visible pages' => [2, 3],
    'second previous token length' => 64,
    'third write channel' => 'pointer-map',
    'third write page' => 105,
    'third visible pages' => [2, 3, 105],
    'fourth write channel' => 'payload',
    'fourth write page' => 106,
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth write channel' => 'pointer-map',
    'fifth write page' => 105,
    'fifth visible pages' => [2, 3, 105, 106],
    'sixth write channel' => 'payload',
    'sixth write page' => 107,
    'sixth visible pages' => [2, 3, 105, 106, 107],
    'write ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'apply ordinals' => [1, 2, 3, 4, 5, 6, 6],
    'row states' => ['current-source-page-write-receipted', 'current-source-page-write-receipted', 'current-source-page-write-receipted', 'current-source-page-write-receipted', 'current-source-page-write-receipted', 'current-source-page-write-receipted', 'current-source-page-write-receipted'],
    'row apply token flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108, 108],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three write apply ordinals' => [1, 2, 3, 4, 4, 4],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; write-receipts reuses next212 current-source apply rows and adds per-page write receipts only',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
    'base apply rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases218 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source write-receipts ' . $name] = static function (TestRunner $t) use ($callback, $expected218, $name): void {
        $t->same($expected218[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source write-receipts write invariant ' . $index] = static function (TestRunner $t) use ($plan218): void {
        $plan = $plan218();
        $summary = $plan->writeSummary();

        $t->same([], $plan->writeErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->writePages());
        $t->same([2, 105], $plan->pointerMapWritePages());
        $t->same([3, 106, 107, 108], $plan->payloadWritePages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->writeRows(), 'write_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->writeRows(), 'apply_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->writeRows(), 'freeblock_receipt_carried'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->writeRows(), 'tail_pages_fenced_for_write'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->writeTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-write-receipts-ready', $summary['status']);
        $t->same(true, $summary['writes_match_apply_pages']);
        $t->same(true, $summary['all_pointer_maps_written_before_payload']);
    };
}

return $tests;
