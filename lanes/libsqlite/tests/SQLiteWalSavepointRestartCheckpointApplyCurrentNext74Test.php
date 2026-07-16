<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$salt1 = 0x74747474;
$salt2 = 0x75757575;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = static fn (): string => $page('db page 1 schema base current74') . $page('db page 2 options base current74');

$walHeaderBytes = static function () use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 74, $salt1, $salt2);
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
    $bytes = $appendFrame($bytes, $seed, 1, 0, $page('wal page 1 schema retained current74'));
    $bytes = $appendFrame($bytes, $seed, 2, 2, $page('wal page 2 options retained current74'));
    $bytes = $appendFrame($bytes, $seed, 2, 0, $page('wal page 2 draft discarded current74'));
    $bytes = $appendFrame($bytes, $seed, 3, 3, $page('wal page 3 row discarded current74'));

    return $bytes;
};

$wal = static fn (): SQLiteWal => SQLiteWal::parse($walBytes(), null, true);
$savepoints = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp_import');
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin_batch');
    $stack->recordWalFrameWrite(3, 2);
    $stack->recordWalFrameWrite(4, 3, true);

    return $stack;
};
$transactions = static function () use ($page): array {
    return [[
    'pages' => [
        2 => $page('next page 2 active plugins committed current74'),
        3 => $page('next page 3 autoload index committed current74'),
    ],
    'database_page_count' => 3,
    'commit' => true,
], [
    'pages' => [
        3 => $page('next page 3 uncommitted discarded current74'),
    ],
    'commit' => false,
    ]];
};

$withRoot = static function (callable $callback): mixed {
    $root = sys_get_temp_dir() . '/port-libsqlite-walsp74-' . bin2hex(random_bytes(4));
    if (!mkdir($root . '/wp-content/database', 0777, true) && !is_dir($root . '/wp-content/database')) {
        throw new RuntimeException('Unable to create WAL savepoint checkpoint test directory');
    }

    return $callback($root);
};

$applyRestart = static fn () => $withRoot(static function (string $root) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): array {
    $applied = (new SQLiteVfsFileWriter($root))->applySavepointRestartCheckpointAppend(
        $savepoints(),
        'plugin_batch',
        $wal(),
        $walBytes(),
        $databaseBytes(),
        $databasePath,
        $transactions(),
        [1, 2, 3],
        'restart'
    );

    return [
        'applied' => $applied,
        'database' => (string) file_get_contents($root . '/' . $databasePath),
        'wal' => (string) file_get_contents($root . '/' . $databasePath . '-wal'),
    ];
});

$applyTruncate = static fn () => $withRoot(static function (string $root) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): array {
    $applied = (new SQLiteVfsFileWriter($root))->applySavepointRestartCheckpointAppend(
        $savepoints(),
        'plugin_batch',
        $wal(),
        $walBytes(),
        $databaseBytes(),
        $databasePath,
        $transactions(),
        [1, 2, 3],
        'truncate'
    );

    return [
        'applied' => $applied,
        'database' => (string) file_get_contents($root . '/' . $databasePath),
        'wal' => (string) file_get_contents($root . '/' . $databasePath . '-wal'),
    ];
});

$applyNoOptionalSync = static fn () => $withRoot(static function (string $root) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): array {
    $applied = (new SQLiteVfsFileWriter($root))->applySavepointRestartCheckpointAppend(
        $savepoints(),
        'plugin_batch',
        $wal(),
        $walBytes(),
        $databaseBytes(),
        $databasePath,
        $transactions(),
        [1, 2],
        'restart',
        null,
        false,
        false
    );

    return [
        'applied' => $applied,
        'wal' => (string) file_get_contents($root . '/' . $databasePath . '-wal'),
    ];
});

$busy = static fn (): array => (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-walsp74-busy-' . bin2hex(random_bytes(4))))->applySavepointRestartCheckpointAppend(
    $savepoints(),
    'plugin_batch',
    $wal(),
    $walBytes(),
    $databaseBytes(),
    $databasePath,
    $transactions(),
    [1, 2],
    'restart',
    1
);

