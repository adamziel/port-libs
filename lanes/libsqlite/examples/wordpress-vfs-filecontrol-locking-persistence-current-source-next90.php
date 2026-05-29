<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsOpenLockFileControlCurrentSource.php';

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$first = SQLiteVfsOpenLockFileControlCurrentSource::planLockingFileControlPersistence([
    'open',
    'file_control(chunk_size, 8192)',
    'lock(reserved)',
    'file_control(chunk_size, 16384)',
    'file_control(reserve_bytes, 16)',
    'close',
], [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix',
]);

$second = SQLiteVfsOpenLockFileControlCurrentSource::planLockingFileControlPersistence([
    'open(file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix)',
], [
    'current' => $first['events'][5]['next'],
]);

echo json_encode([
    'scenario' => 'wordpress-vfs-filecontrol-locking-persistence-current-source-next90',
    'path' => $second['events'][0]['source_key'],
    'unlocked_chunk_status' => $first['events'][1]['status'],
    'unlocked_chunk_reason' => $first['events'][1]['reason'],
    'reserved_chunk_status' => $first['events'][3]['status'],
    'reopen_reused_controls' => $second['events'][0]['reused_controls'],
    'reopened_controls' => $second['events'][0]['next']['handles']['db-2']['controls'],
    'persistent_lock_count' => $second['next']['persistent_lock_count'],
    'dependencies' => $second['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
