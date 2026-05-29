<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage207 = static function (int $pageCount): string {
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

$putPointerMapEntry207 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database207 = static function () use ($makeFirstPage207, $putPointerMapEntry207): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage207(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next207', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry207($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan207 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database207;

    $database = $database207();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafPageSealFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next207-current-source-window-', 50),
        3,
        true,
        $batchSize,
    );
};

$message207 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases207 = [
    'action label' => static fn (): mixed => $plan207()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan207()->writerWindowSummary()['status'],
    'window row count' => static fn (): mixed => $plan207()->writerWindowSummary()['writer_window_row_count'],
    'admitted writer pages' => static fn (): mixed => $plan207()->admittedWriterPages(),
    'summary admitted writer pages' => static fn (): mixed => $plan207()->writerWindowSummary()['admitted_writer_pages'],
    'admitted pointer map pages' => static fn (): mixed => $plan207()->admittedPointerMapWriterPages(),
    'admitted payload pages' => static fn (): mixed => $plan207()->admittedPayloadWriterPages(),
    'sealed pages passthrough' => static fn (): mixed => $plan207()->writerWindowSummary()['sealed_pages'],
    'window errors' => static fn (): mixed => $plan207()->writerWindowErrors(),
    'summary window errors' => static fn (): mixed => $plan207()->writerWindowSummary()['writer_window_errors'],
    'all seal tokens match' => static fn (): mixed => $plan207()->writerWindowSummary()['all_seal_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan207()->writerWindowSummary()['all_pointer_maps_admitted_before_payload'],
    'all leaf freeblocks admitted' => static fn (): mixed => $plan207()->writerWindowSummary()['all_leaf_freeblocks_admitted'],
    'all tail pages fenced' => static fn (): mixed => $plan207()->writerWindowSummary()['all_tail_pages_fenced'],
    'all window chains valid' => static fn (): mixed => $plan207()->writerWindowSummary()['all_window_chains_valid'],
    'window token count' => static fn (): mixed => count($plan207()->writerWindowTokens()),
    'window token lengths' => static fn (): mixed => array_map('strlen', $plan207()->writerWindowTokens()),
    'window signature length' => static fn (): mixed => strlen($plan207()->writerWindowSummary()['writer_window_signature']),
    'next writer token length' => static fn (): mixed => strlen($plan207()->writerWindowSummary()['next_writer_current_source_token']),
    'seal signature length' => static fn (): mixed => strlen($plan207()->writerWindowSummary()['seal_signature']),
    'first window channel' => static fn (): mixed => $plan207()->writerWindowRows()[0]['writer_channel'],
    'first window pages' => static fn (): mixed => $plan207()->writerWindowRows()[0]['writer_pages'],
    'first previous token' => static fn (): mixed => $plan207()->writerWindowRows()[0]['previous_writer_window_token'],
    'second window channel' => static fn (): mixed => $plan207()->writerWindowRows()[1]['writer_channel'],
    'second window pages' => static fn (): mixed => $plan207()->writerWindowRows()[1]['writer_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan207()->writerWindowRows()[1]['previous_writer_window_token']),
    'third window channel' => static fn (): mixed => $plan207()->writerWindowRows()[2]['writer_channel'],
    'third window pages' => static fn (): mixed => $plan207()->writerWindowRows()[2]['writer_pages'],
    'fourth window channel' => static fn (): mixed => $plan207()->writerWindowRows()[3]['writer_channel'],
    'fourth window pages' => static fn (): mixed => $plan207()->writerWindowRows()[3]['writer_pages'],
    'fifth window channel' => static fn (): mixed => $plan207()->writerWindowRows()[4]['writer_channel'],
    'fifth window pages' => static fn (): mixed => $plan207()->writerWindowRows()[4]['writer_pages'],
    'sixth window channel' => static fn (): mixed => $plan207()->writerWindowRows()[5]['writer_channel'],
    'sixth window pages' => static fn (): mixed => $plan207()->writerWindowRows()[5]['writer_pages'],
    'window ordinals' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'writer_ordinal'),
    'seal ordinals' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'seal_ordinal'),
    'window states' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'writer_window_state'),
    'seal token flags' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'seal_token_matches'),
    'leaf freeblock flags' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'leaf_freeblock_admitted'),
    'tail fence flags' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'tail_pages_fenced'),
    'window chain flags' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'writer_chain_valid'),
    'window high water pages' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'high_water_page'),
    'admitted pages by row' => static fn (): mixed => array_column($plan207()->writerWindowRows(), 'admitted_pages_after_window'),
    'batch size three row count' => static fn (): mixed => $plan207(3)->writerWindowSummary()['writer_window_row_count'],
    'batch size three admitted pages' => static fn (): mixed => $plan207(3)->admittedWriterPages(),
    'batch size three window pages' => static fn (): mixed => array_column($plan207(3)->writerWindowRows(), 'writer_pages'),
    'batch size three token count' => static fn (): mixed => count($plan207(3)->writerWindowTokens()),
    'dependency closure' => static fn (): mixed => $plan207()->writerWindowSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan207()->writerWindowSummary()['non_overlap'], 'does not repeat next206'),
    'base action' => static fn (): mixed => $plan207()->basePlan->toArray()['action'],
    'base seal rows' => static fn (): mixed => $plan207()->basePlan->sealedCurrentSourceSummary()['seal_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message207(static fn () => $plan207(0)),
];

