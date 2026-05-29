<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage233 = static function (int $pageCount): string {
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

$putPointerMapEntry233 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database233 = static function () use ($makeFirstPage233, $putPointerMapEntry233): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage233(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next233', str_repeat('cache:', 42)])),
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
        $putPointerMapEntry233($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan233 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database233;

    $database = $database233();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafResumeCheckpointFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next229-current-source-resume-', 50),
        3,
        true,
        $batchSize,
    );
};

$message233 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases233 = [
    'action label' => static fn (): mixed => $plan233()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan233()->checkpointSummary()['status'],
    'checkpoint row count' => static fn (): mixed => $plan233()->checkpointSummary()['checkpoint_row_count'],
    'checkpoint pages' => static fn (): mixed => $plan233()->checkpointPages(),
    'summary checkpoint pages' => static fn (): mixed => $plan233()->checkpointSummary()['checkpoint_pages'],
    'resume pages' => static fn (): mixed => $plan233()->checkpointSummary()['resume_pages'],
    'checkpoint pages match resume pages' => static fn (): mixed => $plan233()->checkpointSummary()['checkpoint_pages_match_resume_pages'],
    'pointer map checkpoint pages' => static fn (): mixed => $plan233()->pointerMapCheckpointPages(),
    'payload checkpoint pages' => static fn (): mixed => $plan233()->payloadCheckpointPages(),
    'checkpoint errors' => static fn (): mixed => $plan233()->checkpointErrors(),
    'summary checkpoint errors' => static fn (): mixed => $plan233()->checkpointSummary()['checkpoint_errors'],
    'all resume tokens match' => static fn (): mixed => $plan233()->checkpointSummary()['all_resume_tokens_match'],
    'all checkpoint links valid' => static fn (): mixed => $plan233()->checkpointSummary()['all_checkpoint_links_valid'],
    'all payload checkpoints have pointer maps' => static fn (): mixed => $plan233()->checkpointSummary()['all_payload_checkpoints_have_pointer_map_visibility'],
    'all duplicate generations tracked' => static fn (): mixed => $plan233()->checkpointSummary()['all_duplicate_pointer_map_generations_tracked'],
    'all freeblock receipts checkpointed' => static fn (): mixed => $plan233()->checkpointSummary()['all_freeblock_receipts_checkpointed'],
    'all tail pages fenced' => static fn (): mixed => $plan233()->checkpointSummary()['all_tail_pages_fenced_at_checkpoint'],
    'checkpoint token count' => static fn (): mixed => count($plan233()->checkpointTokens()),
    'checkpoint token lengths' => static fn (): mixed => array_map('strlen', $plan233()->checkpointTokens()),
    'checkpoint signature length' => static fn (): mixed => strlen($plan233()->checkpointSummary()['checkpoint_signature']),
    'current source token length' => static fn (): mixed => strlen($plan233()->checkpointSummary()['current_source_next233_token']),
    'first checkpoint channel' => static fn (): mixed => $plan233()->checkpointRows()[0]['checkpoint_channel'],
    'first checkpoint page' => static fn (): mixed => $plan233()->checkpointRows()[0]['checkpoint_page'],
    'first visible pointer maps' => static fn (): mixed => $plan233()->checkpointRows()[0]['checkpoint_visible_pointer_map_pages'],
    'first generation' => static fn (): mixed => $plan233()->checkpointRows()[0]['pointer_map_generation'],
    'second checkpoint channel' => static fn (): mixed => $plan233()->checkpointRows()[1]['checkpoint_channel'],
    'second checkpoint page' => static fn (): mixed => $plan233()->checkpointRows()[1]['checkpoint_page'],
    'second visible pointer maps' => static fn (): mixed => $plan233()->checkpointRows()[1]['checkpoint_visible_pointer_map_pages'],
    'third checkpoint channel' => static fn (): mixed => $plan233()->checkpointRows()[2]['checkpoint_channel'],
    'third checkpoint page' => static fn (): mixed => $plan233()->checkpointRows()[2]['checkpoint_page'],
    'third generation' => static fn (): mixed => $plan233()->checkpointRows()[2]['pointer_map_generation'],
    'fourth checkpoint page' => static fn (): mixed => $plan233()->checkpointRows()[3]['checkpoint_page'],
    'fourth visible pointer maps' => static fn (): mixed => $plan233()->checkpointRows()[3]['checkpoint_visible_pointer_map_pages'],
    'fifth checkpoint page' => static fn (): mixed => $plan233()->checkpointRows()[4]['checkpoint_page'],
    'fifth generation' => static fn (): mixed => $plan233()->checkpointRows()[4]['pointer_map_generation'],
    'fifth generation state' => static fn (): mixed => $plan233()->checkpointRows()[4]['pointer_map_generation_state'],
    'last checkpoint page' => static fn (): mixed => $plan233()->checkpointRows()[6]['checkpoint_page'],
    'checkpoint ordinals' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'checkpoint_ordinal'),
    'resume ordinals' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'resume_ordinal'),
    'row states' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'checkpoint_state'),
    'row resume token flags' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'resume_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'checkpoint_link_valid'),
    'row payload visibility flags' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'payload_checkpoint_has_pointer_map_visibility'),
    'row duplicate generation flags' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'duplicate_pointer_map_generation_tracked'),
    'row freeblock flags' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'freeblock_checkpoint_receipt_carried'),
    'row tail fence flags' => static fn (): mixed => array_column($plan233()->checkpointRows(), 'tail_pages_fenced_at_checkpoint'),
    'batch size three row count' => static fn (): mixed => $plan233(3)->checkpointSummary()['checkpoint_row_count'],
    'batch size three pages' => static fn (): mixed => $plan233(3)->checkpointPages(),
    'batch size three token count' => static fn (): mixed => count($plan233(3)->checkpointTokens()),
    'dependency closure' => static fn (): mixed => $plan233()->checkpointSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan233()->checkpointSummary()['non_overlap'], 'does not repeat publication-resume-window'),
    'resume action' => static fn (): mixed => $plan233()->resumePlan->toArray()['action'],
    'resume row count' => static fn (): mixed => $plan233()->resumePlan->resumeSummary()['resume_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message233(static fn () => $plan233(0)),
];

