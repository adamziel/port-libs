<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db schema before plugin import')
    . $page('db wp_options before plugin import')
    . $page('db plugin settings before savepoint')
    . $page('db autoload index before savepoint')
    . $page('db transient before savepoint');

$salt1 = 0x85858585;
$salt2 = 0x85858586;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 85, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (int $pageNumber, int $commit, string $label) use (&$walBytes, &$seed, $salt1, $salt2, $page): void {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$append(1, 0, 'wal schema retained before savepoint');
$append(2, 5, 'wal wp_options retained commit');
$append(3, 0, 'wal plugin settings draft discarded');
$append(4, 5, 'wal autoload index commit discarded');
$append(5, 5, 'wal transient commit discarded');
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordPageImageWrite(1, $page('db schema before plugin import'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordPageImageWrite(2, $page('db wp_options before plugin import'));
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordPageImageWrite(3, $page('db plugin settings before savepoint'));
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordPageImageWrite(4, $page('db autoload index before savepoint'));
$savepoints->recordWalFrameWrite(4, 4, true);
$savepoints->savepoint('transient-cache');
$savepoints->recordPageImageWrite(5, $page('db transient before savepoint'));
$savepoints->recordWalFrameWrite(5, 5, true);

$plan = SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointCurrentSourceNext(
    $savepoints,
    'plugin-settings',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    5
);

echo json_encode([
    'scenario' => 'wordpress-wal-checkpoint-reader-savepoint-current-source-next85',
    'wordpressUse' => 'Report current and next reader page sources after a failed wp_options plugin-settings import rolls back to a SAVEPOINT and a checkpoint runs, so repair tooling can distinguish retained WAL pages from database pages restored by the savepoint boundary.',
    'status' => $plan['status'],
    'checkpointReason' => $plan['checkpoint_reason'],
    'walAction' => $plan['wal_action'],
    'retainedFrameCount' => $plan['retained_frame_count'],
    'discardedFrameCount' => $plan['discarded_frame_count'],
    'rolledBackPageNumbers' => $plan['rolled_back_page_numbers'],
    'currentSources' => $plan['current_sources'],
    'nextSources' => $plan['next_sources'],
    'sourceTransitions' => $plan['source_transitions'],
    'currentSourceRows' => $plan['current_source_rows'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
