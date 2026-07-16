<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteShmIndex.php';
require_once __DIR__ . '/../src/SQLiteWal.php';
require_once __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalSavepointCheckpointPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteShmIndex;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalSavepointCheckpointPlan;

$pageSize = 512;
$salt1 = 0x10510501;
$salt2 = 0x10510502;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('wp next105 schema base')
    . $page('wp next105 options base')
    . $page('wp next105 plugin base')
    . $page('wp next105 autoload base')
    . $page('wp next105 transient base');

$makeWalBytes = static function (array $frames) use ($pageSize, $salt1, $salt2, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 105, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$makeShm = static function (array $readMarks, array $readLocks, int $backfill, int $attempted) use ($pageSize, $salt1, $salt2): string {
    $pageSizeField = (1 << 24) | $pageSize;
    $header = pack('V*', 3007000, $backfill, 105, $pageSizeField, 7, 5, 1, 2, $salt1, $salt2, 5, 6);
    $marks = array_map(static fn ($value): int => $value === null ? 0xffffffff : $value, array_pad(array_values($readMarks), SQLiteShmIndex::READER_COUNT, null));
    $locks = array_pad(array_map(static fn (bool $held): string => $held ? "\x01" : "\x00", array_values($readLocks)), 8, "\x00");
    $checkpoint = pack('V*', $backfill, $marks[0], $marks[1], $marks[2], $marks[3], $marks[4])
        . implode('', array_slice($locks, 0, 8))
        . pack('V*', $attempted, 0);

    return $header . $header . $checkpoint;
};

$walBytes = $makeWalBytes([
    [1, 0, 'wp next105 schema draft retained'],
    [2, 5, 'wp next105 options commit retained'],
    [3, 0, 'wp next105 plugin draft rolled back'],
    [4, 0, 'wp next105 autoload draft rolled back'],
    [4, 5, 'wp next105 autoload commit rolled back'],
    [5, 5, 'wp next105 transient commit rolled back'],
    [2, 5, 'wp next105 options tail rolled back'],
]);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$currentShm = SQLiteShmIndex::parse($makeShm([0, 2, null, null, null], [false, true, false, false, false], 1, 4));
$nextReaderShm = SQLiteShmIndex::parse($makeShm([0, 2, 7, null, null], [false, true, true, false, false], 1, 6));
$allReleasedShm = SQLiteShmIndex::parse($makeShm([0, null, null, null, null], [false, false, false, false, false], 7, 7));

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import-next105');
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->recordWalFrameWrite(2, 2, true);
$savepoints->savepoint('plugin-settings-next105');
$savepoints->recordWalFrameWrite(3, 3);
$savepoints->savepoint('autoload-refresh-next105');
$savepoints->recordWalFrameWrite(4, 4);
$savepoints->recordWalFrameWrite(5, 4, true);
$savepoints->savepoint('transient-row-next105');
$savepoints->recordWalFrameWrite(6, 5, true);
$savepoints->recordWalFrameWrite(7, 2, true);

$plan = SQLiteWalSavepointCheckpointPlan::checkpointRestartTruncateSavepointReaderCurrentSourceNext(
    $savepoints,
    'plugin-settings-next105',
    $wal,
    $walBytes,
    $databaseBytes,
    $currentShm,
    $nextReaderShm,
    $allReleasedShm,
    [1, 2, 3, 4, 5]
);

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'current_reader_end_frame' => $plan['current_reader_end_frame'],
    'pinned_reader_blocks_restart_reset' => $plan['pinned_reader_blocks_restart_reset'],
    'pinned_reader_blocks_truncate_reset' => $plan['pinned_reader_blocks_truncate_reset'],
    'reader_release_unblocks_restart' => $plan['reader_release_unblocks_restart'],
    'reader_release_unblocks_truncate' => $plan['reader_release_unblocks_truncate'],
    'restart_final_wal_generation' => $plan['restart_final_wal_generation'],
    'truncate_final_wal_generation' => $plan['truncate_final_wal_generation'],
    'current_sources' => $plan['current_sources'],
    'restart_released_sources' => $plan['restart_released_sources'],
    'truncate_released_sources' => $plan['truncate_released_sources'],
    'rolled_back_page_numbers' => $plan['rolled_back_page_numbers'],
    'dependency' => in_array('sqlite-wal-restart-truncate-savepoint-reader-current-source-next105', $plan['dependencies'], true),
], JSON_PRETTY_PRINT) . PHP_EOL;
