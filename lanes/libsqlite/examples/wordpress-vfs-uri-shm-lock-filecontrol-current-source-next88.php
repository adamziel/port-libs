<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsShmLockFileControlCurrentSource;

$first = SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([
    'open',
    'shm_lock(write, exclusive)',
    'file_control(persist_wal, on)',
    'file_control(chunk_size, 16384)',
    'release',
    'close',
], [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared',
]);

$reopen = SQLiteVfsShmLockFileControlCurrentSource::planUriShmLockFileControl([
    'open',
    'shm_lock(read, shared)',
    'file_control(mmap_size, 262144)',
], [
    'filename' => 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private',
    'current' => $first['events'][5]['next'],
]);

echo json_encode([
    'scenario' => 'wordpress-vfs-uri-shm-lock-filecontrol-current-source-next88',
    'status' => $reopen['status'],
    'source' => $reopen['events'][0]['source_key'],
    'authority' => $reopen['events'][0]['uri']['authority'],
    'reused_controls' => $reopen['events'][0]['reused_controls'],
    'generation' => $reopen['next']['generation'],
    'controls' => $reopen['next']['controls'],
    'dependencies' => $reopen['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
