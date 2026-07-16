<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage225 = static function (int $pageCount): string {
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

$putPointerMapEntry225 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database225 = static function () use ($makeFirstPage225, $putPointerMapEntry225): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage225(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next225', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry225($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan225 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database225;

    $database = $database225();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafReadWindowFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next225-current-readback-', 50),
        3,
        true,
        $batchSize,
    );
};

$message225 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases225 = [
    'action label' => static fn (): mixed => $plan225()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan225()->readSummary()['status'],
    'read row count' => static fn (): mixed => $plan225()->readSummary()['read_row_count'],
    'read pages' => static fn (): mixed => $plan225()->readPages(),
    'summary read pages' => static fn (): mixed => $plan225()->readSummary()['read_pages'],
    'unique read pages' => static fn (): mixed => $plan225()->readSummary()['unique_read_pages'],
    'pointer map read pages' => static fn (): mixed => $plan225()->pointerMapReadPages(),
    'payload read pages' => static fn (): mixed => $plan225()->payloadReadPages(),
    'read pages match write pages' => static fn (): mixed => $plan225()->readSummary()['read_pages_match_write_pages'],
    'unique pages match writes' => static fn (): mixed => $plan225()->readSummary()['unique_read_pages_match_unique_write_pages'],
    'pointer map reads match writes' => static fn (): mixed => $plan225()->readSummary()['pointer_map_reads_match_writes'],
    'payload reads match writes' => static fn (): mixed => $plan225()->readSummary()['payload_reads_match_writes'],
    'read errors' => static fn (): mixed => $plan225()->readErrors(),
    'summary read errors' => static fn (): mixed => $plan225()->readSummary()['read_errors'],
    'all write tokens match' => static fn (): mixed => $plan225()->readSummary()['all_write_tokens_match'],
    'all current source tokens match' => static fn (): mixed => $plan225()->readSummary()['all_current_source_tokens_match'],
    'all pointer maps before payload' => static fn (): mixed => $plan225()->readSummary()['all_pointer_maps_read_before_payload'],
    'all duplicate rewrites preserved' => static fn (): mixed => $plan225()->readSummary()['all_duplicate_rewrites_preserved'],
    'all tail pages excluded' => static fn (): mixed => $plan225()->readSummary()['all_tail_pages_excluded_from_read'],
    'all read offsets contiguous' => static fn (): mixed => $plan225()->readSummary()['all_read_offsets_contiguous'],
    'duplicate rewrite pages' => static fn (): mixed => $plan225()->readSummary()['duplicate_rewrite_pages'],
    'read token count' => static fn (): mixed => count($plan225()->readTokens()),
    'read token lengths' => static fn (): mixed => array_map('strlen', $plan225()->readTokens()),
    'read signature length' => static fn (): mixed => strlen($plan225()->readSummary()['read_signature']),
    'current source token length' => static fn (): mixed => strlen($plan225()->readSummary()['current_source_next225_token']),
    'read ordinals' => static fn (): mixed => array_column($plan225()->readRows(), 'read_ordinal'),
    'source write ordinals' => static fn (): mixed => array_column($plan225()->readRows(), 'source_write_ordinal'),
    'read channels' => static fn (): mixed => array_column($plan225()->readRows(), 'read_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan225()->readRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan225()->readRows(), 'byte_length'),
    'duplicate rewrite flags' => static fn (): mixed => array_column($plan225()->readRows(), 'duplicate_rewrite_read'),
    'tail exclusion flags' => static fn (): mixed => array_column($plan225()->readRows(), 'tail_page_excluded_from_read'),
    'write token flags' => static fn (): mixed => array_column($plan225()->readRows(), 'write_token_matches'),
    'current source token flags' => static fn (): mixed => array_column($plan225()->readRows(), 'current_source_token_matches'),
    'read chain flags' => static fn (): mixed => array_column($plan225()->readRows(), 'read_chain_valid'),
    'read offset flags' => static fn (): mixed => array_column($plan225()->readRows(), 'read_offset_contiguous'),
    'read states' => static fn (): mixed => array_column($plan225()->readRows(), 'read_state'),
    'first read visible pages' => static fn (): mixed => $plan225()->readRows()[0]['read_visible_pages'],
    'third read visible pages' => static fn (): mixed => $plan225()->readRows()[2]['read_visible_pages'],
    'last read visible pages' => static fn (): mixed => $plan225()->readRows()[6]['read_visible_pages'],
    'first previous read token' => static fn (): mixed => $plan225()->readRows()[0]['previous_read_token'],
    'second previous read token length' => static fn (): mixed => strlen((string) $plan225()->readRows()[1]['previous_read_token']),
    'batch size three read row count' => static fn (): mixed => $plan225(3)->readSummary()['read_row_count'],
    'batch size three read pages' => static fn (): mixed => $plan225(3)->readPages(),
    'batch size three source write ordinals' => static fn (): mixed => array_column($plan225(3)->readRows(), 'source_write_ordinal'),
    'dependency closure' => static fn (): mixed => $plan225()->readSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan225()->readSummary()['non_overlap'], 'does not repeat readback'),
    'base action' => static fn (): mixed => $plan225()->basePlan->toArray()['action'],
    'base read row count' => static fn (): mixed => $plan225()->basePlan->readSummary()['read_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message225(static fn () => $plan225(0)),
];

$expected225 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next225',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next225-ready',
    'read row count' => 7,
    'read pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary read pages' => [2, 105, 105, 3, 106, 107, 108],
    'unique read pages' => [2, 3, 105, 106, 107, 108],
    'pointer map read pages' => [2, 105, 105],
    'payload read pages' => [3, 106, 107, 108],
    'read pages match write pages' => true,
    'unique pages match writes' => true,
    'pointer map reads match writes' => true,
    'payload reads match writes' => true,
    'read errors' => [],
    'summary read errors' => [],
    'all write tokens match' => true,
    'all current source tokens match' => true,
    'all pointer maps before payload' => true,
    'all duplicate rewrites preserved' => true,
    'all tail pages excluded' => true,
    'all read offsets contiguous' => true,
    'duplicate rewrite pages' => [105],
    'read token count' => 7,
    'read token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'read signature length' => 64,
    'current source token length' => 64,
    'read ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source write ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'read channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'duplicate rewrite flags' => [false, false, true, false, false, false, false],
    'tail exclusion flags' => [true, true, true, true, true, true, true],
    'write token flags' => [true, true, true, true, true, true, true],
    'current source token flags' => [true, true, true, true, true, true, true],
    'read chain flags' => [true, true, true, true, true, true, true],
    'read offset flags' => [true, true, true, true, true, true, true],
    'read states' => ['current-source-next225-publication-ready', 'current-source-next225-publication-ready', 'current-source-next225-publication-ready', 'current-source-next225-publication-ready', 'current-source-next225-publication-ready', 'current-source-next225-publication-ready', 'current-source-next225-publication-ready'],
    'first read visible pages' => [2],
    'third read visible pages' => [2, 105],
    'last read visible pages' => [2, 3, 105, 106, 107, 108],
    'first previous read token' => null,
    'second previous read token length' => 64,
    'batch size three read row count' => 6,
    'batch size three read pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source write ordinals' => [1, 2, 3, 4, 5, 6],
    'dependency closure' => 'no new support component needed; next225 reuses readback current-source readback rows, token chains, pointer-map-before-payload ordering, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-readback',
    'base read row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases225 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next225 ' . $name] = static function (TestRunner $t) use ($callback, $expected225, $name): void {
        $t->same($expected225[$name], $callback());
    };
}

foreach (range(1, 72) as $index) {
    $tests['btree vacuum pointermap freeblock current source next225 readback invariant ' . $index] = static function (TestRunner $t) use ($plan225): void {
        $plan = $plan225();
        $summary = $plan->readSummary();

        $t->same([], $plan->readErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->readPages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['unique_read_pages']);
        $t->same([2, 105, 105], $plan->pointerMapReadPages());
        $t->same([3, 106, 107, 108], $plan->payloadReadPages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->readRows(), 'write_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->readRows(), 'tail_page_excluded_from_read'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->readRows(), 'read_offset_contiguous'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->readTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next225-ready', $summary['status']);
        $t->same(true, $summary['read_pages_match_write_pages']);
        $t->same(true, $summary['all_pointer_maps_read_before_payload']);
    };
}

return $tests;
