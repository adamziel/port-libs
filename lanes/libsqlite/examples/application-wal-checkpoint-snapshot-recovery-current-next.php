<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointSnapshotRecoveryPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp db schema before checkpoint') . $page('wp option_value before checkpoint') . $page('wp autoload index before checkpoint');
$salt1 = 0x45454545;
$salt2 = 0x45454546;

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 45, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [2, 0, $page('wp active_plugins draft frame')],
    [3, 3, $page('wp autoload index committed frame')],
    [2, 3, $page('wp active_plugins checkpointed frame')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$checkpoint = $wal->durableCheckpointResult($databaseBytes, 'restart');
$plan = SQLiteWalCheckpointSnapshotRecoveryPlan::currentNextAfterCheckpointRecovery(
    $wal,
    $databaseBytes,
    $checkpoint['wal_bytes'],
    [2, 3],
    'restart',
    1
);

echo json_encode([
    'status' => $plan['status'],
    'recovery_status' => $plan['recovery_status'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'current_reader_keeps_precrash_snapshot' => $plan['current_reader_keeps_precrash_snapshot'],
    'next_reader_uses_checkpoint_database' => $plan['next_reader_uses_checkpoint_database'],
    'next_matches_checkpoint_durable' => $plan['next_matches_checkpoint_durable'],
], JSON_PRETTY_PRINT) . PHP_EOL;
