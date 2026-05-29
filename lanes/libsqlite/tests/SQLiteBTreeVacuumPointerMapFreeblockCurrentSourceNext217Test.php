<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage217 = static function (int $pageCount): string {
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

$putPointerMapEntry217 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database217 = static function () use ($makeFirstPage217, $putPointerMapEntry217): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage217(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next217', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry217($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan217 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database217;

    $database = $database217();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafWriteAdmissionAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next217-current-source-write-', 50),
        3,
        true,
        $batchSize,
    );
};

$message217 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases217 = [
    'action label' => static fn (): mixed => $plan217()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan217()->writeSummary()['status'],
    'write row count' => static fn (): mixed => $plan217()->writeSummary()['write_row_count'],
    'write pages' => static fn (): mixed => $plan217()->writePages(),
    'summary write pages' => static fn (): mixed => $plan217()->writeSummary()['write_pages'],
    'unique write pages' => static fn (): mixed => $plan217()->writeSummary()['unique_write_pages'],
    'pointer map write pages' => static fn (): mixed => $plan217()->pointerMapWritePages(),
    'payload write pages' => static fn (): mixed => $plan217()->payloadWritePages(),
    'leaf freeblock pages' => static fn (): mixed => $plan217()->leafFreeblockWritePages(),
    'overflow write pages' => static fn (): mixed => $plan217()->overflowWritePages(),
    'write pages match apply pages' => static fn (): mixed => $plan217()->writeSummary()['write_pages_match_apply_pages'],
    'pointer map pages match apply pages' => static fn (): mixed => $plan217()->writeSummary()['pointer_map_writes_match_apply_pages'],
    'payload pages match apply pages' => static fn (): mixed => $plan217()->writeSummary()['payload_writes_match_apply_pages'],
    'write errors' => static fn (): mixed => $plan217()->writeErrors(),
    'summary write errors' => static fn (): mixed => $plan217()->writeSummary()['write_errors'],
    'all pointer maps before payload' => static fn (): mixed => $plan217()->writeSummary()['all_pointer_maps_written_before_payload'],
    'all source tokens match' => static fn (): mixed => $plan217()->writeSummary()['all_source_apply_tokens_match'],
    'all write chains valid' => static fn (): mixed => $plan217()->writeSummary()['all_write_chains_valid'],
    'all tail pages excluded' => static fn (): mixed => $plan217()->writeSummary()['all_tail_pages_excluded'],
    'all freeblock receipts carried' => static fn (): mixed => $plan217()->writeSummary()['all_freeblock_receipts_carried'],
    'all write offsets contiguous' => static fn (): mixed => $plan217()->writeSummary()['all_write_offsets_contiguous'],
    'write token count' => static fn (): mixed => count($plan217()->writeTokens()),
    'write token lengths' => static fn (): mixed => array_map('strlen', $plan217()->writeTokens()),
    'write signature length' => static fn (): mixed => strlen($plan217()->writeSummary()['write_signature']),
    'current source token length' => static fn (): mixed => strlen($plan217()->writeSummary()['current_source_next217_token']),
    'write ordinals' => static fn (): mixed => array_column($plan217()->writeRows(), 'write_ordinal'),
    'source apply ordinals' => static fn (): mixed => array_column($plan217()->writeRows(), 'source_apply_ordinal'),
    'write channels' => static fn (): mixed => array_column($plan217()->writeRows(), 'write_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan217()->writeRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan217()->writeRows(), 'byte_length'),
    'sync groups' => static fn (): mixed => array_column($plan217()->writeRows(), 'sync_group'),
    'rewrite flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'rewrites_existing_page'),
    'leaf receipt flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'leaf_freeblock_receipt_carried'),
    'overflow flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'overflow_payload_write'),
    'tail exclusion flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'tail_page_excluded_from_write'),
    'source token flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'source_apply_token_matches'),
    'write chain flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'write_chain_valid'),
    'write offset flags' => static fn (): mixed => array_column($plan217()->writeRows(), 'write_offset_contiguous'),
    'write states' => static fn (): mixed => array_column($plan217()->writeRows(), 'write_state'),
    'first write visible pages' => static fn (): mixed => $plan217()->writeRows()[0]['written_visible_pages'],
    'third write visible pages' => static fn (): mixed => $plan217()->writeRows()[2]['written_visible_pages'],
    'last write visible pages' => static fn (): mixed => $plan217()->writeRows()[6]['written_visible_pages'],
    'first previous write token' => static fn (): mixed => $plan217()->writeRows()[0]['previous_write_token'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan217()->writeRows()[1]['previous_write_token']),
    'batch size three write row count' => static fn (): mixed => $plan217(3)->writeSummary()['write_row_count'],
    'batch size three write pages' => static fn (): mixed => $plan217(3)->writePages(),
    'batch size three source apply ordinals' => static fn (): mixed => array_column($plan217(3)->writeRows(), 'source_apply_ordinal'),
    'dependency closure' => static fn (): mixed => $plan217()->writeSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan217()->writeSummary()['non_overlap'], 'does not repeat next212'),
    'base action' => static fn (): mixed => $plan217()->basePlan->toArray()['action'],
    'base apply row count' => static fn (): mixed => $plan217()->basePlan->applySummary()['apply_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message217(static fn () => $plan217(0)),
];

$expected217 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next217',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next217-ready',
    'write row count' => 7,
    'write pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary write pages' => [2, 105, 105, 3, 106, 107, 108],
    'unique write pages' => [2, 3, 105, 106, 107, 108],
    'pointer map write pages' => [2, 105, 105],
    'payload write pages' => [3, 106, 107, 108],
    'leaf freeblock pages' => [3],
    'overflow write pages' => [106, 107, 108],
    'write pages match apply pages' => true,
    'pointer map pages match apply pages' => true,
    'payload pages match apply pages' => true,
    'write errors' => [],
    'summary write errors' => [],
    'all pointer maps before payload' => true,
    'all source tokens match' => true,
    'all write chains valid' => true,
    'all tail pages excluded' => true,
    'all freeblock receipts carried' => true,
    'all write offsets contiguous' => true,
    'write token count' => 7,
    'write token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'write signature length' => 64,
    'current source token length' => 64,
    'write ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source apply ordinals' => [1, 3, 5, 2, 4, 6, 6],
    'write channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'sync groups' => ['pointer-map-before-payload', 'pointer-map-before-payload', 'pointer-map-before-payload', 'payload-after-pointer-map', 'payload-after-pointer-map', 'payload-after-pointer-map', 'payload-after-pointer-map'],
    'rewrite flags' => [false, false, true, false, false, false, false],
    'leaf receipt flags' => [false, false, false, true, false, false, false],
    'overflow flags' => [false, false, false, false, true, true, true],
    'tail exclusion flags' => [true, true, true, true, true, true, true],
    'source token flags' => [true, true, true, true, true, true, true],
    'write chain flags' => [true, true, true, true, true, true, true],
    'write offset flags' => [true, true, true, true, true, true, true],
    'write states' => ['current-source-page-write-ready', 'current-source-page-write-ready', 'current-source-page-write-ready', 'current-source-page-write-ready', 'current-source-page-write-ready', 'current-source-page-write-ready', 'current-source-page-write-ready'],
    'first write visible pages' => [2],
    'third write visible pages' => [2, 105],
    'last write visible pages' => [2, 3, 105, 106, 107, 108],
    'first previous write token' => null,
    'second previous token length' => 64,
    'batch size three write row count' => 6,
    'batch size three write pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source apply ordinals' => [1, 3, 2, 4, 4, 4],
    'dependency closure' => 'no new support component needed; next217 reuses next212 current-source apply rows, pointer-map apply pages, leaf freeblock receipts, and fenced-tail guards',
    'non overlap' => true,
    'base action' => 'btree-vacuum-pointermap-freeblock-current-source-next212',
    'base apply row count' => 6,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases217 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next217 ' . $name] = static function (TestRunner $t) use ($callback, $expected217, $name): void {
        $t->same($expected217[$name], $callback());
    };
}

foreach (range(1, 70) as $index) {
    $tests['btree vacuum pointermap freeblock current source next217 write invariant ' . $index] = static function (TestRunner $t) use ($plan217): void {
        $plan = $plan217();
        $summary = $plan->writeSummary();

        $t->same([], $plan->writeErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->writePages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['unique_write_pages']);
        $t->same([2, 105, 105], $plan->pointerMapWritePages());
        $t->same([3, 106, 107, 108], $plan->payloadWritePages());
        $t->same([true, true, true, true, true, true, true], array_column($plan->writeRows(), 'source_apply_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->writeRows(), 'tail_page_excluded_from_write'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->writeRows(), 'freeblock_receipt_carried'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->writeTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next217-ready', $summary['status']);
        $t->same(true, $summary['write_pages_match_apply_pages']);
        $t->same(true, $summary['all_pointer_maps_written_before_payload']);
    };
}

return $tests;
