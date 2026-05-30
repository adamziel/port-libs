<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteVfsSyncPlan.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalCheckpointCrashRecoveryPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFileWritePlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$salt1 = 0x82828282;
$salt2 = 0x28282828;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp schema before plugin import')
    . $page('active_plugins before plugin import')
    . $page('transient cache before plugin import');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 82, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('schema draft before plugin savepoint'));
$walBytes = $appendFrame($walBytes, $seed, 2, 3, $page('active_plugins committed before plugin savepoint'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('active_plugins failed plugin draft'));
$walBytes = $appendFrame($walBytes, $seed, 3, 4, $page('transient cache failed plugin commit'));
$wal = SQLiteWal::parse($walBytes, null, true);

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
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    [1, 2, 3],
    'restart',
    'after_wal_sidecar_write',
    $pageSize
);

$report = [
    'scenario' => 'application-wal-recovery-checkpoint-savepoint-current-source-next82',
    'applicationUse' => 'Copied wp_options import recovery rejects stale WAL source bytes before savepoint rollback checkpoint recovery, so a retry does not checkpoint a WAL sidecar from a previous salt or checkpoint sequence.',
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retainedFrameCount' => $plan['retained_frame_count'],
    'discardedFrameCount' => $plan['discarded_frame_count'],
    'persistedWalAction' => $plan['checkpoint_recovery']['persisted_wal_action'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'imagesMatch' => $plan['images_match'],
    'appliedOperations' => array_column($plan['operations_applied'], 'reason'),
    'pendingOperations' => array_column($plan['operations_pending'], 'reason'),
    'dependencies' => $plan['dependencies'],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
