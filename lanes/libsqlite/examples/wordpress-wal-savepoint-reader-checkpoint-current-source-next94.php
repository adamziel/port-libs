<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema before next94')
    . $page('wp option before next94')
    . $page('wp plugin before next94')
    . $page('wp autoload before next94')
    . $page('wp transient before next94');

$makeWalBytes = static function (array $labels) use ($pageSize, $page): string {
    $salt1 = 0x94949494;
    $salt2 = 0x29299494;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 94, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($labels as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wp schema retained next94'],
    [2, 5, 'wp option retained next94'],
    [3, 0, 'wp plugin rollback next94'],
    [4, 0, 'wp autoload rollback next94'],
    [4, 5, 'wp autoload rollback commit next94'],
    [5, 5, 'wp transient rollback commit next94'],
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

$plan = SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointReleaseCurrentSourceNext(
    $stack,
    'reader-visible',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3, 4, 5],
    'restart',
    6
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['current_source_verified'] === true);
    assert($plan['pinned_checkpoint_busy'] === true);
    assert($plan['reader_release_unblocked_checkpoint'] === true);
    assert($plan['released_reader_uses_checkpoint_database'] === true);
    assert(in_array('sqlite-wal-savepoint-reader-checkpoint-current-source-next94', $plan['dependencies'], true));
    echo "wordpress-wal-savepoint-reader-checkpoint-current-source-next94 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pinned_action' => $plan['pinned_wal_action'],
    'released_action' => $plan['released_wal_action'],
    'current_sources' => $plan['current_sources'],
    'pinned_next_sources' => $plan['pinned_next_sources'],
    'released_next_sources' => $plan['released_next_sources'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
