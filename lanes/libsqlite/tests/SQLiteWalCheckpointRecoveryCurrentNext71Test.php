<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointCrashRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db schema before recovered checkpoint') . $page('db option before recovered checkpoint') . $page('db autoload before recovered checkpoint');
$salt1 = 0x71717171;
$salt2 = 0x27272727;

$buildWal = static function (array $frames, ?callable $mutate = null) use ($pageSize, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 71, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commit, $label] = $frame;
        $image = str_pad($label, $pageSize, '.');
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$walWithValidAndCorruptTail = $buildWal([
    [1, 0, 'wal schema page for recovered plugin import'],
    [2, 3, 'wal active_plugins committed before crash'],
    [3, 0, 'wal transient draft valid but uncommitted'],
    [2, 3, 'wal corrupt committed replacement must not replay'],
], static fn (string $bytes): string => substr_replace($bytes, 'X', 32 + ((24 + 512) * 3) + 120, 1));

$walWithOnlyUncommittedFrames = $buildWal([
    [2, 0, 'wal draft option without commit'],
    [3, 0, 'wal draft autoload without commit'],
]);

$walWithCorruptCommittedTail = $buildWal([
    [1, 0, 'wal schema before corrupt committed tail'],
    [2, 3, 'wal option committed before corrupt tail'],
    [3, 3, 'wal corrupt autoload commit must not replay'],
], static fn (string $bytes): string => substr_replace($bytes, 'Y', 32 + ((24 + 512) * 2) + 88, 1));

$restartAfterDatabaseSync = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_database_sync', $pageSize);
$restartAfterWalWrite = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_wal_sidecar_write', $pageSize);
$restartAfterDirectory = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_directory_sync', $pageSize);
$truncateAfterWalWrite = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [1, 2, 3], 'truncate', 'after_wal_sidecar_write', $pageSize);
$noCommit = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithOnlyUncommittedFrames, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_database_sync', $pageSize);
$corruptCommitted = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithCorruptCommittedTail, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_database_sync', $pageSize);

