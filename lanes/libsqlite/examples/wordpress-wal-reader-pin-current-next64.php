<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp schema') . $page('wp_options old siteurl') . $page('wp_options old autoload');
$salt1 = 0x64000001;
$salt2 = 0x64000002;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 64, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl current reader')],
    [3, 3, $page('wp_options autoload commit')],
    [2, 3, $page('wp_options siteurl latest import')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointReaderPinCurrentNextHandoff($databaseBytes, [2, 3], [0, 1, null], 'restart');

echo json_encode([
    'status' => $plan['status'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'next_reader_slot' => $plan['next_reader_slot'],
    'released_read_marks' => $plan['released_read_marks'],
    'retry_wal_action' => $plan['retry_checkpoint']['wal_action'],
    'current_sources' => $plan['current_sources'],
    'next_sources' => $plan['next_sources'],
    'dependency' => in_array('wal-reader-pin-current-next64', $plan['dependencies'], true),
], JSON_PRETTY_PRINT) . PHP_EOL;
