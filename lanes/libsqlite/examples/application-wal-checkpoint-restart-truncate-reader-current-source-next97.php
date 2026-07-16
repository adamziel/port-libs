<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$salt1 = 0x97112233;
$salt2 = 0x97445566;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp schema before next97')
    . $page('wp option before next97')
    . $page('wp autoload before next97')
    . $page('wp plugin before next97')
    . $page('wp transient before next97');

$makeWal = static function (array $frames) use ($pageSize, $salt1, $salt2): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 97, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted, int $mxFrame = 8) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 197, $pageSizeField, $mxFrame, 5, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$walBytes = $makeWal([
    [2, 0, $page('wp option old reader next97')],
    [3, 3, $page('wp autoload first commit next97')],
    [2, 0, $page('wp option next reader next97')],
    [4, 0, $page('wp plugin draft next97')],
    [5, 0, $page('wp transient draft next97')],
    [4, 5, $page('wp plugin committed next97')],
    [2, 0, $page('wp option admin final next97')],
    [3, 5, $page('wp autoload final next97')],
]);

$wal = SQLiteWal::parse($walBytes, null, true);
$plan = $wal->checkpointRestartTruncateReaderPreserveCurrentSourceNext(
    $databaseBytes,
    $walBytes,
    SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4)),
    SQLiteShmIndex::parse($makeShm([0, null, 8, null, null], [false, false, true, false, false], 2, 8)),
    SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 8, 8)),
    [2, 3, 4, 5]
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'reader-current-source-next97');
    assert($plan['current_source_verified'] === true);
    assert($plan['next_reader_blocks_restart_reset'] === true);
    assert($plan['next_reader_blocks_truncate_reset'] === true);
    assert($plan['restart_final_uses_restarted_wal_header'] === true);
    assert($plan['truncate_final_removes_wal_sidecar'] === true);
    assert(in_array('wal-checkpoint-restart-truncate-reader-current-source-next97', $plan['dependencies'], true));
    echo "application-wal-checkpoint-restart-truncate-reader-current-source-next97 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'current_source_verified' => $plan['current_source_verified'],
    'current_reader_frame' => $plan['current_reader_end_frame'],
    'next_reader_frame' => $plan['next_reader_end_frame'],
    'restart_after_current' => $plan['restart_after_current_wal_action'],
    'truncate_after_current' => $plan['truncate_after_current_wal_action'],
    'restart_after_all' => $plan['restart_after_all_wal_action'],
    'truncate_after_all' => $plan['truncate_after_all_wal_action'],
    'current_sources' => $plan['current_sources'],
    'next_sources' => $plan['next_sources_after_current_release'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . "\n";
