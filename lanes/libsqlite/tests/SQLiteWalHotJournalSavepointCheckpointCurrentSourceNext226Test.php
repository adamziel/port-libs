<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next226.sqlite';
$walPath = $databasePath . '-wal';
$journalPath = $databasePath . '-journal';
$databaseBytes = str_repeat('next226 checkpointed wordpress options database image', 12);
$restartWalBytes = 'SQLite format wal restart next226 header salt frame zero';
$truncateWalBytes = "\0";
$databaseDigest = hash('sha256', $databaseBytes);
$restartWalDigest = hash('sha256', $restartWalBytes);
$truncateWalDigest = hash('sha256', $truncateWalBytes);
$staleDigest = hash('sha256', 'stale next226');

$reset = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next218',
    'mode' => 'restart',
    'database_path' => $databasePath,
    'wal_path' => $walPath,
    'journal_path' => $journalPath,
    'can_reset_wal' => true,
    'next_writer_generation' => 227,
    'operation_names' => ['publish_wal_reset_current_source_next218'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next218'],
];
$truncateReset = $reset;
$truncateReset['mode'] = 'truncate';

$files = [
    $databasePath => $databaseBytes,
    $walPath => $restartWalBytes,
];
$truncateFiles = [
    $databasePath => $databaseBytes,
    $walPath => $truncateWalBytes,
];
$receipt = static fn (array $overrides = []): array => array_merge([
    'name' => 'wp-import-writer-next226',
    'mode' => 'restart',
    'writer_generation' => 227,
    'database_digest' => $databaseDigest,
    'wal_digest' => $restartWalDigest,
    'database_synced' => true,
    'wal_synced' => true,
    'directory_synced' => true,
    'hot_journal_absent' => true,
    'savepoint_closed' => true,
    'readers_reopened' => true,
], $overrides);
$receipts = [
    $receipt(),
    $receipt(['name' => 'plugin-settings-writer-next226']),
];
$plan = static fn (?array $input = null, ?array $inputFiles = null, ?array $inputReceipts = null, ?string $wal = null, ?string $dbDigest = null): array =>
    SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next226VerifyResetFiles(
        $input ?? $reset,
        $inputFiles ?? $files,
        $inputReceipts ?? $receipts,
        $wal ?? $restartWalBytes,
        $dbDigest ?? $databaseDigest
    );
$truncatePlan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next226VerifyResetFiles(
    $truncateReset,
    $truncateFiles,
    [
        $receipt(['mode' => 'truncate', 'wal_digest' => $truncateWalDigest]),
    ],
    $truncateWalBytes,
    $databaseDigest
);

