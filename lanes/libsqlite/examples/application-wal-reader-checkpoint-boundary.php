<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp_options page 1 before import') . $page('wp_options page 2 before import') . $page('wp_options page 3 before import');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x51525354;
    $salt2 = 0x61626364;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 19, $salt1, $salt2);
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
    [1, 0, 'wp schema retained before plugin savepoint'],
    [2, 3, 'wp_options retained committed autoload'],
    [3, 0, 'wp plugin settings draft page'],
    [2, 3, 'wp plugin settings rolled back'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordPageImageWrite(1, $page('wp_options page 1 before import'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordPageImageWrite(2, $page('wp_options page 2 before import'));
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordPageImageWrite(3, $page('wp_options page 3 before import'));
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$boundary = SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    'truncate'
);

$summary = [
    'status' => $boundary['status'],
    'mode' => $boundary['mode'],
    'walAction' => $boundary['wal_action'],
    'checkpointBusy' => $boundary['checkpoint_busy'],
    'retainedFrames' => $boundary['retained_frame_count'],
    'discardedFrames' => $boundary['discarded_frame_count'],
    'currentReaderSources' => $boundary['current_reader_sources'],
    'nextReaderSources' => $boundary['next_reader_sources'],
    'currentReaderFrames' => $boundary['current_reader_frame_indexes'],
    'nextReaderFrames' => $boundary['next_reader_frame_indexes'],
    'nextReaderUsesCheckpointDatabase' => $boundary['next_reader_uses_checkpoint_database'],
    'currentReaderKeptWalSnapshot' => $boundary['current_reader_kept_wal_snapshot'],
    'imagesMatch' => $boundary['images_match'],
    'rolledBackPluginVisible' => str_contains(implode('', $boundary['next_reader_images']), 'rolled back'),
    'dependencies' => $boundary['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'ready');
    assert($summary['walAction'] === 'truncate_wal');
    assert($summary['currentReaderSources'] === ['wal', 'wal', 'database']);
    assert($summary['nextReaderSources'] === ['database', 'database', 'database']);
    assert($summary['imagesMatch'] === true);
    assert($summary['rolledBackPluginVisible'] === false);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
