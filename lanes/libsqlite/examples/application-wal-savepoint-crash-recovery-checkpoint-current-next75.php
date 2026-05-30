<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointCrashRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('db schema before plugin import') . $page('db active_plugins before plugin import') . $page('db transient before plugin import');
$salt1 = 0x75757575;
$salt2 = 0x35353535;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 75, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$append = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $label) use ($page, $salt1, $salt2): string {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $append($walBytes, $seed, 1, 0, 'wal schema committed before savepoint');
$walBytes = $append($walBytes, $seed, 2, 3, 'wal active_plugins committed before savepoint');
$walBytes = $append($walBytes, $seed, 2, 0, 'wal active_plugins draft inside failed savepoint');
$walBytes = $append($walBytes, $seed, 3, 4, 'wal transient committed inside failed savepoint');

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('application-import');
$savepoints->recordWalFrameWrite(1, 1, false);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings');
$savepoints->recordWalFrameWrite(3, 2, false);
$savepoints->recordWalFrameWrite(4, 3, true);

$plan = SQLiteWalSavepointCheckpointPlan::crashRecoveryCurrentNextAfterRollbackTo(
    $savepoints,
    'plugin-settings',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    $databasePath,
    [1, 2, 3],
    'restart',
    'after_database_sync',
    $pageSize
);

echo json_encode([
    'scenario' => 'application-wal-savepoint-crash-recovery-checkpoint-current-next75',
    'status' => $plan['status'],
    'retained_frame_count' => $plan['retained_frame_count'],
    'discarded_frame_count' => $plan['discarded_frame_count'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'images_match' => $plan['images_match'],
    'next_replays_persisted_wal' => $plan['next_replays_persisted_wal'],
    'failed_savepoint_draft_checkpointed' => str_contains($plan['checkpoint_recovery']['persisted_database_bytes'], 'draft inside failed savepoint'),
], JSON_PRETTY_PRINT) . PHP_EOL;
