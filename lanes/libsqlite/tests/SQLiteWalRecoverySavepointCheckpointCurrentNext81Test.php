<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$tests = [];

$pageSize = 512;
$salt1 = 0x81818181;
$salt2 = 0x18181818;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db page one schema before release checkpoint81') . $page('db page two option before release checkpoint81') . $page('db page three transient before release checkpoint81');

$buildWal = static function (array $frames) use ($pageSize, $salt1, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 81, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commit, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $buildWal([
    [1, 0, 'wal schema retained before failed plugin81'],
    [2, 3, 'wal active_plugins retained before failed plugin81'],
    [2, 0, 'wal active_plugins draft rolled back plugin81'],
    [3, 3, 'wal transient commit rolled back plugin81'],
]);

$wal = static fn (): SQLiteWal => SQLiteWal::parse($walBytes, $pageSize, true);
$savepoints = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wordpress-import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordWalFrameWrite(4, 3, true);

    return $stack;
};

$restart = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, [1, 2, 3], 'restart');
$truncate = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, [1, 2, 3], 'truncate');
$clampedReader = static fn (): array => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, [1, 2], 'restart', 99);

$cases = [
    'restart status' => [static fn (): mixed => $restart()['status'], 'released-checkpointed'],
    'restart savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings'],
    'restart mode' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'restart rollback retained depth' => [static fn (): mixed => $restart()['rollback']['retained_depth'], 2],
    'restart rollback discarded names empty after rollback target' => [static fn (): mixed => $restart()['rollback']['discarded_frame_names'], []],
    'restart rollback clears target' => [static fn (): mixed => $restart()['rollback']['target_frame_cleared'], true],
    'restart release frame names' => [static fn (): mixed => $restart()['release']['released_frame_names'], ['plugin-settings']],
    'restart release target not transaction' => [static fn (): mixed => $restart()['release']['target_is_transaction'], false],
    'restart release depth is outer transaction only' => [static fn (): mixed => $restart()['release']['result_depth'], 1],
    'restart transaction remains active' => [static fn (): mixed => $restart()['release']['transaction_active_after'], true],
    'restart checkpoint action' => [static fn (): mixed => $restart()['checkpoint']['wal_action'], 'restart_wal'],
    'restart checkpoint can reset' => [static fn (): mixed => $restart()['checkpoint']['can_reset'], true],
    'restart checkpoint not busy' => [static fn (): mixed => $restart()['checkpoint']['busy'], false],
    'restart checkpoint frame count' => [static fn (): mixed => $restart()['checkpoint']['total_committable_frame_count'], 2],
    'restart checkpoint database changed' => [static fn (): mixed => $restart()['checkpoint']['database_bytes'] !== $databaseBytes, true],
    'restart checkpoint database includes retained schema' => [static fn (): mixed => str_contains($restart()['checkpoint']['database_bytes'], 'schema retained before failed'), true],
    'restart checkpoint database includes retained option' => [static fn (): mixed => str_contains($restart()['checkpoint']['database_bytes'], 'active_plugins retained'), true],
    'restart checkpoint database excludes draft' => [static fn (): mixed => str_contains($restart()['checkpoint']['database_bytes'], 'draft rolled back'), false],
    'restart checkpoint database excludes rolled back transient' => [static fn (): mixed => str_contains($restart()['checkpoint']['database_bytes'], 'transient commit rolled back'), false],
    'restart wal header only remains' => [static fn (): mixed => strlen($restart()['checkpoint']['wal_bytes']), 32],
    'restart current reader end frame' => [static fn (): mixed => $restart()['current_reader_end_frame'], 2],
    'restart next reader end frame' => [static fn (): mixed => $restart()['next_reader_end_frame'], 0],
    'restart current sources' => [static fn (): mixed => $restart()['current_reader_sources'], ['wal', 'wal', 'database']],
    'restart next sources' => [static fn (): mixed => $restart()['next_reader_sources'], ['database', 'database', 'database']],
    'restart current frames' => [static fn (): mixed => $restart()['current_reader_frame_indexes'], [1, 2, null]],
    'restart next frames' => [static fn (): mixed => $restart()['next_reader_frame_indexes'], [null, null, null]],
    'restart keeps current wal prefix' => [static fn (): mixed => $restart()['current_reader_kept_rollback_wal_prefix'], true],
    'restart release allows reset' => [static fn (): mixed => $restart()['release_allows_checkpoint_reset'], true],
    'restart next uses checkpoint database' => [static fn (): mixed => $restart()['next_reader_uses_checkpoint_database'], true],
    'restart images match retained prefix' => [static fn (): mixed => $restart()['images_match'], true],
    'restart dependency release marker' => [static fn (): mixed => in_array('sqlite-savepoint-release-after-rollback-current-next81', $restart()['dependencies'], true), true],
    'restart dependency wal marker' => [static fn (): mixed => in_array('sqlite-wal-savepoint-release-checkpoint-current-next81', $restart()['dependencies'], true), true],
    'restart dependency checkpoint marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint', $restart()['dependencies'], true), true],
    'restart checkpoint page count' => [static fn (): mixed => $restart()['checkpoint']['database_page_count'], 3],
    'truncate status' => [static fn (): mixed => $truncate()['status'], 'released-checkpointed'],
    'truncate action' => [static fn (): mixed => $truncate()['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate wal empty' => [static fn (): mixed => $truncate()['checkpoint']['wal_bytes'], ''],
    'truncate can truncate' => [static fn (): mixed => $truncate()['checkpoint']['can_truncate'], true],
    'truncate next reader end frame' => [static fn (): mixed => $truncate()['next_reader_end_frame'], 0],
    'truncate current sources' => [static fn (): mixed => $truncate()['current_reader_sources'], ['wal', 'wal', 'database']],
    'truncate next sources' => [static fn (): mixed => $truncate()['next_reader_sources'], ['database', 'database', 'database']],
    'truncate images match' => [static fn (): mixed => $truncate()['images_match'], true],
    'truncate release allows reset' => [static fn (): mixed => $truncate()['release_allows_checkpoint_reset'], true],
    'clamped reader end frame' => [static fn (): mixed => $clampedReader()['current_reader_end_frame'], 2],
    'clamped current sources' => [static fn (): mixed => $clampedReader()['current_reader_sources'], ['wal', 'wal']],
    'clamped next sources' => [static fn (): mixed => $clampedReader()['next_reader_sources'], ['database', 'database']],
    'clamped images match' => [static fn (): mixed => $clampedReader()['images_match'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal recovery savepoint checkpoint current next81 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal recovery savepoint checkpoint current next81 rejects empty savepoint'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), '', $wal(), $walBytes, $databaseBytes, [1]));
};
$tests['wal recovery savepoint checkpoint current next81 rejects empty pages'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, []));
};
$tests['wal recovery savepoint checkpoint current next81 rejects passive mode'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, [1], 'passive'));
};
$tests['wal recovery savepoint checkpoint current next81 rejects non integer page'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext($savepoints(), 'plugin-settings', $wal(), $walBytes, $databaseBytes, ['1']));
};

return $tests;
