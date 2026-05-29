<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage185 = static function (int $pageCount): string {
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

$putPointerMapEntry185 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database185 = static function () use ($makeFirstPage185, $putPointerMapEntry185): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage185(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next185', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry185($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan185 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database185;

    $database = $database185();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext185(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next185-current-source-apply-', 50),
        3,
        true,
        $batchSize,
    );
};

$message185 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases185 = [
    'action label' => static fn (): mixed => $plan185()->toArray()['action'],
    'status' => static fn (): mixed => $plan185()->receiptSummary()['status'],
    'receipt errors' => static fn (): mixed => $plan185()->receiptErrors(),
    'durable replay pages' => static fn (): mixed => $plan185()->durableReplayPages(),
    'fenced tail pages' => static fn (): mixed => $plan185()->fencedTailPages(),
    'pointer map receipt pages' => static fn (): mixed => $plan185()->pointerMapReceiptPages(),
    'overflow receipt pages' => static fn (): mixed => $plan185()->overflowReceiptPages(),
    'summary durable replay pages' => static fn (): mixed => $plan185()->receiptSummary()['durable_replay_pages'],
    'summary pointer map receipt pages' => static fn (): mixed => $plan185()->receiptSummary()['pointer_map_receipt_pages'],
    'summary overflow receipt pages' => static fn (): mixed => $plan185()->receiptSummary()['overflow_receipt_pages'],
    'summary fenced tail pages' => static fn (): mixed => $plan185()->receiptSummary()['fenced_tail_pages'],
    'summary final page count' => static fn (): mixed => $plan185()->receiptSummary()['final_database_page_count'],
    'summary truncation after replay' => static fn (): mixed => $plan185()->receiptSummary()['truncation_receipt_after_replay'],
    'summary pointer map before overflow' => static fn (): mixed => $plan185()->receiptSummary()['pointer_map_receipt_before_overflow'],
    'summary dependencies' => static fn (): mixed => $plan185()->receiptSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan185()->receiptSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan185()->receiptSummary()['non_overlap'], 'does not repeat next182 scheduling'),
    'row apply orders' => static fn (): mixed => array_column($plan185()->receiptRows(), 'apply_order'),
    'row pages' => static fn (): mixed => array_column($plan185()->receiptRows(), 'page_number'),
    'source operations' => static fn (): mixed => array_column($plan185()->receiptRows(), 'source_operation'),
    'page roles' => static fn (): mixed => array_column($plan185()->receiptRows(), 'page_role'),
    'receipt states' => static fn (): mixed => array_column($plan185()->receiptRows(), 'receipt_state'),
    'receipt kinds' => static fn (): mixed => array_column($plan185()->receiptRows(), 'receipt_kind'),
    'durable flags' => static fn (): mixed => array_column($plan185()->receiptRows(), 'durable_after_apply'),
    'visible flags' => static fn (): mixed => array_column($plan185()->receiptRows(), 'visible_to_next_reader'),
    'excluded flags' => static fn (): mixed => array_column($plan185()->receiptRows(), 'excluded_from_next_reader'),
    'final page count receipts' => static fn (): mixed => array_column($plan185()->receiptRows(), 'final_database_page_count_after_receipt'),
    'pointer map types' => static fn (): mixed => array_column($plan185()->receiptRows(), 'pointer_map_type'),
    'pointer map parents' => static fn (): mixed => array_column($plan185()->receiptRows(), 'pointer_map_parent'),
    'dependency flags' => static fn (): mixed => array_column($plan185()->receiptRows(), 'dependency_replayed_in_schedule'),
    'truncation flags' => static fn (): mixed => array_column($plan185()->receiptRows(), 'tail_truncation_allowed_after_this_row'),
    'hash shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['source_operation'] !== 'replay-page' || strlen((string) $row['next_page_hash']) === 64,
        $plan185()->receiptRows(),
    )),
    'resume shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['source_operation'] !== 'replay-page' || strlen((string) $row['resume_token']) === 64,
        $plan185()->receiptRows(),
    )),
    'receipt token shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => strlen((string) $row['receipt_token']) === 64,
        $plan185()->receiptRows(),
    )),
    'summary receipt signature length' => static fn (): mixed => strlen($plan185()->receiptSummary()['receipt_signature']),
    'summary current source token length' => static fn (): mixed => strlen($plan185()->receiptSummary()['current_source_receipt_token']),
    'base action' => static fn (): mixed => $plan185()->basePlan->toArray()['action'],
    'base ordered replay pages' => static fn (): mixed => $plan185()->basePlan->orderedReplayPages(),
    'base truncate pages' => static fn (): mixed => $plan185()->basePlan->truncateAfterReplayPages(),
    'batch size three durable pages' => static fn (): mixed => $plan185(3)->durableReplayPages(),
    'batch size three fenced pages' => static fn (): mixed => $plan185(3)->fencedTailPages(),
    'bad batch size rejected' => static fn (): mixed => $message185(static fn () => $plan185(0)),
];

