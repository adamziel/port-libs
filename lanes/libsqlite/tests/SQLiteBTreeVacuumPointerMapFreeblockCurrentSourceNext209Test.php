<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage209 = static function (int $pageCount): string {
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

$putPointerMapEntry209 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database209 = static function () use ($makeFirstPage209, $putPointerMapEntry209): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage209(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next209', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry209($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan209 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database209;

    $database = $database209();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafApplyReceiptAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next209-current-source-writer-', 50),
        3,
        true,
        $batchSize,
    );
};

$message209 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases209 = [
    'action label' => static fn (): mixed => $plan209()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan209()->writerSourceSummary()['status'],
    'source row count' => static fn (): mixed => $plan209()->writerSourceSummary()['source_row_count'],
    'writer source pages' => static fn (): mixed => $plan209()->writerSourcePages(),
    'summary writer source pages' => static fn (): mixed => $plan209()->writerSourceSummary()['writer_source_pages'],
    'writer pointer map pages' => static fn (): mixed => $plan209()->writerPointerMapPages(),
    'writer payload pages' => static fn (): mixed => $plan209()->writerPayloadPages(),
    'source matches sealed pages' => static fn (): mixed => $plan209()->writerSourceSummary()['source_matches_sealed_pages'],
    'source errors' => static fn (): mixed => $plan209()->sourceErrors(),
    'summary source errors' => static fn (): mixed => $plan209()->writerSourceSummary()['source_errors'],
    'all seal tokens match' => static fn (): mixed => $plan209()->writerSourceSummary()['all_seal_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan209()->writerSourceSummary()['all_pointer_map_sources_before_payload'],
    'all leaf freeblocks ready' => static fn (): mixed => $plan209()->writerSourceSummary()['all_leaf_freeblock_sources_ready'],
    'all tail pages fenced' => static fn (): mixed => $plan209()->writerSourceSummary()['all_tail_pages_remain_fenced'],
    'all writer chains valid' => static fn (): mixed => $plan209()->writerSourceSummary()['all_writer_source_chains_valid'],
    'source token count' => static fn (): mixed => count($plan209()->sourceTokens()),
    'source token lengths' => static fn (): mixed => array_map('strlen', $plan209()->sourceTokens()),
    'source signature length' => static fn (): mixed => strlen($plan209()->writerSourceSummary()['source_signature']),
    'next writer token length' => static fn (): mixed => strlen($plan209()->writerSourceSummary()['next_writer_current_source_token']),
    'first source channel' => static fn (): mixed => $plan209()->sourceRows()[0]['source_channel'],
    'first source pages' => static fn (): mixed => $plan209()->sourceRows()[0]['writer_source_pages'],
    'first visible pages' => static fn (): mixed => $plan209()->sourceRows()[0]['writer_visible_pages'],
    'first previous token' => static fn (): mixed => $plan209()->sourceRows()[0]['previous_writer_source_token'],
    'second source channel' => static fn (): mixed => $plan209()->sourceRows()[1]['source_channel'],
    'second source pages' => static fn (): mixed => $plan209()->sourceRows()[1]['writer_source_pages'],
    'second visible pages' => static fn (): mixed => $plan209()->sourceRows()[1]['writer_visible_pages'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan209()->sourceRows()[1]['previous_writer_source_token']),
    'third source channel' => static fn (): mixed => $plan209()->sourceRows()[2]['source_channel'],
    'third source pages' => static fn (): mixed => $plan209()->sourceRows()[2]['writer_source_pages'],
    'third visible pages' => static fn (): mixed => $plan209()->sourceRows()[2]['writer_visible_pages'],
    'fourth source channel' => static fn (): mixed => $plan209()->sourceRows()[3]['source_channel'],
    'fourth source pages' => static fn (): mixed => $plan209()->sourceRows()[3]['writer_source_pages'],
    'fourth visible pages' => static fn (): mixed => $plan209()->sourceRows()[3]['writer_visible_pages'],
    'fifth source channel' => static fn (): mixed => $plan209()->sourceRows()[4]['source_channel'],
    'fifth source pages' => static fn (): mixed => $plan209()->sourceRows()[4]['writer_source_pages'],
    'fifth visible pages' => static fn (): mixed => $plan209()->sourceRows()[4]['writer_visible_pages'],
    'sixth source channel' => static fn (): mixed => $plan209()->sourceRows()[5]['source_channel'],
    'sixth source pages' => static fn (): mixed => $plan209()->sourceRows()[5]['writer_source_pages'],
    'sixth visible pages' => static fn (): mixed => $plan209()->sourceRows()[5]['writer_visible_pages'],
    'source ordinals' => static fn (): mixed => array_column($plan209()->sourceRows(), 'source_ordinal'),
    'row states' => static fn (): mixed => array_column($plan209()->sourceRows(), 'writer_source_state'),
    'row seal token flags' => static fn (): mixed => array_column($plan209()->sourceRows(), 'seal_token_matches'),
    'row freeblock flags' => static fn (): mixed => array_column($plan209()->sourceRows(), 'leaf_freeblock_source_ready'),
    'row tail fence flags' => static fn (): mixed => array_column($plan209()->sourceRows(), 'tail_pages_remain_fenced'),
    'row chain flags' => static fn (): mixed => array_column($plan209()->sourceRows(), 'writer_source_chain_valid'),
    'row high water pages' => static fn (): mixed => array_column($plan209()->sourceRows(), 'high_water_page'),
    'batch size three row count' => static fn (): mixed => $plan209(3)->writerSourceSummary()['source_row_count'],
    'batch size three source pages' => static fn (): mixed => $plan209(3)->writerSourcePages(),
    'batch size three writer batches' => static fn (): mixed => array_column($plan209(3)->sourceRows(), 'writer_source_pages'),
    'batch size three token count' => static fn (): mixed => count($plan209(3)->sourceTokens()),
    'dependency closure' => static fn (): mixed => $plan209()->writerSourceSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan209()->writerSourceSummary()['non_overlap'], 'does not repeat next206'),
    'base action' => static fn (): mixed => $plan209()->basePlan->toArray()['action'],
    'base seal rows' => static fn (): mixed => $plan209()->basePlan->sealedCurrentSourceSummary()['seal_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message209(static fn () => $plan209(0)),
];

$expected209 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next209',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next209-ready',
    'source row count' => 6,
    'writer source pages' => [2, 3, 105, 106, 107, 108],
    'summary writer source pages' => [2, 3, 105, 106, 107, 108],
    'writer pointer map pages' => [2, 105],
    'writer payload pages' => [3, 106, 107, 108],
    'source matches sealed pages' => true,
    'source errors' => [],
    'summary source errors' => [],
    'all seal tokens match' => true,
    'all pointer maps before payload' => true,
    'all leaf freeblocks ready' => true,
    'all tail pages fenced' => true,
    'all writer chains valid' => true,
    'source token count' => 6,
    'source token lengths' => [64, 64, 64, 64, 64, 64],
    'source signature length' => 64,
    'next writer token length' => 64,
    'first source channel' => 'pointer-map',
    'first source pages' => [2],
    'first visible pages' => [2],
    'first previous token' => null,
    'second source channel' => 'payload',
    'second source pages' => [3],
    'second visible pages' => [2, 3],
    'second previous token length' => 64,
    'third source channel' => 'pointer-map',
    'third source pages' => [105],
    'third visible pages' => [2, 3, 105],
    'fourth source channel' => 'payload',
    'fourth source pages' => [106],
    'fourth visible pages' => [2, 3, 105, 106],
    'fifth source channel' => 'pointer-map',
    'fifth source pages' => [105],
    'fifth visible pages' => [2, 3, 105, 106],
    'sixth source channel' => 'payload',
    'sixth source pages' => [107, 108],
    'sixth visible pages' => [2, 3, 105, 106, 107, 108],
    'source ordinals' => [1, 2, 3, 4, 5, 6],
    'row states' => ['current-source-writer-ready', 'current-source-writer-ready', 'current-source-writer-ready', 'current-source-writer-ready', 'current-source-writer-ready', 'current-source-writer-ready'],
    'row seal token flags' => [true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true],
    'row chain flags' => [true, true, true, true, true, true],
    'row high water pages' => [3, 3, 106, 106, 108, 108],
    'batch size three row count' => 4,
    'batch size three source pages' => [2, 3, 105, 106, 107, 108],
    'batch size three writer batches' => [[2], [3], [105], [106, 107, 108]],
    'batch size three token count' => 4,
    'dependency closure' => 'no new support component needed; next209 reuses next206 sealed current-source rows, leaf freeblock receipts, pointer-map pages, and fenced-tail metadata',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next206',
    'base seal rows' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases209 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next209 ' . $name] = static function (TestRunner $t) use ($callback, $expected209, $name): void {
        $t->same($expected209[$name], $callback());
    };
}

foreach (range(1, 82) as $index) {
    $tests['btree vacuum pointermap freeblock current source next209 writer invariant ' . $index] = static function (TestRunner $t) use ($plan209): void {
        $plan = $plan209();
        $summary = $plan->writerSourceSummary();

        $t->same([], $plan->sourceErrors());
        $t->same([2, 3, 105, 106, 107, 108], $plan->writerSourcePages());
        $t->same([2, 105], $plan->writerPointerMapPages());
        $t->same([3, 106, 107, 108], $plan->writerPayloadPages());
        $t->same([1, 2, 3, 4, 5, 6], array_column($plan->sourceRows(), 'source_ordinal'));
        $t->same([true, true, true, true, true, true], array_column($plan->sourceRows(), 'seal_token_matches'));
        $t->same([true, true, true, true, true, true], array_column($plan->sourceRows(), 'leaf_freeblock_source_ready'));
        $t->same([true, true, true, true, true, true], array_column($plan->sourceRows(), 'tail_pages_remain_fenced'));
        $t->same([64, 64, 64, 64, 64, 64], array_map('strlen', $plan->sourceTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next209-ready', $summary['status']);
        $t->same(true, $summary['source_matches_sealed_pages']);
    };
}

return $tests;
