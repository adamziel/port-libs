<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage191 = static function (int $pageCount): string {
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

$putPointerMapEntry191 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database191 = static function () use ($makeFirstPage191, $putPointerMapEntry191): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage191(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next191', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry191($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan191 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database191;

    $database = $database191();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafReaderHandoffManifestFromDeleteResult(
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

$message191 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases191 = [
    'action label' => static fn (): mixed => $plan191()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan191()->handoffSummary()['status'],
    'handoff errors' => static fn (): mixed => $plan191()->handoffErrors(),
    'manifest pages' => static fn (): mixed => $plan191()->manifestPages(),
    'pointer map pages' => static fn (): mixed => $plan191()->pointerMapPages(),
    'overflow pages' => static fn (): mixed => $plan191()->overflowPages(),
    'fenced tail pages' => static fn (): mixed => $plan191()->fencedTailPages(),
    'summary manifest pages' => static fn (): mixed => $plan191()->handoffSummary()['manifest_pages'],
    'summary pointer map pages' => static fn (): mixed => $plan191()->handoffSummary()['pointer_map_pages'],
    'summary overflow pages' => static fn (): mixed => $plan191()->handoffSummary()['overflow_pages'],
    'summary fenced tail pages' => static fn (): mixed => $plan191()->handoffSummary()['fenced_tail_pages'],
    'summary final page count' => static fn (): mixed => $plan191()->handoffSummary()['final_database_page_count'],
    'summary header first' => static fn (): mixed => $plan191()->handoffSummary()['database_header_first'],
    'summary pointer before overflow' => static fn (): mixed => $plan191()->handoffSummary()['pointer_map_before_overflow'],
    'summary leaf receipt' => static fn (): mixed => $plan191()->handoffSummary()['leaf_freeblock_receipt_preserved'],
    'summary tail fenced' => static fn (): mixed => $plan191()->handoffSummary()['tail_pages_fenced_from_manifest'],
    'summary error count' => static fn (): mixed => $plan191()->handoffSummary()['handoff_error_count'],
    'dependencies' => static fn (): mixed => $plan191()->handoffSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan191()->handoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan191()->handoffSummary()['non_overlap'], 'does not repeat next188 admission'),
    'row pages' => static fn (): mixed => array_column($plan191()->handoffRows(), 'page_number'),
    'manifest orders' => static fn (): mixed => array_column($plan191()->handoffRows(), 'manifest_order'),
    'manifest ordinals' => static fn (): mixed => array_column($plan191()->handoffRows(), 'manifest_ordinal'),
    'page fences' => static fn (): mixed => array_column($plan191()->handoffRows(), 'page_count_fence'),
    'states' => static fn (): mixed => array_column($plan191()->handoffRows(), 'handoff_state'),
    'actions' => static fn (): mixed => array_column($plan191()->handoffRows(), 'handoff_action'),
    'roles' => static fn (): mixed => array_column($plan191()->handoffRows(), 'handoff_role'),
    'reader admissions' => static fn (): mixed => array_column($plan191()->handoffRows(), 'reader_admission'),
    'reader receipts' => static fn (): mixed => array_column($plan191()->handoffRows(), 'reader_receipt_carried'),
    'freeblock receipts' => static fn (): mixed => array_column($plan191()->handoffRows(), 'secure_delete_freeblock_receipt'),
    'pointer map required' => static fn (): mixed => array_column($plan191()->handoffRows(), 'pointer_map_receipt_required'),
    'overflow required' => static fn (): mixed => array_column($plan191()->handoffRows(), 'overflow_receipt_required'),
    'tail required' => static fn (): mixed => array_column($plan191()->handoffRows(), 'tail_fence_required'),
    'pointer map types' => static fn (): mixed => array_column($plan191()->handoffRows(), 'pointer_map_type'),
    'pointer map parents' => static fn (): mixed => array_column($plan191()->handoffRows(), 'pointer_map_parent'),
    'hash available' => static fn (): mixed => array_column($plan191()->handoffRows(), 'next_page_hash_available'),
    'resume available' => static fn (): mixed => array_column($plan191()->handoffRows(), 'resume_token_available'),
    'visible flags' => static fn (): mixed => array_column($plan191()->handoffRows(), 'visible_to_next_reader'),
    'excluded flags' => static fn (): mixed => array_column($plan191()->handoffRows(), 'excluded_from_next_reader'),
    'manifest token length' => static fn (): mixed => strlen($plan191()->handoffSummary()['manifest_token']),
    'reader epoch length' => static fn (): mixed => strlen($plan191()->handoffSummary()['next_reader_epoch_token']),
    'receipt key length' => static fn (): mixed => strlen($plan191()->handoffRows()[0]['manifest_receipt_key']),
    'base action label' => static fn (): mixed => $plan191()->basePlan->toArray()['action'],
    'base readable pages' => static fn (): mixed => $plan191()->basePlan->nextSourceReadablePages(),
    'base rejected pages' => static fn (): mixed => $plan191()->basePlan->nextSourceRejectedPages(),
    'batch size three manifest pages' => static fn (): mixed => $plan191(3)->manifestPages(),
    'batch size three fenced pages' => static fn (): mixed => $plan191(3)->fencedTailPages(),
    'bad batch size rejected' => static fn (): mixed => $message191(static fn () => $plan191(0)),
];

$expected191 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next191',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next191-ready',
    'handoff errors' => [],
    'manifest pages' => [1, 3, 105, 106, 107, 108],
    'pointer map pages' => [105],
    'overflow pages' => [106, 107, 108],
    'fenced tail pages' => [109, 110],
    'summary manifest pages' => [1, 3, 105, 106, 107, 108],
    'summary pointer map pages' => [105],
    'summary overflow pages' => [106, 107, 108],
    'summary fenced tail pages' => [109, 110],
    'summary final page count' => 108,
    'summary header first' => true,
    'summary pointer before overflow' => true,
    'summary leaf receipt' => true,
    'summary tail fenced' => true,
    'summary error count' => 0,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next188', 'sqlite-current-source-next191'],
    'dependency closure' => 'no new support component needed; next191 reuses next188 current-source reader admission, durable receipt hashes, secure-delete freeblock visibility, pointer-map ordering, and page-count tail fences',
    'non overlap' => true,
    'row pages' => [1, 3, 105, 106, 107, 108, 109, 110],
    'manifest orders' => [0, 1, 2, 3, 4, 5, 6, 7],
    'manifest ordinals' => [1, 2, 3, 4, 5, 6, null, null],
    'page fences' => [108, 108, 108, 108, 108, 108, 108, 108],
    'states' => ['publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'publish-current-source-page', 'exclude-truncated-tail', 'exclude-truncated-tail'],
    'actions' => ['copy-page-into-current-source-manifest', 'copy-page-into-current-source-manifest', 'copy-page-into-current-source-manifest', 'copy-page-into-current-source-manifest', 'copy-page-into-current-source-manifest', 'copy-page-into-current-source-manifest', 'keep-page-out-of-current-source-manifest', 'keep-page-out-of-current-source-manifest'],
    'roles' => ['database-header', 'table-leaf-freeblock', 'pointer-map', 'replacement-overflow', 'replacement-overflow', 'replacement-overflow', 'truncated-tail', 'truncated-tail'],
    'reader admissions' => ['readable', 'readable', 'readable', 'readable', 'readable', 'readable', 'beyond-eof', 'beyond-eof'],
    'reader receipts' => [true, true, true, true, true, true, false, false],
    'freeblock receipts' => [false, true, false, false, false, false, false, false],
    'pointer map required' => [false, false, true, false, false, false, false, false],
    'overflow required' => [false, false, false, true, true, true, false, false],
    'tail required' => [false, false, false, false, false, false, true, true],
    'pointer map types' => [null, 'root-page', null, 'overflow-page', 'first-overflow-page', 'overflow-page', null, null],
    'pointer map parents' => [null, 0, null, 108, 3, 107, null, null],
    'hash available' => [true, true, true, true, true, true, false, false],
    'resume available' => [true, true, true, true, true, true, false, false],
    'visible flags' => [true, true, true, true, true, true, false, false],
    'excluded flags' => [false, false, false, false, false, false, true, true],
    'manifest token length' => 64,
    'reader epoch length' => 64,
    'receipt key length' => 64,
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next188',
    'base readable pages' => [1, 3, 105, 106, 107, 108],
    'base rejected pages' => [109, 110],
    'batch size three manifest pages' => [1, 3, 105, 106, 107, 108],
    'batch size three fenced pages' => [109, 110],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases191 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next191 ' . $name] = static function (TestRunner $t) use ($callback, $expected191, $name): void {
        $t->same($expected191[$name], $callback());
    };
}

foreach (range(1, 44) as $index) {
    $tests['btree vacuum pointermap freeblock current source next191 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan191): void {
        $plan = $plan191();
        $rows = $plan->handoffRows();
        $summary = $plan->handoffSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([1, 3, 105, 106, 107, 108], $plan->manifestPages());
        $t->same([109, 110], $plan->fencedTailPages());
        $t->same([105], $plan->pointerMapPages());
        $t->same([106, 107, 108], $plan->overflowPages());
        $t->same([1, 2, 3, 4, 5, 6, null, null], array_column($rows, 'manifest_ordinal'));
        $t->same(true, $summary['database_header_first']);
        $t->same(true, $summary['pointer_map_before_overflow']);
        $t->same(true, $summary['leaf_freeblock_receipt_preserved']);
        $t->same(true, $summary['tail_pages_fenced_from_manifest']);
        $t->same(108, $summary['final_database_page_count']);
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next191-ready', $summary['status']);
        $t->same(true, $rows[1]['secure_delete_freeblock_receipt']);
        $t->same(true, $rows[2]['pointer_map_receipt_required']);
        $t->same([true, true, true], array_slice(array_column($rows, 'overflow_receipt_required'), 3, 3));
        $t->same([true, true], array_slice(array_column($rows, 'tail_fence_required'), 6, 2));
    };
}

return $tests;
