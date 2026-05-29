<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next215 checkpoint database');
$walDigest = $digest('next215 retained wal');
$writerDigest = $digest('next215 writer generation');
$oldDatabaseDigest = $digest('next215 old database');
$oldWalDigest = $digest('next215 old wal');
$oldWriterDigest = $digest('next215 old writer');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'database_path' => '/srv/www/wp-content/database/wp-next215.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next215.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next215.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 212,
    'next_writer_generation' => 215,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'admitted_writer_names' => ['wp-options-autoload-writer'],
    'reopen_writer_names' => ['stale-plugin-writer'],
    'operation_names' => ['verify_post_checkpoint_writer_generation_current_source_next209'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209'],
];

$passiveReaders = [
    [
        'name' => 'wp-options-current-reader',
        'reader_end_frame' => 213,
        'reader_generation' => 215,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'stale-plugin-reader',
        'reader_end_frame' => 211,
        'reader_generation' => 214,
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_writer_digest' => $oldWriterDigest,
    ],
];

$reopenRows = [
    [
        'name' => 'stale-plugin-reader',
        'reader_end_frame' => 215,
        'reader_generation' => 215,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'reopened' => true,
    ],
];

$pinnedPassive = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint($base, $passiveReaders, 215);
$drainedPassive = static function () use ($pinnedPassive): array {
    $plan = $pinnedPassive();
    $plan['active_reader_names'] = [];
    $plan['checkpointed_frame'] = 215;
    $plan['busy'] = false;
    $plan['wal_action'] = 'passive_checkpoint_complete';
    $plan['database_action'] = 'write_frames_through_215';

    return $plan;
};

