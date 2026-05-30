<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage220 = static function (int $pageCount): string {
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

$putPointerMapEntry220 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database220 = static function () use ($makeFirstPage220, $putPointerMapEntry220): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage220(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_pub', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry220($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan220 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database220;

    $database = $database220();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafPublicationAuditFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('publsh-current-source-commit-', 50),
        3,
        true,
        $batchSize,
    );
};

$message220 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases220 = [
    'action label' => static fn (): mixed => $plan220()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan220()->commitSummary()['status'],
    'commit row count' => static fn (): mixed => $plan220()->commitSummary()['commit_row_count'],
    'commit pages' => static fn (): mixed => $plan220()->commitPages(),
    'summary commit pages' => static fn (): mixed => $plan220()->commitSummary()['commit_pages'],
    'unique commit pages' => static fn (): mixed => $plan220()->commitSummary()['unique_commit_pages'],
    'pointer map commit pages' => static fn (): mixed => $plan220()->pointerMapCommitPages(),
    'payload commit pages' => static fn (): mixed => $plan220()->payloadCommitPages(),
    'commit pages match write pages' => static fn (): mixed => $plan220()->commitSummary()['commit_pages_match_write_pages'],
    'unique commit pages match write pages' => static fn (): mixed => $plan220()->commitSummary()['unique_commit_pages_match_write_pages'],
    'pointer map pages match write pages' => static fn (): mixed => $plan220()->commitSummary()['pointer_map_commit_pages_match_write_pages'],
    'payload pages match write pages' => static fn (): mixed => $plan220()->commitSummary()['payload_commit_pages_match_write_pages'],
    'commit errors' => static fn (): mixed => $plan220()->commitErrors(),
    'summary commit errors' => static fn (): mixed => $plan220()->commitSummary()['commit_errors'],
    'all pointer maps before payload' => static fn (): mixed => $plan220()->commitSummary()['all_pointer_maps_committed_before_payload'],
    'all source tokens match' => static fn (): mixed => $plan220()->commitSummary()['all_source_write_tokens_match'],
    'all chains valid' => static fn (): mixed => $plan220()->commitSummary()['all_commit_chains_valid'],
    'all tail pages excluded' => static fn (): mixed => $plan220()->commitSummary()['all_tail_pages_excluded'],
    'all freeblock receipts carried' => static fn (): mixed => $plan220()->commitSummary()['all_freeblock_receipts_carried'],
    'all offsets contiguous' => static fn (): mixed => $plan220()->commitSummary()['all_commit_offsets_contiguous'],
    'rewrite commit pages' => static fn (): mixed => $plan220()->commitSummary()['rewrite_commit_pages'],
    'commit groups' => static fn (): mixed => $plan220()->commitSummary()['commit_groups'],
    'commit token count' => static fn (): mixed => count($plan220()->commitTokens()),
    'commit token lengths' => static fn (): mixed => array_map('strlen', $plan220()->commitTokens()),
    'commit signature length' => static fn (): mixed => strlen($plan220()->commitSummary()['commit_signature']),
    'current source token length' => static fn (): mixed => strlen($plan220()->commitSummary()['current_source_publication_token']),
    'commit ordinals' => static fn (): mixed => array_column($plan220()->commitRows(), 'commit_ordinal'),
    'source write ordinals' => static fn (): mixed => array_column($plan220()->commitRows(), 'source_write_ordinal'),
    'commit channels' => static fn (): mixed => array_column($plan220()->commitRows(), 'commit_channel'),
    'byte offsets' => static fn (): mixed => array_column($plan220()->commitRows(), 'byte_offset'),
    'byte lengths' => static fn (): mixed => array_column($plan220()->commitRows(), 'byte_length'),
    'row groups' => static fn (): mixed => array_column($plan220()->commitRows(), 'commit_group'),
    'rewrite flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'rewrites_existing_page'),
    'leaf receipt flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'leaf_freeblock_receipt_carried'),
    'overflow commit flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'overflow_payload_commit'),
    'tail exclusion flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'tail_page_excluded_from_commit'),
    'source token flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'source_write_token_matches'),
    'chain flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'commit_chain_valid'),
    'offset flags' => static fn (): mixed => array_column($plan220()->commitRows(), 'commit_offset_contiguous'),
    'commit states' => static fn (): mixed => array_column($plan220()->commitRows(), 'commit_state'),
    'first visible pages' => static fn (): mixed => $plan220()->commitRows()[0]['committed_visible_pages'],
    'third visible pages' => static fn (): mixed => $plan220()->commitRows()[2]['committed_visible_pages'],
    'last visible pages' => static fn (): mixed => $plan220()->commitRows()[6]['committed_visible_pages'],
    'first previous token' => static fn (): mixed => $plan220()->commitRows()[0]['previous_commit_token'],
    'second previous token length' => static fn (): mixed => strlen((string) $plan220()->commitRows()[1]['previous_commit_token']),
    'batch size three row count' => static fn (): mixed => $plan220(3)->commitSummary()['commit_row_count'],
    'batch size three pages' => static fn (): mixed => $plan220(3)->commitPages(),
    'batch size three source ordinals' => static fn (): mixed => array_column($plan220(3)->commitRows(), 'source_write_ordinal'),
    'dependency closure' => static fn (): mixed => $plan220()->commitSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan220()->commitSummary()['non_overlap'], 'does not repeat write-materialization'),
    'write action' => static fn (): mixed => $plan220()->writePlan->toArray()['action'],
    'write row count' => static fn (): mixed => $plan220()->writePlan->writeSummary()['write_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message220(static fn () => $plan220(0)),
];

