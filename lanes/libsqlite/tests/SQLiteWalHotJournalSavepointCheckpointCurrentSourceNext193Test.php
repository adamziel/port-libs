<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next193.sqlite';
$walPath = $databasePath . '-wal';
$retryToken = 'wal-hot-journal-savepoint-checkpoint-next187:retry:' . str_repeat('a', 32);
$handoff = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next187',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'retry_reader_token' => $retryToken,
    'can_admit_retry_checkpoint_source' => true,
    'stale_reader_tokens' => [],
    'retry_transition_digest' => hash('sha256', 'next193-transition'),
    'next_wal_sha256' => hash('sha256', 'next193-next-wal'),
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187'],
];
$slots = [
    [
        'slot' => 0,
        'page_number' => 1,
        'reader_token' => $retryToken,
        'generation' => 193,
        'source' => 'checkpoint-database',
        'frame_index' => null,
    ],
    [
        'slot' => 1,
        'page_number' => 2,
        'reader_token' => $retryToken,
        'generation' => 193,
        'source' => 'next-wal',
        'frame_index' => 1,
    ],
    [
        'slot' => 2,
        'page_number' => 3,
        'reader_token' => $retryToken,
        'generation' => 193,
        'source' => 'next-wal',
        'frame_index' => 2,
    ],
];
$plan = static fn (
    ?array $input = null,
    ?array $readerSlots = null,
    int $generation = 193,
    array $pages = [1, 2, 3]
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next193PublishReaderMarks(
    $input ?? $handoff,
    $readerSlots ?? $slots,
    $generation,
    $pages
);

$staleHandoff = $handoff;
$staleHandoff['stale_reader_tokens'] = ['post-apply-current-token'];
$blockedHandoff = $handoff;
$blockedHandoff['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next187';
$blockedHandoff['can_admit_retry_checkpoint_source'] = false;
$wrongTokenSlots = $slots;
$wrongTokenSlots[1]['reader_token'] = 'stale-token-before-next193';
$wrongGenerationSlots = $slots;
$wrongGenerationSlots[2]['generation'] = 192;
$duplicateSlots = $slots;
$duplicateSlots[2]['slot'] = 1;
$missingFrameSlots = $slots;
$missingFrameSlots[1]['frame_index'] = null;
$checkpointFrameSlots = $slots;
$checkpointFrameSlots[0]['frame_index'] = 4;
$badSourceSlots = $slots;
$badSourceSlots[2]['source'] = 'current-wal';
$unexpectedPageSlots = $slots;
$unexpectedPageSlots[2]['page_number'] = 9;
$missingPageSlots = array_slice($slots, 0, 2);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next193'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'retry_wal_reader_marks_published_after_hot_journal_checkpoint_handoff'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'generation' => [static fn (): mixed => $plan()['generation'], 193],
    'retry token' => [static fn (): mixed => $plan()['retry_reader_token'], $retryToken],
    'expected pages' => [static fn (): mixed => $plan()['expected_pages'], [1, 2, 3]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_pages'], []],
    'row count' => [static fn (): mixed => count($plan()['reader_mark_rows']), 3],
    'published row count' => [static fn (): mixed => $plan()['published_slot_count'], 3],
    'published pages' => [static fn (): mixed => $plan()['published_pages'], [1, 2, 3]],
    'published sources' => [static fn (): mixed => $plan()['published_sources'], ['checkpoint-database', 'next-wal']],
    'can publish' => [static fn (): mixed => $plan()['can_publish_reader_marks'], true],
    'requires reopen false' => [static fn (): mixed => $plan()['requires_reader_reopen'], false],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'row zero source' => [static fn (): mixed => $plan()['reader_mark_rows'][0]['source'], 'checkpoint-database'],
    'row zero frame' => [static fn (): mixed => $plan()['reader_mark_rows'][0]['frame_index'], null],
    'row one frame' => [static fn (): mixed => $plan()['reader_mark_rows'][1]['frame_index'], 1],
    'row two published' => [static fn (): mixed => $plan()['reader_mark_rows'][2]['published'], true],
    'reader mark digest length' => [static fn (): mixed => strlen($plan()['reader_mark_digest']), 64],
    'transition digest carried' => [static fn (): mixed => $plan()['handoff_transition_digest'], hash('sha256', 'next193-transition')],
    'wal sha carried' => [static fn (): mixed => $plan()['next_wal_sha256'], hash('sha256', 'next193-next-wal')],
    'dependency next187 carried' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next187', $plan()['dependencies'], true), true],
    'dependency next193' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next193', $plan()['dependencies'], true), true],
    'wordpress dependency' => [static fn (): mixed => in_array('wordpress-wal-import-retry-reader-mark-publication', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat hot-journal recovery'), true],
    'stale handoff status' => [static fn (): mixed => $plan($staleHandoff)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next193'],
    'stale handoff reason' => [static fn (): mixed => $plan($staleHandoff)['blocked_reasons'], ['stale_reader_tokens_not_retired_before_mark_publish']],
    'blocked handoff reasons' => [static fn (): mixed => $plan($blockedHandoff)['blocked_reasons'], ['next187_retry_handoff_not_admitted', 'retry_checkpoint_source_not_admitted']],
    'wrong token status' => [static fn (): mixed => $plan(null, $wrongTokenSlots)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next193'],
    'wrong token row reason' => [static fn (): mixed => $plan(null, $wrongTokenSlots)['reader_mark_rows'][1]['blocked_reasons'], ['reader_mark_token_not_retry_source']],
    'wrong token aggregate reason' => [static fn (): mixed => $plan(null, $wrongTokenSlots)['blocked_reasons'], ['reader_mark_token_not_retry_source']],
    'wrong generation reason' => [static fn (): mixed => $plan(null, $wrongGenerationSlots)['blocked_reasons'], ['reader_mark_generation_mismatch']],
    'duplicate slot reason' => [static fn (): mixed => $plan(null, $duplicateSlots)['blocked_reasons'], ['duplicate_reader_mark_slot']],
    'missing frame reason' => [static fn (): mixed => $plan(null, $missingFrameSlots)['blocked_reasons'], ['next_wal_reader_mark_missing_frame']],
    'checkpoint frame reason' => [static fn (): mixed => $plan(null, $checkpointFrameSlots)['blocked_reasons'], ['checkpoint_database_reader_mark_has_frame']],
    'bad source reason' => [static fn (): mixed => $plan(null, $badSourceSlots)['blocked_reasons'], ['reader_mark_source_not_retry_visible']],
    'unexpected page reasons' => [static fn (): mixed => $plan(null, $unexpectedPageSlots)['blocked_reasons'], ['reader_mark_page_not_expected', 'reader_mark_pages_missing']],
    'unexpected page missing' => [static fn (): mixed => $plan(null, $unexpectedPageSlots)['missing_pages'], [3]],
    'missing page reasons' => [static fn (): mixed => $plan(null, $missingPageSlots)['blocked_reasons'], ['reader_mark_pages_missing']],
    'missing page list' => [static fn (): mixed => $plan(null, $missingPageSlots)['missing_pages'], [3]],
    'single page publish' => [static fn (): mixed => $plan(null, [$slots[1]], 193, [2])['published_pages'], [2]],
    'single page source' => [static fn (): mixed => $plan(null, [$slots[1]], 193, [2])['published_sources'], ['next-wal']],
    'single checkpoint page publish' => [static fn (): mixed => $plan(null, [$slots[0]], 193, [1])['published_sources'], ['checkpoint-database']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next193 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing handoff key rejected' => static function () use ($handoff, $slots): void {
        $bad = $handoff;
        unset($bad['retry_reader_token']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next193PublishReaderMarks($bad, $slots, 193, [1, 2, 3]);
    },
    'zero generation rejected' => static fn () => $plan(null, null, 0),
    'empty expected pages rejected' => static fn () => $plan(null, null, 193, []),
    'bad expected page rejected' => static fn () => $plan(null, null, 193, [0]),
    'missing slot key rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        unset($bad[0]['frame_index']);
        $plan(null, $bad);
    },
    'negative slot rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        $bad[0]['slot'] = -1;
        $plan(null, $bad);
    },
    'bad slot page rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        $bad[0]['page_number'] = 0;
        $plan(null, $bad);
    },
    'empty token rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        $bad[0]['reader_token'] = '';
        $plan(null, $bad);
    },
    'bad slot generation rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        $bad[0]['generation'] = 0;
        $plan(null, $bad);
    },
    'empty source rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        $bad[0]['source'] = '';
        $plan(null, $bad);
    },
    'bad frame index rejected' => static function () use ($slots, $plan): void {
        $bad = $slots;
        $bad[0]['frame_index'] = 'one';
        $plan(null, $bad);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next193 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
