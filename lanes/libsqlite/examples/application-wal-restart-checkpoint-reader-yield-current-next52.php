<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('db page 1 wp_options schema base')
    . $page('db page 2 active_plugins base')
    . $page('db page 3 autoload index base')
    . $page('db page 4 transient cache base');
$salt1 = 0x52525252;
$salt2 = 0x25252525;
$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 52, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
$appendFrame = static function (string $bytes, array &$seed, int $pageNumber, int $commit, string $image) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);

    return $bytes . $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
};
$walBytes = $appendFrame($walBytes, $seed, 1, 0, $page('frame 1 schema draft before restart'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('frame 2 active plugins pinned reader'));
$walBytes = $appendFrame($walBytes, $seed, 3, 4, $page('frame 3 autoload index committed batch'));
$walBytes = $appendFrame($walBytes, $seed, 2, 0, $page('frame 4 active plugins later draft'));
$walBytes = $appendFrame($walBytes, $seed, 4, 4, $page('frame 5 transient cache committed batch'));
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$pageSizeField = (1 << 24) | $pageSize;
$header = pack('V*', 3007000, 1, 104, $pageSizeField, 5, 4, 0x01010101, 0x02020202, 0x11111111, 0x22222222, 0x33333333, 0x44444444);
$checkpoint = pack('V*', 1, 0, 3, 5, 0xffffffff, 0xffffffff)
    . "\x00\x01\x01\x00\x00\x00\x00\x00"
    . pack('V*', 3, 0);
$shm = SQLiteShmIndex::parse($header . $header . $checkpoint);

$plan = $wal->restartCheckpointReaderYieldCurrentNext(
    $databaseBytes,
    $shm,
    [1, 2, 3, 4],
    [1],
    'restart'
);

$report = [
    'scenario' => 'application-wal-restart-checkpoint-reader-yield-current-next52',
    'applicationUse' => 'A copied wp_options reader releases its SHM read mark so a blocked restart checkpoint can reset the WAL for the next importer reader.',
    'status' => $plan['status'],
    'firstCheckpointReason' => $plan['first_checkpoint']['reason'],
    'yieldedCheckpointReason' => $plan['yielded_checkpoint']['reason'],
    'yieldedSlots' => $plan['yielded_slots'],
    'currentReaderSources' => $plan['current_reader_sources'],
    'nextReaderSources' => $plan['next_reader_sources'],
    'nextUsesDatabase' => $plan['next_reader_uses_database'],
    'nextUsesRestartedWal' => $plan['next_reader_uses_restarted_wal'],
    'dependencies' => $plan['dependencies'],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
