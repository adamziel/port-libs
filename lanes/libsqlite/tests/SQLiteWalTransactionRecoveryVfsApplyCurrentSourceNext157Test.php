<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('next157 base schema page')
    . $page('next157 base wp_options page')
    . $page('next157 base plugin page')
    . $page('next157 base transient page');
$salt1 = 0x15715701;
$salt2 = 0x15715702;

$buildWal = static function (array $frames, ?callable $mutate = null) use ($pageSize, $page, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 157, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $mutate === null ? $bytes : $mutate($bytes);
};

$walWithUncommittedTail = $buildWal([
    [1, 0, 'next157 schema committed before import'],
    [2, 4, 'next157 active_plugins committed'],
    [3, 0, 'next157 plugin draft uncommitted'],
    [4, 0, 'next157 transient draft uncommitted'],
]);
$walWithCorruptTail = $buildWal([
    [1, 0, 'next157 schema committed before corrupt tail'],
    [2, 4, 'next157 options committed before corrupt tail'],
    [3, 0, 'next157 corrupt plugin tail'],
], static fn (string $bytes): string => substr_replace($bytes, 'Z', 32 + (2 * (24 + $pageSize)) + 64, 1));
$walWithNoCommit = $buildWal([
    [1, 0, 'next157 schema draft no commit'],
    [2, 0, 'next157 options draft no commit'],
]);

$withFiles = static function (string $walBytes, callable $callback) use ($databasePath, $databaseBytes): mixed {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-txn-apply-next157-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . $databasePath;
    $walLocal = $databaseLocal . '-wal';
    $directory = dirname($databaseLocal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create WAL transaction recovery VFS test directory');
    }
    file_put_contents($databaseLocal, $databaseBytes);
    file_put_contents($walLocal, $walBytes);

    try {
        return $callback($root, $databaseLocal, $walLocal);
    } finally {
        if (is_file($walLocal)) {
            unlink($walLocal);
        }
        if (is_file($databaseLocal)) {
            unlink($databaseLocal);
        }
        @rmdir($directory);
        @rmdir(dirname($directory));
        @rmdir($root);
    }
};

$applyTail = static fn (): array => $withFiles(
    $walWithUncommittedTail,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'applied' => (new SQLiteVfsFileWriter($root))->applyWalTransactionRecoveryBoundary(
            $walWithUncommittedTail,
            $databaseBytes,
            $databasePath,
            $pageSize
        ),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_bytes' => (string) file_get_contents($walLocal),
    ]
);
$applyCorrupt = static fn (): array => $withFiles(
    $walWithCorruptTail,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'applied' => (new SQLiteVfsFileWriter($root))->applyWalTransactionRecoveryBoundary(
            $walWithCorruptTail,
            $databaseBytes,
            $databasePath,
            $pageSize
        ),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_bytes' => (string) file_get_contents($walLocal),
    ]
);
$applyNoCommit = static fn (): array => $withFiles(
    $walWithNoCommit,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'applied' => (new SQLiteVfsFileWriter($root))->applyWalTransactionRecoveryBoundary(
            $walWithNoCommit,
            $databaseBytes,
            $databasePath,
            $pageSize
        ),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_bytes' => (string) file_get_contents($walLocal),
    ]
);

