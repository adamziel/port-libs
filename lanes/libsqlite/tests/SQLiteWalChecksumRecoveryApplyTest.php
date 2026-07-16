<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/wp-content/database/.ht.sqlite';

$makeWal = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x13572468;
    $salt2 = 0x24681357;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 17, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$baseDatabase = $page('db-header-before-recovery') . $page('base-siteurl-page') . $page('base-autoload-index') . $page('base-plugin-page');
$committedWal = $makeWal([
    [2, 0, $page('wal-siteurl-draft')],
    [2, 4, $page('wal-siteurl-committed')],
    [3, 0, $page('wal-index-draft')],
    [3, 4, $page('wal-index-committed')],
]);
$corruptTailWal = substr($committedWal, 0, -8) . 'corrupt!';
$truncatedTailWal = substr($committedWal, 0, 32 + (3 * (24 + $pageSize)) + 71);
$uncommittedWal = $makeWal([
    [2, 0, $page('uncommitted-plugin-draft')],
    [3, 0, $page('uncommitted-index-draft')],
]);
$badHeaderWal = substr_replace($committedWal, "\xff", 31, 1);

$apply = static function (string $walBytes, string $databaseBytes = null) use ($databasePath, $baseDatabase): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-wal-boundary-' . bin2hex(random_bytes(4));
    $localDatabase = $root . $databasePath;
    $localWal = $localDatabase . '-wal';
    if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
        throw new RuntimeException('Unable to create temporary SQLite fixture directory');
    }
    file_put_contents($localDatabase, $databaseBytes ?? $baseDatabase);
    file_put_contents($localWal, $walBytes . 'stale-local-tail');

    $applied = (new SQLiteVfsFileWriter($root))->applyWalChecksumRecoveryBoundary(
        $walBytes,
        $databaseBytes ?? $baseDatabase,
        $databasePath,
        512
    );

    return [
        'root' => $root,
        'database' => (string) file_get_contents($localDatabase),
        'wal' => (string) file_get_contents($localWal),
        'applied' => $applied,
    ];
};

$boundary = static fn (string $walBytes): array => SQLiteWal::checksumRecoveryBoundary($walBytes, $baseDatabase, 512);
$result = static fn (): array => $apply($corruptTailWal);
$truncatedResult = static fn (): array => $apply($truncatedTailWal);
$uncommittedResult = static fn (): array => $apply($uncommittedWal);
$badHeaderResult = static fn (): array => $apply($badHeaderWal);

