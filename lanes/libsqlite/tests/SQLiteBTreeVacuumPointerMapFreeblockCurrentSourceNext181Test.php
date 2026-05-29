<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage181 = static function (int $pageCount): string {
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

$putPointerMapEntry181 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$overflowPage181 = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database181 = static function () use ($makeFirstPage181, $putPointerMapEntry181, $overflowPage181): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage181(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next181', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    $pages[106] = $overflowPage181(107, 'A');
    $pages[107] = $overflowPage181(108, 'B');
    $pages[108] = $overflowPage181(109, 'C');
    $pages[109] = $overflowPage181(110, 'D');
    $pages[110] = $overflowPage181(0, 'E');

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry181($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan181 = static function (
    ?string $payload = null,
    int $maxTruncatedPages = 4,
) use ($database181): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    $database = $database181();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext181(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        $maxTruncatedPages,
        $payload ?? str_repeat('next181-current-source-snapshot-receipt-', 44),
        3,
        true,
    );
};

$message181 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases181 = [
    'action label' => static fn (): mixed => $plan181()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan181()->snapshotSummary()['status'],
    'snapshot errors' => static fn (): mixed => $plan181()->snapshotErrors(),
    'replayable pages' => static fn (): mixed => $plan181()->replayablePages(),
    'quarantined pages' => static fn (): mixed => $plan181()->quarantinedPages(),
    'leaf freeblock pages' => static fn (): mixed => $plan181()->leafFreeblockPages(),
    'overflow pages' => static fn (): mixed => $plan181()->overflowPages(),
    'pointer map receipt pages' => static fn (): mixed => $plan181()->pointerMapReceiptPages(),
    'summary replayable pages' => static fn (): mixed => $plan181()->snapshotSummary()['replayable_pages'],
    'summary quarantined pages' => static fn (): mixed => $plan181()->snapshotSummary()['quarantined_pages'],
    'summary leaf freeblock pages' => static fn (): mixed => $plan181()->snapshotSummary()['leaf_freeblock_pages'],
    'summary overflow pages' => static fn (): mixed => $plan181()->snapshotSummary()['overflow_pages'],
    'summary pointer map receipt pages' => static fn (): mixed => $plan181()->snapshotSummary()['pointer_map_receipt_pages'],
    'dependencies' => static fn (): mixed => $plan181()->snapshotSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan181()->snapshotSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan181()->snapshotSummary()['non_overlap'], 'does not repeat next178 publication'),
    'row pages' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'page_number'),
    'row slots' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'slot'),
    'row statuses' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'status'),
    'snapshot kinds' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'snapshot_kind'),
    'admitted flags' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'next_reader_admitted'),
    'quarantine reasons' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'quarantine_reason'),
    'publication states' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'publication_state'),
    'receipt kinds' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'receipt_kind'),
    'freeblock carried flags' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'freeblock_receipt_carried'),
    'pointer map carried flags' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'pointer_map_receipt_carried'),
    'next pointer carried flags' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'next_pointer_receipt_carried'),
    'final materialized flags' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'final_materialized'),
    'final next pages' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'final_next_page'),
    'final pointer map types' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'final_pointer_map_type'),
    'final pointer map parents' => static fn (): mixed => array_column($plan181()->snapshotRows(), 'final_pointer_map_parent'),
    'receipt chain key length' => static fn (): mixed => strlen($plan181()->snapshotRows()[0]['receipt_chain_key']),
    'next reader token length' => static fn (): mixed => strlen($plan181()->snapshotSummary()['next_reader_token']),
    'receipt chain token length' => static fn (): mixed => strlen($plan181()->snapshotSummary()['receipt_chain_token']),
    'base action label' => static fn (): mixed => $plan181()->basePlan->toArray()['action'],
    'base published pages' => static fn (): mixed => $plan181()->basePlan->publishedCurrentSourcePages(),
    'base blocked pages' => static fn (): mixed => $plan181()->basePlan->blockedCurrentSourcePages(),
    'wide replayable pages' => static fn (): mixed => $plan181(null, 6)->replayablePages(),
    'wide quarantined pages' => static fn (): mixed => $plan181(null, 6)->quarantinedPages(),
    'small replacement rejected' => static fn (): mixed => $message181(static fn () => $plan181(str_repeat('small', 20))),
];

