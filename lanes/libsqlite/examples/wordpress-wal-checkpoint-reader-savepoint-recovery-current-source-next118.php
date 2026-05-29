<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$databaseBytes = $page('wp schema base')
    . $page('wp options base')
    . $page('wp plugin base')
    . $page('wp autoload base')
    . $page('wp transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x11811801;
    $salt2 = 0x11811802;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 118, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wp schema retained draft'],
    [2, 5, 'wp siteurl retained commit'],
    [3, 0, 'wp plugin savepoint draft'],
    [4, 0, 'wp autoload savepoint draft'],
    [4, 5, 'wp autoload savepoint commit'],
    [5, 5, 'wp transient savepoint commit'],
    [2, 5, 'wp siteurl savepoint tail'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import-next118');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-settings-next118');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 4, true);
$stack->recordWalFrameWrite(6, 5, true);
$stack->recordWalFrameWrite(7, 2, true);

$plan = SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan::plan(
    $stack,
    'plugin-settings-next118',
    $wal,
    $walBytes,
    $databaseBytes,
    $databasePath,
    [1, 2, 3, 4, 5],
    'restart',
    'after_database_sync',
    7
);

$summary = [
    'scenario' => 'wordpress-wal-checkpoint-reader-savepoint-recovery-current-source-next118',
    'wordpressUse' => 'Recover a copied wp_options import after ROLLBACK TO trims a WAL savepoint prefix and a reader-pinned checkpoint crashes, ensuring recovery replays only the retained current-source WAL prefix instead of discarded savepoint frames.',
    'status' => $plan['status'],
    'recoveryStatus' => $plan['recovery_status'],
    'discardedFrames' => $plan['discarded_frame_indexes'],
    'discardedReaderPages' => $plan['discarded_reader_pages'],
    'recoveryCurrentSources' => $plan['recovery_current_sources'],
    'recoveryNextSources' => $plan['recovery_next_sources'],
    'discardedFramesReplayed' => $plan['discarded_frames_replayed'],
    'persistedWalAction' => $plan['persisted_wal_action'],
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'reader-savepoint-checkpoint-recovery-current-source-next118');
    assert($summary['recoveryStatus'] === 'recovered');
    assert($summary['discardedFramesReplayed'] === false);
    assert($summary['discardedReaderPages'] === [2, 3, 4, 5]);
    assert($summary['recoveryCurrentSources'] === ['wal', 'wal', 'database', 'database', 'database']);
    echo "wordpress-wal-checkpoint-reader-savepoint-recovery-current-source-next118 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
