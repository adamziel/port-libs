<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('next82 clean schema before plugin import')
    . $page('next82 clean active plugins before plugin import')
    . $page('next82 clean transient before plugin import');

$makeWalBytes = static function (int $checkpoint, int $salt1, int $salt2, string $tag, int $frames = 4) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $allFrames = [
        [1, 0, "{$tag} schema draft before savepoint"],
        [2, 3, "{$tag} active_plugins committed before savepoint"],
        [2, 0, "{$tag} active_plugins draft inside rolled back savepoint"],
        [3, 4, "{$tag} transient committed inside rolled back savepoint"],
    ];

    foreach (array_slice($allFrames, 0, $frames) as [$pageNumber, $commit, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes(82, 0x82828282, 0x28282828, 'current-source');
$staleSaltBytes = $makeWalBytes(82, 0x82828283, 0x28282828, 'stale-salt');
$staleCheckpointBytes = $makeWalBytes(83, 0x82828282, 0x28282828, 'stale-checkpoint');
$shorterWalBytes = $makeWalBytes(82, 0x82828282, 0x28282828, 'shorter-current-source', 3);
$currentWal = SQLiteWal::parse($walBytes, null, true);
$staleWal = SQLiteWal::parse($staleSaltBytes, null, true);

$makeStack = static function (): SQLiteSavepointStack {
    $savepoints = new SQLiteSavepointStack();
    $savepoints->beginTransaction('application-import-next82');
    $savepoints->recordWalFrameWrite(1, 1, false);
    $savepoints->recordWalFrameWrite(2, 2, true);
    $savepoints->savepoint('plugin-settings');
    $savepoints->recordWalFrameWrite(3, 2, false);
    $savepoints->recordWalFrameWrite(4, 3, true);

    return $savepoints;
};

$recover = static fn (string $mode = 'restart', string $phase = 'after_database_sync', array $pages = [1, 2, 3]): array => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo(
    $makeStack(),
    'plugin-settings',
    $currentWal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    $pages,
    $mode,
    $phase,
    $pageSize
);

$restartDatabase = static fn (): array => $recover();
$restartWal = static fn (): array => $recover('restart', 'after_wal_sidecar_write');
$restartDirectory = static fn (): array => $recover('restart', 'after_directory_sync');
$truncateWal = static fn (): array => $recover('truncate', 'after_wal_sidecar_write');

$cases = [
    'database sync status' => [static fn (): mixed => $restartDatabase()['status'], 'recovered'],
    'database sync savepoint' => [static fn (): mixed => $restartDatabase()['savepoint'], 'plugin-settings'],
    'database sync mode' => [static fn (): mixed => $restartDatabase()['mode'], 'restart'],
    'database sync phase' => [static fn (): mixed => $restartDatabase()['crash_phase'], 'after_database_sync'],
    'database sync rollback frame' => [static fn (): mixed => $restartDatabase()['rollback_to_frame'], 2],
    'database sync retained frames' => [static fn (): mixed => $restartDatabase()['retained_frame_count'], 2],
    'database sync discarded frames' => [static fn (): mixed => $restartDatabase()['discarded_frame_count'], 2],
    'database sync discarded indexes' => [static fn (): mixed => array_column($restartDatabase()['discarded_wal_frames'], 'frame_index'), [3, 4]],
    'database sync discarded pages' => [static fn (): mixed => array_column($restartDatabase()['discarded_wal_frames'], 'page_number'), [2, 3]],
    'database sync current wal prefix bytes' => [static fn (): mixed => $restartDatabase()['current_wal_bytes_length'], 32 + (2 * (24 + $pageSize))],
    'database sync recovery status' => [static fn (): mixed => $restartDatabase()['checkpoint_recovery']['recovery']['status'], 'valid'],
    'database sync recovery reason' => [static fn (): mixed => $restartDatabase()['checkpoint_recovery']['recovery']['reason'], 'all_frames_valid'],
    'database sync committed frame count' => [static fn (): mixed => $restartDatabase()['checkpoint_recovery']['recovery']['committed_frame_count'], 2],
    'database sync valid frame count' => [static fn (): mixed => $restartDatabase()['checkpoint_recovery']['recovery']['valid_frame_count'], 2],
    'database sync no valid tail discarded' => [static fn (): mixed => $restartDatabase()['checkpoint_recovery']['discarded_valid_tail_frame_count'], 0],
    'database sync no corrupt tail discarded' => [static fn (): mixed => $restartDatabase()['checkpoint_recovery']['discarded_corrupt_tail_frame_count'], 0],
    'database sync current sources' => [static fn (): mixed => $restartDatabase()['current_reader_sources'], ['wal', 'wal', 'database']],
    'database sync next sources' => [static fn (): mixed => $restartDatabase()['next_reader_sources'], ['wal', 'wal', 'database']],
    'database sync current frames' => [static fn (): mixed => $restartDatabase()['current_reader_frame_indexes'], [1, 2, null]],
    'database sync next frames' => [static fn (): mixed => $restartDatabase()['next_reader_frame_indexes'], [1, 2, null]],
    'database sync images match' => [static fn (): mixed => $restartDatabase()['images_match'], true],
    'database sync next uses checkpoint database' => [static fn (): mixed => $restartDatabase()['next_uses_checkpoint_database'], true],
    'database sync replays persisted wal' => [static fn (): mixed => $restartDatabase()['next_replays_persisted_wal'], true],
    'database sync reset wal false' => [static fn (): mixed => $restartDatabase()['next_uses_reset_wal'], false],
    'database sync database contains committed option' => [static fn (): mixed => str_contains($restartDatabase()['checkpoint_recovery']['persisted_database_bytes'], 'committed before savepoint'), true],
    'database sync database omits rolled back draft' => [static fn (): mixed => str_contains($restartDatabase()['checkpoint_recovery']['persisted_database_bytes'], 'draft inside rolled back'), false],
    'database sync database omits rolled back transient' => [static fn (): mixed => str_contains($restartDatabase()['checkpoint_recovery']['persisted_database_bytes'], 'transient committed inside rolled back'), false],
    'database sync page three remains base' => [static fn (): mixed => str_contains((string) $restartDatabase()['checkpoint_recovery']['next_reader'][2]['image'], 'clean transient'), true],
    'database sync applied reasons' => [static fn (): mixed => array_column($restartDatabase()['operations_applied'], 'reason'), ['checkpoint_recovered_committed_wal_prefix', 'sync_checkpoint_database_image']],
    'database sync pending restart header' => [static fn (): mixed => $restartDatabase()['operations_pending'][0]['reason'], 'restart_recovered_wal_header'],
    'database sync current source marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-crash-recovery-checkpoint-current-next75', $restartDatabase()['dependencies'], true), true],
    'database sync recovery marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-recovery-current-next', $restartDatabase()['dependencies'], true), true],
    'wal sync reason' => [static fn (): mixed => $restartWal()['checkpoint_recovery']['reason'], 'checkpoint_wal_recovery_sidecar_durable_before_directory_sync'],
    'wal sync persisted action' => [static fn (): mixed => $restartWal()['checkpoint_recovery']['persisted_wal_action'], 'restart_recovered_wal'],
    'wal sync header only' => [static fn (): mixed => $restartWal()['checkpoint_recovery']['persisted_wal_bytes_length'], 32],
    'wal sync next end frame zero' => [static fn (): mixed => $restartWal()['checkpoint_recovery']['next_reader_end_frame'], 0],
    'wal sync next sources database' => [static fn (): mixed => $restartWal()['next_reader_sources'], ['database', 'database', 'database']],
    'wal sync next frames null' => [static fn (): mixed => $restartWal()['next_reader_frame_indexes'], [null, null, null]],
    'wal sync images match' => [static fn (): mixed => $restartWal()['images_match'], true],
    'wal sync reset wal true' => [static fn (): mixed => $restartWal()['next_uses_reset_wal'], true],
    'wal sync does not replay wal' => [static fn (): mixed => $restartWal()['next_replays_persisted_wal'], false],
    'wal sync applied operation count' => [static fn (): mixed => count($restartWal()['operations_applied']), 3],
    'wal sync pending wal sync' => [static fn (): mixed => $restartWal()['operations_pending'][0]['reason'], 'sync_recovered_wal_sidecar'],
    'directory sync reason' => [static fn (): mixed => $restartDirectory()['checkpoint_recovery']['reason'], 'checkpoint_wal_recovery_fully_durable'],
    'directory sync pending empty' => [static fn (): mixed => $restartDirectory()['operations_pending'], []],
    'directory sync applied all operations' => [static fn (): mixed => count($restartDirectory()['operations_applied']), 5],
    'truncate wal mode' => [static fn (): mixed => $truncateWal()['mode'], 'truncate'],
    'truncate wal action' => [static fn (): mixed => $truncateWal()['checkpoint_recovery']['persisted_wal_action'], 'truncate_recovered_wal'],
    'truncate wal bytes empty' => [static fn (): mixed => $truncateWal()['checkpoint_recovery']['persisted_wal_bytes_length'], 0],
    'truncate wal reset false' => [static fn (): mixed => $truncateWal()['next_uses_reset_wal'], false],
    'truncate wal next sources database' => [static fn (): mixed => $truncateWal()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate wal pending target' => [static fn (): mixed => $truncateWal()['operations_pending'][0]['target'], $databasePath . '-wal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal recovery checkpoint savepoint current source next82 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal recovery checkpoint savepoint current source next82 rejects stale salt source bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $staleSaltBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $currentWal, $staleSaltBytes, $databaseBytes, $databasePath, [1], 'restart', 'after_database_sync', $pageSize));
};

$tests['wal recovery checkpoint savepoint current source next82 rejects stale checkpoint source bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $staleCheckpointBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $currentWal, $staleCheckpointBytes, $databaseBytes, $databasePath, [1], 'restart', 'after_database_sync', $pageSize));
};

$tests['wal recovery checkpoint savepoint current source next82 rejects shorter source bytes'] = static function (TestRunner $t) use ($makeStack, $currentWal, $shorterWalBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $currentWal, $shorterWalBytes, $databaseBytes, $databasePath, [1], 'restart', 'after_database_sync', $pageSize));
};

$tests['wal recovery checkpoint savepoint current source next82 rejects stale parsed wal with current bytes'] = static function (TestRunner $t) use ($makeStack, $staleWal, $walBytes, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo($makeStack(), 'plugin-settings', $staleWal, $walBytes, $databaseBytes, $databasePath, [1], 'restart', 'after_database_sync', $pageSize));
};

return $tests;