$expected181 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next181',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next181-ready',
    'snapshot errors' => [],
    'replayable pages' => [3, 106, 107, 108, 109],
    'quarantined pages' => [110],
    'leaf freeblock pages' => [3],
    'overflow pages' => [106, 107, 108, 109],
    'pointer map receipt pages' => [110],
    'summary replayable pages' => [3, 106, 107, 108, 109],
    'summary quarantined pages' => [110],
    'summary leaf freeblock pages' => [3],
    'summary overflow pages' => [106, 107, 108, 109],
    'summary pointer map receipt pages' => [110],
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next178', 'sqlite-current-source-next181'],
    'dependency closure' => 'no new support component needed; next181 reuses next178 publication receipts, leaf freeblock receipts, overflow next-pointer receipts, and auto-vacuum pointer-map receipt metadata',
    'non overlap' => true,
    'row pages' => [3, 106, 107, 108, 109, 110],
    'row slots' => [1, 2, 3, 4, 5, null],
    'row statuses' => ['stable-leaf-freeblock', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail-page'],
    'snapshot kinds' => ['leaf-freeblock-current-source', 'overflow-current-source', 'overflow-current-source', 'overflow-current-source', 'overflow-tail-current-source', 'quarantined-truncated-tail'],
    'admitted flags' => [true, true, true, true, true, false],
    'quarantine reasons' => [null, null, null, null, null, 'truncated-tail-fenced-from-next-reader'],
    'publication states' => ['published-current-source', 'published-current-source', 'published-current-source', 'published-current-source', 'published-current-source', 'blocked-truncated-tail'],
    'receipt kinds' => ['leaf-freeblock-receipt', 'overflow-chain-receipt', 'overflow-chain-receipt', 'overflow-chain-receipt', 'overflow-tail-rewrite-receipt', 'truncated-tail-fence-receipt'],
    'freeblock carried flags' => [true, false, false, false, false, false],
    'pointer map carried flags' => [false, false, false, false, false, true],
    'next pointer carried flags' => [false, false, false, false, true, true],
    'final materialized flags' => [true, true, true, true, true, false],
    'final next pages' => [null, 107, 108, 109, 0, null],
    'final pointer map types' => ['root-page', 'first-overflow-page', 'overflow-page', 'overflow-page', 'overflow-page', null],
    'final pointer map parents' => [0, 3, 106, 107, 108, null],
    'receipt chain key length' => 64,
    'next reader token length' => 64,
    'receipt chain token length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next178',
    'base published pages' => [3, 106, 107, 108, 109],
    'base blocked pages' => [110],
    'wide replayable pages' => [3, 106, 107, 108, 109],
    'wide quarantined pages' => [110],
    'small replacement rejected' => 'SQLite b-tree vacuum pointer-map freeblock next161 requires appended overflow pages after partial vacuum',
];

$tests = [];

foreach ($cases181 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next181 ' . $name] = static function (TestRunner $t) use ($callback, $expected181, $name): void {
        $t->same($expected181[$name], $callback());
    };
}

foreach (range(1, 60) as $index) {
    $tests['btree vacuum pointermap freeblock current source next181 snapshot invariant ' . $index] = static function (TestRunner $t) use ($plan181): void {
        $plan = $plan181();
        $rows = $plan->snapshotRows();

        $t->same([], $plan->snapshotErrors());
        $t->same([3, 106, 107, 108, 109], $plan->replayablePages());
        $t->same([110], $plan->quarantinedPages());
        $t->same([1, 2, 3, 4, 5, null], array_column($rows, 'slot'));
        $t->same([true, true, true, true, true, false], array_column($rows, 'next_reader_admitted'));
        $t->same('leaf-freeblock-current-source', $rows[0]['snapshot_kind']);
        $t->same('overflow-tail-current-source', $rows[4]['snapshot_kind']);
        $t->same('quarantined-truncated-tail', $rows[5]['snapshot_kind']);
        $t->same('truncated-tail-fenced-from-next-reader', $rows[5]['quarantine_reason']);
        $t->same(true, $rows[0]['freeblock_receipt_carried']);
        $t->same(true, $rows[4]['next_pointer_receipt_carried']);
        $t->same(true, $rows[5]['pointer_map_receipt_carried']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next181-ready', $plan->snapshotSummary()['status']);
    };
}

return $tests;
