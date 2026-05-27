<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointCrashRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x33333333;
$salt2 = 0x71717171;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = static fn (): string => $page('db page 1 schema baseline') . $page('db page 2 options baseline');

$walHeaderBytes = static function (int $checkpoint = 33) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$walBytes = static function () use ($walHeaderBytes, $appendFrame, $page): string {
    $bytes = $walHeaderBytes();
    $seed = SQLiteWal::checksumPair(substr($bytes, 0, 24), false);
    $bytes = $appendFrame($bytes, $seed, 1, 0, $page('wal page 1 schema migrated before crash'));
    $bytes = $appendFrame($bytes, $seed, 2, 2, $page('wal page 2 active_plugins before crash'));

    return $bytes;
};

$wal = static fn (): SQLiteWal => SQLiteWal::parse($walBytes(), null, true);
$restartAfterDatabaseSync = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1, 2, 3], 'restart', 'after_database_sync');
$restartAfterWalWrite = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1, 2, 3], 'restart', 'after_wal_sidecar_write');
$restartAfterDirectory = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1, 2, 3], 'restart', 'after_directory_sync');
$truncateAfterWalWrite = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1, 2, 3], 'truncate', 'after_wal_sidecar_write');
$truncateAfterDirectory = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1, 2, 3], 'truncate', 'after_directory_sync');
$busyRestart = static fn (): array => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1, 2], 'restart', 'after_database_sync', 1);

