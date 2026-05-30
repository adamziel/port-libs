<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp-options-schema-before') . $page('wp-options-autoload-before') . $page('wp-options-plugin-before');

$salt1 = 0x20260527;
$salt2 = 0x00000015;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 15, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [1, 0, 'wp schema committed before failed plugin'],
    [2, 3, 'autoload index committed before failed plugin'],
    [3, 0, 'plugin option draft before rollback'],
    [2, 3, 'autoload draft commit to discard'],
] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application_import');
$savepoints->recordPageImageWrite(1, $page('wp-options-schema-before'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordPageImageWrite(2, $page('wp-options-autoload-before'));
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings_batch');
$savepoints->recordPageImageWrite(3, $page('wp-options-plugin-before'));
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$plan = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
    $savepoints,
    'plugin_settings_batch',
    $wal,
    $walBytes,
    $databaseBytes,
    'truncate'
);

$summary = [
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'checkpointedFrames' => $plan['current_checkpoint']['checkpointed_frame_count'],
    'walAction' => $plan['current_durable']['wal_action'],
    'walBytesAfterCheckpoint' => $plan['current_durable']['wal_bytes_length'],
    'containsDiscardedPluginDraft' => str_contains($plan['current_durable']['database_bytes'], 'plugin option draft'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'ready' || $summary['retainedFrames'] !== 2 || $summary['discardedFrames'] !== 2) {
        fwrite(STDERR, "unexpected WAL savepoint checkpoint summary\n");
        exit(1);
    }
    if ($summary['walAction'] !== 'truncate_wal' || $summary['walBytesAfterCheckpoint'] !== 0) {
        fwrite(STDERR, "unexpected WAL checkpoint reset action\n");
        exit(1);
    }
    if ($summary['containsDiscardedPluginDraft']) {
        fwrite(STDERR, "discarded plugin draft reached checkpoint database image\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