$cases = [
    'corrupt tail status is recovered prefix' => static fn (): mixed => $boundary($corruptTailWal)['status'],
    'corrupt tail reason is checksum mismatch' => static fn (): mixed => $boundary($corruptTailWal)['reason'],
    'corrupt tail keeps three valid frames' => static fn (): mixed => $boundary($corruptTailWal)['valid_frame_count'],
    'corrupt tail reports fourth frame invalid' => static fn (): mixed => $boundary($corruptTailWal)['first_invalid_frame'],
    'corrupt tail trims to three frame prefix bytes' => static fn (): mixed => $boundary($corruptTailWal)['recovery_end_offset'],
    'corrupt tail has valid wal prefix length' => static fn (): mixed => strlen($boundary($corruptTailWal)['valid_wal_bytes']),
    'corrupt tail last commit is second frame' => static fn (): mixed => $boundary($corruptTailWal)['last_commit_frame'],
    'corrupt tail last commit page count is four' => static fn (): mixed => $boundary($corruptTailWal)['last_commit_page_count'],
    'corrupt tail preserves one uncommitted valid frame' => static fn (): mixed => $boundary($corruptTailWal)['uncommitted_frame_count'],
    'corrupt tail can checkpoint committed prefix' => static fn (): mixed => $boundary($corruptTailWal)['can_checkpoint'],
    'corrupt tail checkpoint keeps database page count' => static fn (): mixed => $boundary($corruptTailWal)['checkpoint_database_page_count'],
    'corrupt tail checkpoint contains committed siteurl page' => static fn (): mixed => str_contains((string) $boundary($corruptTailWal)['checkpoint_database_bytes'], 'wal-siteurl-committed'),
    'corrupt tail checkpoint ignores uncommitted index draft' => static fn (): mixed => str_contains((string) $boundary($corruptTailWal)['checkpoint_database_bytes'], 'wal-index-draft'),
    'corrupt tail apply status' => static fn (): mixed => $result()['applied']['status'],
    'corrupt tail apply is atomic' => static fn (): mixed => $result()['applied']['atomic'],
    'corrupt tail apply operation count' => static fn (): mixed => $result()['applied']['applied'],
    'corrupt tail apply bytes written include database and wal prefix' => static fn (): mixed => $result()['applied']['bytes_written'],
    'corrupt tail apply durable sync count' => static fn (): mixed => $result()['applied']['durable_syncs'],
    'corrupt tail apply directory sync count' => static fn (): mixed => $result()['applied']['directory_syncs'],
    'corrupt tail apply writes committed page into database' => static fn (): mixed => str_contains($result()['database'], 'wal-siteurl-committed'),
    'corrupt tail apply leaves uncommitted index out of database' => static fn (): mixed => str_contains($result()['database'], 'wal-index-draft'),
    'corrupt tail apply truncates wal to valid prefix' => static fn (): mixed => strlen($result()['wal']),
    'corrupt tail apply removes stale local tail' => static fn (): mixed => str_contains($result()['wal'], 'stale-local-tail'),
    'corrupt tail apply keeps valid uncommitted frame in wal' => static fn (): mixed => str_contains($result()['wal'], 'wal-index-draft'),
    'corrupt tail apply removes corrupt frame bytes' => static fn (): mixed => str_contains($result()['wal'], 'wal-index-committed'),
    'corrupt tail apply exposes recovery status' => static fn (): mixed => $result()['applied']['recovery']['status'],
    'corrupt tail apply exposes recovery dependency' => static fn (): mixed => in_array('sqlite-wal-checksum-recovery-boundary', $result()['applied']['dependencies'], true),
    'corrupt tail apply exposes vfs dependency' => static fn (): mixed => in_array('sqlite-wal-checksum-boundary-vfs-apply', $result()['applied']['dependencies'], true),
    'corrupt tail first operation checkpoints database' => static fn (): mixed => $result()['applied']['operations'][0]['reason'],
    'corrupt tail wal restore operation follows database sync' => static fn (): mixed => $result()['applied']['operations'][3]['reason'],
    'corrupt tail final operation persists directory' => static fn (): mixed => $result()['applied']['operations'][6]['reason'],
    'truncated tail reason is truncated frame tail' => static fn (): mixed => $boundary($truncatedTailWal)['reason'],
    'truncated tail invalid frame is fourth slot' => static fn (): mixed => $boundary($truncatedTailWal)['first_invalid_frame'],
    'truncated tail valid frame count' => static fn (): mixed => $boundary($truncatedTailWal)['valid_frame_count'],
    'truncated tail apply wal length' => static fn (): mixed => strlen($truncatedResult()['wal']),
    'truncated tail apply removes partial payload' => static fn (): mixed => str_contains($truncatedResult()['wal'], 'wal-index-committed'),
    'uncommitted wal cannot checkpoint' => static fn (): mixed => $boundary($uncommittedWal)['can_checkpoint'],
    'uncommitted wal apply only writes wal sidecar' => static fn (): mixed => $uncommittedResult()['applied']['applied'],
    'uncommitted wal apply preserves database bytes' => static fn (): mixed => $uncommittedResult()['database'] === $baseDatabase,
    'uncommitted wal apply syncs wal only' => static fn (): mixed => $uncommittedResult()['applied']['durable_syncs'],
    'uncommitted wal operation starts with wal restore' => static fn (): mixed => $uncommittedResult()['applied']['operations'][0]['reason'],
    'uncommitted wal directory operation is third' => static fn (): mixed => $uncommittedResult()['applied']['operations'][3]['reason'],
    'header corruption status is corrupt' => static fn (): mixed => $boundary($badHeaderWal)['status'],
    'header corruption cannot checkpoint' => static fn (): mixed => $boundary($badHeaderWal)['can_checkpoint'],
    'header corruption apply keeps only wal header bytes' => static fn (): mixed => strlen($badHeaderResult()['wal']),
    'header corruption apply preserves database bytes' => static fn (): mixed => $badHeaderResult()['database'] === $baseDatabase,
    'header corruption operation count' => static fn (): mixed => $badHeaderResult()['applied']['applied'],
    'empty database path is rejected' => static function () use ($committedWal, $baseDatabase): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-boundary-reject-' . bin2hex(random_bytes(4))))
                ->applyWalChecksumRecoveryBoundary($committedWal, $baseDatabase, '', 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only writer is rejected' => static function () use ($committedWal, $baseDatabase, $databasePath): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-boundary-ro-' . bin2hex(random_bytes(4)), true))
                ->applyWalChecksumRecoveryBoundary($committedWal, $baseDatabase, $databasePath, 512);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'unaligned database image is rejected before writes' => static function () use ($committedWal, $baseDatabase, $databasePath): mixed {
        try {
            (new SQLiteVfsFileWriter(sys_get_temp_dir() . '/port-libsqlite-wal-boundary-align-' . bin2hex(random_bytes(4))))
                ->applyWalChecksumRecoveryBoundary($committedWal, substr($baseDatabase, 1), $databasePath, 512);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'corrupt tail status is recovered prefix' => 'recovered_prefix',
    'corrupt tail reason is checksum mismatch' => 'frame_checksum_mismatch',
    'corrupt tail keeps three valid frames' => 3,
    'corrupt tail reports fourth frame invalid' => 4,
    'corrupt tail trims to three frame prefix bytes' => 1640,
    'corrupt tail has valid wal prefix length' => 1640,
    'corrupt tail last commit is second frame' => 2,
    'corrupt tail last commit page count is four' => 4,
    'corrupt tail preserves one uncommitted valid frame' => 1,
    'corrupt tail can checkpoint committed prefix' => true,
    'corrupt tail checkpoint keeps database page count' => 4,
    'corrupt tail checkpoint contains committed siteurl page' => true,
    'corrupt tail checkpoint ignores uncommitted index draft' => false,
    'corrupt tail apply status' => 'applied',
    'corrupt tail apply is atomic' => true,
    'corrupt tail apply operation count' => 7,
    'corrupt tail apply bytes written include database and wal prefix' => 3688,
    'corrupt tail apply durable sync count' => 2,
    'corrupt tail apply directory sync count' => 1,
    'corrupt tail apply writes committed page into database' => true,
    'corrupt tail apply leaves uncommitted index out of database' => false,
    'corrupt tail apply truncates wal to valid prefix' => 1640,
    'corrupt tail apply removes stale local tail' => false,
    'corrupt tail apply keeps valid uncommitted frame in wal' => true,
    'corrupt tail apply removes corrupt frame bytes' => false,
    'corrupt tail apply exposes recovery status' => 'recovered_prefix',
    'corrupt tail apply exposes recovery dependency' => true,
    'corrupt tail apply exposes vfs dependency' => true,
    'corrupt tail first operation checkpoints database' => 'checkpoint_valid_wal_recovery_prefix',
    'corrupt tail wal restore operation follows database sync' => 'restore_valid_wal_recovery_prefix',
    'corrupt tail final operation persists directory' => 'persist_wal_recovery_boundary_sidecars',
    'truncated tail reason is truncated frame tail' => 'truncated_frame_tail',
    'truncated tail invalid frame is fourth slot' => 4,
    'truncated tail valid frame count' => 3,
    'truncated tail apply wal length' => 1640,
    'truncated tail apply removes partial payload' => false,
    'uncommitted wal cannot checkpoint' => false,
    'uncommitted wal apply only writes wal sidecar' => 4,
    'uncommitted wal apply preserves database bytes' => true,
    'uncommitted wal apply syncs wal only' => 1,
    'uncommitted wal operation starts with wal restore' => 'restore_valid_wal_recovery_prefix',
    'uncommitted wal directory operation is third' => 'persist_wal_recovery_boundary_sidecars',
    'header corruption status is corrupt' => 'corrupt',
    'header corruption cannot checkpoint' => false,
    'header corruption apply keeps only wal header bytes' => 32,
    'header corruption apply preserves database bytes' => true,
    'header corruption operation count' => 4,
    'empty database path is rejected' => 'rejected',
    'read only writer is rejected' => 'rejected',
    'unaligned database image is rejected before writes' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['wal checksum recovery vfs apply ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
