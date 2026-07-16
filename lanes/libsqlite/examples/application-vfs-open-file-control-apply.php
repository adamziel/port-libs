<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenFileControl;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$root = sys_get_temp_dir() . '/port-libsqlite-application-vfs-open-file-control-' . bin2hex(random_bytes(4));
$databasePath = '/srv/www/wp-content/database/.ht.sqlite';
$localDatabase = $root . $databasePath;
if (!is_dir(dirname($localDatabase)) && !mkdir(dirname($localDatabase), 0777, true) && !is_dir(dirname($localDatabase))) {
    throw new RuntimeException('Failed to create Application SQLite fixture directory');
}
file_put_contents($localDatabase, str_repeat('wp option page', 256));

$open = SQLiteVfsOpenFileControl::forFilename(
    $root,
    'file:/srv/www/wp-content/database/.ht.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
    true,
    true,
    4096,
    ['safe_append', 'powersafe_overwrite'],
    'full',
    false,
    4096,
    0
);

$applied = $open->applyMany([
    'persist_wal' => true,
    ['op' => 'name_hint', 'value' => 'wp-options-import'],
    ['op' => 'size_hint', 'value' => 20000],
    'mmap_size' => 65536,
]);

echo json_encode([
    'scenario' => 'application-vfs-open-file-control-apply',
    'applicationUse' => 'Apply SQLite xFileControl size hints through the native PHP VFS file handle opened for copied wp_options imports, including chunk-size rounded preallocation and file-control state without requiring ext/sqlite.',
    'path' => $databasePath,
    'fileControlApplied' => $applied['file_control']['applied'],
    'bytesPreallocated' => $applied['bytes_preallocated'],
    'targetSize' => $applied['preallocations'][0]['target_size'] ?? null,
    'finalSize' => $applied['stat']['size'],
    'persistWal' => $applied['file_control']['controls']['persist_wal'],
    'mmapSize' => $applied['file_control']['controls']['mmap_size'],
    'dependencies' => $applied['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
