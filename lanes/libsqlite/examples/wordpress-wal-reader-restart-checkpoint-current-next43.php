<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalReaderRestartCheckpointPlan;

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteWalReaderRestartCheckpointPlan.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options schema before checkpoint')
    . $page('wp_options active_plugins before checkpoint')
    . $page('wp_options autoload index before checkpoint');
$databasePath = 'wp-content/database/.ht.sqlite';
$salt1 = 0x43434343;
$salt2 = 0x90909090;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 43, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, 'old reader active_plugins draft'],
    [3, 3, 'autoload index committed'],
    [2, 3, 'new reader active_plugins committed'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$pageSizeField = (1 << 24) | $pageSize;
$header = pack('V*', 3007000, 1, 43, $pageSizeField, 3, 3, 0, 0, $salt1, $salt2, 0, 0);
$checkpoint = pack('V*', 1, 0, 1, 3, 0xffffffff, 0xffffffff)
    . "\x00\x01\x01\x00\x00\x00\x00\x00"
    . pack('V*', 2, 0);
$shm = SQLiteShmIndex::parse($header . $header . $checkpoint);

$plan = SQLiteWalReaderRestartCheckpointPlan::plan(
    SQLiteWal::parse($walBytes, null, true),
    $databaseBytes,
    $shm,
    [1, 2, 3],
    $databasePath,
    'restart'
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'current-reader-pinned');
    assert($plan['wal_action'] === 'preserve_wal');
    assert($plan['current_frame_indexes'] === [null, 1, null]);
    assert($plan['next_frame_indexes'] === [null, 3, 2]);
    assert($plan['changed_pages'] === [2, 3]);
    echo "wordpress wal reader restart checkpoint current-next43 smoke passed\n";

    return;
}

echo json_encode([
    'status' => $plan['status'],
    'wal_action' => $plan['wal_action'],
    'checkpoint_reason' => $plan['checkpoint_reason'],
    'current_reader_end_frame' => $plan['current_reader_end_frame'],
    'next_reader_end_frame' => $plan['next_reader_end_frame'],
    'changed_pages' => $plan['changed_pages'],
    'operations' => $plan['operations'],
], JSON_PRETTY_PRINT) . PHP_EOL;