$expected233 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next233',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next233-ready',
    'checkpoint row count' => 7,
    'checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'resume pages' => [2, 3, 105, 106, 105, 107, 108],
    'checkpoint pages match resume pages' => true,
    'pointer map checkpoint pages' => [2, 105],
    'payload checkpoint pages' => [3, 106, 107, 108],
    'checkpoint errors' => [],
    'summary checkpoint errors' => [],
    'all resume tokens match' => true,
    'all checkpoint links valid' => true,
    'all payload checkpoints have pointer maps' => true,
    'all duplicate generations tracked' => true,
    'all freeblock receipts checkpointed' => true,
    'all tail pages fenced' => true,
    'checkpoint token count' => 7,
    'checkpoint token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'checkpoint signature length' => 64,
    'current source token length' => 64,
    'first checkpoint channel' => 'pointer-map',
    'first checkpoint page' => 2,
    'first visible pointer maps' => [2],
    'first generation' => 1,
    'second checkpoint channel' => 'payload',
    'second checkpoint page' => 3,
    'second visible pointer maps' => [2],
    'third checkpoint channel' => 'pointer-map',
    'third checkpoint page' => 105,
    'third generation' => 1,
    'fourth checkpoint page' => 106,
    'fourth visible pointer maps' => [2, 105],
    'fifth checkpoint page' => 105,
    'fifth generation' => 2,
    'fifth generation state' => ['2:1', '105:2'],
    'last checkpoint page' => 108,
    'checkpoint ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'resume ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => ['current-source-checkpoint-admitted', 'current-source-checkpoint-admitted', 'current-source-checkpoint-admitted', 'current-source-checkpoint-admitted', 'current-source-checkpoint-admitted', 'current-source-checkpoint-admitted', 'current-source-checkpoint-admitted'],
    'row resume token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row payload visibility flags' => [true, true, true, true, true, true, true],
    'row duplicate generation flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three token count' => 6,
    'dependency closure' => 'no new support component needed; next233 reuses publication-resume-window resume rows and records checkpoint-admission receipts only',
    'non overlap' => true,
    'resume action' => 'btree-vacuum-pointermap-freeblock-publication-resume-window',
    'resume row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases233 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next233 ' . $name] = static function (TestRunner $t) use ($callback, $expected233, $name): void {
        $t->same($expected233[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next233 checkpoint invariant ' . $index] = static function (TestRunner $t) use ($plan233): void {
        $plan = $plan233();
        $summary = $plan->checkpointSummary();

        $t->same([], $plan->checkpointErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->checkpointPages());
        $t->same([2, 105], $plan->pointerMapCheckpointPages());
        $t->same([3, 106, 107, 108], $plan->payloadCheckpointPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->checkpointRows(), 'checkpoint_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'resume_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'checkpoint_link_valid'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'payload_checkpoint_has_pointer_map_visibility'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'duplicate_pointer_map_generation_tracked'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'freeblock_checkpoint_receipt_carried'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'tail_pages_fenced_at_checkpoint'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->checkpointTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next233-ready', $summary['status']);
        $t->same(true, $summary['checkpoint_pages_match_resume_pages']);
        $t->same(true, $summary['all_payload_checkpoints_have_pointer_map_visibility']);
    };
}

return $tests;