$cases = [
    'restart database sync status' => [static fn (): mixed => $restartAfterDatabaseSync()['status'], 'recovered'],
    'restart database sync reason' => [static fn (): mixed => $restartAfterDatabaseSync()['reason'], 'checkpoint_database_durable_recovery_replays_committed_prefix'],
    'restart database sync recovery reason' => [static fn (): mixed => $restartAfterDatabaseSync()['recovery']['reason'], 'uncommitted_valid_tail_before_corrupt_frame'],
    'restart database sync recovery status' => [static fn (): mixed => $restartAfterDatabaseSync()['recovery']['status'], 'recovered_committed_prefix'],
    'restart database sync valid frame count' => [static fn (): mixed => $restartAfterDatabaseSync()['recovery']['valid_frame_count'], 3],
    'restart database sync committed frame count' => [static fn (): mixed => $restartAfterDatabaseSync()['recovery']['committed_frame_count'], 2],
    'restart database sync first invalid frame' => [static fn (): mixed => $restartAfterDatabaseSync()['recovery']['first_invalid_frame'], 4],
    'restart database sync discarded valid tail' => [static fn (): mixed => $restartAfterDatabaseSync()['discarded_valid_tail_frame_count'], 1],
    'restart database sync discarded corrupt tail' => [static fn (): mixed => $restartAfterDatabaseSync()['discarded_corrupt_tail_frame_count'], 1],
    'restart database sync persisted action' => [static fn (): mixed => $restartAfterDatabaseSync()['persisted_wal_action'], 'preserve_pre_recovery_wal'],
    'restart database sync persisted wal keeps original corrupt bytes length' => [static fn (): mixed => $restartAfterDatabaseSync()['persisted_wal_bytes_length'], strlen($walWithValidAndCorruptTail)],
    'restart database sync current end frame sees valid prefix' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_end_frame'], 3],
    'restart database sync next end frame replays committed prefix' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_end_frame'], 2],
    'restart database sync current sources' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_sources'], ['wal', 'wal', 'database']],
    'restart database sync next sources' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_sources'], ['wal', 'wal', 'database']],
    'restart database sync current frame indexes' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_frame_indexes'], [1, 2, null]],
    'restart database sync next frame indexes' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_frame_indexes'], [1, 2, null]],
    'restart database sync images match because drafts are uncommitted' => [static fn (): mixed => $restartAfterDatabaseSync()['images_match'], true],
    'restart database sync next uses checkpoint database' => [static fn (): mixed => $restartAfterDatabaseSync()['next_uses_checkpoint_database'], true],
    'restart database sync replays persisted wal' => [static fn (): mixed => $restartAfterDatabaseSync()['next_replays_persisted_wal'], true],
    'restart database sync not reset wal yet' => [static fn (): mixed => $restartAfterDatabaseSync()['next_uses_reset_wal'], false],
    'restart database sync checkpoint contains committed option' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['persisted_database_bytes'], 'active_plugins committed'), true],
    'restart database sync checkpoint excludes draft page' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['persisted_database_bytes'], 'transient draft'), false],
    'restart database sync current does not see draft page' => [static fn (): mixed => str_contains((string) $restartAfterDatabaseSync()['current_reader'][2]['image'], 'transient draft'), false],
    'restart database sync next sees database autoload page' => [static fn (): mixed => str_contains((string) $restartAfterDatabaseSync()['next_reader'][2]['image'], 'autoload before recovered'), true],
    'restart database sync applied operations' => [static fn (): mixed => array_column($restartAfterDatabaseSync()['operations_applied'], 'reason'), ['checkpoint_recovered_committed_wal_prefix', 'sync_checkpoint_database_image']],
    'restart database sync first pending operation' => [static fn (): mixed => $restartAfterDatabaseSync()['operations_pending'][0]['reason'], 'restart_recovered_wal_header'],
    'restart database sync dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-recovery-current-next', $restartAfterDatabaseSync()['dependencies'], true), true],
    'restart database sync transaction dependency marker' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $restartAfterDatabaseSync()['dependencies'], true), true],
    'restart wal write reason' => [static fn (): mixed => $restartAfterWalWrite()['reason'], 'checkpoint_wal_recovery_sidecar_durable_before_directory_sync'],
    'restart wal write persisted action' => [static fn (): mixed => $restartAfterWalWrite()['persisted_wal_action'], 'restart_recovered_wal'],
    'restart wal write persisted wal is header only' => [static fn (): mixed => $restartAfterWalWrite()['persisted_wal_bytes_length'], 32],
    'restart wal write next end frame zero' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_end_frame'], 0],
    'restart wal write next sources database' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_sources'], ['database', 'database', 'database']],
    'restart wal write frames all null' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_frame_indexes'], [null, null, null]],
    'restart wal write images match after reset' => [static fn (): mixed => $restartAfterWalWrite()['images_match'], true],
    'restart wal write uses reset wal' => [static fn (): mixed => $restartAfterWalWrite()['next_uses_reset_wal'], true],
    'restart wal write no persisted replay' => [static fn (): mixed => $restartAfterWalWrite()['next_replays_persisted_wal'], false],
    'restart wal write applied operation count' => [static fn (): mixed => count($restartAfterWalWrite()['operations_applied']), 3],
    'restart wal write pending wal sync' => [static fn (): mixed => $restartAfterWalWrite()['operations_pending'][0]['reason'], 'sync_recovered_wal_sidecar'],
    'restart directory reason' => [static fn (): mixed => $restartAfterDirectory()['reason'], 'checkpoint_wal_recovery_fully_durable'],
    'restart directory pending empty' => [static fn (): mixed => $restartAfterDirectory()['operations_pending'], []],
    'restart directory applied all five operations' => [static fn (): mixed => count($restartAfterDirectory()['operations_applied']), 5],
    'truncate wal write action' => [static fn (): mixed => $truncateAfterWalWrite()['persisted_wal_action'], 'truncate_recovered_wal'],
    'truncate wal write bytes empty' => [static fn (): mixed => $truncateAfterWalWrite()['persisted_wal_bytes_length'], 0],
    'truncate wal write next sources database' => [static fn (): mixed => $truncateAfterWalWrite()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate wal write reset flag false' => [static fn (): mixed => $truncateAfterWalWrite()['next_uses_reset_wal'], false],
    'truncate wal write pending sync target wal' => [static fn (): mixed => $truncateAfterWalWrite()['operations_pending'][0]['target'], $databasePath . '-wal'],
    'no commit status recovered' => [static fn (): mixed => $noCommit()['status'], 'recovered'],
    'no commit recovery reason' => [static fn (): mixed => $noCommit()['recovery']['reason'], 'no_committed_transaction_in_valid_prefix'],
    'no commit checkpoint database unchanged' => [static fn (): mixed => $noCommit()['persisted_database_bytes'] === $databaseBytes, true],
    'no commit current sources hide wal drafts' => [static fn (): mixed => $noCommit()['current_reader_sources'], ['database', 'database', 'database']],
    'no commit next sources stay database' => [static fn (): mixed => $noCommit()['next_reader_sources'], ['database', 'database', 'database']],
    'no commit next does not checkpoint database' => [static fn (): mixed => $noCommit()['next_uses_checkpoint_database'], false],
    'no commit applied operations empty before database sync' => [static fn (): mixed => $noCommit()['operations_applied'], []],
    'no commit pending starts with restart header' => [static fn (): mixed => $noCommit()['operations_pending'][0]['reason'], 'restart_recovered_wal_header'],
    'corrupt committed recovery reason' => [static fn (): mixed => $corruptCommitted()['recovery']['reason'], 'corrupt_tail_after_committed_prefix'],
    'corrupt committed valid frames' => [static fn (): mixed => $corruptCommitted()['recovery']['valid_frame_count'], 2],
    'corrupt committed corrupt discard count' => [static fn (): mixed => $corruptCommitted()['discarded_corrupt_tail_frame_count'], 1],
    'corrupt committed current sources' => [static fn (): mixed => $corruptCommitted()['current_reader_sources'], ['wal', 'wal', 'database']],
    'corrupt committed next sources' => [static fn (): mixed => $corruptCommitted()['next_reader_sources'], ['wal', 'wal', 'database']],
    'corrupt committed images match' => [static fn (): mixed => $corruptCommitted()['images_match'], true],
    'corrupt committed excludes corrupt autoload commit' => [static fn (): mixed => str_contains($corruptCommitted()['persisted_database_bytes'], 'must not replay'), false],
    'rejects empty path' => [static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, '', [1], 'restart', 'after_database_sync', $pageSize), InvalidArgumentException::class],
    'rejects empty page list' => [static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [], 'restart', 'after_database_sync', $pageSize), InvalidArgumentException::class],
    'rejects passive mode' => [static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [1], 'passive', 'after_database_sync', $pageSize), InvalidArgumentException::class],
    'rejects bad crash phase' => [static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, [1], 'restart', 'after_wal_header_only', $pageSize), InvalidArgumentException::class],
    'rejects non integer page' => [static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes($walWithValidAndCorruptTail, $databaseBytes, $databasePath, ['1'], 'restart', 'after_database_sync', $pageSize), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint recovery current next71 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);

            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
