<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp-options-schema-base')
    . $page('wp-options-active-plugins-base')
    . $page('wp-options-plugin-settings-base')
    . $page('wp-options-transient-base');

$salt1 = 0x20260528;
$salt2 = 0x00000084;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 84, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [1, 0, 'schema retained before plugin release'],
    [2, 4, 'active_plugins retained before plugin release'],
    [3, 0, 'plugin settings draft inside released savepoint'],
    [3, 4, 'plugin settings committed by released savepoint'],
    [4, 0, 'transient draft inside nested release'],
    [4, 4, 'transient committed by nested release'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wordpress-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 3, true);
$savepoints->savepoint('transient-refresh');
$savepoints->recordWalFrameWrite(5, 4);
$savepoints->recordWalFrameWrite(6, 4, true);

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = SQLiteWalSavepointCheckpointPlan::releaseReaderCheckpointCurrentSourceNext(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [2, 3, 4],
    'restart'
);

$summary = [
    'scenario' => 'wordpress-wal-savepoint-release-checkpoint-current-source-next84',
    'status' => $plan['status'],
    'sourceVerified' => $plan['current_source_verified'],
    'walFrameCount' => $plan['current_wal_frame_count'],
    'checkpointSequence' => $plan['current_wal_checkpoint_sequence'],
    'releasedFrames' => $plan['released_frame_names'],
    'mergedPages' => $plan['merged_page_numbers'],
    'walAction' => $plan['wal_action'],
    'beforeSources' => $plan['before_reader_sources'],
    'nextSources' => $plan['next_reader_sources'],
    'checkpointedPluginSettings' => str_contains($plan['next_reader'][1]['image'], 'plugin settings committed'),
    'checkpointedTransient' => str_contains($plan['next_reader'][2]['image'], 'transient committed'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'ready' || !$summary['sourceVerified'] || $summary['walFrameCount'] !== 6) {
        fwrite(STDERR, "unexpected release/checkpoint source summary\n");
        exit(1);
    }
    if ($summary['walAction'] !== 'restart_wal' || !$summary['checkpointedPluginSettings'] || !$summary['checkpointedTransient']) {
        fwrite(STDERR, "release checkpoint did not preserve released savepoint pages\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
