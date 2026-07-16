<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp base schema') . $page('wp base options') . $page('wp base plugin') . $page('wp base transient') . $page('wp base future');
$salt1 = 0x10010064;
$salt2 = 0x20020064;
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 100, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [1, 0, 'wp retained schema'],
    [2, 5, 'wp retained options'],
    [3, 0, 'wp plugin savepoint draft'],
    [4, 5, 'wp transient savepoint commit'],
    [5, 0, 'wp valid uncommitted tail'],
    [2, 5, 'wp corrupt stale options'],
] as $index => [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $frame = $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    if ($index === 5) {
        $frame = substr_replace($frame, 'X', 40, 1);
    }
    $walBytes .= $frame;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next100');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4, true);
$stack->recordWalFrameWrite(5, 5);

$plan = SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings',
    $walBytes,
    $databaseBytes,
    '/srv/www/wp-content/database/wp.sqlite',
    [1, 2, 3, 4, 5],
    'restart',
    null,
    $pageSize
);

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'committedFrameCount' => $plan['committed_frame_count'],
    'discardedValidTail' => $plan['discarded_valid_tail_frame_count'],
    'discardedCorruptTail' => $plan['discarded_corrupt_tail_frame_count'],
    'retainedFrameCount' => $plan['retained_frame_count'],
    'walAction' => $plan['wal_action'],
    'nextUsesCheckpointDatabase' => $plan['next_uses_checkpoint_database'],
    'operations' => array_column($plan['operations'], 'reason'),
], JSON_PRETTY_PRINT) . "\n";
