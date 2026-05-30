<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';
$databaseBytes = $page('db-header-before') . $page('db-option-before') . $page('db-plugin-before');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x45464748;
    $salt2 = 0x81828384;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 32, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $label] = $frame;
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, 'retained-schema-frame'],
    [2, 3, 'retained-option-commit'],
    [3, 0, 'plugin-draft-frame'],
    [2, 3, 'rolled-back-option-commit'],
    [1, 0, 'nested-draft-frame'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$makeStack = static function () use ($page): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('application-import');
    $stack->recordPageImageWrite(1, $page('db-header-before'));
    $stack->recordWalFrameWrite(1, 1);
    $stack->recordPageImageWrite(2, $page('db-option-before'));
    $stack->recordWalFrameWrite(2, 2, true);
    $stack->savepoint('plugin-settings');
    $stack->recordPageImageWrite(3, $page('db-plugin-before'));
    $stack->recordWalFrameWrite(3, 3);
    $stack->recordWalFrameWrite(4, 2, true);
    $stack->savepoint('nested-plugin');
    $stack->recordWalFrameWrite(5, 1);

    return $stack;
};

$apply = static function (string $mode = 'truncate', ?int $currentReader = null, ?int $nextReader = null, array $pages = [1, 2, 3]) use ($databasePath, $databaseBytes, $wal, $walBytes, $makeStack): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-savepoint-checkpoint-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    $localWal = $localDatabase . '-wal';
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create temporary SQLite WAL checkpoint fixture');
    }
    file_put_contents($localDatabase, $databaseBytes);
    file_put_contents($localWal, $walBytes . 'stale-tail');

    $applied = (new SQLiteVfsFileWriter($root))->applySavepointCheckpointVisibility(
        $makeStack(),
        'plugin-settings',
        $wal,
        $walBytes,
        $databaseBytes,
        $databasePath,
        $pages,
        $mode,
        $currentReader,
        $nextReader
    );

    return [
        'root' => $root,
        'database' => (string) file_get_contents($localDatabase),
        'wal' => (string) file_get_contents($localWal),
        'applied' => $applied,
    ];
};

$truncate = static fn (): array => $apply();
$restart = static fn (): array => $apply('restart');
$full = static fn (): array => $apply('full');
$busyRestart = static fn (): array => $apply('restart', 1);
$beforeCommit = static fn (): array => $apply('truncate', 0);

