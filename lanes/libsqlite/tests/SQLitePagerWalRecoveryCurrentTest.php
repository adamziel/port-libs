<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$baseDatabase = $page('current recovery schema base')
    . $page('current recovery wp_options base')
    . $page('current recovery active_plugins base')
    . $page('current recovery transient base');
$salt1 = 0x5a170001;
$salt2 = 0x5a170002;

$buildWal = static function (array $frames, ?callable $mutate = null) use ($pageSize, $page, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 5, $salt1, $salt2);
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

$committedTailWal = $buildWal([
    [1, 0, 'current recovery schema committed'],
    [2, 4, 'current recovery option committed'],
    [3, 0, 'current recovery plugin draft'],
    [4, 0, 'current recovery transient draft'],
]);
$corruptTailWal = $buildWal([
    [1, 0, 'current recovery schema before corrupt'],
    [2, 4, 'current recovery option before corrupt'],
    [3, 0, 'current recovery corrupt draft'],
], static fn (string $bytes): string => substr_replace($bytes, 'x', 32 + (2 * (24 + $pageSize)) + 48, 1));
$draftOnlyWal = $buildWal([
    [2, 0, 'current recovery draft option only'],
    [3, 0, 'current recovery draft plugin only'],
]);

$withCurrentFiles = static function (?string $walBytes, callable $callback) use ($databasePath, $baseDatabase): mixed {
    $root = sys_get_temp_dir() . '/port-libsqlite-current-wal-recovery-' . bin2hex(random_bytes(4));
    $databaseLocal = $root . '/' . $databasePath;
    $walLocal = $databaseLocal . '-wal';
    $directory = dirname($databaseLocal);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create current WAL recovery test directory');
    }
    file_put_contents($databaseLocal, $baseDatabase);
    if ($walBytes !== null) {
        file_put_contents($walLocal, $walBytes);
    }

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

$recoverCommittedTail = static fn (): array => $withCurrentFiles(
    $committedTailWal,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'result' => (new SQLiteVfsFileWriter($root))->applyCurrentWalTransactionRecovery($databasePath, $pageSize),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_bytes' => (string) file_get_contents($walLocal),
    ]
);
$recoverCorruptTail = static fn (): array => $withCurrentFiles(
    $corruptTailWal,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'result' => (new SQLiteVfsFileWriter($root))->applyCurrentWalTransactionRecovery($databasePath, $pageSize),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_bytes' => (string) file_get_contents($walLocal),
    ]
);
$recoverDraftOnly = static fn (): array => $withCurrentFiles(
    $draftOnlyWal,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'result' => (new SQLiteVfsFileWriter($root))->applyCurrentWalTransactionRecovery($databasePath, $pageSize),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_bytes' => (string) file_get_contents($walLocal),
    ]
);
$recoverMissingWal = static fn (): array => $withCurrentFiles(
    null,
    static fn (string $root, string $databaseLocal, string $walLocal): array => [
        'result' => (new SQLiteVfsFileWriter($root))->applyCurrentWalTransactionRecovery($databasePath, $pageSize),
        'database_bytes' => (string) file_get_contents($databaseLocal),
        'wal_exists' => is_file($walLocal),
    ]
);

