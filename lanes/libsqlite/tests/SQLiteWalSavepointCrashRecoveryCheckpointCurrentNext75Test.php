<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$salt1 = 0x75757575;
$salt2 = 0x35353535;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db page one clean schema before import') . $page('db page two clean active_plugins before import') . $page('db page three clean transient before import');

$buildWal = static function (array $frames, ?callable $mutate = null) use ($pageSize, $salt1, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 75, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commit, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$walBytes = $buildWal([
    [1, 0, 'wal schema committed before plugin savepoint'],
    [2, 3, 'wal active_plugins committed before plugin savepoint'],
    [2, 0, 'wal active_plugins draft inside failed savepoint'],
    [3, 4, 'wal transient committed inside failed savepoint'],
]);
$walBytesWithCorruptDiscardedTail = $buildWal([
    [1, 0, 'wal schema committed before corrupt savepoint'],
    [2, 3, 'wal active_plugins committed before corrupt savepoint'],
    [2, 0, 'wal corrupt draft inside failed savepoint'],
    [3, 4, 'wal corrupt transient inside failed savepoint'],
], static fn (string $bytes): string => substr_replace($bytes, 'Z', 32 + ((24 + 512) * 3) + 44, 1));
$walBytesWithoutCommitBeforeSavepoint = $buildWal([
    [1, 0, 'wal schema draft before savepoint'],
    [2, 0, 'wal option draft before savepoint'],
    [3, 3, 'wal transient committed inside savepoint'],
]);

$stack = static function (): SQLiteSavepointStack {
    $savepoints = new SQLiteSavepointStack();
    $savepoints->beginTransaction('application-import');
    $savepoints->recordWalFrameWrite(1, 1, false);
    $savepoints->recordWalFrameWrite(2, 2, true);
    $savepoints->savepoint('plugin-settings');
    $savepoints->recordWalFrameWrite(3, 2, false);
    $savepoints->recordWalFrameWrite(4, 3, true);

    return $savepoints;
};
$stackWithoutCommitBeforeSavepoint = static function (): SQLiteSavepointStack {
    $savepoints = new SQLiteSavepointStack();
    $savepoints->beginTransaction('application-import');
    $savepoints->recordWalFrameWrite(1, 1, false);
    $savepoints->recordWalFrameWrite(2, 2, false);
    $savepoints->savepoint('plugin-settings');
    $savepoints->recordWalFrameWrite(3, 3, true);

    return $savepoints;
};

$wal = static fn (string $bytes = null, bool $checksumsValidated = true): SQLiteWal => SQLiteWal::parse($bytes ?? $walBytes, $pageSize, $checksumsValidated);
$restartAfterDatabaseSync = static fn (): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_database_sync', $pageSize);
$restartAfterWalWrite = static fn (): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_wal_sidecar_write', $pageSize);
$restartAfterDirectory = static fn (): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_directory_sync', $pageSize);
$truncateAfterWalWrite = static fn (): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [1, 2, 3], 'truncate', 'after_wal_sidecar_write', $pageSize);
$corruptDiscardedTail = static fn (): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal($walBytesWithCorruptDiscardedTail, false), $walBytesWithCorruptDiscardedTail, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_database_sync', $pageSize);
$noCommitBeforeSavepoint = static fn (): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stackWithoutCommitBeforeSavepoint(), 'plugin-settings', $wal($walBytesWithoutCommitBeforeSavepoint), $walBytesWithoutCommitBeforeSavepoint, $databaseBytes, $databasePath, [1, 2, 3], 'restart', 'after_database_sync', $pageSize);

