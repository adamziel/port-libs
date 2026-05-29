<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage205 = static function (int $pageCount): string {
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

$putPointerMapEntry205 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database205 = static function () use ($makeFirstPage205, $putPointerMapEntry205): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage205(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next205', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(85 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry205($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan205 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database205;

    $database = $database205();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFreeblockHandoffFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next205-current-source-cursor-', 50),
        3,
        true,
        $batchSize,
    );
};

$message205 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases205 = [
    'action label' => static fn (): mixed => $plan205()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['status'],
    'freeblock errors' => static fn (): mixed => $plan205()->freeblockErrors(),
    'summary freeblock errors' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['freeblock_errors'],
    'freeblock row count' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['freeblock_row_count'],
    'required pointer map pages' => static fn (): mixed => $plan205()->requiredPointerMapPages(),
    'summary required pointer map pages' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['required_pointer_map_pages'],
    'reusable leaf freeblock pages' => static fn (): mixed => $plan205()->reusableLeafFreeblockPages(),
    'summary reusable leaf freeblock pages' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['reusable_leaf_freeblock_pages'],
    'reusable overflow payload pages' => static fn (): mixed => $plan205()->reusableOverflowPayloadPages(),
    'summary reusable overflow payload pages' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['reusable_overflow_payload_pages'],
    'handoff source pages' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['handoff_source_pages'],
    'handoff token count' => static fn (): mixed => count($plan205()->freeblockHandoffSummary()['handoff_tokens']),
    'handoff token lengths' => static fn (): mixed => array_map('strlen', $plan205()->freeblockHandoffSummary()['handoff_tokens']),
    'handoff signature length' => static fn (): mixed => strlen($plan205()->freeblockHandoffSummary()['handoff_signature']),
    'next writer freeblock token length' => static fn (): mixed => strlen($plan205()->freeblockHandoffSummary()['next_writer_freeblock_token']),
    'all pointer maps ready' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['all_pointer_maps_ready'],
    'all leaf freeblocks reusable' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['all_leaf_freeblocks_reusable'],
    'all overflow payloads replayable' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['all_overflow_payloads_replayable'],
    'all fenced tail pages blocked' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['all_fenced_tail_pages_blocked'],
    'all cursor tokens chained' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['all_cursor_tokens_chained'],
    'dependencies' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['dependencies'],
    'dependency closure' => static fn (): mixed => $plan205()->freeblockHandoffSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan205()->freeblockHandoffSummary()['non_overlap'], 'does not repeat next203'),
    'row pages' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'page_number'),
    'row cursor indexes' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'cursor_index'),
    'row batch indexes' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'batch_index'),
    'row channels' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'handoff_channel'),
    'row states' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'handoff_state'),
    'row pointer flags' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'pointer_map_ready_before_payload'),
    'row leaf flags' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'leaf_freeblock_reusable'),
    'row overflow flags' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'overflow_payload_replayable'),
    'row fenced flags' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'fenced_tail_blocked'),
    'row chain flags' => static fn (): mixed => array_column($plan205()->freeblockRows(), 'cursor_token_chained'),
    'row previous handoff tokens' => static fn (): mixed => array_map(static fn (mixed $value): mixed => $value === null ? null : strlen((string) $value), array_column($plan205()->freeblockRows(), 'previous_freeblock_handoff_token')),
    'row handoff token lengths' => static fn (): mixed => array_map('strlen', array_column($plan205()->freeblockRows(), 'freeblock_handoff_token')),
    'base action label' => static fn (): mixed => $plan205()->basePlan->toArray()['action'],
    'base current source pages' => static fn (): mixed => $plan205()->basePlan->currentSourcePages(),
    'base cursor row count' => static fn (): mixed => $plan205()->basePlan->currentSourceCursorSummary()['cursor_row_count'],
    'batch size three row count' => static fn (): mixed => $plan205(3)->freeblockHandoffSummary()['freeblock_row_count'],
    'batch size three row channels' => static fn (): mixed => array_column($plan205(3)->freeblockRows(), 'handoff_channel'),
    'batch size three handoff source pages' => static fn (): mixed => $plan205(3)->freeblockHandoffSummary()['handoff_source_pages'],
    'bad batch size rejected' => static fn (): mixed => $message205(static fn () => $plan205(0)),
];

