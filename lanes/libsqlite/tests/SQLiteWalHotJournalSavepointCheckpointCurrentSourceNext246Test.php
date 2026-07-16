<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('next246 checkpoint database image');
$pageCacheDigest = $hash('next246 clean checkpoint page cache');
$readerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next243',
    'reader_snapshot_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next246.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next246.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next246.sqlite-wal',
    'source_token' => 'wp-next246-current-source',
    'commit_generation' => 246,
    'schema_cookie' => 946,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'checkpoint_frame' => 28,
    'dirty_pages' => [1, 2, 5, 9],
    'commit_frames' => [25, 26, 28],
    'accepted_reader_names' => ['schema-reader', 'options-reader', 'autoload-index-reader'],
    'operation_names' => ['admit_reopened_reader_snapshot_baseline_next243'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243'],
];

$receipt = static function (string $name, string $target, string $operation, int $sequence, array $override = []) use ($readerPlan): array {
    $paths = [
        'database' => $readerPlan['database_path'],
        'wal' => $readerPlan['wal_path'],
        'journal' => $readerPlan['journal_path'],
        'directory' => dirname($readerPlan['database_path']),
    ];

    return array_replace([
        'name' => $name,
        'target' => $target,
        'operation' => $operation,
        'path' => $paths[$target],
        'sequence' => $sequence,
        'pages' => [],
        'frames' => [],
        'source_token' => $readerPlan['source_token'],
        'commit_generation' => $readerPlan['commit_generation'],
        'exclusive_lock_held' => true,
        'savepoint_replayable' => true,
        'io_error' => null,
    ], $override);
};

$receipts = [
    $receipt('write-schema-page', 'database', 'write_database_page', 1, ['pages' => [1]]),
    $receipt('write-options-page', 'database', 'write_database_page', 2, ['pages' => [2, 5]]),
    $receipt('write-index-page', 'database', 'write_database_page', 3, ['pages' => [9]]),
    $receipt('mark-wal-commit-frames', 'wal', 'mark_wal_commit_frame', 4, ['frames' => [25, 26, 28]]),
    $receipt('sync-database', 'database', 'sync', 5),
    $receipt('sync-wal', 'wal', 'sync', 6),
    $receipt('sync-directory', 'directory', 'sync', 7),
    $receipt('delete-hot-journal', 'journal', 'delete', 8),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, $receipts);