$expected207 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next207',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next207-ready',
    'window row count' => 6,
    'admitted writer pages' => [2, 3, 105, 106, 107, 108],
    'summary admitted writer pages' => [2, 3, 105, 106, 107, 108],
    'admitted pointer map pages' => [2, 105],
    'admitted payload pages' => [3, 106, 107, 108],
    'sealed pages passthrough' => [2, 3, 105, 106, 107, 108],
    'window errors' => [],
    'summary window errors' => [],
    'all seal tokens match' => true,
    'all pointer maps before payload' => true,
    'all leaf freeblocks admitted' => true,
    'all tail pages fenced' => true,
    'all window chains valid' => true,
    'window token count' => 6,
    'window token lengths' => [64, 64, 64, 64, 64, 64],
    'window signature length' => 64,
    'next writer token length' => 64,
    'seal signature length' => 64,
    'first window channel' => 'pointer-map',
    'first window pages' => [2],
    'first previous token' => null,
    'second window channel' => 'payload',
    'second window pages' => [3],
    'second previous token length' => 64,
    'third window channel' => 'pointer-map',
    'third window pages' => [105],
    'fourth window channel' => 'payload',
    'fourth window pages' => [106],
    'fifth window channel' => 'pointer-map',
    'fifth window pages' => [105],
    'sixth window channel' => 'payload',
    'sixth window pages' => [107, 108],
    'window ordinals' => [1, 2, 3, 4, 5, 6],
    'seal ordinals' => [1, 2, 3, 4, 5, 6],
    'window states' => ['current-source-writer-window-ready', 'current-source-writer-window-ready', 'current-source-writer-window-ready', 'current-source-writer-window-ready', 'current-source-writer-window-ready', 'current-source-writer-window-ready'],
    'seal token flags' => [true, true, true, true, true, true],
    'leaf freeblock flags' => [true, true, true, true, true, true],
    'tail fence flags' => [true, true, true, true, true, true],
    'window chain flags' => [true, true, true, true, true, true],
    'window high water pages' => [3, 3, 106, 106, 108, 108],
    'admitted pages by row' => [[2], [2, 3], [2, 3, 105], [2, 3, 105, 106], [2, 3, 105, 106], [2, 3, 105, 106, 107, 108]],
    'batch size three row count' => 4,
    'batch size three admitted pages' => [2, 3, 105, 106, 107, 108],
    'batch size three window pages' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next207 reuses next206 sealed pointer-map and payload/freeblock rows to admit a deterministic writer window',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next206',
    'base seal rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases207 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next207 ' . $name] = static function (TestRunner $t) use ($callback, $expected207, $name): void {
        $t->same($expected207[$name], $callback());
    };
}

foreach (range(1, 74) as $index) {
    $tests['btree vacuum pointermap freeblock current source next207 writer window invariant ' . $index] = static function (TestRunner $t) use ($plan207): void {
        $plan = $plan207();
        $summary = $plan->writerWindowSummary();

        $t->same([], $plan->writerWindowErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->admittedWriterPages());
        $t->same([2, 105], $plan->admittedPointerMapWriterPages());
        $t->same([3, 106, 107, 108], $plan->admittedPayloadWriterPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->writerWindowRows(), 'writer_ordinal'));
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->writerWindowRows(), 'seal_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->writerWindowRows(), 'seal_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->writerWindowRows(), 'leaf_freeblock_admitted'));
        $t->same([true, true, true, true, true, true], array_column($plan->writerWindowRows(), 'tail_pages_fenced'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->writerWindowTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next207-ready', $summary['status']);
    };
}

return $tests;
