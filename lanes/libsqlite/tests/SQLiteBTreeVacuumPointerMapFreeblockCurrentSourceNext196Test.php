<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage196 = static function (int $pageCount): string {
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

$putPointerMapEntry196 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database196 = static function () use ($makeFirstPage196, $putPointerMapEntry196): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage196(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next196', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(75 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry196($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan196 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database196;

    $database = $database196();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCommitAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next196-source-next-writer-', 50),
        3,
        true,
        $batchSize,
    );
};

$message196 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases196 = [
    'action label' => static fn (): mixed => $plan196()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan196()->sourceNextSummary()['status'],
    'handoff row count' => static fn (): mixed => $plan196()->sourceNextSummary()['handoff_row_count'],
    'next writable pages' => static fn (): mixed => $plan196()->nextWritablePages(),
    'summary next writable pages' => static fn (): mixed => $plan196()->sourceNextSummary()['next_writable_pages'],
    'reader pages' => static fn (): mixed => $plan196()->sourceNextSummary()['reader_pages'],
    'handoff errors' => static fn (): mixed => $plan196()->handoffErrors(),
    'summary handoff errors' => static fn (): mixed => $plan196()->sourceNextSummary()['handoff_errors'],
    'all validation tokens match' => static fn (): mixed => $plan196()->sourceNextSummary()['all_validation_tokens_match'],
    'all pointer maps carried forward' => static fn (): mixed => $plan196()->sourceNextSummary()['all_pointer_maps_carried_forward'],
    'all freeblock receipts carried forward' => static fn (): mixed => $plan196()->sourceNextSummary()['all_freeblock_receipts_carried_forward'],
    'all handoff chains valid' => static fn (): mixed => $plan196()->sourceNextSummary()['all_handoff_chains_valid'],
    'all fenced pages blocked' => static fn (): mixed => $plan196()->sourceNextSummary()['all_fenced_pages_blocked_from_next_writer'],
    'source token count' => static fn (): mixed => count($plan196()->sourceNextTokens()),
    'source token lengths' => static fn (): mixed => array_map('strlen', $plan196()->sourceNextTokens()),
    'source signature length' => static fn (): mixed => strlen($plan196()->sourceNextSummary()['source_next_signature']),
    'writer token length' => static fn (): mixed => strlen($plan196()->sourceNextSummary()['next_writer_source_token']),
    'validation signature length' => static fn (): mixed => strlen($plan196()->sourceNextSummary()['validation_signature']),
    'first row validated pages' => static fn (): mixed => $plan196()->handoffRows()[0]['validated_pages'],
    'first row next writable pages' => static fn (): mixed => $plan196()->handoffRows()[0]['next_writable_pages'],
    'first row previous token' => static fn (): mixed => $plan196()->handoffRows()[0]['previous_source_next_token'],
    'first row pointer maps' => static fn (): mixed => $plan196()->handoffRows()[0]['visible_pointer_map_pages'],
    'first row payload pages' => static fn (): mixed => $plan196()->handoffRows()[0]['visible_payload_pages'],
    'second row validated pages' => static fn (): mixed => $plan196()->handoffRows()[1]['validated_pages'],
    'second row next writable pages' => static fn (): mixed => $plan196()->handoffRows()[1]['next_writable_pages'],
    'second row previous token length' => static fn (): mixed => strlen((string) $plan196()->handoffRows()[1]['previous_source_next_token']),
    'second row pointer maps' => static fn (): mixed => $plan196()->handoffRows()[1]['visible_pointer_map_pages'],
    'second row payload pages' => static fn (): mixed => $plan196()->handoffRows()[1]['visible_payload_pages'],
    'third row validated pages' => static fn (): mixed => $plan196()->handoffRows()[2]['validated_pages'],
    'third row next writable pages' => static fn (): mixed => $plan196()->handoffRows()[2]['next_writable_pages'],
    'third row previous token length' => static fn (): mixed => strlen((string) $plan196()->handoffRows()[2]['previous_source_next_token']),
    'third row pointer maps' => static fn (): mixed => $plan196()->handoffRows()[2]['visible_pointer_map_pages'],
    'third row payload pages' => static fn (): mixed => $plan196()->handoffRows()[2]['visible_payload_pages'],
    'row states' => static fn (): mixed => array_column($plan196()->handoffRows(), 'source_next_state'),
    'row validation flags' => static fn (): mixed => array_column($plan196()->handoffRows(), 'validation_token_matches'),
    'row pointer flags' => static fn (): mixed => array_column($plan196()->handoffRows(), 'pointer_map_carried_forward'),
    'row freeblock flags' => static fn (): mixed => array_column($plan196()->handoffRows(), 'freeblock_receipt_carried_forward'),
    'row chain flags' => static fn (): mixed => array_column($plan196()->handoffRows(), 'handoff_chain_valid'),
    'row fenced flags' => static fn (): mixed => array_column($plan196()->handoffRows(), 'fenced_pages_blocked_from_next_writer'),
    'row high water pages' => static fn (): mixed => array_column($plan196()->handoffRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan196(3)->sourceNextSummary()['handoff_row_count'],
    'batch size three next writable pages' => static fn (): mixed => $plan196(3)->nextWritablePages(),
    'batch size three writable batches' => static fn (): mixed => array_column($plan196(3)->handoffRows(), 'next_writable_pages'),
    'batch size three source token count' => static fn (): mixed => count($plan196(3)->sourceNextTokens()),
    'dependency closure' => static fn (): mixed => $plan196()->sourceNextSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan196()->sourceNextSummary()['non_overlap'], 'does not repeat next192'),
    'base action' => static fn (): mixed => $plan196()->basePlan->toArray()['action'],
    'base validation rows' => static fn (): mixed => $plan196()->basePlan->validationSummary()['validation_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message196(static fn () => $plan196(0)),
];

$expected196 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next196',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next196-ready',
    'handoff row count' => 3,
    'next writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'summary next writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'reader pages' => [1, 2, 3, 105, 106, 107, 108],
    'handoff errors' => [],
    'summary handoff errors' => [],
    'all validation tokens match' => true,
    'all pointer maps carried forward' => true,
    'all freeblock receipts carried forward' => true,
    'all handoff chains valid' => true,
    'all fenced pages blocked' => true,
    'source token count' => 3,
    'source token lengths' => [64, 64, 64],
    'source signature length' => 64,
    'writer token length' => 64,
    'validation signature length' => 64,
    'first row validated pages' => [1, 2, 3],
    'first row next writable pages' => [1, 2, 3],
    'first row previous token' => null,
    'first row pointer maps' => [2],
    'first row payload pages' => [3],
    'second row validated pages' => [105, 106],
    'second row next writable pages' => [1, 2, 3, 105, 106],
    'second row previous token length' => 64,
    'second row pointer maps' => [105],
    'second row payload pages' => [106],
    'third row validated pages' => [107, 108],
    'third row next writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'third row previous token length' => 64,
    'third row pointer maps' => [105],
    'third row payload pages' => [107, 108],
    'row states' => ['next-writer-source-ready', 'next-writer-source-ready', 'next-writer-source-ready'],
    'row validation flags' => [true, true, true],
    'row pointer flags' => [true, true, true],
    'row freeblock flags' => [true, true, true],
    'row chain flags' => [true, true, true],
    'row fenced flags' => [true, true, true],
    'row high water pages' => [3, 106, 108],
    'batch size three row count' => 2,
    'batch size three next writable pages' => [1, 2, 3, 105, 106, 107, 108],
    'batch size three writable batches' => [[1, 2, 3, 105], [1, 2, 3, 105, 106, 107, 108]],
    'batch size three source token count' => 2,
    'dependency closure' => 'no new support component needed; next196 reuses next192 reader validation, checkpoint tokens, pointer-map ordering, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next192',
    'base validation rows' => 3,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases196 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next196 ' . $name] = static function (TestRunner $t) use ($callback, $expected196, $name): void {
        $t->same($expected196[$name], $callback());
    };
}

foreach (range(1, 60) as $index) {
    $tests['btree vacuum pointermap freeblock current source next196 source-next invariant ' . $index] = static function (TestRunner $t) use ($plan196): void {
        $plan = $plan196();
        $summary = $plan->sourceNextSummary();

        $t->same([], $plan->handoffErrors());
        $t->same([1, 2, 3, 105, 106, 107, 108], $plan->nextWritablePages());
        $t->same([true, true, true], array_column($plan->handoffRows(), 'validation_token_matches'));
        $t->same([true, true, true], array_column($plan->handoffRows(), 'pointer_map_carried_forward'));
        $t->same([true, true, true], array_column($plan->handoffRows(), 'freeblock_receipt_carried_forward'));
        $t->same([true, true, true], array_column($plan->handoffRows(), 'handoff_chain_valid'));
        $t->same([true, true, true], array_column($plan->handoffRows(), 'fenced_pages_blocked_from_next_writer'));
        $t->same([64, 64, 64], array_map('strlen', $plan->sourceNextTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next196-ready', $summary['status']);
    };
}

return $tests;
