<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointSnapshotRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$salt1 = 0x45454545;
$salt2 = 0x45454546;
$databaseBytes = $page('db-page-1-schema-before') . $page('db-page-2-options-before') . $page('db-page-3-index-before');

$makeWal = static function (array $frames, int $checkpoint = 45) use ($pageSize, $salt1, $salt2): string {
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [2, 0, $page('wal-frame-1-siteurl-draft')],
    [3, 3, $page('wal-frame-2-autoload-index-commit')],
    [2, 0, $page('wal-frame-3-siteurl-later-draft')],
    [2, 3, $page('wal-frame-4-siteurl-latest-commit')],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$checkpoint = $wal->durableCheckpointResult($databaseBytes, 'restart');
$validRestartWalBytes = $checkpoint['wal_bytes'];
$emptyWalBytes = '';
$truncatedWalBytes = substr($validRestartWalBytes, 0, 31);
$checksumCorruptWalBytes = substr($validRestartWalBytes, 0, 28) . (~$validRestartWalBytes[28]) . substr($validRestartWalBytes, 29);

$valid = static fn (): array => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $validRestartWalBytes, [1, 2, 3], 'restart', 2);
$empty = static fn (): array => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $emptyWalBytes, [1, 2, 3], 'truncate', 2);
$truncated = static fn (): array => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $truncatedWalBytes, [1, 2, 3], 'restart', 2);
$corrupt = static fn (): array => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $checksumCorruptWalBytes, [1, 2, 3], 'restart', 2);
$latest = static fn (): array => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $validRestartWalBytes, [2, 3], 'restart');

