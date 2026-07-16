<?php

declare(strict_types=1);

require dirname(__DIR__) . '/../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$makeWal = static function (array $frames) use ($pageSize): string {
    $salt1 = 0x23456789;
    $salt2 = 0xabcdef01;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 202, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $image]) {
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$walBytes = $makeWal([
    [1, 0, $page('before-plugin-import')],
    [2, 0, $page('plugin-setting-draft')],
    [3, 3, $page('plugin-setting-commit')],
]);
$wal = SQLiteWal::parse($walBytes, null, true);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp_options_import');
$savepoints->recordPageImageWrite(1, $page('clean-option-page'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->savepoint('plugin_settings_batch');
$savepoints->recordPageImageWrite(2, $page('old-plugin-setting'));
$savepoints->recordWalFrameWrite(2, 2);
$savepoints->recordPageImageWrite(3, $page('old-autoload-index'));
$savepoints->recordWalFrameWrite(3, 3, true);

$root = sys_get_temp_dir() . '/port-libsqlite-application-wal-savepoint-' . bin2hex(random_bytes(4));
$writer = new SQLiteVfsFileWriter($root);
$databasePath = '/wp-content/database/.ht.sqlite';
$dirtyDatabase = $page('dirty-option-page') . $page('dirty-plugin-setting') . $page('dirty-autoload-index');

$result = $writer->applySavepointRollback(
    $savepoints,
    'plugin_settings_batch',
    $dirtyDatabase,
    $pageSize,
    $databasePath,
    $wal,
    $walBytes
);

echo json_encode([
    'status' => $result['status'],
    'applied' => $result['applied'],
    'database_restored_page_numbers' => $result['database_image']['restored_page_numbers'],
    'wal_retained_frame_count' => $result['wal_truncation']['retained_frame_count'],
    'wal_discarded_frame_count' => $result['wal_truncation']['discarded_frame_count'],
    'durable_syncs' => $result['durable_syncs'],
    'directory_syncs' => $result['directory_syncs'],
    'dependencies' => $result['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
