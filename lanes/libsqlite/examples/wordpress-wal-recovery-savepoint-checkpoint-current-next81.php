<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$salt1 = 0x81818181;
$salt2 = 0x18181818;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp schema before plugin recovery81') . $page('active_plugins before plugin recovery81') . $page('transient before plugin recovery81');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 81, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [1, 0, 'wp schema retained recovery81'],
    [2, 3, 'active_plugins retained recovery81'],
    [2, 0, 'active_plugins failed draft recovery81'],
    [3, 3, 'transient failed commit recovery81'],
] as [$pageNumber, $commit, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wordpress-import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings');
$stack->recordWalFrameWrite(3, 2);
$stack->recordWalFrameWrite(4, 3, true);

$plan = SQLiteWalSavepointCheckpointPlan::releaseAfterRollbackCheckpointCurrentNext(
    $stack,
    'plugin-settings',
    SQLiteWal::parse($walBytes, $pageSize, true),
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    'restart'
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'released-checkpointed');
    assert($plan['current_reader_sources'] === ['wal', 'wal', 'database']);
    assert($plan['next_reader_sources'] === ['database', 'database', 'database']);
    assert($plan['release_allows_checkpoint_reset'] === true);
    assert(str_contains($plan['checkpoint']['database_bytes'], 'active_plugins retained recovery81'));
    assert(!str_contains($plan['checkpoint']['database_bytes'], 'failed draft recovery81'));
    echo "wordpress wal recovery savepoint checkpoint current-next81 self-test passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'mode' => $plan['mode'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'release_allows_checkpoint_reset' => $plan['release_allows_checkpoint_reset'],
    'checkpoint_wal_action' => $plan['checkpoint']['wal_action'],
], JSON_PRETTY_PRINT) . "\n";