$expected205 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next205',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next205-ready',
    'freeblock errors' => [],
    'summary freeblock errors' => [],
    'freeblock row count' => 7,
    'required pointer map pages' => [2, 105, 105],
    'summary required pointer map pages' => [2, 105, 105],
    'reusable leaf freeblock pages' => [3],
    'summary reusable leaf freeblock pages' => [3],
    'reusable overflow payload pages' => [106, 107, 108],
    'summary reusable overflow payload pages' => [106, 107, 108],
    'handoff source pages' => [2, 3, 105, 106, 107, 108],
    'handoff token count' => 7,
    'handoff token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'handoff signature length' => 64,
    'next writer freeblock token length' => 64,
    'all pointer maps ready' => true,
    'all leaf freeblocks reusable' => true,
    'all overflow payloads replayable' => true,
    'all fenced tail pages blocked' => true,
    'all cursor tokens chained' => true,
    'dependencies' => ['sqlite-btree-vacuum-pointermap-freeblock-current-source-next203', 'sqlite-current-source-next205'],
    'dependency closure' => 'no new support component needed; next205 reuses next203 cursor admission, pointer-map dependency pages, leaf freeblock receipts, and fenced-tail metadata',
    'non overlap' => true,
    'row pages' => [2, 3, 105, 106, 105, 107, 108],
    'row cursor indexes' => [0, 0, 1, 1, 2, 2, 2],
    'row batch indexes' => [0, 0, 1, 1, 2, 2, 2],
    'row channels' => ['pointer-map-dependency', 'leaf-freeblock', 'pointer-map-dependency', 'overflow-payload', 'pointer-map-dependency', 'overflow-payload', 'overflow-payload'],
    'row states' => ['current-source-handoff-ready', 'current-source-handoff-ready', 'current-source-handoff-ready', 'current-source-handoff-ready', 'current-source-handoff-ready', 'current-source-handoff-ready', 'current-source-handoff-ready'],
    'row pointer flags' => [true, true, true, true, true, true, true],
    'row leaf flags' => [true, true, true, true, true, true, true],
    'row overflow flags' => [true, true, true, true, true, true, true],
    'row fenced flags' => [true, true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true, true],
    'row previous handoff tokens' => [null, 64, 64, 64, 64, 64, 64],
    'row handoff token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'base action label' => 'btree-vacuum-pointermap-freeblock-current-source-next203',
    'base current source pages' => [2, 3, 105, 106, 107, 108],
    'base cursor row count' => 3,
    'batch size three row count' => 6,
    'batch size three row channels' => ['pointer-map-dependency', 'leaf-freeblock', 'pointer-map-dependency', 'overflow-payload', 'overflow-payload', 'overflow-payload'],
    'batch size three handoff source pages' => [2, 3, 105, 106, 107, 108],
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases205 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next205 ' . $name] = static function (TestRunner $t) use ($callback, $expected205, $name): void {
        $t->same($expected205[$name], $callback());
    };
}

foreach (range(1, 72) as $index) {
    $tests['btree vacuum pointermap freeblock current source next205 handoff invariant ' . $index] = static function (TestRunner $t) use ($plan205): void {
        $plan = $plan205();
        $summary = $plan->freeblockHandoffSummary();

        $t->same([], $plan->freeblockErrors());
        $t->same([2, 105, 105], $plan->requiredPointerMapPages());
        $t->same([3], $plan->reusableLeafFreeblockPages());
        $t->same([106, 107, 108], $plan->reusableOverflowPayloadPages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['handoff_source_pages']);
        $t->same([true, true, true, true, true, true, true], array_column($plan->freeblockRows(), 'pointer_map_ready_before_payload'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->freeblockRows(), 'fenced_tail_blocked'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', array_column($plan->freeblockRows(), 'freeblock_handoff_token')));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next205-ready', $summary['status']);
    };
}

return $tests;
