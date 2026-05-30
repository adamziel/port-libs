<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointRecoveryPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp-options-schema-before') . $page('wp-options-autoload-before') . $page('wp-options-plugin-before');

$salt1 = 0x20260527;
$salt2 = 0x00000030;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 30, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [1, 0, 'wp schema committed before plugin'],
    [2, 3, 'autoload index committed before plugin'],
    [3, 0, 'plugin option draft to rollback'],
    [2, 3, 'plugin autoload commit to discard'],
    [1, 0, 'nested draft after failed plugin'],
] as $frame) {
    [$pageNumber, $commitPageCount, $label] = $frame;
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application_import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings_batch');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);
$savepoints->savepoint('nested_plugin_retry');
$savepoints->recordWalFrameWrite(5, 1);

$plan = SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo(
    $savepoints,
    'plugin_settings_batch',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    [1, 2, 3]
);

$summary = [
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retainedFrames' => $plan['retained_frame_count'],
    'discardedFrames' => $plan['discarded_frame_count'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'imagesMatchAcrossRecovery' => $plan['images_match'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'containsDiscardedPluginDraft' => str_contains($plan['current_wal_bytes'], 'plugin option draft'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'valid' || $summary['retainedFrames'] !== 2 || $summary['discardedFrames'] !== 3) {
        fwrite(STDERR, "unexpected WAL savepoint recovery summary\n");
        exit(1);
    }
    if (!$summary['imagesMatchAcrossRecovery'] || !$summary['nextUsesCheckpointDatabase']) {
        fwrite(STDERR, "unexpected current/next recovery visibility\n");
        exit(1);
    }
    if ($summary['containsDiscardedPluginDraft']) {
        fwrite(STDERR, "discarded plugin draft remained in current WAL prefix\n");
        exit(1);
    }
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
