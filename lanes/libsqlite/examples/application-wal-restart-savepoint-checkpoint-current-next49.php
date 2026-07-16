<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalAppendPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x49494949;
$salt2 = 0x51515151;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('db page 1 schema base') . $page('db page 2 options base');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 49, $salt1, $salt2);
$checksum = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $checksum[0], $checksum[1]);
$seed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
$appendFrame = static function (int $pageNumber, int $commit, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$appendFrame(1, 0, $page('wal page 1 schema retained before savepoint'));
$appendFrame(2, 2, $page('wal page 2 options retained before savepoint'));
$appendFrame(2, 0, $page('wal page 2 plugin draft discarded by rollback'));
$appendFrame(3, 3, $page('wal page 3 transient draft discarded by rollback'));

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_batch');
$savepoints->recordWalFrameWrite(3, 2);
$savepoints->recordWalFrameWrite(4, 3, true);

$plan = SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
    $savepoints,
    'plugin_batch',
    SQLiteWal::parse($walBytes, null, true),
    $walBytes,
    $databaseBytes,
    $databasePath,
    [[
        'pages' => [
            2 => $page('next writer page 2 active_plugins committed after restart'),
            3 => $page('next writer page 3 autoload index committed after restart'),
        ],
        'database_page_count' => 3,
        'commit' => true,
    ]],
    [1, 2, 3],
    'restart'
);

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retained_frames' => $plan['retained_frame_count'],
    'discarded_frames' => $plan['discarded_frame_count'],
    'checkpoint_action' => $plan['checkpoint']['wal_action'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'next_commit_frame' => $plan['append']['last_commit_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
