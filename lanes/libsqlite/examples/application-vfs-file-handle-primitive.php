<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsFileHandle;

$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-file-handle-' . bin2hex(random_bytes(4));
$database = '/srv/www/wp-content/database/.ht.sqlite';
$wal = $database . '-wal';
$localDatabase = $root . $database;

if (!is_dir(dirname($localDatabase))) {
    mkdir(dirname($localDatabase), 0777, true);
}

$page = str_pad("SQLite format 3\0wp_options copied page", 512, "\0");
file_put_contents($localDatabase, $page . str_repeat('A', 512));

$databaseHandle = new SQLiteVfsFileHandle($root, $database);
$walHandle = new SQLiteVfsFileHandle($root, $wal);

$stat = $databaseHandle->stat();
$header = $databaseHandle->readAt(0, 16);
$shortRead = $databaseHandle->readAt(1018, 16);
$write = $walHandle->writeAt(0, 'copied-wal-frame');
$truncate = $walHandle->truncateTo(8);
$readOnly = new SQLiteVfsFileHandle($root, $database, true);

$readOnlyBlocked = false;
try {
    $readOnly->writeAt(0, 'blocked');
} catch (LogicException) {
    $readOnlyBlocked = true;
}

echo json_encode([
    'scenario' => 'application-vfs-file-handle-primitive',
    'applicationUse' => 'Read copied wp_options database pages and apply WAL sidecar bytes through bounded SQLite VFS xRead/xWrite/xTruncate/xFileSize primitives without requiring ext/sqlite.',
    'root' => $root,
    'database' => [
        'path' => $database,
        'exists' => $stat['exists'],
        'size' => $stat['size'],
        'header' => $header['data'],
        'tailReadStatus' => $shortRead['status'],
        'tailShortReadBytes' => $shortRead['short_read'],
    ],
    'wal' => [
        'path' => $wal,
        'bytesWritten' => $write['bytes_written'],
        'sizeAfterWrite' => $write['size'],
        'sizeAfterTruncate' => $truncate['size'],
        'localBytes' => file_get_contents($root . $wal),
    ],
    'readOnlyBlocked' => $readOnlyBlocked,
    'dependencies' => array_values(array_unique(array_merge(
        $stat['dependencies'],
        $header['dependencies'],
        $write['dependencies'],
        $truncate['dependencies']
    ))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