$cases = [
    'valid status recovered' => [static fn (): mixed => $valid()['status'], 'recovered'],
    'valid mode preserved' => [static fn (): mixed => $valid()['mode'], 'restart'],
    'valid reader frame pinned' => [static fn (): mixed => $valid()['reader_end_frame'], 2],
    'valid recovery status' => [static fn (): mixed => $valid()['recovery_status'], 'valid-wal'],
    'valid has no recovery error' => [static fn (): mixed => $valid()['recovery_error'], null],
    'valid recovered wal frame count zero' => [static fn (): mixed => $valid()['recovered_wal_frame_count'], 0],
    'valid recovered wal last commit null' => [static fn (): mixed => $valid()['recovered_wal_last_commit'], null],
    'valid checkpoint action restart' => [static fn (): mixed => $valid()['checkpoint']['wal_action'], 'restart_wal'],
    'valid checkpoint database changed' => [static fn (): mixed => $valid()['checkpoint']['database_bytes'] !== $databaseBytes, true],
    'valid current sources keep wal snapshot' => [static fn (): mixed => $valid()['current_reader_sources'], ['database', 'wal', 'wal']],
    'valid next sources use checkpoint database' => [static fn (): mixed => $valid()['next_reader_sources'], ['database', 'database', 'database']],
    'valid current frame indexes' => [static fn (): mixed => $valid()['current_reader_frame_indexes'], [null, 1, 2]],
    'valid next frame indexes' => [static fn (): mixed => $valid()['next_reader_frame_indexes'], [null, null, null]],
    'valid page two current has draft' => [static fn (): mixed => str_contains((string) $valid()['current_reader_images'][1], 'siteurl-draft'), true],
    'valid page two next has latest commit' => [static fn (): mixed => str_contains((string) $valid()['next_reader_images'][1], 'latest-commit'), true],
    'valid page three next has autoload commit' => [static fn (): mixed => str_contains((string) $valid()['next_reader_images'][2], 'autoload-index-commit'), true],
    'valid current keeps precrash snapshot' => [static fn (): mixed => $valid()['current_reader_keeps_precrash_snapshot'], true],
    'valid next does not use recovered wal frames' => [static fn (): mixed => $valid()['next_reader_uses_recovered_wal'], false],
    'valid next uses checkpoint database' => [static fn (): mixed => $valid()['next_reader_uses_checkpoint_database'], true],
    'valid next matches checkpoint durable' => [static fn (): mixed => $valid()['next_matches_checkpoint_durable'], true],
    'valid wal bytes length header only' => [static fn (): mixed => $valid()['wal_bytes_length'], 32],
    'valid dependency marker' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-snapshot-recovery-current-next', $valid()['dependencies'], true), true],
    'empty status recovered' => [static fn (): mixed => $empty()['status'], 'recovered'],
    'empty mode truncate' => [static fn (): mixed => $empty()['mode'], 'truncate'],
    'empty recovery status' => [static fn (): mixed => $empty()['recovery_status'], 'empty-wal'],
    'empty wal length' => [static fn (): mixed => $empty()['wal_bytes_length'], 0],
    'empty next sources database' => [static fn (): mixed => $empty()['next_reader_sources'], ['database', 'database', 'database']],
    'empty checkpoint action truncate' => [static fn (): mixed => $empty()['checkpoint']['wal_action'], 'truncate_wal'],
    'empty next page two latest' => [static fn (): mixed => str_contains((string) $empty()['next_reader_images'][1], 'latest-commit'), true],
    'empty current page two draft' => [static fn (): mixed => str_contains((string) $empty()['current_reader_images'][1], 'siteurl-draft'), true],
    'empty next matches durable' => [static fn (): mixed => $empty()['next_matches_checkpoint_durable'], true],
    'truncated status fallback' => [static fn (): mixed => $truncated()['status'], 'recovered-with-database-fallback'],
    'truncated recovery status' => [static fn (): mixed => $truncated()['recovery_status'], 'invalid-wal-fallback-database'],
    'truncated recovery error mentions short header' => [static fn (): mixed => str_contains((string) $truncated()['recovery_error'], 'header'), true],
    'truncated wal frame count zero' => [static fn (): mixed => $truncated()['recovered_wal_frame_count'], 0],
    'truncated next sources database' => [static fn (): mixed => $truncated()['next_reader_sources'], ['database', 'database', 'database']],
    'truncated next matches durable' => [static fn (): mixed => $truncated()['next_matches_checkpoint_durable'], true],
    'truncated current snapshot retained' => [static fn (): mixed => $truncated()['current_reader_keeps_precrash_snapshot'], true],
    'truncated next checkpoint page three' => [static fn (): mixed => str_contains((string) $truncated()['next_reader_images'][2], 'autoload-index-commit'), true],
    'corrupt status fallback' => [static fn (): mixed => $corrupt()['status'], 'recovered-with-database-fallback'],
    'corrupt recovery status' => [static fn (): mixed => $corrupt()['recovery_status'], 'invalid-wal-fallback-database'],
    'corrupt recovery error mentions checksum' => [static fn (): mixed => str_contains((string) $corrupt()['recovery_error'], 'checksum'), true],
    'corrupt wal frame count zero' => [static fn (): mixed => $corrupt()['recovered_wal_frame_count'], 0],
    'corrupt next sources database' => [static fn (): mixed => $corrupt()['next_reader_sources'], ['database', 'database', 'database']],
    'corrupt next uses checkpoint database' => [static fn (): mixed => $corrupt()['next_reader_uses_checkpoint_database'], true],
    'corrupt next matches durable' => [static fn (): mixed => $corrupt()['next_matches_checkpoint_durable'], true],
    'latest reader frame defaults to frame count' => [static fn (): mixed => $latest()['reader_end_frame'], 4],
    'latest current page two latest commit' => [static fn (): mixed => str_contains((string) $latest()['current_reader_images'][0], 'latest-commit'), true],
    'latest current page three autoload commit' => [static fn (): mixed => str_contains((string) $latest()['current_reader_images'][1], 'autoload-index-commit'), true],
    'latest next page two latest commit' => [static fn (): mixed => str_contains((string) $latest()['next_reader_images'][0], 'latest-commit'), true],
    'latest next page three autoload commit' => [static fn (): mixed => str_contains((string) $latest()['next_reader_images'][1], 'autoload-index-commit'), true],
    'latest current keeps snapshot flag false when images match' => [static fn (): mixed => $latest()['current_reader_keeps_precrash_snapshot'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal checkpoint snapshot recovery current next45 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal checkpoint snapshot recovery current next45 rejects empty page list'] = static function (TestRunner $t) use ($wal, $databaseBytes, $validRestartWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $validRestartWalBytes, []));
};

$tests['wal checkpoint snapshot recovery current next45 rejects non integer page'] = static function (TestRunner $t) use ($wal, $databaseBytes, $validRestartWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $validRestartWalBytes, ['2']));
};

$tests['wal checkpoint snapshot recovery current next45 rejects unsupported mode'] = static function (TestRunner $t) use ($wal, $databaseBytes, $validRestartWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $validRestartWalBytes, [1], 'invalid'));
};

$tests['wal checkpoint snapshot recovery current next45 rejects out of range reader frame'] = static function (TestRunner $t) use ($wal, $databaseBytes, $validRestartWalBytes): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery($wal, $databaseBytes, $validRestartWalBytes, [1], 'restart', 99));
};

return $tests;
