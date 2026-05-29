<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base')
    . $page('wp option base')
    . $page('wp autoload base')
    . $page('wp transient base')
    . $page('wp plugin base');

$salt1 = 0x13013001;
$salt2 = 0x13013002;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 130, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp retained schema draft'],
    [2, 5, 'wp retained siteurl commit'],
    [3, 0, 'wp discarded autoload draft'],
    [4, 5, 'wp discarded transient commit'],
    [2, 5, 'wp discarded option retry'],
    [5, 5, 'wp discarded plugin tail'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next130');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-batch-next130');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);
$stack->recordWalFrameWrite(5, 2, true);
$stack->recordWalFrameWrite(6, 5, true);

$plan = SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan::readerCheckpointSavepointTruncateAfterRollbackPlan(
    $stack,
    'plugin-batch-next130',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    2
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-reader-checkpoint-savepoint-truncate-current-source-next130') {
        throw new RuntimeException('unexpected WAL checkpoint truncate status');
    }
    if ($plan['wal_sidecar_removed_for_next_open'] !== true || $plan['next_open_uses_checkpoint_database'] !== true) {
        throw new RuntimeException('next WordPress reader did not reopen from the checkpointed database image');
    }
    if ($plan['rows'][1]['next_open_label'] !== 'wp retained siteurl commit') {
        throw new RuntimeException('retained wp_options page was not checkpointed before WAL truncation');
    }

    echo "wordpress-wal-reader-checkpoint-savepoint-truncate-current-source-next130 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'wal_sidecar_removed_for_next_open' => $plan['wal_sidecar_removed_for_next_open'],
    'next_open_sources' => $plan['next_open_sources'],
    'source_transitions' => $plan['source_transitions'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
