<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('restartReaderRelease checkpoint database');
$walDigest = $digest('restartReaderRelease retained wal');
$writerDigest = $digest('restartReaderRelease writer generation');
$oldDatabaseDigest = $digest('restartReaderRelease old database');
$oldWalDigest = $digest('restartReaderRelease old wal');
$oldWriterDigest = $digest('restartReaderRelease old writer');
$saltBefore = $digest('restartReaderRelease wal salt before');
$saltAfter = $digest('restartReaderRelease wal salt after');
$hotJournalDigest = $digest('restartReaderRelease hot journal');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp-restart-reader-release.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-restart-reader-release.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-restart-reader-release.sqlite-wal',
    'page_size' => 512,
    'requested_checkpoint_frame' => 214,
    'checkpointed_frame' => 214,
    'busy' => false,
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'next_writer_generation' => 214,
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$options = [
    'wal_salt_before' => $saltBefore,
    'wal_salt_after' => $saltAfter,
    'hot_journal_digest' => $hotJournalDigest,
    'savepoint_closed' => true,
    'exclusive_checkpoint_lock' => true,
    'database_synced' => true,
    'wal_header_synced' => true,
    'directory_synced' => true,
    'delete_hot_journal_after_reset' => true,
];

$readers = [
    [
        'name' => 'wp-options-released-reader',
        'released' => true,
        'reader_end_frame' => 214,
        'reader_generation' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'wp-cron-released-reader',
        'released' => true,
        'reader_end_frame' => 213,
        'reader_generation' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-generation-reader',
        'released' => false,
        'reader_end_frame' => 213,
        'reader_generation' => 212,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'future-frame-reader',
        'released' => false,
        'reader_end_frame' => 215,
        'reader_generation' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-database-reader',
        'released' => false,
        'reader_end_frame' => 213,
        'reader_generation' => 214,
        'observed_database_digest' => $oldDatabaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-wal-reader',
        'released' => false,
        'reader_end_frame' => 213,
        'reader_generation' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $oldWalDigest,
        'observed_writer_digest' => $writerDigest,
    ],
    [
        'name' => 'old-writer-reader',
        'released' => false,
        'reader_end_frame' => 213,
        'reader_generation' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $oldWriterDigest,
    ],
    [
        'name' => 'dirty-reader',
        'released' => false,
        'reader_end_frame' => 213,
        'reader_generation' => 214,
        'observed_database_digest' => $databaseDigest,
        'observed_wal_digest' => $walDigest,
        'observed_writer_digest' => $writerDigest,
        'dirty' => true,
    ],
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, $options);
$blockedCurrentReader = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(
    $base,
    [array_merge($readers[0], ['released' => false])],
    $options
);
$blockedPassive = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(
    array_merge($base, ['checkpointed_frame' => 213, 'busy' => true]),
    $readers,
    $options
);
$blockedNoStale = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(
    $base,
    [$readers[0], $readers[1]],
    $options
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-restart-reader-release'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'restart_checkpoint_resets_wal_after_current_source_readers_release'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next212'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-restart-reader-release.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-restart-reader-release.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-restart-reader-release.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'requested checkpoint frame' => [static fn (): mixed => $plan()['requested_checkpoint_frame'], 214],
    'checkpointed frame' => [static fn (): mixed => $plan()['checkpointed_frame'], 214],
    'restart allowed' => [static fn (): mixed => $plan()['restart_allowed'], true],
    'reset allowed' => [static fn (): mixed => $plan()['reset_allowed'], true],
    'truncate disallowed' => [static fn (): mixed => $plan()['truncate_allowed'], false],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'restart_wal_header_with_rotated_salt'],
    'database action' => [static fn (): mixed => $plan()['database_action'], 'write_frames_through_214'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'delete_hot_journal_after_wal_restart_sync'],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'wal digest before' => [static fn (): mixed => $plan()['wal_digest_before'], $walDigest],
    'writer digest' => [static fn (): mixed => $plan()['writer_digest'], $writerDigest],
    'writer generation' => [static fn (): mixed => $plan()['writer_generation'], 214],
    'wal salt before' => [static fn (): mixed => $plan()['wal_salt_before'], $saltBefore],
    'wal salt after' => [static fn (): mixed => $plan()['wal_salt_after'], $saltAfter],
    'hot journal digest' => [static fn (): mixed => $plan()['hot_journal_digest'], $hotJournalDigest],
    'first reader admitted' => [static fn (): mixed => $plan()['reader_rows'][0]['admitted'], true],
    'first reader action' => [static fn (): mixed => $plan()['reader_rows'][0]['reader_action'], 'released_before_restart_checkpoint'],
    'first reader does not pin' => [static fn (): mixed => $plan()['reader_rows'][0]['pins_current_source'], false],
    'second reader frame' => [static fn (): mixed => $plan()['reader_rows'][1]['reader_end_frame'], 213],
    'old generation action' => [static fn (): mixed => $plan()['reader_rows'][2]['reader_action'], 'reopen_reader_before_restart_checkpoint'],
    'old generation reason' => [static fn (): mixed => $plan()['reader_rows'][2]['blocked_reasons'], ['reader_generation_mismatch']],
    'future frame reason' => [static fn (): mixed => $plan()['reader_rows'][3]['blocked_reasons'], ['reader_end_frame_after_requested_checkpoint']],
    'old database reason' => [static fn (): mixed => $plan()['reader_rows'][4]['blocked_reasons'], ['reader_database_digest_mismatch']],
    'old wal reason' => [static fn (): mixed => $plan()['reader_rows'][5]['blocked_reasons'], ['reader_wal_digest_mismatch']],
    'old writer reason' => [static fn (): mixed => $plan()['reader_rows'][6]['blocked_reasons'], ['reader_writer_digest_mismatch']],
    'dirty reason' => [static fn (): mixed => $plan()['reader_rows'][7]['blocked_reasons'], ['reader_cache_dirty']],
    'dirty flag' => [static fn (): mixed => $plan()['reader_rows'][7]['dirty'], true],
    'current reader names empty' => [static fn (): mixed => $plan()['current_reader_names'], []],
    'reopen reader names' => [static fn (): mixed => $plan()['reopen_reader_names'], ['old-generation-reader', 'future-frame-reader', 'old-database-reader', 'old-wal-reader', 'old-writer-reader', 'dirty-reader']],
    'blocked reader reasons' => [static fn (): mixed => $plan()['blocked_reader_reasons'], ['reader_generation_mismatch', 'reader_end_frame_after_requested_checkpoint', 'reader_database_digest_mismatch', 'reader_wal_digest_mismatch', 'reader_writer_digest_mismatch', 'reader_cache_dirty']],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['passive_checkpoint_complete', 'all_current_readers_released', 'stale_readers_reopened', 'savepoint_closed', 'exclusive_checkpoint_lock', 'database_synced', 'wal_header_synced', 'directory_synced', 'wal_salt_rotated', 'hot_journal_digest_verified', 'delete_hot_journal_after_reset']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'sync sequence' => [static fn (): mixed => $plan()['sync_sequence'], ['database', 'wal-header', 'directory', 'hot-journal-delete']],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_passive_checkpoint_reader_pin_current_source_next212'],
    'operation verify present' => [static fn (): mixed => in_array('verify_restart_checkpoint_reader_release_current_source_restart_reader_release', $plan()['operation_names'], true), true],
    'operation restart present' => [static fn (): mixed => in_array('restart_wal_after_hot_journal_savepoint_checkpoint_restart_reader_release', $plan()['operation_names'], true), true],
    'restart digest length' => [static fn (): mixed => strlen($plan()['restart_digest']), 64],
    'dependency restartReaderRelease' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-restart-reader-release', $plan()['dependencies'], true), true],
    'dependency reader release' => [static fn (): mixed => in_array('sqlite-restart-checkpoint-current-source-reader-release', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-restart-checkpoint-deletes-hot-journal-after-wal-reset', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next212 PASSIVE reader pins'), true],
    'blocked current reader status' => [static fn (): mixed => $blockedCurrentReader()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-restart-reader-release'],
    'blocked current reader guard' => [static fn (): mixed => $blockedCurrentReader()['blocked_guard_names'], ['all_current_readers_released', 'stale_readers_reopened']],
    'blocked current reader action' => [static fn (): mixed => $blockedCurrentReader()['reader_rows'][0]['reader_action'], 'preserve_wal_for_reader'],
    'blocked current reader name' => [static fn (): mixed => $blockedCurrentReader()['current_reader_names'], ['wp-options-released-reader']],
    'blocked passive guards' => [static fn (): mixed => $blockedPassive()['blocked_guard_names'], ['passive_checkpoint_complete']],
    'blocked no stale guard' => [static fn (): mixed => $blockedNoStale()['blocked_guard_names'], ['stale_readers_reopened']],
    'blocked no sync guards' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['database_synced' => false, 'wal_header_synced' => false, 'directory_synced' => false]))['blocked_guard_names'], ['database_synced', 'wal_header_synced', 'directory_synced']],
    'blocked same salt guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['wal_salt_after' => $saltBefore]))['blocked_guard_names'], ['wal_salt_rotated']],
    'blocked zero hot journal guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['hot_journal_digest' => str_repeat('0', 64)]))['blocked_guard_names'], ['hot_journal_digest_verified']],
    'blocked missing delete guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['delete_hot_journal_after_reset' => false]))['blocked_guard_names'], ['delete_hot_journal_after_reset']],
    'blocked missing lock guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['exclusive_checkpoint_lock' => false]))['blocked_guard_names'], ['exclusive_checkpoint_lock']],
    'blocked open savepoint guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['savepoint_closed' => false]))['blocked_guard_names'], ['savepoint_closed']],
    'blocked wal action preserve' => [static fn (): mixed => $blockedCurrentReader()['wal_action'], 'preserve_wal'],
    'blocked journal action preserve' => [static fn (): mixed => $blockedCurrentReader()['journal_action'], 'preserve_hot_journal'],
    'blocked sync sequence pending' => [static fn (): mixed => $blockedCurrentReader()['sync_sequence'], ['database', 'wal-header-pending']],
    'blocked operation preserve present' => [static fn (): mixed => in_array('preserve_wal_until_restart_checkpoint_safe_restart_reader_release', $blockedCurrentReader()['operation_names'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source restart-reader-release ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(['status' => 'bad'], $readers, $options),
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, [], $options),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(array_merge($base, ['requested_checkpoint_frame' => 0]), $readers, $options),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(array_merge($base, ['database_digest' => 'short']), $readers, $options),
    'bad writer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease(array_merge($base, ['next_writer_generation' => 0]), $readers, $options),
    'bad salt rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, $readers, array_merge($options, ['wal_salt_before' => 'short'])),
    'missing reader name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, [array_merge($readers[0], ['name' => ''])], $options),
    'bad reader released rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, [array_merge($readers[0], ['released' => 'yes'])], $options),
    'bad reader frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, [array_merge($readers[0], ['reader_end_frame' => 0])], $options),
    'bad reader digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::restartCheckpointAfterReaderRelease($base, [array_merge($readers[0], ['observed_writer_digest' => 'short'])], $options),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source restart-reader-release ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
