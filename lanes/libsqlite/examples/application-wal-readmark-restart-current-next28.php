<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = $page('wp-options-db-page-1') . $page('wp-options-db-page-2') . $page('wp-options-db-page-3');
$salt1 = 0x10203040;
$salt2 = 0x50607080;

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 28, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('draft option_value update')],
    [3, 3, $page('autoload index commit')],
    [2, 0, $page('edited option_value update')],
    [1, 0, $page('schema cookie update')],
    [2, 3, $page('final option_value commit')],
] as $frame) {
    [$pageNumber, $commitPageCount, $image] = $frame;
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$pageSizeField = (1 << 24) | $pageSize;
$header = pack('V*', 3007000, 1, 128, $pageSizeField, 5, 3, 1, 2, 3, 4, 5, 6);
$checkpoint = pack('V*', 1, 0, 2, 5, 0xffffffff, 9)
    . "\x00\x01\x01\x00\x01\x00\x00\x00"
    . pack('V*', 4, 0);

$wal = SQLiteWal::parse($walBytes, null, true);
$pinned = $wal->restartReadMarkTransition($database, SQLiteShmIndex::parse($header . $header . $checkpoint), 'restart');

$releasedCheckpoint = pack('V*', 5, 0, 5, 0xffffffff, 0xffffffff, 0xffffffff)
    . "\x00\x00\x00\x00\x00\x00\x00\x00"
    . pack('V*', 5, 0);
$released = $wal->restartReadMarkTransition($database, SQLiteShmIndex::parse($header . $header . $releasedCheckpoint), 'restart');

echo json_encode([
    'application' => 'wp_options WAL read-mark restart current/next diagnostics',
    'pinned_status' => $pinned['status'],
    'pinned_reader_end_frame' => $pinned['current_reader_end_frame'],
    'pinned_wal_action' => $pinned['checkpoint']['wal_action'],
    'pinned_next_read_marks' => $pinned['next_read_marks'],
    'released_status' => $released['status'],
    'released_wal_action' => $released['checkpoint']['wal_action'],
    'released_next_header_sequence' => $released['next_wal_header']['checkpoint_sequence'],
    'released_next_read_marks' => $released['next_read_marks'],
], JSON_PRETTY_PRINT) . PHP_EOL;
