<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp_options schema') . $page('siteurl before checkpoint') . $page('autoload index before checkpoint');
$salt1 = 0x31415926;
$salt2 = 0x27182818;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 31, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('siteurl visible to pinned reader')],
    [3, 3, $page('autoload index committed')],
    [2, 0, $page('siteurl draft after pinned reader')],
    [2, 3, $page('siteurl latest for next reader')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$report = $wal->checkpointReaderPinCurrentNext($databaseBytes, [2, 3], [0, 2, 4, null], 'restart');

echo 'checkpoint_reason=' . $report['checkpoint_reason'] . PHP_EOL;
echo 'wal_action=' . $report['wal_action'] . PHP_EOL;
echo 'current_reader_end_frame=' . $report['current_reader_end_frame'] . PHP_EOL;
echo 'next_reader_end_frame=' . $report['next_reader_end_frame'] . PHP_EOL;
echo 'current_stable=' . ($report['current_stable'] ? 'yes' : 'no') . PHP_EOL;
echo 'next_matches_latest_snapshot=' . ($report['next_matches_latest_snapshot'] ? 'yes' : 'no') . PHP_EOL;
echo 'current_page2_frame=' . $report['current_frame_indexes'][0] . PHP_EOL;
echo 'next_page2_frame=' . $report['next_frame_indexes'][0] . PHP_EOL;
