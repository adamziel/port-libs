<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.');
$databaseBytes = $page('wp schema') . $page('wp_options old siteurl') . $page('wp_options old autoload') . $page('wp_options old plugin meta');
$salt1 = 0x68000001;
$salt2 = 0x68000002;

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 68, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl pinned reader')],
    [3, 4, $page('wp_options autoload pinned commit')],
    [2, 0, $page('wp_options siteurl next reader')],
    [4, 4, $page('wp_options plugin meta next commit')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointReaderPinSlotHandoffCurrentNext(
    $databaseBytes,
    [2, 3, 4],
    [0, 2, null],
    null,
    'restart'
);

echo json_encode([
    'status' => $plan['status'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'next_reader_slot' => $plan['next_reader_slot'],
    'next_read_marks' => $plan['next_read_marks'],
    'checkpoint_with_next_busy' => $plan['checkpoint_with_next']['busy'],
    'released_checkpoint_action' => $plan['released_checkpoint']['wal_action'],
    'release_unblocks_reset' => $plan['release_unblocks_reset'],
    'current_sources' => $plan['current_sources'],
    'next_sources' => $plan['next_sources'],
    'dependency' => in_array('wal-reader-pin-current-next68', $plan['dependencies'], true),
], JSON_PRETTY_PRINT) . PHP_EOL;
