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
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema before') . $page('wp option before') . $page('wp plugin before');
$salt1 = 0x14920001;
$salt2 = 0x14920002;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 149, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([[1, 0, 'wp schema retained'], [2, 3, 'wp option retained'], [3, 0, 'wp plugin rolled back'], [2, 3, 'wp option rolled back']] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-import');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-update');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 2, true);

$summary = SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReplayCurrentSourceNext(
    $savepoints,
    'plugin-update',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    'restart'
);

echo json_encode([
    'status' => $summary['status'],
    'originalReaderSources' => $summary['original_reader_sources'],
    'retainedReaderSources' => $summary['retained_reader_sources'],
    'nextReaderSources' => $summary['next_reader_sources'],
    'rolledBackPages' => $summary['rolled_back_pages'],
    'checkpointedPages' => $summary['checkpointed_pages'],
    'walAction' => $summary['wal_action'],
    'imagesMatchRetainedToNext' => $summary['images_match_retained_to_next'],
], JSON_PRETTY_PRINT) . PHP_EOL;