$cases = [
    'truncate apply status' => static fn (): mixed => $truncate()['applied']['status'],
    'truncate apply is atomic' => static fn (): mixed => $truncate()['applied']['atomic'],
    'truncate operation count' => static fn (): mixed => $truncate()['applied']['applied'],
    'truncate bytes written include database only' => static fn (): mixed => $truncate()['applied']['bytes_written'],
    'truncate bytes truncated include database only' => static fn (): mixed => $truncate()['applied']['bytes_truncated'],
    'truncate durable sync count' => static fn (): mixed => $truncate()['applied']['durable_syncs'],
    'truncate directory sync count' => static fn (): mixed => $truncate()['applied']['directory_syncs'],
    'truncate database write reason' => static fn (): mixed => $truncate()['applied']['operations'][0]['reason'],
    'truncate wal write reason' => static fn (): mixed => $truncate()['applied']['operations'][3]['reason'],
    'truncate directory sync reason' => static fn (): mixed => $truncate()['applied']['operations'][6]['reason'],
    'truncate checkpoint status is ready' => static fn (): mixed => $truncate()['applied']['savepoint_checkpoint']['status'],
    'truncate checkpoint mode is normalized' => static fn (): mixed => $truncate()['applied']['savepoint_checkpoint']['mode'],
    'truncate retained frame count' => static fn (): mixed => $truncate()['applied']['savepoint_checkpoint']['retained_frame_count'],
    'truncate discarded frame count' => static fn (): mixed => $truncate()['applied']['savepoint_checkpoint']['discarded_frame_count'],
    'truncate wal action' => static fn (): mixed => $truncate()['applied']['savepoint_checkpoint']['current_durable']['wal_action'],
    'truncate wal file is empty' => static fn (): mixed => strlen($truncate()['wal']),
    'truncate database contains retained schema' => static fn (): mixed => str_contains($truncate()['database'], 'retained-schema-frame'),
    'truncate database contains retained option' => static fn (): mixed => str_contains($truncate()['database'], 'retained-option-commit'),
    'truncate database excludes rolled back option' => static fn (): mixed => str_contains($truncate()['database'], 'rolled-back-option-commit'),
    'truncate wal removes stale tail' => static fn (): mixed => str_contains($truncate()['wal'], 'stale-tail'),
    'truncate boundary current page sources' => static fn (): mixed => $truncate()['applied']['reader_boundary']['current_reader_sources'],
    'truncate boundary next page sources' => static fn (): mixed => $truncate()['applied']['reader_boundary']['next_reader_sources'],
    'truncate boundary current frames' => static fn (): mixed => $truncate()['applied']['reader_boundary']['current_reader_frame_indexes'],
    'truncate boundary next frames' => static fn (): mixed => $truncate()['applied']['reader_boundary']['next_reader_frame_indexes'],
    'truncate boundary images match' => static fn (): mixed => $truncate()['applied']['reader_boundary']['images_match'],
    'truncate boundary current reader kept wal' => static fn (): mixed => $truncate()['applied']['reader_boundary']['current_reader_kept_wal_snapshot'],
    'truncate boundary next reader uses database' => static fn (): mixed => $truncate()['applied']['reader_boundary']['next_reader_uses_checkpoint_database'],
    'truncate dependency has vfs visibility marker' => static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-vfs-visibility-apply', $truncate()['applied']['dependencies'], true),
    'truncate dependency has current next marker' => static fn (): mixed => in_array('sqlite-wal-reader-checkpoint-boundary-current-next', $truncate()['applied']['dependencies'], true),
    'truncate dependency has atomic rollback marker' => static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $truncate()['applied']['dependencies'], true),
    'restart wal action' => static fn (): mixed => $restart()['applied']['savepoint_checkpoint']['current_durable']['wal_action'],
    'restart wal is header only' => static fn (): mixed => strlen($restart()['wal']),
    'restart wal parses as empty wal' => static fn (): mixed => SQLiteWal::parse($restart()['wal'], 512, true)->frameCount(),
    'restart database contains retained option' => static fn (): mixed => str_contains($restart()['database'], 'retained-option-commit'),
    'restart boundary next reader uses database' => static fn (): mixed => $restart()['applied']['reader_boundary']['next_reader_uses_checkpoint_database'],
    'full wal action preserves wal' => static fn (): mixed => $full()['applied']['savepoint_checkpoint']['current_durable']['wal_action'],
    'full wal keeps retained prefix length' => static fn (): mixed => strlen($full()['wal']),
    'full wal excludes rolled back frame' => static fn (): mixed => str_contains($full()['wal'], 'rolled-back-option-commit'),
    'full boundary next reader still sees wal' => static fn (): mixed => $full()['applied']['reader_boundary']['next_reader_sources'][0],
    'full boundary images match' => static fn (): mixed => $full()['applied']['reader_boundary']['images_match'],
    'busy restart status is busy' => static fn (): mixed => $busyRestart()['applied']['savepoint_checkpoint']['status'],
    'busy restart reason reports reader blocker' => static fn (): mixed => $busyRestart()['applied']['savepoint_checkpoint']['reason'],
    'busy restart preserves wal' => static fn (): mixed => $busyRestart()['applied']['savepoint_checkpoint']['current_durable']['wal_action'],
    'busy restart wal keeps retained prefix' => static fn (): mixed => strlen($busyRestart()['wal']),
    'busy restart next reader still has wal source' => static fn (): mixed => $busyRestart()['applied']['reader_boundary']['next_reader_sources'][1],
    'before commit current reader sees database page one' => static fn (): mixed => $beforeCommit()['applied']['reader_boundary']['current_reader_sources'][0],
    'before commit images do not match next checkpoint' => static fn (): mixed => $beforeCommit()['applied']['reader_boundary']['images_match'],
    'single page visibility only returns one current row' => static fn (): mixed => count($apply('truncate', null, null, [2])['applied']['reader_boundary']['current_reader']),
    'single page visibility returns page two' => static fn (): mixed => $apply('truncate', null, null, [2])['applied']['reader_boundary']['next_reader'][0]['page_number'],
    'empty database path is rejected' => static function () use ($makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-empty-path-' . bin2hex(random_bytes(4))))
                ->applySavepointCheckpointVisibility($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, '', [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only writer is rejected' => static function () use ($databasePath, $makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-readonly-' . bin2hex(random_bytes(4)), true))
                ->applySavepointCheckpointVisibility($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, $databasePath, [1]);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty page list is rejected' => static function () use ($databasePath, $makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-empty-pages-' . bin2hex(random_bytes(4))))
                ->applySavepointCheckpointVisibility($makeStack(), 'plugin-settings', $wal, $walBytes, $databaseBytes, $databasePath, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'missing savepoint is rejected' => static function () use ($databasePath, $makeStack, $wal, $walBytes, $databaseBytes): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-missing-savepoint-' . bin2hex(random_bytes(4))))
                ->applySavepointCheckpointVisibility($makeStack(), 'missing', $wal, $walBytes, $databaseBytes, $databasePath, [1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'truncate apply status' => 'applied',
    'truncate apply is atomic' => true,
    'truncate operation count' => 7,
    'truncate bytes written include database only' => 1536,
    'truncate bytes truncated include database only' => 1536,
    'truncate durable sync count' => 2,
    'truncate directory sync count' => 1,
    'truncate database write reason' => 'apply_savepoint_checkpoint_database_image',
    'truncate wal write reason' => 'apply_savepoint_checkpoint_wal_state',
    'truncate directory sync reason' => 'persist_savepoint_checkpoint_visibility',
    'truncate checkpoint status is ready' => 'ready',
    'truncate checkpoint mode is normalized' => 'truncate',
    'truncate retained frame count' => 2,
    'truncate discarded frame count' => 3,
    'truncate wal action' => 'truncate_wal',
    'truncate wal file is empty' => 0,
    'truncate database contains retained schema' => true,
    'truncate database contains retained option' => true,
    'truncate database excludes rolled back option' => false,
    'truncate wal removes stale tail' => false,
    'truncate boundary current page sources' => ['wal', 'wal', 'database'],
    'truncate boundary next page sources' => ['database', 'database', 'database'],
    'truncate boundary current frames' => [1, 2, null],
    'truncate boundary next frames' => [null, null, null],
    'truncate boundary images match' => true,
    'truncate boundary current reader kept wal' => true,
    'truncate boundary next reader uses database' => true,
    'truncate dependency has vfs visibility marker' => true,
    'truncate dependency has current next marker' => true,
    'truncate dependency has atomic rollback marker' => true,
    'restart wal action' => 'restart_wal',
    'restart wal is header only' => 32,
    'restart wal parses as empty wal' => 0,
    'restart database contains retained option' => true,
    'restart boundary next reader uses database' => true,
    'full wal action preserves wal' => 'preserve_wal',
    'full wal keeps retained prefix length' => 1104,
    'full wal excludes rolled back frame' => false,
    'full boundary next reader still sees wal' => 'wal',
    'full boundary images match' => true,
    'busy restart status is busy' => 'busy',
    'busy restart reason reports reader blocker' => 'reader_blocks_checkpoint_completion',
    'busy restart preserves wal' => 'preserve_wal',
    'busy restart wal keeps retained prefix' => 1104,
    'busy restart next reader still has wal source' => 'wal',
    'before commit current reader sees database page one' => 'database',
    'before commit images do not match next checkpoint' => false,
    'single page visibility only returns one current row' => 1,
    'single page visibility returns page two' => 2,
    'empty database path is rejected' => 'rejected',
    'read only writer is rejected' => 'rejected',
    'empty page list is rejected' => 'rejected',
    'missing savepoint is rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal savepoint checkpoint visibility current next32 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