$blockedPlan = static fn (array $replaceReceipt, ?array $replacePlan = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(
    $replacePlan ?? $readerPlan,
    array_replace($receipts, [1 => array_replace($receipts[1], $replaceReceipt)])
);
$withoutReceipt = static fn (int $index): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(
    $readerPlan,
    array_values(array_filter($receipts, static fn (array $_, int $key): bool => $key !== $index, ARRAY_FILTER_USE_BOTH))
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next246'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'durable_vfs_handoff_promotes_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next243'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next246.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next246.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next246.sqlite-journal'],
    'source token' => [static fn (): mixed => $plan()['source_token'], 'wp-next246-current-source'],
    'commit generation' => [static fn (): mixed => $plan()['commit_generation'], 246],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 946],
    'database digest' => [static fn (): mixed => $plan()['database_digest'], $databaseDigest],
    'page cache digest' => [static fn (): mixed => $plan()['page_cache_digest'], $pageCacheDigest],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 28],
    'dirty pages' => [static fn (): mixed => $plan()['dirty_pages'], [1, 2, 5, 9]],
    'commit frames' => [static fn (): mixed => $plan()['commit_frames'], [25, 26, 28]],
    'reader names' => [static fn (): mixed => $plan()['accepted_reader_names'], ['schema-reader', 'options-reader', 'autoload-index-reader']],
    'write receipt names' => [static fn (): mixed => $plan()['write_receipt_names'], ['write-schema-page', 'write-options-page', 'write-index-page', 'mark-wal-commit-frames', 'sync-database', 'sync-wal', 'sync-directory', 'delete-hot-journal']],
    'accepted write receipts' => [static fn (): mixed => $plan()['accepted_write_receipt_names'], ['write-schema-page', 'write-options-page', 'write-index-page', 'mark-wal-commit-frames', 'sync-database', 'sync-wal', 'sync-directory', 'delete-hot-journal']],
    'blocked write receipts empty' => [static fn (): mixed => $plan()['blocked_write_receipt_names'], []],
    'duplicate receipts empty' => [static fn (): mixed => $plan()['duplicate_write_receipt_names'], []],
    'written pages' => [static fn (): mixed => $plan()['written_database_pages'], [1, 2, 5, 9]],
    'missing pages empty' => [static fn (): mixed => $plan()['missing_database_pages'], []],
    'written frames' => [static fn (): mixed => $plan()['written_commit_frames'], [25, 26, 28]],
    'missing frames empty' => [static fn (): mixed => $plan()['missing_commit_frames'], []],
    'sync targets' => [static fn (): mixed => $plan()['sync_targets'], ['database', 'wal', 'directory']],
    'missing sync empty' => [static fn (): mixed => $plan()['missing_sync_targets'], []],
    'delete targets' => [static fn (): mixed => $plan()['delete_targets'], ['journal']],
    'operation order' => [static fn (): mixed => $plan()['operation_order'], ['write_database_page', 'write_database_page', 'write_database_page', 'mark_wal_commit_frame', 'sync:database', 'sync:wal', 'sync:directory', 'delete']],
    'write order safe' => [static fn (): mixed => $plan()['write_order_safe'], true],
    'blocked reasons empty' => [static fn (): mixed => $plan()['blocked_write_reasons'], []],
    'handoff admitted' => [static fn (): mixed => $plan()['durable_handoff_admitted'], true],
    'checkpoint action' => [static fn (): mixed => $plan()['checkpoint_action'], 'publish_database_image_as_checkpoint_current_source'],
    'wal action' => [static fn (): mixed => $plan()['wal_action'], 'retain_committed_frames_until_reader_epoch_advances'],
    'journal action' => [static fn (): mixed => $plan()['journal_action'], 'delete_hot_journal_after_directory_sync'],
    'savepoint action' => [static fn (): mixed => $plan()['savepoint_action'], 'release_savepoint_after_vfs_handoff'],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next243_reader_snapshot_admitted', 'vfs_write_receipt_names_unique', 'all_dirty_pages_written_to_database', 'all_commit_frames_marked', 'database_wal_directory_synced', 'hot_journal_deleted_after_sync', 'all_vfs_receipts_accepted']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'row target' => [static fn (): mixed => $plan()['write_rows'][1]['target'], 'database'],
    'row operation' => [static fn (): mixed => $plan()['write_rows'][1]['operation'], 'write_database_page'],
    'row pages' => [static fn (): mixed => $plan()['write_rows'][1]['pages'], [2, 5]],
    'row reason' => [static fn (): mixed => $plan()['write_rows'][1]['receipt_reason'], 'vfs_write_receipt_matches_checkpoint_current_source'],
    'handoff digest length' => [static fn (): mixed => strlen($plan()['handoff_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('admit_reopened_reader_snapshot_baseline_next243', $plan()['operation_names'], true), true],
    'operation added' => [static fn (): mixed => in_array('admit_durable_current_source_handoff_next246', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next243', $plan()['dependencies'], true), true],
    'dependency next246' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next246', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-current-source', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat reader snapshot matching'), true],
    'path mismatch blocked' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['blocked_write_reasons'], ['vfs_write_path_mismatch', 'checkpoint_database_page_write_missing']],
    'source token mismatch blocked' => [static fn (): mixed => $blockedPlan(['source_token' => 'old-source'])['blocked_write_reasons'], ['vfs_write_source_token_mismatch', 'checkpoint_database_page_write_missing']],
    'generation mismatch blocked' => [static fn (): mixed => $blockedPlan(['commit_generation' => 245])['blocked_write_reasons'], ['vfs_write_commit_generation_mismatch', 'checkpoint_database_page_write_missing']],
    'lock missing blocked' => [static fn (): mixed => $blockedPlan(['exclusive_lock_held' => false])['blocked_write_reasons'], ['vfs_write_exclusive_lock_missing', 'checkpoint_database_page_write_missing']],
    'savepoint not replayable blocked' => [static fn (): mixed => $blockedPlan(['savepoint_replayable' => false])['blocked_write_reasons'], ['vfs_write_savepoint_not_replayable', 'checkpoint_database_page_write_missing']],
    'io error blocked' => [static fn (): mixed => $blockedPlan(['io_error' => 'SQLITE_IOERR_FSYNC'])['blocked_write_reasons'], ['vfs_write_io_error', 'checkpoint_database_page_write_missing']],
    'missing dirty page blocked' => [static fn (): mixed => $blockedPlan(['pages' => [2]])['missing_database_pages'], [5]],
    'missing commit frames blocked' => [static fn (): mixed => $withoutReceipt(3)['blocked_write_reasons'], ['checkpoint_commit_frame_mark_missing', 'checkpoint_current_source_write_order_unsafe']],
    'missing wal sync blocked' => [static fn (): mixed => $withoutReceipt(5)['missing_sync_targets'], ['wal']],
    'missing hot journal delete blocked' => [static fn (): mixed => $withoutReceipt(7)['blocked_write_reasons'], ['checkpoint_hot_journal_delete_missing', 'checkpoint_current_source_write_order_unsafe']],
    'unsafe order blocked' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, array_merge([$receipts[7]], array_slice($receipts, 0, 7)))['blocked_guard_names'], ['hot_journal_deleted_after_sync']],
    'duplicate receipt names' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, array_replace($receipts, [2 => array_replace($receipts[2], ['name' => 'write-options-page'])]))['duplicate_write_receipt_names'], ['write-options-page']],
    'blocked status' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next246'],
    'blocked reason' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['reason'], 'durable_vfs_handoff_holds_checkpoint_current_source'],
    'blocked checkpoint action' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['checkpoint_action'], 'retain_previous_current_source_until_vfs_handoff_is_durable'],
    'blocked wal action' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['wal_action'], 'hold_wal_reset_and_restart'],
    'blocked journal action' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['journal_action'], 'keep_hot_journal_recovery_visible'],
    'blocked savepoint action' => [static fn (): mixed => $blockedPlan(['path' => '/tmp/wrong.sqlite'])['savepoint_action'], 'keep_savepoint_scope_replayable'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next246 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad base rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['status' => 'bad']), $receipts),
    'not admitted rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['reader_snapshot_admitted' => false]), $receipts),
    'empty receipts rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, []),
    'bad database path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['database_path' => '']), $receipts),
    'bad wal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['wal_path' => '']), $receipts),
    'bad journal path rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['journal_path' => '']), $receipts),
    'bad source token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['source_token' => 'bad token']), $receipts),
    'bad generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['commit_generation' => 0]), $receipts),
    'bad schema cookie rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['schema_cookie' => 0]), $receipts),
    'bad database digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['database_digest' => 'short']), $receipts),
    'bad page cache digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['page_cache_digest' => 'short']), $receipts),
    'bad checkpoint frame rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['checkpoint_frame' => -1]), $receipts),
    'bad dirty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['dirty_pages' => []]), $receipts),
    'bad commit frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['commit_frames' => [0]]), $receipts),
    'bad reader names rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff(array_replace($readerPlan, ['accepted_reader_names' => []]), $receipts),
    'bad receipt name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['name' => 'bad name'])]),
    'bad receipt target rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['target' => 'temp'])]),
    'bad receipt operation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['operation' => 'truncate'])]),
    'bad receipt sequence rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['sequence' => 0])]),
    'bad receipt pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['pages' => ['bad']])]),
    'bad receipt frames rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[3], ['frames' => ['bad']])]),
    'bad receipt token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['source_token' => 'bad token'])]),
    'bad receipt generation rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next246AdmitDurableCurrentSourceHandoff($readerPlan, [array_replace($receipts[0], ['commit_generation' => 0])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next246 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
