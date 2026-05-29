<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage188 = static function (int $pageCount): string {
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

$putPointerMapEntry188 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database188 = static function () use ($makeFirstPage188, $putPointerMapEntry188): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage188(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next188', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry188($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan188 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database188;

    $database = $database188();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext188(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next188-current-source-reader-', 50),
        3,
        true,
        $batchSize,
    );
};

$message188 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases188 = [
    'action label' => static fn (): mixed => $plan188()->toArray()['action'],
    'status' => static fn (): mixed => $plan188()->readerSummary()['status'],
    'reader errors' => static fn (): mixed => $plan188()->readerErrors(),
    'readable pages' => static fn (): mixed => $plan188()->nextSourceReadablePages(),
    'rejected pages' => static fn (): mixed => $plan188()->nextSourceRejectedPages(),
    'receipt carried pages' => static fn (): mixed => $plan188()->receiptCarriedPages(),
    'summary readable pages' => static fn (): mixed => $plan188()->readerSummary()['next_source_readable_pages'],
    'summary rejected pages' => static fn (): mixed => $plan188()->readerSummary()['next_source_rejected_pages'],
    'summary receipt pages' => static fn (): mixed => $plan188()->readerSummary()['receipt_carried_pages'],
    'summary final page count' => static fn (): mixed => $plan188()->readerSummary()['final_database_page_count'],
    'summary leaf visible' => static fn (): mixed => $plan188()->readerSummary()['freeblock_leaf_visible_to_reader'],
    'summary pointer map before overflow' => static fn (): mixed => $plan188()->readerSummary()['pointer_map_before_overflow'],
    'summary tail rejected' => static fn (): mixed => $plan188()->readerSummary()['tail_pages_rejected_after_page_count_fence'],
    'summary dependencies' => static fn (): mixed => $plan188()->readerSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan188()->readerSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan188()->readerSummary()['non_overlap'], 'does not repeat next185 receipt publication'),
    'row orders' => static fn (): mixed => array_column($plan188()->readerRows(), 'reader_order'),
    'row pages' => static fn (): mixed => array_column($plan188()->readerRows(), 'page_number'),
    'page counts' => static fn (): mixed => array_column($plan188()->readerRows(), 'page_count_seen_by_reader'),
    'source states' => static fn (): mixed => array_column($plan188()->readerRows(), 'source_receipt_state'),
    'source kinds' => static fn (): mixed => array_column($plan188()->readerRows(), 'source_receipt_kind'),
    'source roles' => static fn (): mixed => array_column($plan188()->readerRows(), 'source_page_role'),
    'reader admissions' => static fn (): mixed => array_column($plan188()->readerRows(), 'reader_admission'),
    'reader actions' => static fn (): mixed => array_column($plan188()->readerRows(), 'reader_action'),
    'carried flags' => static fn (): mixed => array_column($plan188()->readerRows(), 'receipt_carried_to_reader'),
    'visible flags' => static fn (): mixed => array_column($plan188()->readerRows(), 'visible_to_next_reader'),
    'excluded flags' => static fn (): mixed => array_column($plan188()->readerRows(), 'excluded_from_next_reader'),
    'pointer map types' => static fn (): mixed => array_column($plan188()->readerRows(), 'pointer_map_type'),
    'pointer map parents' => static fn (): mixed => array_column($plan188()->readerRows(), 'pointer_map_parent'),
    'hash shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['reader_admission'] !== 'readable' || strlen((string) $row['next_page_hash']) === 64,
        $plan188()->readerRows(),
    )),
    'resume shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['reader_admission'] !== 'readable' || strlen((string) $row['resume_token']) === 64,
        $plan188()->readerRows(),
    )),
    'tail hash nulls' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['reader_admission'] === 'readable' || $row['next_page_hash'] === null,
        $plan188()->readerRows(),
    )),
    'tail resume nulls' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => $row['reader_admission'] === 'readable' || $row['resume_token'] === null,
        $plan188()->readerRows(),
    )),
    'reader token shape' => static fn (): mixed => array_values(array_map(
        static fn (array $row): bool => strlen((string) $row['reader_token']) === 64,
        $plan188()->readerRows(),
    )),
    'summary reader epoch length' => static fn (): mixed => strlen($plan188()->readerSummary()['reader_epoch_token']),
    'summary receipt carry length' => static fn (): mixed => strlen($plan188()->readerSummary()['receipt_carry_token']),
    'base action' => static fn (): mixed => $plan188()->basePlan->toArray()['action'],
    'base durable pages' => static fn (): mixed => $plan188()->basePlan->durableReplayPages(),
    'base fenced pages' => static fn (): mixed => $plan188()->basePlan->fencedTailPages(),
    'batch size three readable pages' => static fn (): mixed => $plan188(3)->nextSourceReadablePages(),
    'batch size three rejected pages' => static fn (): mixed => $plan188(3)->nextSourceRejectedPages(),
    'bad batch size rejected' => static fn (): mixed => $message188(static fn () => $plan188(0)),
];