$restartWal = static fn (): SQLiteWal => SQLiteWal::parse($applyRestart()['wal'], null, true);
$truncateWal = static fn (): SQLiteWal => SQLiteWal::parse($applyTruncate()['wal'], null, true);

$cases = [
    'restart applies atomically' => [static fn (): mixed => $applyRestart()['applied']['atomic'], true],
    'restart status applied' => [static fn (): mixed => $applyRestart()['applied']['status'], 'applied'],
    'restart operation count' => [static fn (): mixed => $applyRestart()['applied']['applied'], 7],
    'restart writes database and wal' => [static fn (): mixed => $applyRestart()['applied']['bytes_written'], (2 * $pageSize) + (32 + (3 * (24 + $pageSize)))],
    'restart truncates database and wal' => [static fn (): mixed => $applyRestart()['applied']['bytes_truncated'], (2 * $pageSize) + (32 + (3 * (24 + $pageSize)))],
    'restart durable syncs database and wal' => [static fn (): mixed => $applyRestart()['applied']['durable_syncs'], 2],
    'restart directory syncs once' => [static fn (): mixed => $applyRestart()['applied']['directory_syncs'], 1],
    'restart first operation database write' => [static fn (): mixed => $applyRestart()['applied']['operations'][0]['reason'], 'apply_savepoint_restart_checkpoint_database_image'],
    'restart second operation database truncate' => [static fn (): mixed => $applyRestart()['applied']['operations'][1]['reason'], 'trim_savepoint_restart_checkpoint_database_image'],
    'restart wal write replaces whole sidecar' => [static fn (): mixed => $applyRestart()['applied']['operations'][3]['reason'], 'apply_savepoint_restart_checkpoint_appended_wal'],
    'restart wal trim follows write' => [static fn (): mixed => $applyRestart()['applied']['operations'][4]['reason'], 'trim_savepoint_restart_checkpoint_appended_wal'],
    'restart dependency includes vfs apply74' => [static fn (): mixed => in_array('sqlite-wal-savepoint-restart-checkpoint-vfs-apply74', $applyRestart()['applied']['dependencies'], true), true],
    'restart dependency includes checkpoint current next' => [static fn (): mixed => in_array('sqlite-wal-savepoint-restart-checkpoint-current-next', $applyRestart()['applied']['dependencies'], true), true],
    'restart plan retained frames' => [static fn (): mixed => $applyRestart()['applied']['savepoint_checkpoint_append']['retained_frame_count'], 2],
    'restart plan discarded frames' => [static fn (): mixed => $applyRestart()['applied']['savepoint_checkpoint_append']['discarded_frame_count'], 2],
    'restart plan checkpoint action' => [static fn (): mixed => $applyRestart()['applied']['savepoint_checkpoint_append']['checkpoint']['wal_action'], 'restart_wal'],
    'restart plan append last commit' => [static fn (): mixed => $applyRestart()['applied']['savepoint_checkpoint_append']['append']['last_commit_frame'], 2],
    'restart database keeps retained page one' => [static fn (): mixed => str_contains($applyRestart()['database'], 'schema retained current74'), true],
    'restart database keeps retained page two' => [static fn (): mixed => str_contains($applyRestart()['database'], 'options retained current74'), true],
    'restart database excludes discarded draft' => [static fn (): mixed => str_contains($applyRestart()['database'], 'draft discarded current74'), false],
    'restart wal includes committed retry page two' => [static fn (): mixed => str_contains($applyRestart()['wal'], 'active plugins committed current74'), true],
    'restart wal includes committed retry page three' => [static fn (): mixed => str_contains($applyRestart()['wal'], 'autoload index committed current74'), true],
    'restart wal includes uncommitted retry tail' => [static fn (): mixed => str_contains($applyRestart()['wal'], 'uncommitted discarded current74'), true],
    'restart wal excludes rolled back draft' => [static fn (): mixed => str_contains($applyRestart()['wal'], 'draft discarded current74'), false],
    'restart wal excludes rolled back row' => [static fn (): mixed => str_contains($applyRestart()['wal'], 'row discarded current74'), false],
    'restart parsed wal frame count' => [static fn (): mixed => $restartWal()->frameCount(), 3],
    'restart parsed wal last commit' => [static fn (): mixed => $restartWal()->lastCommitFrame()?->index, 2],
    'restart parsed wal uncommitted count' => [static fn (): mixed => $restartWal()->uncommittedFrameCount(), 1],
    'restart checkpoint image has retry commit' => [static fn (): mixed => str_contains($restartWal()->checkpointDatabaseImage($applyRestart()['database']), 'active plugins committed current74'), true],
    'restart checkpoint image excludes retry tail' => [static fn (): mixed => str_contains($restartWal()->checkpointDatabaseImage($applyRestart()['database']), 'uncommitted discarded current74'), false],
    'truncate status applied' => [static fn (): mixed => $applyTruncate()['applied']['status'], 'applied'],
    'truncate operation count' => [static fn (): mixed => $applyTruncate()['applied']['applied'], 7],
    'truncate checkpoint action' => [static fn (): mixed => $applyTruncate()['applied']['savepoint_checkpoint_append']['checkpoint']['wal_action'], 'truncate_wal'],
    'truncate checkpoint wal empty before append' => [static fn (): mixed => $applyTruncate()['applied']['savepoint_checkpoint_append']['checkpoint']['wal_bytes_length'], 0],
    'truncate wal starts fresh with retry frames' => [static fn (): mixed => $truncateWal()->frameCount(), 3],
    'truncate wal last commit db count' => [static fn (): mixed => $truncateWal()->lastCommitFrame()?->databasePageCountAfterCommit, 3],
    'truncate database includes retained page two' => [static fn (): mixed => str_contains($applyTruncate()['database'], 'options retained current74'), true],
    'truncate wal excludes rolled back row' => [static fn (): mixed => str_contains($applyTruncate()['wal'], 'row discarded current74'), false],
    'no optional sync operation count' => [static fn (): mixed => $applyNoOptionalSync()['applied']['applied'], 5],
    'no optional sync durable syncs database only' => [static fn (): mixed => $applyNoOptionalSync()['applied']['durable_syncs'], 1],
    'no optional sync skips directory sync' => [static fn (): mixed => $applyNoOptionalSync()['applied']['directory_syncs'], 0],
    'no optional sync still writes valid wal' => [static fn (): mixed => SQLiteWal::parse($applyNoOptionalSync()['wal'], null, true)->frameCount(), 3],
    'busy status' => [static fn (): mixed => $busy()['status'], 'busy'],
    'busy applies no operations' => [static fn (): mixed => $busy()['applied'], 0],
    'busy append skipped' => [static fn (): mixed => $busy()['savepoint_checkpoint_append']['append'], []],
    'busy atomic reported' => [static fn (): mixed => $busy()['atomic'], true],
    'busy dependency includes apply74' => [static fn (): mixed => in_array('sqlite-wal-savepoint-restart-checkpoint-vfs-apply74', $busy()['dependencies'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal savepoint restart checkpoint apply current next74 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal savepoint restart checkpoint apply current next74 rejects empty path'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applySavepointRestartCheckpointAppend($savepoints(), 'plugin_batch', $wal(), $walBytes(), $databaseBytes(), '', $transactions(), [1]));
};

$tests['wal savepoint restart checkpoint apply current next74 rejects empty savepoint'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applySavepointRestartCheckpointAppend($savepoints(), '', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), [1]));
};

$tests['wal savepoint restart checkpoint apply current next74 rejects empty pages'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applySavepointRestartCheckpointAppend($savepoints(), 'plugin_batch', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), []));
};

$tests['wal savepoint restart checkpoint apply current next74 rejects read only writer'] = static function (TestRunner $t) use ($savepoints, $wal, $walBytes, $databaseBytes, $databasePath, $transactions): void {
    $t->throws(LogicException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir(), true))->applySavepointRestartCheckpointAppend($savepoints(), 'plugin_batch', $wal(), $walBytes(), $databaseBytes(), $databasePath, $transactions(), [1]));
};

return $tests;