$cases = [
    'committed status' => [static fn (): mixed => $recoverCommittedTail()['result']['status'], 'applied'],
    'committed atomic' => [static fn (): mixed => $recoverCommittedTail()['result']['atomic'], true],
    'committed operations' => [static fn (): mixed => $recoverCommittedTail()['result']['applied'], 7],
    'committed bytes written' => [static fn (): mixed => $recoverCommittedTail()['result']['bytes_written'], 2048 + 1104],
    'committed bytes truncated' => [static fn (): mixed => $recoverCommittedTail()['result']['bytes_truncated'], 2048 + 1104],
    'committed durable syncs' => [static fn (): mixed => $recoverCommittedTail()['result']['durable_syncs'], 2],
    'committed directory syncs' => [static fn (): mixed => $recoverCommittedTail()['result']['directory_syncs'], 1],
    'committed source database path' => [static fn (): mixed => $recoverCommittedTail()['result']['current_source']['database_path'], $databasePath],
    'committed source wal path' => [static fn (): mixed => $recoverCommittedTail()['result']['current_source']['wal_path'], $databasePath . '-wal'],
    'committed source database bytes' => [static fn (): mixed => $recoverCommittedTail()['result']['current_source']['database_bytes'], 2048],
    'committed source wal bytes' => [static fn (): mixed => $recoverCommittedTail()['result']['current_source']['wal_bytes'], strlen($committedTailWal)],
    'committed source had wal' => [static fn (): mixed => $recoverCommittedTail()['result']['current_source']['had_wal'], true],
    'committed recovery status' => [static fn (): mixed => $recoverCommittedTail()['result']['recovery']['status'], 'recovered_committed_prefix'],
    'committed recovery reason' => [static fn (): mixed => $recoverCommittedTail()['result']['recovery']['reason'], 'uncommitted_valid_tail_after_last_commit'],
    'committed frame count' => [static fn (): mixed => $recoverCommittedTail()['result']['recovery']['committed_frame_count'], 2],
    'committed discarded valid tail' => [static fn (): mixed => $recoverCommittedTail()['result']['recovery']['discarded_valid_tail_frame_count'], 2],
    'committed discarded corrupt tail' => [static fn (): mixed => $recoverCommittedTail()['result']['recovery']['discarded_corrupt_tail_frame_count'], 0],
    'committed database has option page' => [static fn (): mixed => str_contains($recoverCommittedTail()['database_bytes'], 'current recovery option committed'), true],
    'committed database drops plugin draft' => [static fn (): mixed => str_contains($recoverCommittedTail()['database_bytes'], 'current recovery plugin draft'), false],
    'committed database drops transient draft' => [static fn (): mixed => str_contains($recoverCommittedTail()['database_bytes'], 'current recovery transient draft'), false],
    'committed wal length' => [static fn (): mixed => strlen($recoverCommittedTail()['wal_bytes']), 1104],
    'committed wal parse frames' => [static fn (): mixed => SQLiteWal::parse($recoverCommittedTail()['wal_bytes'], $pageSize, true)->frameCount(), 2],
    'committed wal has no uncommitted frames' => [static fn (): mixed => SQLiteWal::parse($recoverCommittedTail()['wal_bytes'], $pageSize, true)->uncommittedFrameCount(), 0],
    'committed dependency current recovery' => [static fn (): mixed => in_array('sqlite-current-wal-transaction-recovery', $recoverCommittedTail()['result']['dependencies'], true), true],
    'committed dependency transaction boundary' => [static fn (): mixed => in_array('sqlite-wal-transaction-recovery-boundary', $recoverCommittedTail()['result']['dependencies'], true), true],
    'committed dependency atomic' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $recoverCommittedTail()['result']['dependencies'], true), true],
    'committed first operation' => [static fn (): mixed => $recoverCommittedTail()['result']['operations'][0]['reason'], 'checkpoint_committed_wal_transaction_prefix'],
    'committed wal truncate operation' => [static fn (): mixed => $recoverCommittedTail()['result']['operations'][4]['reason'], 'discard_uncommitted_or_corrupt_wal_transaction_tail'],
    'corrupt status' => [static fn (): mixed => $recoverCorruptTail()['result']['status'], 'applied'],
    'corrupt reason' => [static fn (): mixed => $recoverCorruptTail()['result']['recovery']['reason'], 'corrupt_tail_after_committed_prefix'],
    'corrupt invalid frame' => [static fn (): mixed => $recoverCorruptTail()['result']['recovery']['first_invalid_frame'], 3],
    'corrupt discarded corrupt count' => [static fn (): mixed => $recoverCorruptTail()['result']['recovery']['discarded_corrupt_tail_frame_count'], 1],
    'corrupt database has committed page' => [static fn (): mixed => str_contains($recoverCorruptTail()['database_bytes'], 'current recovery option before corrupt'), true],
    'corrupt database drops corrupt draft' => [static fn (): mixed => str_contains($recoverCorruptTail()['database_bytes'], 'current recovery corrupt draft'), false],
    'corrupt wal length' => [static fn (): mixed => strlen($recoverCorruptTail()['wal_bytes']), 1104],
    'corrupt wal parse frames' => [static fn (): mixed => SQLiteWal::parse($recoverCorruptTail()['wal_bytes'], $pageSize, true)->frameCount(), 2],
    'draft status' => [static fn (): mixed => $recoverDraftOnly()['result']['status'], 'applied'],
    'draft operations omit database' => [static fn (): mixed => $recoverDraftOnly()['result']['applied'], 4],
    'draft bytes written header only' => [static fn (): mixed => $recoverDraftOnly()['result']['bytes_written'], 32],
    'draft bytes truncated header only' => [static fn (): mixed => $recoverDraftOnly()['result']['bytes_truncated'], 32],
    'draft durable syncs' => [static fn (): mixed => $recoverDraftOnly()['result']['durable_syncs'], 1],
    'draft recovery reason' => [static fn (): mixed => $recoverDraftOnly()['result']['recovery']['reason'], 'no_committed_transaction_in_valid_prefix'],
    'draft database unchanged' => [static fn (): mixed => $recoverDraftOnly()['database_bytes'], $baseDatabase],
    'draft wal header only' => [static fn (): mixed => strlen($recoverDraftOnly()['wal_bytes']), 32],
    'draft wal parse empty' => [static fn (): mixed => SQLiteWal::parse($recoverDraftOnly()['wal_bytes'], $pageSize, true)->frameCount(), 0],
    'missing wal status' => [static fn (): mixed => $recoverMissingWal()['result']['status'], 'skipped'],
    'missing wal reason' => [static fn (): mixed => $recoverMissingWal()['result']['recovery']['reason'], 'wal_sidecar_missing'],
    'missing wal operations' => [static fn (): mixed => $recoverMissingWal()['result']['applied'], 0],
    'missing wal source had wal false' => [static fn (): mixed => $recoverMissingWal()['result']['current_source']['had_wal'], false],
    'missing wal source wal bytes zero' => [static fn (): mixed => $recoverMissingWal()['result']['current_source']['wal_bytes'], 0],
    'missing wal database unchanged' => [static fn (): mixed => $recoverMissingWal()['database_bytes'], $baseDatabase],
    'missing wal file stays absent' => [static fn (): mixed => $recoverMissingWal()['wal_exists'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager wal recovery current ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$tests['pager wal recovery current rejects empty database path'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): mixed => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyCurrentWalTransactionRecovery(''));
};

$tests['pager wal recovery current rejects missing database'] = static function (TestRunner $t) use ($databasePath): void {
    $root = sys_get_temp_dir() . '/port-libsqlite-current-wal-recovery-missing-' . bin2hex(random_bytes(4));
    $t->throws(RuntimeException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root))->applyCurrentWalTransactionRecovery($databasePath));
};

$tests['pager wal recovery current rejects readonly writer when wal exists'] = static function (TestRunner $t) use ($withCurrentFiles, $committedTailWal, $databasePath, $pageSize): void {
    $withCurrentFiles($committedTailWal, static function (string $root) use ($t, $databasePath, $pageSize): void {
        $t->throws(LogicException::class, static fn (): mixed => (new SQLiteVfsFileWriter($root, readOnly: true))->applyCurrentWalTransactionRecovery($databasePath, $pageSize));
    });
};

return $tests;
