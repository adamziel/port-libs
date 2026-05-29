<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next202.sqlite';
$databaseBytes = str_repeat('next202 checkpoint database page ', 20);
$databaseDigest = hash('sha256', $databaseBytes);
$walDigest = hash('sha256', 'next202 restarted wal sidecar');
$sidecarDigest = hash('sha256', 'next202 sidecar publication');
$publication = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next196',
    'database_path' => $databasePath,
    'journal_path' => $databasePath . '-journal',
    'wal_path' => $databasePath . '-wal',
    'mode' => 'restart',
    'checkpointed_database_digest' => $databaseDigest,
    'persisted_wal_digest' => $walDigest,
    'sidecar_digest' => $sidecarDigest,
    'operation_names' => ['publish_wal_sidecar_current_source_next196'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next196'],
];
$handles = [
    ['name' => 'wp-options-select-current', 'kind' => 'statement', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
    ['name' => 'wp-postmeta-reader-current', 'kind' => 'reader', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
    ['name' => 'wp-import-writer-current', 'kind' => 'writer', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
    ['name' => 'stale-database-handle', 'kind' => 'statement', 'observed_database_digest' => hash('sha256', 'old database'), 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
    ['name' => 'stale-wal-handle', 'kind' => 'reader', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => hash('sha256', 'old wal'), 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart'],
    ['name' => 'stale-sidecar-handle', 'kind' => 'reader', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => hash('sha256', 'old sidecar'), 'observed_mode' => 'restart'],
    ['name' => 'stale-mode-handle', 'kind' => 'reader', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'truncate'],
    ['name' => 'dirty-handle', 'kind' => 'statement', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart', 'dirty' => true],
    ['name' => 'closed-handle', 'kind' => 'reader', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart', 'closed' => true],
];

$plan = static fn (
    ?array $base = null,
    ?string $db = null,
    string $journal = '',
    bool $savepoint = true,
    bool $lock = true,
    bool $dbSync = true,
    bool $walSync = true,
    bool $dirSync = true,
    ?array $rows = null
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan(
    $base ?? $publication,
    $db ?? $databaseBytes,
    $journal,
    $savepoint,
    $lock,
    $dbSync,
    $walSync,
    $dirSync,
    $rows ?? $handles
);
$ok = static fn (): array => $plan();
$truncatePublication = $publication;
$truncatePublication['mode'] = 'truncate';
$truncatePublication['persisted_wal_digest'] = hash('sha256', '');
$truncatePublication['sidecar_digest'] = hash('sha256', 'next202 truncated sidecar');
$truncateHandles = [
    ['name' => 'wp-options-truncate-current', 'kind' => 'statement', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => hash('sha256', ''), 'observed_sidecar_digest' => $truncatePublication['sidecar_digest'], 'observed_mode' => 'truncate'],
    ['name' => 'wp-reader-needs-wal-after-truncate', 'kind' => 'reader', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => hash('sha256', ''), 'observed_sidecar_digest' => $truncatePublication['sidecar_digest'], 'observed_mode' => 'truncate', 'requires_wal_sidecar' => true],
];
$truncate = static fn (): array => $plan($truncatePublication, null, '', true, true, true, true, true, $truncateHandles);
$badStatus = $publication;
$badStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next196';

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next202'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'checkpoint_file_receipts_admit_current_source_handles_after_hot_journal_savepoint'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'restart'],
    'database digest' => [static fn (): mixed => $ok()['database_digest'], $databaseDigest],
    'expected database digest' => [static fn (): mixed => $ok()['expected_database_digest'], $databaseDigest],
    'wal digest' => [static fn (): mixed => $ok()['persisted_wal_digest'], $walDigest],
    'sidecar digest' => [static fn (): mixed => $ok()['sidecar_digest'], $sidecarDigest],
    'hot digest empty' => [static fn (): mixed => $ok()['hot_journal_digest'], hash('sha256', '')],
    'hot length' => [static fn (): mixed => $ok()['hot_journal_bytes_length'], 0],
    'savepoint released' => [static fn (): mixed => $ok()['savepoint_released'], true],
    'exclusive lock' => [static fn (): mixed => $ok()['exclusive_checkpoint_lock'], true],
    'database sync' => [static fn (): mixed => $ok()['database_sync_receipt'], true],
    'wal sync' => [static fn (): mixed => $ok()['wal_sync_receipt'], true],
    'directory sync' => [static fn (): mixed => $ok()['directory_sync_receipt'], true],
    'handle count' => [static fn (): mixed => count($ok()['handle_rows']), 9],
    'admitted handles' => [static fn (): mixed => $ok()['admitted_handle_names'], ['wp-options-select-current', 'wp-postmeta-reader-current', 'wp-import-writer-current']],
    'reopen handles' => [static fn (): mixed => $ok()['reopen_handle_names'], ['stale-database-handle', 'stale-wal-handle', 'stale-sidecar-handle', 'stale-mode-handle', 'dirty-handle', 'closed-handle']],
    'first admitted' => [static fn (): mixed => $ok()['handle_rows'][0]['admitted'], true],
    'first reason' => [static fn (): mixed => $ok()['handle_rows'][0]['reason'], 'handle_receipts_match_checkpoint_current_source'],
    'first transition' => [static fn (): mixed => $ok()['handle_rows'][0]['transition'], 'wp-options-select-current>admit-current-source-handle:next202'],
    'writer kind' => [static fn (): mixed => $ok()['handle_rows'][2]['kind'], 'writer'],
    'database failure' => [static fn (): mixed => $ok()['handle_rows'][3]['failed_checks'], ['database_digest']],
    'wal failure' => [static fn (): mixed => $ok()['handle_rows'][4]['failed_checks'], ['wal_digest']],
    'sidecar failure' => [static fn (): mixed => $ok()['handle_rows'][5]['failed_checks'], ['sidecar_digest']],
    'mode failure' => [static fn (): mixed => $ok()['handle_rows'][6]['failed_checks'], ['checkpoint_mode']],
    'dirty failure' => [static fn (): mixed => $ok()['handle_rows'][7]['failed_checks'], ['not_dirty']],
    'closed failure' => [static fn (): mixed => $ok()['handle_rows'][8]['failed_checks'], ['not_closed']],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['next196_sidecar_publication', 'checkpointed_database_digest', 'hot_journal_removed', 'savepoint_released', 'exclusive_checkpoint_lock', 'sync_receipts', 'handle_mix']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true, true, true, true, true]],
    'blocked empty' => [static fn (): mixed => $ok()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => in_array('publish_wal_sidecar_current_source_next196', $ok()['operation_names'], true), true],
    'operation verify' => [static fn (): mixed => in_array('verify_checkpoint_file_receipts_current_source_next202', $ok()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_or_reopen_current_source_handles_next202', $ok()['operation_names'], true), true],
    'receipt digest length' => [static fn (): mixed => strlen($ok()['receipt_digest']), 64],
    'dependency next202' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next202', $ok()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-checkpoint-handle-reopen', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'bad status blocked' => [static fn (): mixed => $plan($badStatus)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next202'],
    'bad status guard' => [static fn (): mixed => $plan($badStatus)['blocked_guard_names'], ['next196_sidecar_publication']],
    'database digest blocked' => [static fn (): mixed => $plan(null, 'different database bytes')['blocked_guard_names'], ['checkpointed_database_digest']],
    'hot journal blocked' => [static fn (): mixed => $plan(null, null, 'hot journal bytes')['blocked_guard_names'], ['hot_journal_removed', 'handle_mix']],
    'savepoint blocked' => [static fn (): mixed => $plan(null, null, '', false)['blocked_guard_names'], ['savepoint_released', 'handle_mix']],
    'lock blocked' => [static fn (): mixed => $plan(null, null, '', true, false)['blocked_guard_names'], ['exclusive_checkpoint_lock', 'handle_mix']],
    'database sync blocked' => [static fn (): mixed => $plan(null, null, '', true, true, false)['blocked_guard_names'], ['sync_receipts', 'handle_mix']],
    'wal sync blocked' => [static fn (): mixed => $plan(null, null, '', true, true, true, false)['blocked_guard_names'], ['sync_receipts', 'handle_mix']],
    'directory sync blocked' => [static fn (): mixed => $plan(null, null, '', true, true, true, true, false)['blocked_guard_names'], ['sync_receipts', 'handle_mix']],
    'all current lacks mix' => [static fn (): mixed => $plan(null, null, '', true, true, true, true, true, array_slice($handles, 0, 3))['blocked_guard_names'], ['handle_mix']],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next202'],
    'truncate admitted' => [static fn (): mixed => $truncate()['admitted_handle_names'], ['wp-options-truncate-current']],
    'truncate reopen' => [static fn (): mixed => $truncate()['reopen_handle_names'], ['wp-reader-needs-wal-after-truncate']],
    'truncate wal required failure' => [static fn (): mixed => $truncate()['handle_rows'][1]['failed_checks'], ['wal_sidecar_required_after_truncate']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next202 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing publication rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan([], $databaseBytes, '', true, true, true, true, true, $handles),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan(array_merge($publication, ['mode' => 'passive']), $databaseBytes, '', true, true, true, true, true, $handles),
    'empty database rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan($publication, '', '', true, true, true, true, true, $handles),
    'missing handles rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan($publication, $databaseBytes, '', true, true, true, true, true, []),
    'missing handle name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan($publication, $databaseBytes, '', true, true, true, true, true, [['kind' => 'reader']]),
    'bad handle kind rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next202Plan($publication, $databaseBytes, '', true, true, true, true, true, [['name' => 'bad', 'kind' => 'cursor', 'observed_database_digest' => $databaseDigest, 'observed_wal_digest' => $walDigest, 'observed_sidecar_digest' => $sidecarDigest, 'observed_mode' => 'restart']]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next202 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
