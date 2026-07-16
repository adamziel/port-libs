<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$salt1 = 0x10203040;
$salt2 = 0x50607080;
$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-writer-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';

$makeFirstPage = static function (int $databaseSizePages) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databaseSizePages), 28, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$walHeaderPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, $salt1, $salt2);
$checksumSeed = SQLiteWal::checksumPair($walHeaderPrefix, false);
$walBytes = $walHeaderPrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]);
$appendFrame = static function (string $walBytes, array &$checksumSeed, int $pageNumber, int $commit, string $pageImage) use ($salt1, $salt2): string {
    $framePrefix = pack('N*', $pageNumber, $commit, $salt1, $salt2);
    $checksumSeed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $pageImage, false, $checksumSeed[0], $checksumSeed[1]);

    return $walBytes . $framePrefix . pack('N*', $checksumSeed[0], $checksumSeed[1]) . $pageImage;
};

$baseDatabaseBytes = $makeFirstPage(2) . str_pad('base wp_options page', $pageSize, "\0");
$walBytes = $appendFrame($walBytes, $checksumSeed, 2, 2, str_pad('committed wp_options import page', $pageSize, "\0"));
$wal = SQLiteWal::parse($walBytes, null, true);
$writer = new SQLiteVfsFileWriter($root);
$restart = $writer->applyWalCheckpoint($wal, $baseDatabaseBytes, $databasePath, 'restart');
$truncate = $writer->applyWalCheckpoint($wal, $baseDatabaseBytes, $databasePath, 'truncate');

echo json_encode([
    'scenario' => 'application-vfs-file-writer-apply',
    'applicationUse' => 'Apply accepted WAL checkpoint write plans through bounded native PHP file handles for copied wp_options database repairs, including database page writes, WAL restart/truncate sidecar writes, sync markers, and directory persistence diagnostics without ext/sqlite.',
    'root' => $root,
    'databasePath' => $databasePath,
    'localDatabaseBytes' => filesize($root . $databasePath),
    'localWalBytesAfterTruncate' => filesize($root . $databasePath . '-wal'),
    'restart' => [
        'status' => $restart['status'],
        'applied' => $restart['applied'],
        'bytesWritten' => $restart['bytes_written'],
        'durableSyncs' => $restart['durable_syncs'],
        'directorySyncs' => $restart['directory_syncs'],
        'dependencies' => $restart['dependencies'],
    ],
    'truncate' => [
        'status' => $truncate['status'],
        'applied' => $truncate['applied'],
        'bytesWritten' => $truncate['bytes_written'],
        'bytesTruncated' => $truncate['bytes_truncated'],
        'durableSyncs' => $truncate['durable_syncs'],
        'directorySyncs' => $truncate['directory_syncs'],
        'dependencies' => $truncate['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