$expected185 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next185',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next185-ready',
    'receipt errors' => [],
    'durable replay pages' => [1, 3, 105, 106, 107, 108],
    'fenced tail pages' => [109, 110],
    'pointer map receipt pages' => [105],
    'overflow receipt pages' => [106, 107, 108],
    'summary durable replay pages' => [1, 3, 105, 106, 107, 108],
    'summary pointer map receipt pages' => [105],
    'summary overflow receipt pages' => [106, 107, 108],
    'summary fenced tail pages' => [109, 110],
    'summary final page count' => 108,
    'summary truncation after replay' => true,
    'summary pointer map before overflow' => true,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next182', 'sqlite-current-source-next185'],
    'dependency closure' => 'no new support component needed; next185 reuses next182 ordered apply rows, replay hashes, pointer-map dependency receipts, and fenced-tail truncation pages',
    'non overlap' => true,
    'row apply orders' => [0, 1, 2, 3, 4, 5, 6, 7],
    'row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'source operations' => ['replay-page', 'replay-page', 'replay-page', 'replay-page', 'replay-page', 'replay-page', 'truncate-fenced-tail', 'truncate-fenced-tail'],
    'page roles' => ['database-header', 'table-leaf-freeblock', 'pointer-map', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail', 'truncated-tail'],
    'receipt states' => ['page-applied', 'page-applied', 'page-applied', 'page-applied', 'page-applied', 'page-applied', 'tail-truncated', 'tail-truncated'],
    'receipt kinds' => ['database-header-apply-receipt', 'leaf-freeblock-apply-receipt', 'pointer-map-apply-receipt', 'overflow-apply-receipt', 'overflow-apply-receipt', 'overflow-apply-receipt', 'tail-truncation-receipt', 'tail-truncation-receipt'],
    'durable flags' => [true, true, true, true, true, true, false, false],
    'visible flags' => [true, true, true, true, true, true, false, false],
    'excluded flags' => [false, false, false, false, false, false, true, true],
    'final page count receipts' => [null, null, null, null, null, null, 108, 108],
    'pointer map types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'pointer map parents' => [null, 0, null, 108, 3, 107, null, null],
    'dependency flags' => [true, true, true, true, true, true, true, true],
    'truncation flags' => [false, false, false, false, false, true, true, true],
    'hash shape' => [true, true, true, true, true, true, true, true],
    'resume shape' => [true, true, true, true, true, true, true, true],
    'receipt token shape' => [true, true, true, true, true, true, true, true],
    'summary receipt signature length' => 64,
    'summary current source token length' => 64,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next182',
    'base ordered replay pages' => [1, 3, 105, 106, 107, 108],
    'base truncate pages' => [109, 110],
    'batch size three durable pages' => [1, 3, 105, 106, 107, 108],
    'batch size three fenced pages' => [109, 110],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases185 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next185 ' . $name] = static function (TestRunner $t) use ($callback, $expected185, $name): void {
        $t->same($expected185[$name], $callback());
    };
}

foreach (range(1, 54) as $index) {
    $tests['btree vacuum pointermap freeblock current source next185 receipt invariant ' . $index] = static function (TestRunner $t) use ($plan185): void {
        $plan = $plan185();
        $rows = $plan->receiptRows();
        $summary = $plan->receiptSummary();

        $t->same([], $plan->receiptErrors());
        $t->same([1, 3, 105, 106, 107, 108], $plan->durableReplayPages());
        $t->same([109, 110], $plan->fencedTailPages());
        $t->same([105], $plan->pointerMapReceiptPages());
        $t->same([106, 107, 108], $plan->overflowReceiptPages());
        $t->same([true, true, true, true, true, true, false, false], array_column($rows, 'durable_after_apply'));
        $t->same([false, false, false, false, false, false, true, true], array_column($rows, 'excluded_from_next_reader'));
        $t->same([null, null, null, null, null, null, 108, 108], array_column($rows, 'final_database_page_count_after_receipt'));
        $t->same(true, $summary['truncation_receipt_after_replay']);
        $t->same(true, $summary['pointer_map_receipt_before_overflow']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next185-ready', $summary['status']);
    };
}

return $tests;
