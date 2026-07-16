<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/www/wp-content/database/wp-next147.sqlite';
$databaseBytes = $page('wp next147 base schema')
    . $page('wp next147 base options')
    . $page('wp next147 base plugins')
    . $page('wp next147 base autoload')
    . $page('wp next147 base rewrite');

$salt1 = 0x14714701;
$salt2 = 0x14714702;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 147, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, 'wp next147 options import draft'],
    [3, 5, 'wp next147 plugins import commit'],
    [4, 0, 'wp next147 failed autoload savepoint'],
    [5, 5, 'wp next147 failed rewrite commit'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 2);
$savepoints->recordWalFrameWrite(2, 3, true);
$savepoints->savepoint('autoload-batch');
$savepoints->recordWalFrameWrite(3, 4);
$savepoints->recordWalFrameWrite(4, 5, true);

$plan = SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $savepoints,
    'autoload-batch',
    [2, 3, 4, 5],
    2,
    [[
        'pages' => [
            4 => $page('wp next147 retry autoload commit'),
            5 => $page('wp next147 retry rewrite commit'),
        ],
        'database_page_count' => 5,
        'commit' => true,
    ]]
);

$summary = [
    'scenario' => 'application-wal-reader-restart-savepoint-checkpoint-current-source-next147',
    'status' => $plan['status'],
    'applicationUse' => 'A copied Application options import rolls back a failed autoload savepoint, runs a restart checkpoint while an existing reader remains pinned to the truncated current WAL source, then appends retry frames to the restarted WAL generation for later readers.',
    'readerPreserved' => $plan['reader_preserved_by_restart_checkpoint'],
    'nextGenerationSeparated' => $plan['next_generation_separated_from_reader'],
    'discardedFrames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
    'readerSources' => $plan['reader_after_sources'],
    'nextSources' => $plan['next_sources'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-reader-restart-savepoint-checkpoint-current-source-next147'
    || $summary['readerPreserved'] !== true
    || $summary['nextGenerationSeparated'] !== true
    || $summary['discardedFrames'] !== [3, 4]
) {
    fwrite(STDERR, "application-wal-reader-restart-savepoint-checkpoint-current-source-next147 self-test failed\n");
    exit(1);
}

echo "application-wal-reader-restart-savepoint-checkpoint-current-source-next147 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