$badPlan = $reset;
$badPlan['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next218';
$badMode = $reset;
$badMode['mode'] = 'passive';
$badGeneration = $reset;
$badGeneration['next_writer_generation'] = 0;
$badFiles = $files;
$badFiles[$databasePath] = 'stale database';
$badWalFiles = $files;
$badWalFiles[$walPath] = 'stale wal';
$journalFiles = $files;
$journalFiles[$journalPath] = 'hot journal still here';
$missingDb = [$walPath => $restartWalBytes];
$missingWal = [$databasePath => $databaseBytes];

$blockedReceipts = [
    $receipt([
        'name' => 'bad-mode',
        'mode' => 'truncate',
    ]),
    $receipt([
        'name' => 'bad-generation',
        'writer_generation' => 226,
    ]),
    $receipt([
        'name' => 'bad-database',
        'database_digest' => $staleDigest,
    ]),
    $receipt([
        'name' => 'bad-wal',
        'wal_digest' => $staleDigest,
    ]),
    $receipt([
        'name' => 'bad-sync',
        'database_synced' => false,
        'wal_synced' => false,
        'directory_synced' => false,
        'hot_journal_absent' => false,
        'savepoint_closed' => false,
        'readers_reopened' => false,
    ]),
];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next226'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'restart_truncate_reset_files_match_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $plan()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next218'],
    'mode' => [static fn (): mixed => $plan()['mode'], 'restart'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], $walPath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'expected database digest' => [static fn (): mixed => $plan()['expected_database_digest'], $databaseDigest],
    'observed database digest' => [static fn (): mixed => $plan()['observed_database_digest'], $databaseDigest],
    'expected wal digest' => [static fn (): mixed => $plan()['expected_wal_digest'], $restartWalDigest],
    'observed wal digest' => [static fn (): mixed => $plan()['observed_wal_digest'], $restartWalDigest],
    'expected wal length' => [static fn (): mixed => $plan()['expected_wal_length'], strlen($restartWalBytes)],
    'observed wal length' => [static fn (): mixed => $plan()['observed_wal_length'], strlen($restartWalBytes)],
    'journal absent' => [static fn (): mixed => $plan()['journal_present'], false],
    'next writer generation' => [static fn (): mixed => $plan()['next_writer_generation'], 227],
    'receipt count' => [static fn (): mixed => $plan()['receipt_count'], 2],
    'receipt names' => [static fn (): mixed => $plan()['receipt_names'], ['wp-import-writer-next226', 'plugin-settings-writer-next226']],
    'blocked empty' => [static fn (): mixed => $plan()['blocked_reasons'], []],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next218_reset_admitted', 'database_bytes_match_checkpoint_digest', 'wal_bytes_match_reset_mode', 'hot_journal_absent_after_reset', 'all_reset_receipts_match_current_source']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'can publish' => [static fn (): mixed => $plan()['can_publish_reopened_current_source'], true],
    'operation inherited' => [static fn (): mixed => $plan()['operation_names'][0], 'publish_wal_reset_current_source_next218'],
    'operation verify' => [static fn (): mixed => in_array('verify_reset_file_state_current_source_next226', $plan()['operation_names'], true), true],
    'operation publish' => [static fn (): mixed => in_array('publish_reopened_current_source_after_reset_next226', $plan()['operation_names'], true), true],
    'publication digest length' => [static fn (): mixed => strlen($plan()['publication_digest']), 64],
    'dependency next226' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next226', $plan()['dependencies'], true), true],
    'dependency reopen fence' => [static fn (): mixed => in_array('sqlite-wal-reset-file-state-reopen-fence', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-wal-reset-file-state-reopen', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next218 reset admission'), true],
    'database file matched' => [static fn (): mixed => $plan()['file_rows'][0]['matched'], true],
    'wal file matched' => [static fn (): mixed => $plan()['file_rows'][1]['matched'], true],
    'journal file matched' => [static fn (): mixed => $plan()['file_rows'][2]['matched'], true],
    'database file name' => [static fn (): mixed => $plan()['file_rows'][0]['name'], 'database'],
    'wal file name' => [static fn (): mixed => $plan()['file_rows'][1]['name'], 'wal'],
    'journal file name' => [static fn (): mixed => $plan()['file_rows'][2]['name'], 'hot-journal'],
    'first receipt admitted' => [static fn (): mixed => $plan()['receipt_rows'][0]['admitted'], true],
    'first receipt reason' => [static fn (): mixed => $plan()['receipt_rows'][0]['receipt_reason'], 'reset_file_state_receipt_matches_current_source'],
    'first receipt database matches' => [static fn (): mixed => $plan()['receipt_rows'][0]['database_digest_matches'], true],
    'first receipt wal matches' => [static fn (): mixed => $plan()['receipt_rows'][0]['wal_digest_matches'], true],
    'first receipt db sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['database_synced'], true],
    'first receipt wal sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['wal_synced'], true],
    'first receipt directory sync' => [static fn (): mixed => $plan()['receipt_rows'][0]['directory_synced'], true],
    'first receipt journal absent' => [static fn (): mixed => $plan()['receipt_rows'][0]['hot_journal_absent'], true],
    'first receipt savepoint closed' => [static fn (): mixed => $plan()['receipt_rows'][0]['savepoint_closed'], true],
    'first receipt readers reopened' => [static fn (): mixed => $plan()['receipt_rows'][0]['readers_reopened'], true],
    'truncate status' => [static fn (): mixed => $truncatePlan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next226'],
    'truncate mode' => [static fn (): mixed => $truncatePlan()['mode'], 'truncate'],
    'truncate wal digest' => [static fn (): mixed => $truncatePlan()['expected_wal_digest'], $truncateWalDigest],
    'truncate wal length' => [static fn (): mixed => $truncatePlan()['observed_wal_length'], 1],
    'blocked database status' => [static fn (): mixed => $plan(null, $badFiles)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next226'],
    'blocked database reason' => [static fn (): mixed => $plan(null, $badFiles)['blocked_reasons'], ['database_digest_mismatch']],
    'blocked wal reason' => [static fn (): mixed => $plan(null, $badWalFiles)['blocked_reasons'], ['wal_reset_bytes_mismatch']],
    'blocked journal reason' => [static fn (): mixed => $plan(null, $journalFiles)['blocked_reasons'], ['hot_journal_still_present_after_reset']],
    'missing database reason' => [static fn (): mixed => $plan(null, $missingDb)['blocked_reasons'], ['database_file_missing_after_reset']],
    'missing wal reason' => [static fn (): mixed => $plan(null, $missingWal)['blocked_reasons'], ['wal_file_missing_after_reset']],
    'blocked receipt names' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['receipt_names'], ['bad-mode', 'bad-generation', 'bad-database', 'bad-wal', 'bad-sync']],
    'blocked receipt status' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next226'],
    'blocked receipt guards' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['blocked_guard_names'], ['all_reset_receipts_match_current_source']],
    'bad mode receipt reason' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['receipt_rows'][0]['receipt_reason'], 'reset_mode_mismatch'],
    'bad generation receipt reason' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['receipt_rows'][1]['receipt_reason'], 'writer_generation_mismatch'],
    'bad database receipt reason' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['receipt_rows'][2]['receipt_reason'], 'receipt_database_digest_mismatch'],
    'bad wal receipt reason' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['receipt_rows'][3]['receipt_reason'], 'receipt_wal_digest_mismatch'],
    'bad sync receipt reason' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['receipt_rows'][4]['receipt_reason'], 'database_sync_missing,wal_sync_missing,directory_sync_missing,hot_journal_absence_receipt_missing,savepoint_closed_receipt_missing,readers_reopened_receipt_missing'],
    'blocked reasons unique' => [static fn (): mixed => $plan(null, null, $blockedReceipts)['blocked_reasons'], ['reset_mode_mismatch', 'writer_generation_mismatch', 'receipt_database_digest_mismatch', 'receipt_wal_digest_mismatch', 'database_sync_missing', 'wal_sync_missing', 'directory_sync_missing', 'hot_journal_absence_receipt_missing', 'savepoint_closed_receipt_missing', 'readers_reopened_receipt_missing']],
    'bad db guard names' => [static fn (): mixed => $plan(null, $badFiles)['blocked_guard_names'], ['database_bytes_match_checkpoint_digest', 'all_reset_receipts_match_current_source']],
    'bad wal guard names' => [static fn (): mixed => $plan(null, $badWalFiles)['blocked_guard_names'], ['wal_bytes_match_reset_mode', 'all_reset_receipts_match_current_source']],
    'journal guard names' => [static fn (): mixed => $plan(null, $journalFiles)['blocked_guard_names'], ['hot_journal_absent_after_reset', 'all_reset_receipts_match_current_source']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next226 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => $plan($badPlan),
    'bad mode rejected' => static fn () => $plan($badMode),
    'bad generation rejected' => static fn () => $plan($badGeneration),
    'empty files rejected' => static fn () => $plan(null, []),
    'empty receipts rejected' => static fn () => $plan(null, null, []),
    'empty wal rejected' => static fn () => $plan(null, null, null, ''),
    'bad database digest rejected' => static fn () => $plan(null, null, null, null, 'bad'),
    'bad file path rejected' => static fn () => $plan(null, ['' => 'bytes']),
    'bad receipt name rejected' => static fn () => $plan(null, null, [$receipt(['name' => ''])]),
    'bad receipt generation rejected' => static fn () => $plan(null, null, [$receipt(['writer_generation' => 0])]),
    'bad receipt database digest rejected' => static fn () => $plan(null, null, [$receipt(['database_digest' => 'bad'])]),
    'bad receipt wal digest rejected' => static fn () => $plan(null, null, [$receipt(['wal_digest' => 'bad'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next226 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
