<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp_options schema before current source next87')
    . $page('active_plugins before current source next87')
    . $page('autoload index before current source next87');

$makeWal = static function (array $frames) use ($pageSize, $page): string {
    $salt1 = 0x20260587;
    $salt2 = 0x87000002;
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 87, $salt1, $salt2);
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

$walBytes = $makeWal([
    [1, 0, 'wp_options schema retained next87'],
    [2, 3, 'active_plugins retained commit next87'],
    [3, 0, 'plugin autoload draft rolled back next87'],
    [2, 3, 'active_plugins plugin draft rolled back next87'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wordpress-import');
$stack->recordPageImageWrite(1, $page('wp_options schema before current source next87'));
$stack->recordWalFrameWrite(1, 1);
$stack->recordPageImageWrite(2, $page('active_plugins before current source next87'));
$stack->recordWalFrameWrite(2, 2, true);
$stack->savepoint('plugin-import');
$stack->recordPageImageWrite(3, $page('autoload index before current source next87'));
$stack->recordWalFrameWrite(3, 3);
$stack->recordWalFrameWrite(4, 2, true);

$plan = SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext(
    $stack,
    'plugin-import',
    $wal,
    $walBytes,
    $databaseBytes,
    [1, 2, 3],
    'restart'
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['current_source_verified'] === true);
    assert($plan['retained_source']['frame_count'] === 2);
    assert($plan['discarded_frame_count'] === 2);
    assert($plan['current_reader_sources'] === ['wal', 'wal', 'database']);
    assert($plan['next_reader_sources'] === ['database', 'database', 'database']);
    assert($plan['next_source']['kind'] === 'restart_wal');
    assert($plan['images_match'] === true);
    echo "wordpress-wal-checkpoint-savepoint-reader-current-source-next87 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'mode' => $plan['mode'],
    'current_source_verified' => $plan['current_source_verified'],
    'current_source_frame_count' => $plan['current_source']['frame_count'],
    'retained_frame_count' => $plan['retained_frame_count'],
    'discarded_frame_count' => $plan['discarded_frame_count'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'next_source' => $plan['next_source']['kind'],
], JSON_PRETTY_PRINT) . PHP_EOL;
