<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next216 checkpoint database');
$walDigest = $digest('next216 retained wal before reset');
$writerDigest = $digest('next216 writer fence');
$oldDigest = $digest('next216 stale digest');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp-next216.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next216.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next216.sqlite-wal',
    'page_size' => 512,
    'requested_checkpoint_frame' => 216,
    'checkpointed_frame' => 214,
    'busy' => true,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 216,
    'minimum_statement_generation' => 213,
    'active_reader_names' => ['wp-options-current-reader', 'wp-cron-current-reader'],
    'reopen_reader_names' => ['old-import-reader', 'old-plugin-reader'],
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$transitions = [
    [
        'name' => 'wp-options-current-reader',
        'released' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'wp-cron-current-reader',
        'released' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 216,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-import-reader',
        'reopened' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 213,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-plugin-reader',
        'reopened' => true,
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 216,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
];

$restart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $transitions, 'RESTART');
$truncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $transitions, 'TRUNCATE');
$blockedActive = static function () use ($base, $transitions): array {
    $rows = $transitions;
    $rows[0]['released'] = false;

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $rows, 'RESTART');
};
$blockedStale = static function () use ($base, $transitions): array {
    $rows = $transitions;
    $rows[2]['reopened'] = false;

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $rows, 'TRUNCATE');
};
$unknownReader = static function () use ($base, $transitions, $databaseDigest, $walDigest, $writerDigest): array {
    $rows = $transitions;
    $rows[] = [
        'name' => 'untracked-theme-reader',
        'closed' => true,
        'reader_generation' => 216,
        'reader_end_frame' => 216,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ];

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $rows, 'RESTART');
};
$badDigest = static function () use ($base, $transitions, $oldDigest): array {
    $rows = $transitions;
    $rows[1]['observed_wal_digest'] = $oldDigest;

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $rows, 'RESTART');
};
$notBusy = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain(array_merge($base, ['busy' => false]), $transitions, 'RESTART');

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next216'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'restart_or_truncate_checkpoint_publishes_next_source_after_reader_drain'],
    'base status' => [static fn (): mixed => $restart()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next212'],
    'database path' => [static fn (): mixed => $restart()['database_path'], '/srv/www/wp-content/database/wp-next216.sqlite'],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], '/srv/www/wp-content/database/wp-next216.sqlite-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], '/srv/www/wp-content/database/wp-next216.sqlite-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'RESTART'],
    'requested frame' => [static fn (): mixed => $restart()['requested_checkpoint_frame'], 216],
    'previous frame' => [static fn (): mixed => $restart()['previous_checkpointed_frame'], 214],
    'checkpointed frame reaches requested' => [static fn (): mixed => $restart()['checkpointed_frame'], 216],
    'busy false after drain' => [static fn (): mixed => $restart()['busy'], false],
    'reset allowed' => [static fn (): mixed => $restart()['reset_allowed'], true],
    'restart truncate disallowed' => [static fn (): mixed => $restart()['truncate_allowed'], false],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'restart_wal_header_after_reader_drain'],
    'restart database action' => [static fn (): mixed => $restart()['database_action'], 'write_frames_through_216'],
    'next reader generation' => [static fn (): mixed => $restart()['next_reader_generation'], 217],
    'reset salt length' => [static fn (): mixed => strlen((string) $restart()['reset_salt']), 16],
    'database digest' => [static fn (): mixed => $restart()['database_digest'], $databaseDigest],
    'wal digest before reset' => [static fn (): mixed => $restart()['wal_digest_before_reset'], $walDigest],
    'writer digest' => [static fn (): mixed => $restart()['writer_digest'], $writerDigest],
    'next writer generation' => [static fn (): mixed => $restart()['next_writer_generation'], 216],
    'minimum statement generation' => [static fn (): mixed => $restart()['minimum_statement_generation'], 213],
    'active reader names' => [static fn (): mixed => $restart()['active_reader_names'], ['wp-options-current-reader', 'wp-cron-current-reader']],
    'reopen reader names' => [static fn (): mixed => $restart()['reopen_reader_names'], ['old-import-reader', 'old-plugin-reader']],
    'released active readers' => [static fn (): mixed => $restart()['released_active_reader_names'], ['wp-options-current-reader', 'wp-cron-current-reader']],
    'blocked active readers empty' => [static fn (): mixed => $restart()['blocked_active_reader_names'], []],
    'reopened stale readers' => [static fn (): mixed => $restart()['reopened_stale_reader_names'], ['old-import-reader', 'old-plugin-reader']],
    'blocked stale readers empty' => [static fn (): mixed => $restart()['blocked_stale_reader_names'], []],
    'unknown readers empty' => [static fn (): mixed => $restart()['unknown_reader_names'], []],
    'first transition role' => [static fn (): mixed => $restart()['reader_transition_rows'][0]['role'], 'active'],
    'first transition admitted' => [static fn (): mixed => $restart()['reader_transition_rows'][0]['admitted_for_reset'], true],
    'first transition label' => [static fn (): mixed => $restart()['reader_transition_rows'][0]['transition'], 'released_current_reader_pin'],
    'stale transition role' => [static fn (): mixed => $restart()['reader_transition_rows'][2]['role'], 'stale'],
    'stale transition admitted' => [static fn (): mixed => $restart()['reader_transition_rows'][2]['admitted_for_reset'], true],
    'stale transition label' => [static fn (): mixed => $restart()['reader_transition_rows'][2]['transition'], 'reopened_stale_reader_handle'],
    'guard names' => [static fn (): mixed => $restart()['guard_names'], ['passive_checkpoint_was_busy', 'all_current_readers_released', 'all_stale_readers_reopened', 'no_unknown_reader_transitions', 'checkpoint_reaches_requested_frame']],
    'guard matches' => [static fn (): mixed => $restart()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $restart()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $restart()['operation_names'][0], 'verify_passive_checkpoint_reader_pin_current_source_next212'],
    'operation verify present' => [static fn (): mixed => in_array('verify_reader_drain_before_restart_truncate_current_source_next216', $restart()['operation_names'], true), true],
    'operation restart present' => [static fn (): mixed => in_array('restart_wal_after_reader_drain_next216', $restart()['operation_names'], true), true],
    'checkpoint digest length' => [static fn (): mixed => strlen($restart()['checkpoint_digest']), 64],
    'dependency next212 inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212', $restart()['dependencies'], true), true],
    'dependency next216' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next216', $restart()['dependencies'], true), true],
    'dependency drain' => [static fn (): mixed => in_array('sqlite-restart-truncate-after-hot-journal-reader-drain', $restart()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-reset-after-reader-drain', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'TRUNCATE'],
    'truncate allowed' => [static fn (): mixed => $truncate()['truncate_allowed'], true],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal_after_reader_drain'],
    'truncate operation' => [static fn (): mixed => in_array('truncate_wal_after_reader_drain_next216', $truncate()['operation_names'], true), true],
    'truncate salt differs' => [static fn (): mixed => $truncate()['reset_salt'] !== $restart()['reset_salt'], true],
    'active not released blocked status' => [static fn (): mixed => $blockedActive()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next216'],
    'active not released busy' => [static fn (): mixed => $blockedActive()['busy'], true],
    'active not released blocked guard' => [static fn (): mixed => $blockedActive()['blocked_guard_names'], ['all_current_readers_released']],
    'active not released name' => [static fn (): mixed => $blockedActive()['blocked_active_reader_names'], ['wp-options-current-reader']],
    'active not released reason' => [static fn (): mixed => $blockedActive()['reader_transition_rows'][0]['blocked_reasons'], ['current_reader_not_released']],
    'stale not reopened blocked guard' => [static fn (): mixed => $blockedStale()['blocked_guard_names'], ['all_stale_readers_reopened']],
    'stale not reopened name' => [static fn (): mixed => $blockedStale()['blocked_stale_reader_names'], ['old-import-reader']],
    'stale not reopened reason' => [static fn (): mixed => $blockedStale()['reader_transition_rows'][2]['blocked_reasons'], ['stale_reader_not_reopened']],
    'unknown reader guard' => [static fn (): mixed => $unknownReader()['blocked_guard_names'], ['no_unknown_reader_transitions']],
    'unknown reader name' => [static fn (): mixed => $unknownReader()['unknown_reader_names'], ['untracked-theme-reader']],
    'unknown reader reason' => [static fn (): mixed => $unknownReader()['reader_transition_rows'][4]['blocked_reasons'], ['reader_not_tracked_by_passive_checkpoint']],
    'bad digest blocked active guard' => [static fn (): mixed => $badDigest()['blocked_guard_names'], ['all_current_readers_released']],
    'bad digest reason' => [static fn (): mixed => $badDigest()['reader_transition_rows'][1]['blocked_reasons'], ['reader_wal_digest_mismatch']],
    'not busy guard' => [static fn (): mixed => $notBusy()['blocked_guard_names'], ['passive_checkpoint_was_busy']],
    'blocked wal preserved' => [static fn (): mixed => $blockedActive()['wal_action'], 'preserve_wal_until_reader_drain'],
    'blocked database action' => [static fn (): mixed => $blockedActive()['database_action'], 'keep_frames_through_214'],
    'blocked reset salt null' => [static fn (): mixed => $blockedActive()['reset_salt'], null],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next216 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain(['status' => 'bad'], $transitions, 'RESTART'),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, $transitions, 'PASSIVE'),
    'empty transitions rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, [], 'RESTART'),
    'bad digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain(array_merge($base, ['database_digest' => 'short']), $transitions, 'RESTART'),
    'bad frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain(array_merge($base, ['requested_checkpoint_frame' => 0]), $transitions, 'RESTART'),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain(array_merge($base, ['next_writer_generation' => 0]), $transitions, 'RESTART'),
    'bad active names rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain(array_merge($base, ['active_reader_names' => []]), $transitions, 'RESTART'),
    'bad transition name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, [array_merge($transitions[0], ['name' => ''])], 'RESTART'),
    'bad transition frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, [array_merge($transitions[0], ['reader_end_frame' => -1])], 'RESTART'),
    'bad transition digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next216RestartOrTruncateAfterReaderDrain($base, [array_merge($transitions[0], ['observed_writer_digest' => 'short'])], 'RESTART'),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next216 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
