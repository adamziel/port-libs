<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x54545454;
$salt2 = 0x94949494;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = $page('db page 1 wp_options schema')
    . $page('db page 2 siteurl baseline')
    . $page('db page 3 autoload index baseline')
    . $page('db page 4 plugin baseline');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 54, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('frame 1 siteurl draft before pinned reader')],
    [3, 3, $page('frame 2 autoload commit before pinned reader')],
    [2, 0, $page('frame 3 siteurl edit after pinned reader')],
    [4, 0, $page('frame 4 plugin draft')],
    [2, 0, $page('frame 5 siteurl final before commit')],
    [4, 4, $page('frame 6 plugin commit after reader')],
    [3, 4, $page('frame 7 autoload index final commit')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize): string {
    $header = pack('V*', 3007000, $backfill, 154, (1 << 24) | $pageSize, 7, 4, 1, 2, 3, 4, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");

    return $header . $header
        . pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);
};

$plan = SQLiteWal::parse($walBytes, null, true)->checkpointReaderPinRestartRetryCurrentNext(
    $database,
    SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6)),
    SQLiteShmIndex::parse($makeShm([0, 7, null, null, null], [false, false, false, false, false], 7, 7)),
    [2, 3, 4],
    'restart'
);

echo json_encode([
    'status' => $plan['status'],
    'first_checkpoint' => $plan['first']['checkpoint']['reason'],
    'retry_checkpoint' => $plan['retry']['checkpoint']['wal_action'],
    'current_reader_sources' => $plan['current_reader_sources'],
    'next_reader_sources' => $plan['next_reader_sources'],
    'next_uses_restarted_wal' => $plan['next_reader_uses_restarted_wal'],
], JSON_PRETTY_PRINT) . PHP_EOL;