$expected188 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next188',
    'status' => 'btree-vacuum-pointermap-freeblock-current-source-next188-ready',
    'reader errors' => [],
    'readable pages' => [1, 3, 105, 106, 107, 108],
    'rejected pages' => [109, 110],
    'receipt carried pages' => [1, 3, 105, 106, 107, 108],
    'summary readable pages' => [1, 3, 105, 106, 107, 108],
    'summary rejected pages' => [109, 110],
    'summary receipt pages' => [1, 3, 105, 106, 107, 108],
    'summary final page count' => 108,
    'summary leaf visible' => true,
    'summary pointer map before overflow' => true,
    'summary tail rejected' => true,
    'summary dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next185', 'sqlite-current-source-next188'],
    'dependency closure' => 'no new support component needed; next188 reuses next185 durable receipt rows, final page-count fences, secure-delete freeblock receipts, overflow receipt hashes, and pointer-map ordering',
    'non overlap' => true,
    'row orders' => [0, 1, 2, 3, 4, 5, 6, 7],
    'row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'page counts' => [108, 108, 108, 108, 108, 108, 108, 108],
    'source states' => ['page-applied', 'page-applied', 'page-applied', 'page-applied', 'page-applied', 'page-applied', 'tail-truncated', 'tail-truncated'],
    'source kinds' => ['database-header-apply-receipt', 'leaf-freeblock-apply-receipt', 'pointer-map-apply-receipt', 'overflow-apply-receipt', 'overflow-apply-receipt', 'overflow-apply-receipt', 'tail-truncation-receipt', 'tail-truncation-receipt'],
    'source roles' => ['database-header', 'table-leaf-freeblock', 'pointer-map', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail', 'truncated-tail'],
    'reader admissions' => ['readable', 'readable', 'readable', 'readable', 'readable', 'readable', 'beyond-eof', 'beyond-eof'],
    'reader actions' => ['read-current-source-page', 'read-current-source-page', 'read-current-source-page', 'read-current-source-page', 'read-current-source-page', 'read-current-source-page', 'reject-tail-page', 'reject-tail-page'],
    'carried flags' => [true, true, true, true, true, true, false, false],
    'visible flags' => [true, true, true, true, true, true, false, false],
    'excluded flags' => [false, false, false, false, false, false, true, true],
    'pointer map types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'pointer map parents' => [null, 0, null, 108, 3, 107, null, null],
    'hash shape' => [true, true, true, true, true, true, true, true],
    'resume shape' => [true, true, true, true, true, true, true, true],
    'tail hash nulls' => [true, true, true, true, true, true, true, true],
    'tail resume nulls' => [true, true, true, true, true, true, true, true],
    'reader token shape' => [true, true, true, true, true, true, true, true],
    'summary reader epoch length' => 64,
    'summary receipt carry length' => 64,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next185',
    'base durable pages' => [1, 3, 105, 106, 107, 108],
    'base fenced pages' => [109, 110],
    'batch size three readable pages' => [1, 3, 105, 106, 107, 108],
    'batch size three rejected pages' => [109, 110],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases188 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next188 ' . $name] = static function (TestRunner $t) use ($callback, $expected188, $name): void {
        $t->same($expected188[$name], $callback());
    };
}

foreach (range(1, 54) as $index) {
    $tests['btree vacuum pointermap freeblock current source next188 reader invariant ' . $index] = static function (TestRunner $t) use ($plan188): void {
        $plan = $plan188();
        $rows = $plan->readerRows();
        $summary = $plan->readerSummary();

        $t->same([], $plan->readerErrors());
        $t->same([1, 3, 105, 106, 107, 108], $plan->nextSourceReadablePages());
        $t->same([109, 110], $plan->nextSourceRejectedPages());
        $t->same([1, 3, 105, 106, 107, 108], $plan->receiptCarriedPages());
        $t->same([true, true, true, true, true, true, false, false], array_column($rows, 'receipt_carried_to_reader'));
        $t->same([false, false, false, false, false, false, true, true], array_column($rows, 'excluded_from_next_reader'));
        $t->same(['readable', 'readable', 'readable', 'readable', 'readable', 'readable', 'beyond-eof', 'beyond-eof'], array_column($rows, 'reader_admission'));
        $t->same(108, $summary['final_database_page_count']);
        $t->same(true, $summary['freeblock_leaf_visible_to_reader']);
        $t->same(true, $summary['tail_pages_rejected_after_page_count_fence']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next188-ready', $summary['status']);
    };
}

return $tests;
