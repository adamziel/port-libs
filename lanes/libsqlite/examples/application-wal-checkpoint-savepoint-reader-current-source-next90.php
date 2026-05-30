<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base next90')
    . $page('wp options base next90')
    . $page('wp plugin settings base next90')
    . $page('wp autoload index base next90')
    . $page('wp transient base next90');

$salt1 = 0x90909090;
$salt2 = 0x19199090;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 90, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [1, 0, 'wp schema retained next90'],
    [2, 5, 'wp options retained commit next90'],
    [3, 0, 'wp plugin settings discarded draft next90'],
    [4, 0, 'wp autoload retained draft next90'],
    [4, 5, 'wp autoload retained commit next90'],
    [5, 5, 'wp transient discarded commit next90'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->savepoint('autoload-refresh');
$savepoints->recordWalFrameWrite(4, 4);
$savepoints->recordWalFrameWrite(5, 4, true);
$savepoints->savepoint('transient-row');
$savepoints->recordWalFrameWrite(6, 5, true);

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointPinnedCurrentSourceNext(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    6
);

$summary = [
    'scenario' => 'application-wal-checkpoint-savepoint-reader-current-source-next90',
    'applicationUse' => 'A copied wp_options import rolls back a failed plugin-settings SAVEPOINT while a reader remains pinned; repair tooling can verify the exact current WAL frame source before deciding that the checkpoint must preserve the retained WAL prefix for current and next readers.',
    'status' => $plan['status'],
    'checkpointReason' => $plan['checkpoint_reason'],
    'walAction' => $plan['wal_action'],
    'currentSourceVerified' => $plan['current_source_verified'],
    'currentSourceFrameCount' => $plan['current_source']['frame_count'],
    'retainedSourceFrameCount' => $plan['retained_source']['frame_count'],
    'commitFrameIndexes' => $plan['commit_frame_indexes'],
    'rolledBackFrameIndexes' => $plan['rolled_back_frame_indexes'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'frameSourceRows' => array_map(static fn (array $row): array => [
        'frame_index' => $row['frame_index'],
        'page_number' => $row['page_number'],
        'commit_frame' => $row['commit_frame'],
        'source_offset' => $row['source_offset'],
        'matched_current_wal' => $row['matched_current_wal'],
    ], $plan['frame_source_rows']),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'busy' || $summary['checkpointReason'] !== 'reader_blocks_wal_reset') {
        fwrite(STDERR, "application-wal-checkpoint-savepoint-reader-current-source-next90 status failed\n");
        exit(1);
    }
    if ($summary['walAction'] !== 'preserve_wal' || $summary['retainedSourceFrameCount'] !== 2) {
        fwrite(STDERR, "application-wal-checkpoint-savepoint-reader-current-source-next90 WAL retention failed\n");
        exit(1);
    }
    if ($summary['commitFrameIndexes'] !== [2, 5, 6] || $summary['rolledBackFrameIndexes'] !== [3, 4, 5, 6]) {
        fwrite(STDERR, "application-wal-checkpoint-savepoint-reader-current-source-next90 frame summary failed\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