$expected220 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-publication-audit',
    'summary status' => 'btree-vacuum-pointermap-freeblock-publication-audit-ready',
    'commit row count' => 7,
    'commit pages' => [2, 105, 105, 3, 106, 107, 108],
    'summary commit pages' => [2, 105, 105, 3, 106, 107, 108],
    'unique commit pages' => [2, 3, 105, 106, 107, 108],
    'pointer map commit pages' => [2, 105, 105],
    'payload commit pages' => [3, 106, 107, 108],
    'commit pages match write pages' => true,
    'unique commit pages match write pages' => true,
    'pointer map pages match write pages' => true,
    'payload pages match write pages' => true,
    'commit errors' => [],
    'summary commit errors' => [],
    'all pointer maps before payload' => true,
    'all source tokens match' => true,
    'all chains valid' => true,
    'all tail pages excluded' => true,
    'all freeblock receipts carried' => true,
    'all offsets contiguous' => true,
    'rewrite commit pages' => [105],
    'commit groups' => ['commit-pointer-map-before-payload', 'commit-payload-after-pointer-map'],
    'commit token count' => 7,
    'commit token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'commit signature length' => 64,
    'current source token length' => 64,
    'commit ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'source write ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'commit channels' => ['pointer-map', 'pointer-map', 'pointer-map', 'payload', 'payload', 'payload', 'payload'],
    'byte offsets' => [512, 53248, 53248, 1024, 53760, 54272, 54784],
    'byte lengths' => [512, 512, 512, 512, 512, 512, 512],
    'row groups' => ['commit-pointer-map-before-payload', 'commit-pointer-map-before-payload', 'commit-pointer-map-before-payload', 'commit-payload-after-pointer-map', 'commit-payload-after-pointer-map', 'commit-payload-after-pointer-map', 'commit-payload-after-pointer-map'],
    'rewrite flags' => [false, false, true, false, false, false, false],
    'leaf receipt flags' => [false, false, false, true, false, false, false],
    'overflow commit flags' => [false, false, false, false, true, true, true],
    'tail exclusion flags' => [true, true, true, true, true, true, true],
    'source token flags' => [true, true, true, true, true, true, true],
    'chain flags' => [true, true, true, true, true, true, true],
    'offset flags' => [true, true, true, true, true, true, true],
    'commit states' => ['current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready', 'current-source-page-commit-ready'],
    'first visible pages' => [2],
    'third visible pages' => [2, 105],
    'last visible pages' => [2, 3, 105, 106, 107, 108],
    'first previous token' => null,
    'second previous token length' => 64,
    'batch size three row count' => 6,
    'batch size three pages' => [2, 105, 3, 106, 107, 108],
    'batch size three source ordinals' => [1, 2, 3, 4, 5, 6],
    'dependency closure' => 'no new support component needed; publication-audit reuses write-materialization page-write rows, pointer-map-first ordering, duplicate pointer-map rewrite receipts, and fenced-tail guards',
    'non overlap' => true,
    'write action' => 'btree-vacuum-pointermap-freeblock-write-materialization',
    'write row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases220 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source publication-audit ' . $name] = static function (TestRunner $t) use ($callback, $expected220, $name): void {
        $t->same($expected220[$name], $callback());
    };
}

foreach (range(1, 70) as $index) {
    $tests['btree vacuum pointermap freeblock current source publication-audit commit invariant ' . $index] = static function (TestRunner $t) use ($plan220): void {
        $plan = $plan220();
        $summary = $plan->commitSummary();

        $t->same([], $plan->commitErrors());
        $t->same([2, 105, 105, 3, 106, 107, 108], $plan->commitPages());
        $t->same([2, 3, 105, 106, 107, 108], $summary['unique_commit_pages']);
        $t->same([2, 105, 105], $plan->pointerMapCommitPages());
        $t->same([3, 106, 107, 108], $plan->payloadCommitPages());
        $t->same([105], $summary['rewrite_commit_pages']);
        $t->same([true, true, true, true, true, true, true], array_column($plan->commitRows(), 'source_write_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->commitRows(), 'tail_page_excluded_from_commit'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->commitRows(), 'freeblock_receipt_carried'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->commitTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-publication-audit-ready', $summary['status']);
        $t->same(true, $summary['commit_pages_match_write_pages']);
        $t->same(true, $summary['all_pointer_maps_committed_before_payload']);
    };
}

return $tests;
