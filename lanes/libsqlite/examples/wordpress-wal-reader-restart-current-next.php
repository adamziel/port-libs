<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp-options-db-page-1') . $page('wp-options-db-page-2') . $page('wp-options-db-page-3');

$salt1 = 0x31415926;
$salt2 = 0x53589793;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 8, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

foreach ([
    [1, 0, 'wp-options-autoload-before'],
    [2, 3, 'wp-options-settings-commit-a'],
    [1, 0, 'wp-options-autoload-draft-b'],
    [3, 3, 'wp-options-settings-commit-b'],
] as [$pageNumber, $commitPageCount, $label]) {
    $image = $page($label);
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$header = pack('V*', 3007000, 1, 4, $pageSize, 4, 3, 0, 0, $salt1, $salt2, 0, 0);
$checkpoint = pack('V*', 1, 2, 0xffffffff, 0xffffffff, 0xffffffff, 0xffffffff)
    . pack('C*', 1, 0, 0, 0, 0, 0, 0, 0)
    . pack('V*', 4, 0);
$shm = SQLiteShmIndex::parse($header . $header . $checkpoint);

$visibility = $wal->restartCurrentNextReaderVisibility($databaseBytes, $shm, [1, 2, 3], 'restart');

echo json_encode([
    'status' => $visibility['status'],
    'wal_action' => $visibility['wal_action'],
    'current_reader_end_frame' => $visibility['current_reader_end_frame'],
    'next_reader_end_frame' => $visibility['next_reader_end_frame'],
    'current_sources' => $visibility['current_reader_sources'],
    'next_sources' => $visibility['next_reader_sources'],
    'current_frames' => $visibility['current_reader_frame_indexes'],
    'next_frames' => $visibility['next_reader_frame_indexes'],
    'dependencies' => $visibility['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
