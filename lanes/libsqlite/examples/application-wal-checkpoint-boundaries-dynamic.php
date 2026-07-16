<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteWalHeader.php';
require_once __DIR__ . '/../src/SQLiteWalFrame.php';
require_once __DIR__ . '/../src/SQLiteWal.php';

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 1024;
$page = static fn (string $label): string => str_pad(substr($label, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = $page('app_settings base schema')
    . $page('app_settings base key_value rows')
    . $page('app_settings base tenant rows')
    . $page('app_settings base audit rows');
$salt1 = 0x61020304;
$salt2 = 0x71020304;
$header = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 77, $salt1, $salt2);
$checksum = SQLiteWal::checksumPair($header, false);
$walBytes = $header . pack('N*', $checksum[0], $checksum[1]);

$append = static function (string $bytes, array &$checksum, int $pageNumber, int $commit, string $label) use ($page, $salt1, $salt2): string {
    $image = $page($label);
    $prefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair(substr($prefix, 0, 8) . $image, false, $checksum[0], $checksum[1]);

    return $bytes . $prefix . pack('N*', $checksum[0], $checksum[1]) . $image;
};

$walBytes = $append($walBytes, $checksum, 2, 0, 'app_settings pending key update frame');
$walBytes = $append($walBytes, $checksum, 3, 0, 'app_settings pending tenant update frame');
$walBytes = $append($walBytes, $checksum, 2, 0, 'app_settings superseding key update frame');
$walBytes = $append($walBytes, $checksum, 4, 4, 'app_settings commit frame');

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$noop = $wal->checkpointBoundaryResult($databaseBytes, 'noop', null, 2);
$passiveReader = $wal->checkpointBoundaryResult($databaseBytes, 'passive', 3, 0);
$truncate = $wal->checkpointBoundaryResult($databaseBytes, 'truncate', null, 0);
$rollbackMode = $wal->checkpointBoundaryResult($databaseBytes, 'passive', null, 0, false);

$summary = [
    'status' => 'ok',
    'source' => 'upstream wal.test, wal3.test, walrestart.test, walckptnoop.test, and e_walckpt.test checkpoint boundary semantics',
    'noopResult' => $noop['result'],
    'passiveReaderResult' => $passiveReader['result'],
    'truncateResult' => $truncate['result'],
    'rollbackModeResult' => $rollbackMode['result'],
    'truncateAction' => $truncate['wal_action'],
    'dependencies' => $truncate['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['noopResult'] === [0, 4, 2]);
    assert($summary['passiveReaderResult'] === [0, 4, 3]);
    assert($summary['truncateResult'] === [0, 0, 0]);
    assert($summary['rollbackModeResult'] === [0, -1, -1]);
    assert($summary['truncateAction'] === 'truncate_wal');
    assert(in_array('sqlite-wal-checkpoint-boundary-result', $summary['dependencies'], true));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