$cases = [
    'tail status applied' => [static fn (): mixed => $applyTail()['applied']['status'], 'applied'],
    'tail atomic flag' => [static fn (): mixed => $applyTail()['applied']['atomic'], true],
    'tail applied operation count' => [static fn (): mixed => $applyTail()['applied']['applied'], 7],
    'tail bytes written includes database and wal prefix' => [static fn (): mixed => $applyTail()['applied']['bytes_written'], 2048 + 1104],
    'tail bytes truncated includes database and wal prefix' => [static fn (): mixed => $applyTail()['applied']['bytes_truncated'], 2048 + 1104],
    'tail durable sync count' => [static fn (): mixed => $applyTail()['applied']['durable_syncs'], 2],
    'tail directory sync count' => [static fn (): mixed => $applyTail()['applied']['directory_syncs'], 1],
    'tail recovery status' => [static fn (): mixed => $applyTail()['applied']['recovery']['status'], 'recovered_committed_prefix'],
    'tail recovery reason' => [static fn (): mixed => $applyTail()['applied']['recovery']['reason'], 'uncommitted_valid_tail_after_last_commit'],
    'tail committed frame count' => [static fn (): mixed => $applyTail()['applied']['recovery']['committed_frame_count'], 2],
    'tail valid frame count' => [static fn (): mixed => $applyTail()['applied']['recovery']['valid_frame_count'], 4],
    'tail discarded valid frames' => [static fn (): mixed => $applyTail()['applied']['recovery']['discarded_valid_tail_frame_count'], 2],
    'tail discarded corrupt frames' => [static fn (): mixed => $applyTail()['applied']['recovery']['discarded_corrupt_tail_frame_count'], 0],
    'tail database contains committed option' => [static fn (): mixed => str_contains($applyTail()['database_bytes'], 'next157 active_plugins committed'), true],
    'tail database excludes plugin draft' => [static fn (): mixed => str_contains($applyTail()['database_bytes'], 'next157 plugin draft uncommitted'), false],
    'tail database excludes transient draft' => [static fn (): mixed => str_contains($applyTail()['database_bytes'], 'next157 transient draft uncommitted'), false],
    'tail wal bytes committed length' => [static fn (): mixed => strlen($applyTail()['wal_bytes']), 1104],
    'tail wal parseable committed prefix' => [static fn (): mixed => SQLiteWal::parse($applyTail()['wal_bytes'], $pageSize, true)->frameCount(), 2],
    'tail wal has no uncommitted frames' => [static fn (): mixed => SQLiteWal::parse($applyTail()['wal_bytes'], $pageSize, true)->uncommittedFrameCount(), 0],
    'tail first operation writes database' => [static fn (): mixed => $applyTail()['applied']['operations'][0]['reason'], 'checkpoint_committed_wal_transaction_prefix'],
    'tail second operation trims database' => [static fn (): mixed => $applyTail()['applied']['operations'][1]['reason'], 'trim_checkpointed_transaction_database_image'],
    'tail fourth operation writes wal prefix' => [static fn (): mixed => $applyTail()['applied']['operations'][3]['reason'], 'restore_committed_wal_transaction_prefix'],
    'tail fifth operation discards wal tail' => [static fn (): mixed => $applyTail()['applied']['operations'][4]['reason'], 'discard_uncommitted_or_corrupt_wal_transaction_tail'],
    'tail dependency marker' => [static fn (): mixed => in_array('sqlite-wal-transaction-boundary-vfs-apply', $applyTail()['applied']['dependencies'], true), true],
    'tail atomic dependency marker' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $applyTail()['applied']['dependencies'], true), true],
    'tail transaction dependency marker' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $applyTail()['applied']['dependencies'], true), true],
    'corrupt status applied' => [static fn (): mixed => $applyCorrupt()['applied']['status'], 'applied'],
    'corrupt reason' => [static fn (): mixed => $applyCorrupt()['applied']['recovery']['reason'], 'corrupt_tail_after_committed_prefix'],
    'corrupt first invalid frame' => [static fn (): mixed => $applyCorrupt()['applied']['recovery']['first_invalid_frame'], 3],
    'corrupt discarded corrupt count' => [static fn (): mixed => $applyCorrupt()['applied']['recovery']['discarded_corrupt_tail_frame_count'], 1],
    'corrupt committed frame count' => [static fn (): mixed => $applyCorrupt()['applied']['recovery']['committed_frame_count'], 2],
    'corrupt wal bytes committed length' => [static fn (): mixed => strlen($applyCorrupt()['wal_bytes']), 1104],
    'corrupt database contains committed option' => [static fn (): mixed => str_contains($applyCorrupt()['database_bytes'], 'next157 options committed before corrupt tail'), true],
    'corrupt database excludes corrupt tail' => [static fn (): mixed => str_contains($applyCorrupt()['database_bytes'], 'next157 corrupt plugin tail'), false],
    'corrupt wal parseable committed prefix' => [static fn (): mixed => SQLiteWal::parse($applyCorrupt()['wal_bytes'], $pageSize, true)->lastCommitFrame()?->index, 2],
    'no commit status applied' => [static fn (): mixed => $applyNoCommit()['applied']['status'], 'applied'],
    'no commit operation count omits database checkpoint' => [static fn (): mixed => $applyNoCommit()['applied']['applied'], 4],
    'no commit bytes written is header only' => [static fn (): mixed => $applyNoCommit()['applied']['bytes_written'], 32],
    'no commit bytes truncated is header only' => [static fn (): mixed => $applyNoCommit()['applied']['bytes_truncated'], 32],
    'no commit durable sync count' => [static fn (): mixed => $applyNoCommit()['applied']['durable_syncs'], 1],
    'no commit directory sync count' => [static fn (): mixed => $applyNoCommit()['applied']['directory_syncs'], 1],
    'no commit recovery reason' => [static fn (): mixed => $applyNoCommit()['applied']['recovery']['reason'], 'no_committed_transaction_in_valid_prefix'],
    'no commit can checkpoint false' => [static fn (): mixed => $applyNoCommit()['applied']['recovery']['can_checkpoint'], false],
    'no commit database unchanged' => [static fn (): mixed => $applyNoCommit()['database_bytes'], $databaseBytes],
    'no commit wal header length' => [static fn (): mixed => strlen($applyNoCommit()['wal_bytes']), 32],
    'no commit wal parseable empty' => [static fn (): mixed => SQLiteWal::parse($applyNoCommit()['wal_bytes'], $pageSize, true)->frameCount(), 0],
    'no commit first operation writes wal' => [static fn (): mixed => $applyNoCommit()['applied']['operations'][0]['reason'], 'restore_committed_wal_transaction_prefix'],
    'no commit second operation discards tail' => [static fn (): mixed => $applyNoCommit()['applied']['operations'][1]['reason'], 'discard_uncommitted_or_corrupt_wal_transaction_tail'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal transaction recovery vfs apply current source next157 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['wal transaction recovery vfs apply current source next157 rejects empty path'] = static function (TestRunner $t) use ($walWithUncommittedTail, $databaseBytes, $pageSize): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyWalTransactionRecoveryBoundary($walWithUncommittedTail, $databaseBytes, '', $pageSize));
};

$tests['wal transaction recovery vfs apply current source next157 rejects readonly writer'] = static function (TestRunner $t) use ($walWithUncommittedTail, $databaseBytes, $databasePath, $pageSize): void {
    $t->throws(LogicException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir(), readOnly: true))->applyWalTransactionRecoveryBoundary($walWithUncommittedTail, $databaseBytes, $databasePath, $pageSize));
};

$tests['wal transaction recovery vfs apply current source next157 rejects bad wal checksum'] = static function (TestRunner $t) use ($walWithUncommittedTail, $databaseBytes, $databasePath, $pageSize): void {
    $badWal = substr_replace($walWithUncommittedTail, "\0\0\0\0", 0, 4);
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyWalTransactionRecoveryBoundary($badWal, $databaseBytes, $databasePath, $pageSize));
};

return $tests;
