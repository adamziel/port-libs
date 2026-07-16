<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFileHeaderLoader;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage = substr_replace($firstPage, pack('N', 2), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$path = tempnam(sys_get_temp_dir(), 'wp-sqlite-copy-');
if ($path === false) {
    throw new RuntimeException('Unable to create copied Application SQLite fixture');
}

file_put_contents($path, $firstPage . str_repeat("\0", 512));
$uri = 'file:' . str_replace('%2F', '/', rawurlencode($path)) . '?mode=ro&immutable=1&vfs=unix-none';
$inspection = SQLiteFileHeaderLoader::inspect($uri);
@unlink($path);

echo json_encode([
    'scenario' => 'application-file-header-loader-preflight',
    'applicationUse' => 'Read and validate only the bounded SQLite database header for a copied Application database before full file-handle/VFS loading, without ext/sqlite.',
    'database' => [
        'status' => $inspection['status'],
        'path' => $inspection['path'],
        'bytesRead' => $inspection['bytes_read'],
        'fileSize' => $inspection['file_size'],
        'pageSize' => $inspection['header']?->pageSize,
        'databaseSizePages' => $inspection['header']?->databaseSizePages,
        'completeFirstPage' => $inspection['complete_first_page'],
        'completeDeclaredPages' => $inspection['complete_declared_pages'],
        'readOnly' => $inspection['open']['read_only'],
        'vfs' => $inspection['open']['vfs'],
        'dependencies' => $inspection['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
