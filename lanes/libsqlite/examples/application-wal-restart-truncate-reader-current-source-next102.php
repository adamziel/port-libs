<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x10211223;
$salt2 = 0x10244556;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema before next102')
    . $page('wp option before next102')
    . $page('wp autoload before next102')
    . $page('wp plugin before next102')
    . $page('wp transient before next102');

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 102, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 202, $pageSizeField, 8, 5, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");

    return $header . $header . pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);
};

$walBytes = $makeWal([
    [2, 0, $page('wp option old reader next102')],
    [3, 3, $page('wp autoload first commit next102')],
    [2, 0, $page('wp option staged next102')],
    [4, 0, $page('wp plugin draft next102')],
    [5, 0, $page('wp transient draft next102')],
    [4, 5, $page('wp plugin committed next102')],
    [2, 0, $page('wp option final next102')],
    [5, 5, $page('wp transient committed next102')],
]);

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointRestartTruncateReaderRecoveryCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4)),
    SQLiteShmIndex::parse($makeShm([0, 2, 8, null, null], [false, true, true, false, false], 1, 7)),
    SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 8, 8)),
    [2, 3, 4, 5]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'reader-current-source-next102');
    assert($plan['shm_source_verified'] === true);
    assert($plan['restart_final_wal_generation']['action'] === 'restart_wal');
    assert($plan['truncate_final_wal_generation']['action'] === 'truncate_wal');
    assert(in_array('wal-restart-truncate-reader-current-source-next102', $plan['dependencies'], true));
    echo "application-wal-restart-truncate-reader-current-source-next102 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'shm_source_verified' => $plan['shm_source_verified'],
    'current_sources' => $plan['current_source_names_next102'],
    'next_sources' => $plan['next_source_names_next102'],
    'restart_final' => $plan['restart_final_wal_generation'],
    'truncate_final' => $plan['truncate_final_wal_generation'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