$restart = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), $reopenRows, 'restart');
$truncate = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), $reopenRows, 'truncate');
$stillPinned = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($pinnedPassive(), $reopenRows, 'restart');
$missingReopen = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [], 'restart');
$notReopened = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['reopened' => false])], 'restart');
$wrongDigest = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['observed_wal_digest' => $oldWalDigest])], 'restart');
$futureFrame = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['reader_end_frame' => 216])], 'restart');
$unexpectedReader = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['name' => 'unexpected-reader'])], 'restart');
$completeWithoutPassivePin = static function () use ($drainedPassive, $reopenRows): array {
    $plan = $drainedPassive();
    $plan['busy'] = false;
    $plan['active_reader_names'] = [];
    $plan['operation_names'] = array_values(array_filter(
        $plan['operation_names'],
        static fn (string $name): bool => $name !== 'preserve_wal_for_pinned_reader_next212'
    ));

    return SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($plan, $reopenRows, 'restart');
};

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next215'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'restart_checkpoint_resets_wal_after_current_source_readers_reopen'],
    'base status' => [static fn (): mixed => $restart()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next212'],
    'database path' => [static fn (): mixed => $restart()['database_path'], '/srv/www/wp-content/database/wp-next215.sqlite'],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], '/srv/www/wp-content/database/wp-next215.sqlite-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], '/srv/www/wp-content/database/wp-next215.sqlite-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'requested frame' => [static fn (): mixed => $restart()['requested_checkpoint_frame'], 215],
    'passive checkpointed frame' => [static fn (): mixed => $restart()['passive_checkpointed_frame'], 215],
    'checkpointed frame complete' => [static fn (): mixed => $restart()['checkpointed_frame'], 215],
    'busy false' => [static fn (): mixed => $restart()['busy'], false],
    'reset allowed' => [static fn (): mixed => $restart()['reset_allowed'], true],
    'truncate false on restart' => [static fn (): mixed => $restart()['truncate_allowed'], false],
    'restart wal action' => [static fn (): mixed => $restart()['wal_action'], 'reset_wal_header_after_restart_checkpoint'],
    'database action' => [static fn (): mixed => $restart()['database_action'], 'write_frames_through_215'],
    'journal action' => [static fn (): mixed => $restart()['journal_action'], 'hot_journal_removed_before_wal_reset'],
    'new current source epoch' => [static fn (): mixed => $restart()['new_current_source_epoch'], 216],
    'minimum statement generation' => [static fn (): mixed => $restart()['minimum_statement_generation'], 212],
    'next writer generation' => [static fn (): mixed => $restart()['next_writer_generation'], 215],
    'database digest' => [static fn (): mixed => $restart()['database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $restart()['wal_digest'], $walDigest],
    'writer digest' => [static fn (): mixed => $restart()['writer_digest'], $writerDigest],
    'required reopen names' => [static fn (): mixed => $restart()['required_reopen_reader_names'], ['stale-plugin-reader']],
    'active reader names drained' => [static fn (): mixed => $restart()['active_reader_names'], []],
    'admitted reopen names' => [static fn (): mixed => $restart()['admitted_reopen_reader_names'], ['stale-plugin-reader']],
    'missing reopens empty' => [static fn (): mixed => $restart()['missing_reopen_reader_names'], []],
    'unexpected reopens empty' => [static fn (): mixed => $restart()['unexpected_reopen_reader_names'], []],
    'blocked reasons empty' => [static fn (): mixed => $restart()['blocked_reader_reasons'], []],
    'reader reopened' => [static fn (): mixed => $restart()['reader_rows'][0]['reopened'], true],
    'reader admitted' => [static fn (): mixed => $restart()['reader_rows'][0]['admitted'], true],
    'reader transition' => [static fn (): mixed => $restart()['reader_rows'][0]['transition'], 'stale-plugin-reader>reopened-current-source:next215'],
    'reader reason' => [static fn (): mixed => $restart()['reader_rows'][0]['reader_reason'], 'reader_reopened_on_current_source_for_restart_checkpoint'],
    'guard names' => [static fn (): mixed => $restart()['guard_names'], ['prior_passive_checkpoint_reported_reader_pin', 'all_stale_readers_reopened', 'reopened_readers_match_current_source', 'no_active_reader_pin_remaining', 'checkpoint_covers_requested_frame']],
    'guard matches' => [static fn (): mixed => $restart()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $restart()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => in_array('verify_passive_checkpoint_reader_pin_current_source_next212', $restart()['operation_names'], true), true],
    'operation verify present' => [static fn (): mixed => in_array('verify_restart_checkpoint_reopen_current_source_next215', $restart()['operation_names'], true), true],
    'operation publish present' => [static fn (): mixed => in_array('publish_restart_checkpoint_current_source_next215', $restart()['operation_names'], true), true],
    'checkpoint digest length' => [static fn (): mixed => strlen($restart()['checkpoint_digest']), 64],
    'dependency next212 inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212', $restart()['dependencies'], true), true],
    'dependency next215' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next215', $restart()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-restart-checkpoint-reset-after-reader-reopen', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat next212 passive progress'), true],
    'truncate mode' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'truncate allowed' => [static fn (): mixed => $truncate()['truncate_allowed'], true],
    'truncate wal action' => [static fn (): mixed => $truncate()['wal_action'], 'truncate_wal_after_restart_checkpoint'],
    'still pinned status blocked' => [static fn (): mixed => $stillPinned()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next215'],
    'still pinned busy' => [static fn (): mixed => $stillPinned()['busy'], true],
    'still pinned wal preserved' => [static fn (): mixed => $stillPinned()['wal_action'], 'preserve_wal'],
    'still pinned guard' => [static fn (): mixed => $stillPinned()['blocked_guard_names'], ['no_active_reader_pin_remaining', 'checkpoint_covers_requested_frame']],
    'not reopened status blocked' => [static fn (): mixed => $notReopened()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next215'],
    'not reopened reason' => [static fn (): mixed => $notReopened()['reader_rows'][0]['reader_reason'], 'reader_not_reopened'],
    'not reopened blocked reason' => [static fn (): mixed => $notReopened()['blocked_reader_reasons'], ['reader_not_reopened']],
    'wrong digest blocked reason' => [static fn (): mixed => $wrongDigest()['blocked_reader_reasons'], ['reader_wal_digest_mismatch']],
    'future frame blocked reason' => [static fn (): mixed => $futureFrame()['blocked_reader_reasons'], ['reader_end_frame_outside_checkpoint_window']],
    'unexpected reader missing name' => [static fn (): mixed => $unexpectedReader()['missing_reopen_reader_names'], ['stale-plugin-reader']],
    'unexpected reader extra name' => [static fn (): mixed => $unexpectedReader()['unexpected_reopen_reader_names'], ['unexpected-reader']],
    'unexpected reader blocked guard' => [static fn (): mixed => $unexpectedReader()['blocked_guard_names'], ['all_stale_readers_reopened']],
    'complete without passive pin guard' => [static fn (): mixed => $completeWithoutPassivePin()['blocked_guard_names'], ['prior_passive_checkpoint_reported_reader_pin']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next215 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint(['status' => 'bad'], $reopenRows),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), $reopenRows, 'passive'),
    'empty reopen rows rejected' => $missingReopen,
    'bad requested frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint(array_merge($drainedPassive(), ['requested_checkpoint_frame' => 0]), $reopenRows),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint(array_merge($drainedPassive(), ['database_digest' => 'short']), $reopenRows),
    'bad active names rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint(array_merge($drainedPassive(), ['active_reader_names' => [null]]), $reopenRows),
    'missing reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['name' => ''])]),
    'bad reader frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['reader_end_frame' => 0])]),
    'bad reader digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next215RestartCheckpoint($drainedPassive(), [array_merge($reopenRows[0], ['observed_database_digest' => 'short'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next215 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
