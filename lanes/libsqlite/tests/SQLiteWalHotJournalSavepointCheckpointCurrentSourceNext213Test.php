<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('next213 checkpoint database');
$walDigest = $digest('next213 retained wal');
$writerDigest = $digest('next213 writer generation');
$checkpointDigest = $digest('next213 passive checkpoint rows');
$oldDatabaseDigest = $digest('next213 old database');
$oldWalDigest = $digest('next213 old wal');
$oldWriterDigest = $digest('next213 old writer');
$oldCheckpointDigest = $digest('next213 old checkpoint');
$hotJournalDigest = $digest('next213 retained hot journal');

$base = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'database_path' => '/srv/www/wp-content/database/wp-next213.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next213.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next213.sqlite-wal',
    'page_size' => 512,
    'requested_checkpoint_frame' => 213,
    'checkpointed_frame' => 210,
    'busy' => true,
    'wal_action' => 'preserve_wal',
    'database_digest' => $databaseDigest,
    'wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'checkpoint_digest' => $checkpointDigest,
    'next_writer_generation' => 213,
    'minimum_statement_generation' => 209,
    'active_reader_names' => ['wp-options-current-reader', 'wp-cron-current-reader'],
    'reopen_reader_names' => ['old-generation-reader', 'old-frame-reader', 'old-database-reader', 'old-wal-reader', 'old-writer-reader', 'hot-journal-reader', 'savepoint-reader', 'missing-lock-reader', 'dirty-reader', 'closed-reader'],
    'operation_names' => ['verify_passive_checkpoint_reader_pin_current_source_next212'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next212'],
];

$receipt = static fn (string $name): array => [
    'name' => $name,
    'reader_end_frame' => 213,
    'reader_generation' => 213,
    'observed_database_digest' => $databaseDigest,
    'observed_wal_digest' => $walDigest,
    'observed_writer_digest' => $writerDigest,
    'observed_checkpoint_digest' => $checkpointDigest,
    'lock_receipt' => true,
];

$receipts = [
    $receipt('old-generation-reader'),
    $receipt('old-frame-reader'),
    $receipt('old-database-reader'),
    $receipt('old-wal-reader'),
    $receipt('old-writer-reader'),
    $receipt('hot-journal-reader'),
    $receipt('savepoint-reader'),
    $receipt('missing-lock-reader'),
    $receipt('dirty-reader'),
    $receipt('closed-reader'),
];

$blockedReceipts = [
    array_merge($receipt('old-generation-reader'), ['reader_generation' => 212]),
    array_merge($receipt('old-frame-reader'), ['reader_end_frame' => 208]),
    array_merge($receipt('future-frame-reader'), ['reader_end_frame' => 214]),
    array_merge($receipt('old-database-reader'), ['observed_database_digest' => $oldDatabaseDigest]),
    array_merge($receipt('old-wal-reader'), ['observed_wal_digest' => $oldWalDigest]),
    array_merge($receipt('old-writer-reader'), ['observed_writer_digest' => $oldWriterDigest]),
    array_merge($receipt('old-checkpoint-reader'), ['observed_checkpoint_digest' => $oldCheckpointDigest]),
    array_merge($receipt('hot-journal-reader'), ['hot_journal_digest' => $hotJournalDigest]),
    array_merge($receipt('savepoint-reader'), ['savepoint_depth' => 1]),
    array_merge($receipt('missing-lock-reader'), ['lock_receipt' => false]),
    array_merge($receipt('dirty-reader'), ['dirty' => true]),
    array_merge($receipt('closed-reader'), ['closed' => true]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, $receipts, 213);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, $blockedReceipts, 213);
