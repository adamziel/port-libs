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
$salt1 = 0x76112233;
$salt2 = 0x76445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema baseline')
    . $page('wp_options siteurl baseline')
    . $page('wp_options autoload baseline')
    . $page('wp plugin settings baseline')
    . $page('wp transient baseline');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 76, $salt1, $salt2);
$seed = SQLiteWal::checksumPair($prefix, false);
$walBytes = $prefix . pack('N*', $seed[0], $seed[1]);
foreach ([
    [2, 0, $page('wp_options siteurl before old reader')],
    [3, 3, $page('wp_options autoload before old reader')],
    [2, 0, $page('wp_options siteurl after old reader')],
    [4, 0, $page('wp plugin settings draft')],
    [5, 0, $page('wp transient draft')],
    [4, 5, $page('wp plugin settings committed')],
    [2, 5, $page('wp_options siteurl committed')],
] as [$pageNumber, $commitPageCount, $image]) {
    $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
    $walBytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
}

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 176, $pageSizeField, 7, 5, 1, 2, 3, 4, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointReaderPinRestartCurrentNext(
    $databaseBytes,
    SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4)),
    SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6)),
    SQLiteShmIndex::parse($makeShm([0, null, 7, null, null], [false, false, true, false, false], 2, 7)),
    SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7)),
    [2, 3, 4, 5],
    'restart',
);

$summary = [
    'scenario' => 'wordpress-wal-reader-pin-checkpoint-restart-current-next76',
    'status' => $plan['status'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'current_release_still_busy' => $plan['after_current_release']['checkpoint']['busy'],
    'current_release_reason' => $plan['after_current_release']['checkpoint']['reason'],
    'final_checkpoint_action' => $plan['after_all_release']['checkpoint']['wal_action'],
    'next_reader_blocks_reset' => $plan['next_reader_blocks_reset'],
    'final_reset_ready' => $plan['final_reset_ready'],
    'current_sources' => $plan['current_reader_sources'],
    'next_sources' => $plan['next_reader_sources'],
    'final_sources' => $plan['final_reader_sources'],
    'dependency' => in_array('wal-reader-pin-checkpoint-restart-current-next76', $plan['dependencies'], true),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'reader-pin-next-reader-blocks-restart-current-next76');
    assert($summary['current_reader_frame'] === 2);
    assert($summary['next_reader_frame'] === 7);
    assert($summary['current_release_still_busy'] === true);
    assert($summary['current_release_reason'] === 'reader_blocks_wal_reset');
    assert($summary['final_checkpoint_action'] === 'restart_wal');
    assert($summary['next_reader_blocks_reset'] === true);
    assert($summary['final_reset_ready'] === true);
    assert($summary['current_sources'] === ['wal', 'wal', 'missing', 'missing']);
    assert($summary['next_sources'] === ['wal', 'wal', 'wal', 'wal']);
    assert($summary['final_sources'] === ['database', 'database', 'database', 'database']);
    assert($summary['dependency'] === true);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
