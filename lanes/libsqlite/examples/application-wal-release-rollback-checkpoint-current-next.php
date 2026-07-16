<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$salt1 = 0x55555555;
$salt2 = 0x95959595;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('base schema before plugin import')
    . $page('base active plugins option')
    . $page('base transient cache option')
    . $page('base plugin settings option')
    . $page('base autoload index page');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 55, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (int $pageNumber, int $commitPageCount, string $image) use (&$walBytes, &$seed, $salt1, $salt2): void {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};

$appendFrame(1, 0, $page('frame 1 retained schema import'));
$appendFrame(2, 5, $page('frame 2 retained active_plugins commit'));
$appendFrame(3, 0, $page('frame 3 parent transient draft'));
$appendFrame(4, 0, $page('frame 4 released plugin settings draft'));
$appendFrame(5, 5, $page('frame 5 released autoload index commit'));
$appendFrame(3, 0, $page('frame 6 parent transient retry'));
$appendFrame(4, 5, $page('frame 7 parent plugin settings commit'));
$wal = SQLiteWal::parse($walBytes, null, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-batch');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->savepoint('autoload-index');
$savepoints->recordWalFrameWrite(4, 4);
$savepoints->recordWalFrameWrite(5, 5, true);
$savepoints->recordWalFrameWrite(6, 3);
$savepoints->recordWalFrameWrite(7, 4, true);

$plan = SQLiteWalSavepointCheckpointPlan::releaseThenRollbackCheckpointCurrentNext(
    $savepoints,
    'autoload-index',
    'plugin-batch',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart'
);

echo json_encode([
    'status' => $plan['status'],
    'released_savepoint' => $plan['released_savepoint'],
    'rollback_savepoint' => $plan['rollback_savepoint'],
    'released_frame_names' => $plan['released_frame_names'],
    'merged_page_numbers' => $plan['merged_page_numbers'],
    'retained_frame_count' => $plan['retained_frame_count'],
    'discarded_frame_count' => $plan['discarded_frame_count'],
    'rolled_back_released_frames' => $plan['rolled_back_released_frames'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'wal_action' => $plan['boundary']['wal_action'],
    'checkpoint_reason' => $plan['boundary']['checkpoint_reason'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
