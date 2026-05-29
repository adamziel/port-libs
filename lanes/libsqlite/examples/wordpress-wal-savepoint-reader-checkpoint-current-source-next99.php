<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema base next99')
    . $page('wp option base next99')
    . $page('wp plugin base next99')
    . $page('wp autoload base next99')
    . $page('wp transient base next99');

$makeWalBytes = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x99119911;
    $salt2 = 0x99229922;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 99, $salt1, $salt2);
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
    [1, 0, 'wp schema retained next99'],
    [2, 5, 'wp option retained next99'],
    [3, 0, 'wp plugin rollback next99'],
    [4, 0, 'wp autoload rollback next99'],
    [4, 5, 'wp autoload rollback commit next99'],
    [5, 5, 'wp transient rollback commit next99'],
    [2, 5, 'wp option tail rollback next99'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordWalFrameWrite(1, 1);
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('reader-visible');
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 4);
$stack->recordWalFrameWrite(5, 4, true);
$stack->recordWalFrameWrite(6, 5, true);
$stack->recordWalFrameWrite(7, 2, true);

$plan = SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext(
    $stack,
    'reader-visible',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    7
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['current_source_verified'] === true);
    assert($plan['pinned_checkpoint_busy'] === true);
    assert($plan['reader_release_unblocked_checkpoint'] === true);
    assert($plan['released_reader_uses_checkpoint_database'] === true);
    assert($plan['released_wal_action'] === 'restart_wal');
    assert(in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next99', $plan['dependencies'], true));
    echo "wordpress-wal-savepoint-reader-checkpoint-current-source-next99 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'wordpressUse' => 'Summarizes copied wp_options WAL savepoint rollback with a pinned current reader and the next reader after checkpoint reset, including exact current-source frame offsets and source transitions without ext/sqlite.',
    'pinnedAction' => $plan['pinned_wal_action'],
    'releasedAction' => $plan['released_wal_action'],
    'currentSources' => $plan['current_sources'],
    'pinnedNextSources' => $plan['pinned_next_sources'],
    'releasedNextSources' => $plan['released_next_sources'],
    'rolledBackFrames' => $plan['rolled_back_frame_indexes'],
    'sourceDigest' => $plan['source_digest'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
