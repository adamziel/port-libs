<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBusyHandler;
use PortLibs\LibSqlite\SQLiteOpenPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$copiedDatabase = SQLiteOpenPlan::forFilename(
    'file:/srv/application/wp-content/database/site%20copy.sqlite?mode=rw&cache=shared&busy_timeout=25',
    true,
    false,
    false,
    SQLiteBusyHandler::timeout(25)
);

$readOnlyArchive = SQLiteOpenPlan::forFilename(
    'file://localhost/srv/application/archive/site.sqlite?mode=ro&immutable=1&vfs=unix-none',
    true,
    false
);

$newCopy = SQLiteOpenPlan::forFilename(
    'file:/srv/application/wp-content/database/new-copy.sqlite?mode=rwc',
    false,
    true
);

echo json_encode([
    'applicationUse' => 'Preview native SQLite open admission for copied Application databases before a future file-handle/VFS layer touches the filesystem or requires ext/sqlite.',
    'copiedDatabase' => [
        'status' => $copiedDatabase['status'],
        'path' => $copiedDatabase['path'],
        'mode' => $copiedDatabase['mode'],
        'cache' => $copiedDatabase['cache'],
        'dependencies' => $copiedDatabase['dependencies'],
        'busy' => [
            'timeout_ms' => $copiedDatabase['busy']['timeout_ms'] ?? null,
            'retry_count' => $copiedDatabase['busy']['retry_count'] ?? null,
            'total_sleep_ms' => $copiedDatabase['busy']['total_sleep_ms'] ?? null,
        ],
    ],
    'readOnlyArchive' => [
        'status' => $readOnlyArchive['status'],
        'path' => $readOnlyArchive['path'],
        'readOnly' => $readOnlyArchive['read_only'],
        'immutable' => $readOnlyArchive['immutable'],
        'vfs' => $readOnlyArchive['vfs'],
        'dependencies' => $readOnlyArchive['dependencies'],
    ],
    'newCopy' => [
        'status' => $newCopy['status'],
        'path' => $newCopy['path'],
        'canCreate' => $newCopy['can_create'],
        'dependencies' => $newCopy['dependencies'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