$cases = [
    'restart database sync status' => [static fn (): mixed => $restartAfterDatabaseSync()['status'], 'recovered'],
    'restart database sync reason' => [static fn (): mixed => $restartAfterDatabaseSync()['reason'], 'checkpoint_database_durable_wal_replayed_idempotently'],
    'restart database sync mode' => [static fn (): mixed => $restartAfterDatabaseSync()['mode'], 'restart'],
    'restart database sync phase' => [static fn (): mixed => $restartAfterDatabaseSync()['crash_phase'], 'after_database_sync'],
    'restart database sync database path' => [static fn (): mixed => $restartAfterDatabaseSync()['database_path'], $databasePath],
    'restart database sync wal path' => [static fn (): mixed => $restartAfterDatabaseSync()['wal_path'], $databasePath . '-wal'],
    'restart database sync checkpoint not busy' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint']['busy'], false],
    'restart database sync checkpoint action restart' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint']['wal_action'], 'restart_wal'],
    'restart database sync checkpoint reason' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint']['reason'], 'restart_checkpoint_can_reset_wal'],
    'restart database sync checkpointed frame count' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint']['checkpointed_frame_count'], 2],
    'restart database sync persisted action preserves old wal' => [static fn (): mixed => $restartAfterDatabaseSync()['persisted_wal_action'], 'preserve_pre_reset_wal'],
    'restart database sync persisted wal length old wal' => [static fn (): mixed => $restartAfterDatabaseSync()['persisted_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'restart database sync current end frame' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_end_frame'], 2],
    'restart database sync next end frame replays old wal' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_end_frame'], 2],
    'restart database sync current sources' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_sources'], ['wal', 'wal', 'missing']],
    'restart database sync next sources' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_sources'], ['wal', 'wal', 'missing']],
    'restart database sync current frames' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_frame_indexes'], [1, 2, null]],
    'restart database sync next frames' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_frame_indexes'], [1, 2, null]],
    'restart database sync current has one missing page' => [static fn (): mixed => count($restartAfterDatabaseSync()['current_reader_errors']), 1],
    'restart database sync next has one missing page' => [static fn (): mixed => count($restartAfterDatabaseSync()['next_reader_errors']), 1],
    'restart database sync images match' => [static fn (): mixed => $restartAfterDatabaseSync()['images_match'], true],
    'restart database sync uses checkpoint database' => [static fn (): mixed => $restartAfterDatabaseSync()['next_uses_checkpoint_database'], true],
    'restart database sync replays persisted wal' => [static fn (): mixed => $restartAfterDatabaseSync()['next_replays_persisted_wal'], true],
    'restart database sync not reset wal' => [static fn (): mixed => $restartAfterDatabaseSync()['next_uses_reset_wal'], false],
    'restart database sync applied operation count' => [static fn (): mixed => count($restartAfterDatabaseSync()['operations_applied']), 2],
    'restart database sync pending operation count' => [static fn (): mixed => count($restartAfterDatabaseSync()['operations_pending']), 3],
    'restart database sync first pending writes wal header' => [static fn (): mixed => $restartAfterDatabaseSync()['operations_pending'][0]['reason'], 'write_restarted_wal_header'],
    'restart database sync database contains checkpoint page one' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['persisted_database_bytes'], 'schema migrated before crash'), true],
    'restart database sync database contains checkpoint page two' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['persisted_database_bytes'], 'active_plugins before crash'), true],
    'restart database sync next page two still sees wal image' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['next_reader'][1]['image'], 'active_plugins before crash'), true],
    'restart wal write reason' => [static fn (): mixed => $restartAfterWalWrite()['reason'], 'checkpoint_wal_sidecar_state_recovered_before_directory_sync'],
    'restart wal write persisted action' => [static fn (): mixed => $restartAfterWalWrite()['persisted_wal_action'], 'restart_wal'],
    'restart wal write persisted header length' => [static fn (): mixed => $restartAfterWalWrite()['persisted_wal_bytes_length'], 32],
    'restart wal write next end frame zero' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_end_frame'], 0],
    'restart wal write next sources database' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_sources'], ['database', 'database', 'missing']],
    'restart wal write next frames null' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_frame_indexes'], [null, null, null]],
    'restart wal write images match' => [static fn (): mixed => $restartAfterWalWrite()['images_match'], true],
    'restart wal write does not replay old wal' => [static fn (): mixed => $restartAfterWalWrite()['next_replays_persisted_wal'], false],
    'restart wal write uses reset wal' => [static fn (): mixed => $restartAfterWalWrite()['next_uses_reset_wal'], true],
    'restart wal write applied operation count' => [static fn (): mixed => count($restartAfterWalWrite()['operations_applied']), 3],
    'restart wal write pending sync wal first' => [static fn (): mixed => $restartAfterWalWrite()['operations_pending'][0]['reason'], 'sync_wal_sidecar'],
    'restart directory reason' => [static fn (): mixed => $restartAfterDirectory()['reason'], 'checkpoint_fully_durable_after_directory_sync'],
    'restart directory pending operations empty' => [static fn (): mixed => $restartAfterDirectory()['operations_pending'], []],
    'restart directory applied all operations' => [static fn (): mixed => count($restartAfterDirectory()['operations_applied']), 5],
    'restart directory next sources database' => [static fn (): mixed => $restartAfterDirectory()['next_reader_sources'], ['database', 'database', 'missing']],
    'truncate wal write checkpoint action' => [static fn (): mixed => $truncateAfterWalWrite()['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate wal write persisted action' => [static fn (): mixed => $truncateAfterWalWrite()['persisted_wal_action'], 'truncate_wal'],
    'truncate wal write persisted wal empty' => [static fn (): mixed => $truncateAfterWalWrite()['persisted_wal_bytes_length'], 0],
    'truncate wal write next end frame zero' => [static fn (): mixed => $truncateAfterWalWrite()['next_reader_end_frame'], 0],
    'truncate wal write next sources database' => [static fn (): mixed => $truncateAfterWalWrite()['next_reader_sources'], ['database', 'database', 'missing']],
    'truncate wal write images match' => [static fn (): mixed => $truncateAfterWalWrite()['images_match'], true],
    'truncate wal write applied operation count' => [static fn (): mixed => count($truncateAfterWalWrite()['operations_applied']), 3],
    'truncate wal write pending directory sync' => [static fn (): mixed => $truncateAfterWalWrite()['operations_pending'][0]['reason'], 'persist_database_and_wal_directory_entries'],
    'truncate directory pending empty' => [static fn (): mixed => $truncateAfterDirectory()['operations_pending'], []],
    'truncate directory applied all operations' => [static fn (): mixed => count($truncateAfterDirectory()['operations_applied']), 4],
    'truncate directory images match' => [static fn (): mixed => $truncateAfterDirectory()['images_match'], true],
    'dependencies include crash recovery' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-crash-recovery-current-next', $restartAfterDatabaseSync()['dependencies'], true), true],
    'dependencies include wal checkpoint' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restartAfterDatabaseSync()['dependencies'], true), true],
    'dependencies include file write coordination' => [static fn (): mixed => in_array('vfs-file-write-coordination', $restartAfterDatabaseSync()['dependencies'], true), true],
    'busy status preserves reader block' => [static fn (): mixed => $busyRestart()['status'], 'busy'],
    'busy reason preserves reset block' => [static fn (): mixed => $busyRestart()['reason'], 'reader_blocks_checkpoint_completion'],
    'busy pending keeps full write plan' => [static fn (): mixed => count($busyRestart()['operations_pending']), 5],
    'busy next does not checkpoint database' => [static fn (): mixed => $busyRestart()['next_uses_checkpoint_database'], false],
    'busy persisted action preserves wal' => [static fn (): mixed => $busyRestart()['persisted_wal_action'], 'preserve_wal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal crash recovery checkpoint current next33 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal crash recovery checkpoint current next33 rejects empty path'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), '', [1]));
};

$tests['wal crash recovery checkpoint current next33 rejects empty pages'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, []));
};

$tests['wal crash recovery checkpoint current next33 rejects passive mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1], 'passive'));
};

$tests['wal crash recovery checkpoint current next33 rejects bad phase'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, [1], 'restart', 'before_database_sync'));
};

$tests['wal crash recovery checkpoint current next33 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $databasePath): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointCrashRecoveryPlan::plan($wal(), $databaseBytes(), $databasePath, ['1']));
};

return $tests;
