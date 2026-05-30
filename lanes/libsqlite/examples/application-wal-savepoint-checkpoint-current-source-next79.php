<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('wp schema base for current-source guard')
    . $page('wp active_plugins base for current-source guard')
    . $page('wp autoload index base for current-source guard');

$makeWalBytes = static function (int $salt1, string $tag) use ($pageSize, $page): string {
    $salt2 = 0x19191919;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 79, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    $append = static function (int $pageNumber, int $commit, string $image) use (&$bytes, &$seed, $salt1, $salt2): void {
        $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    };

    $append(2, 0, "{$tag} retained active_plugins draft" . str_repeat("\0", $pageSize - strlen("{$tag} retained active_plugins draft")));
    $append(2, 2, "{$tag} retained active_plugins commit" . str_repeat("\0", $pageSize - strlen("{$tag} retained active_plugins commit")));
    $append(3, 0, "{$tag} rolled back autoload draft" . str_repeat("\0", $pageSize - strlen("{$tag} rolled back autoload draft")));
    $append(3, 3, "{$tag} rolled back autoload commit" . str_repeat("\0", $pageSize - strlen("{$tag} rolled back autoload commit")));

    return $bytes;
};

$currentWalBytes = $makeWalBytes(0x79797979, 'current');
$staleWalBytes = $makeWalBytes(0x7979797a, 'stale');
$currentWal = SQLiteWal::parse($currentWalBytes, null, true);
$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_import');
$savepoints->recordWalFrameWrite(1, 2);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin_settings');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->recordWalFrameWrite(4, 3, true);

$plan = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
    $savepoints,
    'plugin_settings',
    $currentWal,
    $currentWalBytes,
    $databaseBytes,
    'restart'
);

$staleRejected = false;
try {
    SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
        $savepoints,
        'plugin_settings',
        $currentWal,
        $staleWalBytes,
        $databaseBytes,
        'restart'
    );
} catch (InvalidArgumentException) {
    $staleRejected = true;
}

echo json_encode([
    'scenario' => 'application-wal-savepoint-checkpoint-current-source-next79',
    'applicationUse' => 'Guard a wp_options plugin import ROLLBACK TO savepoint plus RESTART checkpoint so stale WAL bytes from a prior source salt cannot be checkpointed into the current database image.',
    'status' => $plan['status'],
    'retainedWalFrames' => $plan['retained_frame_count'],
    'discardedWalFrames' => $plan['discarded_frame_count'],
    'checkpointAction' => $plan['current_durable']['wal_action'],
    'staleWalSourceRejected' => $staleRejected,
    'databaseContainsRetainedOption' => str_contains($plan['current_durable']['database_bytes'], 'current retained active_plugins commit'),
    'databaseContainsRolledBackAutoload' => str_contains($plan['current_durable']['database_bytes'], 'rolled back autoload commit'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