$missing = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, array_slice($receipts, 0, 8), 213);
$activeReuse = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, array_merge($receipts, [$receipt('wp-options-current-reader')]), 213);
$earlyTarget = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, $receipts, 212);
$notBusy = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(array_merge($base, ['busy' => false, 'wal_action' => 'passive_checkpoint_complete']), $receipts, 213);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next213'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'restart_checkpoint_admits_only_reopened_hot_journal_free_reader_receipts'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next212'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next213.sqlite'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next213.sqlite-journal'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next213.sqlite-wal'],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'requested frame' => [static fn (): mixed => $plan()['requested_checkpoint_frame'], 213],
    'passive checkpointed frame' => [static fn (): mixed => $plan()['passive_checkpointed_frame'], 210],
    'target frame' => [static fn (): mixed => $plan()['target_checkpoint_frame'], 213],
    'target complete' => [static fn (): mixed => $plan()['target_complete'], true],
    'restart allowed' => [static fn (): mixed => $plan()['restart_allowed'], true],
    'reset allowed' => [static fn (): mixed => $plan()['reset_allowed'], true],
    'truncate disallowed' => [static fn (): mixed => $plan()['truncate_allowed'], false],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'restart_wal_after_reopened_readers'],
    'database action' => [static fn (): mixed => $plan()['database_action'], 'write_frames_through_213'],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $plan()['wal_digest'], $walDigest],
    'writer digest' => [static fn (): mixed => $plan()['writer_digest'], $writerDigest],
    'checkpoint digest' => [static fn (): mixed => $plan()['checkpoint_digest'], $checkpointDigest],
    'writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 213],
    'minimum generation' => [static fn (): mixed => $plan()['minimum_statement_generation'], 209],
    'active readers preserved' => [static fn (): mixed => $plan()['active_reader_names'], ['wp-options-current-reader', 'wp-cron-current-reader']],
    'required reopen readers' => [static fn (): mixed => $plan()['required_reopen_reader_names'], ['old-generation-reader', 'old-frame-reader', 'old-database-reader', 'old-wal-reader', 'old-writer-reader', 'hot-journal-reader', 'savepoint-reader', 'missing-lock-reader', 'dirty-reader', 'closed-reader']],
    'admitted reopen readers' => [static fn (): mixed => $plan()['admitted_reopen_reader_names'], ['old-generation-reader', 'old-frame-reader', 'old-database-reader', 'old-wal-reader', 'old-writer-reader', 'hot-journal-reader', 'savepoint-reader', 'missing-lock-reader', 'dirty-reader', 'closed-reader']],
    'blocked reopen readers empty' => [static fn (): mixed => $plan()['blocked_reopen_reader_names'], []],
    'missing reopen readers empty' => [static fn (): mixed => $plan()['missing_reopen_reader_names'], []],
    'unexpected active reader receipts empty' => [static fn (): mixed => $plan()['unexpected_active_reader_receipts'], []],
    'first receipt admitted' => [static fn (): mixed => $plan()['receipt_rows'][0]['admitted'], true],
    'first receipt reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'reader_reopened_on_current_source_for_restart_checkpoint'],
    'first expected target' => [static fn (): mixed => $plan()['receipt_rows'][0]['expected_target_frame'], 213],
    'first expected generation' => [static fn (): mixed => $plan()['receipt_rows'][0]['expected_generation'], 213],
    'first lock receipt' => [static fn (): mixed => $plan()['receipt_rows'][0]['lock_receipt'], true],
    'first hot journal retained false' => [static fn (): mixed => $plan()['receipt_rows'][0]['hot_journal_retained'], false],
    'first savepoint depth' => [static fn (): mixed => $plan()['receipt_rows'][0]['savepoint_depth'], 0],
    'first dirty false' => [static fn (): mixed => $plan()['receipt_rows'][0]['dirty'], false],
    'first closed false' => [static fn (): mixed => $plan()['receipt_rows'][0]['closed'], false],
    'receipt blocked reasons empty' => [static fn (): mixed => $plan()['receipt_blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next212_partial_passive_checkpoint', 'target_frame_reaches_requested_checkpoint', 'stale_reader_reopen_receipts_complete', 'active_reader_pins_not_reused_for_reset', 'receipt_rows_current_source_clean']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'verify_passive_checkpoint_reader_pin_current_source_next212'],
    'operation verify present' => [static fn (): mixed => in_array('verify_restart_checkpoint_reader_reopen_receipts_current_source_next213', $plan()['operation_names'], true), true],
    'operation restart present' => [static fn (): mixed => in_array('restart_wal_after_reopened_reader_receipts_next213', $plan()['operation_names'], true), true],
    'restart receipt digest length' => [static fn (): mixed => strlen($plan()['restart_receipt_digest']), 64],
    'dependency next213' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next213', $plan()['dependencies'], true), true],
    'dependency restart receipts' => [static fn (): mixed => in_array('sqlite-restart-checkpoint-reader-reopen-receipts-after-hot-journal', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-restart-checkpoint-reopens-stale-readers', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next212 passive frame selection'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next213'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'restart_checkpoint_waits_for_reopened_current_source_reader_receipts'],
    'blocked wal action' => [static fn (): mixed => $blocked()['wal_action'], 'preserve_wal_until_reopen'],
    'blocked database action' => [static fn (): mixed => $blocked()['database_action'], 'write_frames_through_210'],
    'blocked restart disallowed' => [static fn (): mixed => $blocked()['restart_allowed'], false],
    'blocked reset disallowed' => [static fn (): mixed => $blocked()['reset_allowed'], false],
    'blocked reader names' => [static fn (): mixed => $blocked()['blocked_reopen_reader_names'], ['old-generation-reader', 'old-frame-reader', 'future-frame-reader', 'old-database-reader', 'old-wal-reader', 'old-writer-reader', 'old-checkpoint-reader', 'hot-journal-reader', 'savepoint-reader', 'missing-lock-reader', 'dirty-reader', 'closed-reader']],
    'blocked reasons' => [static fn (): mixed => $blocked()['receipt_blocked_reasons'], ['receipt_generation_mismatch', 'receipt_frame_before_current_statement', 'receipt_frame_before_restart_target', 'receipt_frame_after_restart_target', 'receipt_database_digest_mismatch', 'receipt_wal_digest_mismatch', 'receipt_writer_digest_mismatch', 'receipt_checkpoint_digest_mismatch', 'receipt_retains_hot_journal_digest', 'receipt_savepoint_scope_not_closed', 'receipt_missing_shared_lock', 'receipt_cache_dirty', 'receipt_handle_closed']],
    'blocked guard clean receipts' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['stale_reader_reopen_receipts_complete', 'receipt_rows_current_source_clean']],
    'generation reason' => [static fn (): mixed => $blocked()['receipt_rows'][0]['receipt_reason'], 'receipt_generation_mismatch'],
    'frame before reason' => [static fn (): mixed => $blocked()['receipt_rows'][1]['receipt_reason'], 'receipt_frame_before_current_statement|receipt_frame_before_restart_target'],
    'future frame reason' => [static fn (): mixed => $blocked()['receipt_rows'][2]['receipt_reason'], 'receipt_frame_after_restart_target'],
    'database reason' => [static fn (): mixed => $blocked()['receipt_rows'][3]['receipt_reason'], 'receipt_database_digest_mismatch'],
    'wal reason' => [static fn (): mixed => $blocked()['receipt_rows'][4]['receipt_reason'], 'receipt_wal_digest_mismatch'],
    'writer reason' => [static fn (): mixed => $blocked()['receipt_rows'][5]['receipt_reason'], 'receipt_writer_digest_mismatch'],
    'checkpoint reason' => [static fn (): mixed => $blocked()['receipt_rows'][6]['receipt_reason'], 'receipt_checkpoint_digest_mismatch'],
    'hot journal reason' => [static fn (): mixed => $blocked()['receipt_rows'][7]['receipt_reason'], 'receipt_retains_hot_journal_digest'],
    'hot journal retained flag' => [static fn (): mixed => $blocked()['receipt_rows'][7]['hot_journal_retained'], true],
    'savepoint reason' => [static fn (): mixed => $blocked()['receipt_rows'][8]['receipt_reason'], 'receipt_savepoint_scope_not_closed'],
    'savepoint depth' => [static fn (): mixed => $blocked()['receipt_rows'][8]['savepoint_depth'], 1],
    'missing lock reason' => [static fn (): mixed => $blocked()['receipt_rows'][9]['receipt_reason'], 'receipt_missing_shared_lock'],
    'missing lock flag' => [static fn (): mixed => $blocked()['receipt_rows'][9]['lock_receipt'], false],
    'dirty reason' => [static fn (): mixed => $blocked()['receipt_rows'][10]['receipt_reason'], 'receipt_cache_dirty'],
    'dirty flag' => [static fn (): mixed => $blocked()['receipt_rows'][10]['dirty'], true],
    'closed reason' => [static fn (): mixed => $blocked()['receipt_rows'][11]['receipt_reason'], 'receipt_handle_closed'],
    'closed flag' => [static fn (): mixed => $blocked()['receipt_rows'][11]['closed'], true],
    'missing receipts guard' => [static fn (): mixed => $missing()['blocked_guard_names'], ['stale_reader_reopen_receipts_complete']],
    'missing receipts names' => [static fn (): mixed => $missing()['missing_reopen_reader_names'], ['dirty-reader', 'closed-reader']],
    'active reuse guard' => [static fn (): mixed => $activeReuse()['blocked_guard_names'], ['active_reader_pins_not_reused_for_reset']],
    'active reuse names' => [static fn (): mixed => $activeReuse()['unexpected_active_reader_receipts'], ['wp-options-current-reader']],
    'early target guard' => [static fn (): mixed => $earlyTarget()['blocked_guard_names'], ['target_frame_reaches_requested_checkpoint', 'stale_reader_reopen_receipts_complete', 'receipt_rows_current_source_clean']],
    'early target complete false' => [static fn (): mixed => $earlyTarget()['target_complete'], false],
    'not busy guard' => [static fn (): mixed => $notBusy()['blocked_guard_names'], ['next212_partial_passive_checkpoint']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next213 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(['status' => 'bad'], $receipts, 213),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, [], 213),
    'zero target rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, $receipts, 0),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(array_merge($base, ['database_digest' => 'short']), $receipts, 213),
    'bad checkpointed frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(array_merge($base, ['checkpointed_frame' => 0]), $receipts, 213),
    'bad writer generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(array_merge($base, ['next_writer_generation' => 0]), $receipts, 213),
    'bad minimum generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(array_merge($base, ['minimum_statement_generation' => -1]), $receipts, 213),
    'bad active reader list rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission(array_merge($base, ['active_reader_names' => [null]]), $receipts, 213),
    'missing receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, [array_merge($receipts[0], ['name' => ''])], 213),
    'bad receipt frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, [array_merge($receipts[0], ['reader_end_frame' => 0])], 213),
    'bad receipt digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, [array_merge($receipts[0], ['observed_wal_digest' => 'short'])], 213),
    'bad hot journal digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next213RestartAdmission($base, [array_merge($receipts[0], ['hot_journal_digest' => 'short'])], 213),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next213 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
