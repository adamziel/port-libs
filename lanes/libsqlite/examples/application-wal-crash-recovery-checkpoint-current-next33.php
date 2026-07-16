<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointCrashRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFileWritePlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointCrashRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x33333333;
$salt2 = 0x71717171;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('db page 1 schema baseline') . $page('db page 2 options baseline');

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 33, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('wal page 1 schema migrated before crash'));
$walBytes = $appendFrame($walBytes, $seed, 2, 2, $page('wal page 2 active_plugins before crash'));
$wal = SQLiteWal::parse($walBytes, null, true);

$plan = SQLiteWalCheckpointCrashRecoveryPlan::plan(
    $wal,
    $databaseBytes,
    $databasePath,
    [1, 2, 3],
    'restart',
    'after_database_sync'
);

$report = [
    'scenario' => 'application-wal-crash-recovery-checkpoint-current-next33',
    'applicationUse' => 'Copied wp_options checkpoint recovery after a crash between durable database pages and durable WAL reset, proving the old WAL can be replayed idempotently for the next opener while current readers remain stable.',
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'checkpointAction' => $plan['checkpoint']['wal_action'],
    'persistedWalAction' => $plan['persisted_wal_action'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'imagesMatch' => $plan['images_match'],
    'appliedOperations' => array_column($plan['operations_applied'], 'reason'),
    'pendingOperations' => array_column($plan['operations_pending'], 'reason'),
    'dependencies' => $plan['dependencies'],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
