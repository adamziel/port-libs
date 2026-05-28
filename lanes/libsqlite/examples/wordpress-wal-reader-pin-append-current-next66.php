<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp schema') . $page('wp_options old siteurl') . $page('wp_options old autoload') . $page('wp_options old metadata');
$salt1 = 0x66000001;
$salt2 = 0x66000002;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 66, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl pinned reader')],
    [3, 4, $page('wp_options autoload checkpoint commit')],
    [2, 4, $page('wp_options siteurl before append')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointReaderPinAppendCurrentNext(
    $databaseBytes,
    [2, 3, 4],
    [0, 1, null],
    [
        ['page_number' => 2, 'commit_page_count' => 0, 'page_image' => $page('wp_options siteurl appended writer')],
        ['page_number' => 4, 'commit_page_count' => 4, 'page_image' => $page('wp_options metadata appended commit')],
    ],
    'restart'
);

echo json_encode([
    'status' => $plan['status'],
    'checkpoint_busy' => $plan['checkpoint_busy'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'next_reader_slot' => $plan['next_reader_slot'],
    'next_read_marks' => $plan['next_read_marks'],
    'current_sources' => $plan['current_sources'],
    'next_sources' => $plan['next_sources'],
    'next_sees_appended_commit' => $plan['next_sees_appended_commit'],
    'dependency' => in_array('wal-reader-pin-current-next66', $plan['dependencies'], true),
], JSON_PRETTY_PRINT) . PHP_EOL;
