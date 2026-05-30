<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$root = sys_get_temp_dir() . '/port-libsqlite-walsp74-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$localDatabase = $root . $databasePath;
$localWal = $localDatabase . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp schema before plugin retry') . $page('wp options before plugin retry');

$salt1 = 0x74747474;
$salt2 = 0x75757575;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 74, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$checksum, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $checksum[0], $checksum[1]);

    return $bytes . $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('wal schema retained before retry'));
$walBytes = $appendFrame($walBytes, $seed, 2, 2, $page('wal options retained before retry'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('wal plugin draft rolled back'));
$walBytes = $appendFrame($walBytes, $seed, 3, 3, $page('wal plugin row rolled back'));
$wal = SQLiteWal::parse($walBytes, null, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_batch');
$savepoints->recordWalFrameWrite(3, 2);
$savepoints->recordWalFrameWrite(4, 3, true);

$transactions = [[
    'pages' => [
        2 => $page('retry committed active_plugins option'),
        3 => $page('retry committed autoload index page'),
    ],
    'database_page_count' => 3,
    'commit' => true,
], [
    'pages' => [
        3 => $page('retry uncommitted draft tail'),
    ],
    'commit' => false,
]];

$applied = (new SQLiteVfsFileWriter($root))->applySavepointRestartCheckpointAppend(
    $savepoints,
    'plugin_batch',
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    $transactions,
    [1, 2, 3],
    'restart'
);
$databaseAfter = (string) file_get_contents($localDatabase);
$walAfter = (string) file_get_contents($localWal);
$nextWal = SQLiteWal::parse($walAfter, null, true);

echo json_encode([
    'scenario' => 'application-wal-savepoint-restart-checkpoint-apply-current-next74',
    'applicationUse' => 'Apply a failed wp_options plugin import ROLLBACK TO savepoint, restart checkpoint the retained WAL prefix into the database image, then persist retry WAL frames atomically through native PHP file handles without ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'status' => $applied['status'],
    'appliedOperations' => $applied['applied'],
    'bytesWritten' => $applied['bytes_written'],
    'bytesTruncated' => $applied['bytes_truncated'],
    'durableSyncs' => $applied['durable_syncs'],
    'directorySyncs' => $applied['directory_syncs'],
    'retainedWalFrames' => $applied['savepoint_checkpoint_append']['retained_frame_count'],
    'discardedWalFrames' => $applied['savepoint_checkpoint_append']['discarded_frame_count'],
    'checkpointWalAction' => $applied['savepoint_checkpoint_append']['checkpoint']['wal_action'],
    'nextWalFrames' => $nextWal->frameCount(),
    'nextWalLastCommitFrame' => $nextWal->lastCommitFrame()?->index,
    'databaseContainsRetainedOptions' => str_contains($databaseAfter, 'wal options retained before retry'),
    'walContainsRetryCommit' => str_contains($walAfter, 'retry committed active_plugins option'),
    'walContainsRolledBackDraft' => str_contains($walAfter, 'wal plugin draft rolled back'),
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