$cases = [
    'restart database sync status' => [static fn (): mixed => $restartAfterDatabaseSync()['status'], 'recovered'],
    'restart database sync savepoint' => [static fn (): mixed => $restartAfterDatabaseSync()['savepoint'], 'plugin-settings'],
    'restart database sync mode' => [static fn (): mixed => $restartAfterDatabaseSync()['mode'], 'restart'],
    'restart database sync phase' => [static fn (): mixed => $restartAfterDatabaseSync()['crash_phase'], 'after_database_sync'],
    'restart database sync rollback frame' => [static fn (): mixed => $restartAfterDatabaseSync()['rollback_to_frame'], 2],
    'restart database sync retained frames' => [static fn (): mixed => $restartAfterDatabaseSync()['retained_frame_count'], 2],
    'restart database sync discarded frames' => [static fn (): mixed => $restartAfterDatabaseSync()['discarded_frame_count'], 2],
    'restart database sync discarded frame indexes' => [static fn (): mixed => array_column($restartAfterDatabaseSync()['discarded_wal_frames'], 'frame_index'), [3, 4]],
    'restart database sync discarded pages' => [static fn (): mixed => array_column($restartAfterDatabaseSync()['discarded_wal_frames'], 'page_number'), [2, 3]],
    'restart database sync current wal bytes prefix' => [static fn (): mixed => $restartAfterDatabaseSync()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'restart database sync recovery reason' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint_recovery']['recovery']['reason'], 'all_frames_valid'],
    'restart database sync committed frame count' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint_recovery']['recovery']['committed_frame_count'], 2],
    'restart database sync valid frame count' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint_recovery']['recovery']['valid_frame_count'], 2],
    'restart database sync no discarded valid tail after rollback' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint_recovery']['discarded_valid_tail_frame_count'], 0],
    'restart database sync no discarded corrupt tail after rollback' => [static fn (): mixed => $restartAfterDatabaseSync()['checkpoint_recovery']['discarded_corrupt_tail_frame_count'], 0],
    'restart database sync current sources' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_sources'], ['wal', 'wal', 'database']],
    'restart database sync next sources' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_sources'], ['wal', 'wal', 'database']],
    'restart database sync current frames' => [static fn (): mixed => $restartAfterDatabaseSync()['current_reader_frame_indexes'], [1, 2, null]],
    'restart database sync next frames' => [static fn (): mixed => $restartAfterDatabaseSync()['next_reader_frame_indexes'], [1, 2, null]],
    'restart database sync images match' => [static fn (): mixed => $restartAfterDatabaseSync()['images_match'], true],
    'restart database sync checkpoint database used' => [static fn (): mixed => $restartAfterDatabaseSync()['next_uses_checkpoint_database'], true],
    'restart database sync persisted wal replayed' => [static fn (): mixed => $restartAfterDatabaseSync()['next_replays_persisted_wal'], true],
    'restart database sync reset false' => [static fn (): mixed => $restartAfterDatabaseSync()['next_uses_reset_wal'], false],
    'restart database sync checkpoint contains pre savepoint option' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['checkpoint_recovery']['persisted_database_bytes'], 'committed before plugin'), true],
    'restart database sync excludes failed option draft' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['checkpoint_recovery']['persisted_database_bytes'], 'draft inside failed'), false],
    'restart database sync excludes failed transient commit' => [static fn (): mixed => str_contains($restartAfterDatabaseSync()['checkpoint_recovery']['persisted_database_bytes'], 'transient committed inside failed'), false],
    'restart database sync next sees clean transient page' => [static fn (): mixed => str_contains((string) $restartAfterDatabaseSync()['checkpoint_recovery']['next_reader'][2]['image'], 'clean transient'), true],
    'restart database sync applied operations' => [static fn (): mixed => array_column($restartAfterDatabaseSync()['operations_applied'], 'reason'), ['checkpoint_recovered_committed_wal_prefix', 'sync_checkpoint_database_image']],
    'restart database sync pending starts wal header' => [static fn (): mixed => $restartAfterDatabaseSync()['operations_pending'][0]['reason'], 'restart_recovered_wal_header'],
    'restart database sync dependency marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-crash-recovery-checkpoint-current-next75', $restartAfterDatabaseSync()['dependencies'], true), true],
    'restart database sync recovery dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-recovery-current-next', $restartAfterDatabaseSync()['dependencies'], true), true],
    'restart wal write reason' => [static fn (): mixed => $restartAfterWalWrite()['checkpoint_recovery']['reason'], 'checkpoint_wal_recovery_sidecar_durable_before_directory_sync'],
    'restart wal write action' => [static fn (): mixed => $restartAfterWalWrite()['checkpoint_recovery']['persisted_wal_action'], 'restart_recovered_wal'],
    'restart wal write wal header only' => [static fn (): mixed => $restartAfterWalWrite()['checkpoint_recovery']['persisted_wal_bytes_length'], 32],
    'restart wal write next end frame zero' => [static fn (): mixed => $restartAfterWalWrite()['checkpoint_recovery']['next_reader_end_frame'], 0],
    'restart wal write next sources database' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_sources'], ['database', 'database', 'database']],
    'restart wal write next frames null' => [static fn (): mixed => $restartAfterWalWrite()['next_reader_frame_indexes'], [null, null, null]],
    'restart wal write images match' => [static fn (): mixed => $restartAfterWalWrite()['images_match'], true],
    'restart wal write uses reset wal' => [static fn (): mixed => $restartAfterWalWrite()['next_uses_reset_wal'], true],
    'restart wal write does not replay wal' => [static fn (): mixed => $restartAfterWalWrite()['next_replays_persisted_wal'], false],
    'restart wal write applied count' => [static fn (): mixed => count($restartAfterWalWrite()['operations_applied']), 3],
    'restart wal write pending sync wal' => [static fn (): mixed => $restartAfterWalWrite()['operations_pending'][0]['reason'], 'sync_recovered_wal_sidecar'],
    'restart directory reason' => [static fn (): mixed => $restartAfterDirectory()['checkpoint_recovery']['reason'], 'checkpoint_wal_recovery_fully_durable'],
    'restart directory pending empty' => [static fn (): mixed => $restartAfterDirectory()['operations_pending'], []],
    'restart directory applied all operations' => [static fn (): mixed => count($restartAfterDirectory()['operations_applied']), 5],
    'truncate wal write mode' => [static fn (): mixed => $truncateAfterWalWrite()['mode'], 'truncate'],
    'truncate wal write action' => [static fn (): mixed => $truncateAfterWalWrite()['checkpoint_recovery']['persisted_wal_action'], 'truncate_recovered_wal'],
    'truncate wal write bytes empty' => [static fn (): mixed => $truncateAfterWalWrite()['checkpoint_recovery']['persisted_wal_bytes_length'], 0],
    'truncate wal write reset false' => [static fn (): mixed => $truncateAfterWalWrite()['next_uses_reset_wal'], false],
    'truncate wal write next sources database' => [static fn (): mixed => $truncateAfterWalWrite()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate wal write pending wal sync target' => [static fn (): mixed => $truncateAfterWalWrite()['operations_pending'][0]['target'], $databasePath . '-wal'],
    'corrupt discarded tail status still recovered' => [static fn (): mixed => $corruptDiscardedTail()['status'], 'recovered'],
    'corrupt discarded tail retained frame count' => [static fn (): mixed => $corruptDiscardedTail()['retained_frame_count'], 2],
    'corrupt discarded tail recovery sees no corrupt tail' => [static fn (): mixed => $corruptDiscardedTail()['checkpoint_recovery']['discarded_corrupt_tail_frame_count'], 0],
    'corrupt discarded tail excludes corrupt draft' => [static fn (): mixed => str_contains($corruptDiscardedTail()['checkpoint_recovery']['persisted_database_bytes'], 'corrupt draft'), false],
    'corrupt discarded tail excludes corrupt transient' => [static fn (): mixed => str_contains($corruptDiscardedTail()['checkpoint_recovery']['persisted_database_bytes'], 'corrupt transient'), false],
    'no commit before savepoint reason' => [static fn (): mixed => $noCommitBeforeSavepoint()['checkpoint_recovery']['recovery']['reason'], 'no_committed_transaction_in_valid_prefix'],
    'no commit before savepoint database unchanged' => [static fn (): mixed => $noCommitBeforeSavepoint()['checkpoint_recovery']['persisted_database_bytes'] === $databaseBytes, true],
    'no commit before savepoint current sources database' => [static fn (): mixed => $noCommitBeforeSavepoint()['current_reader_sources'], ['database', 'database', 'database']],
    'no commit before savepoint next sources database' => [static fn (): mixed => $noCommitBeforeSavepoint()['next_reader_sources'], ['database', 'database', 'database']],
    'no commit before savepoint no checkpoint database' => [static fn (): mixed => $noCommitBeforeSavepoint()['next_uses_checkpoint_database'], false],
    'no commit before savepoint operations not applied before db sync' => [static fn (): mixed => $noCommitBeforeSavepoint()['operations_applied'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint crash recovery checkpoint current next75 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint crash recovery checkpoint current next75 rejects empty savepoint'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), '', $wal(), $walBytes, $databaseBytes, $databasePath, [1], 'restart', 'after_database_sync', $pageSize));
};
$tests['wal savepoint crash recovery checkpoint current next75 rejects empty path'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, '', [1], 'restart', 'after_database_sync', $pageSize));
};
$tests['wal savepoint crash recovery checkpoint current next75 rejects empty page list'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [], 'restart', 'after_database_sync', $pageSize));
};
$tests['wal savepoint crash recovery checkpoint current next75 rejects bad mode'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [1], 'passive', 'after_database_sync', $pageSize));
};
$tests['wal savepoint crash recovery checkpoint current next75 rejects bad phase'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, [1], 'restart', 'before_database_sync', $pageSize));
};
$tests['wal savepoint crash recovery checkpoint current next75 rejects non integer page'] = static function (TestRunner $t) use ($stack, $wal, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($stack(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, $databasePath, ['1'], 'restart', 'after_database_sync', $pageSize));
};

return $tests;
