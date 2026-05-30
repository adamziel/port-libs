<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsUriOpenLockByteCurrentSourceNext;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([
    [
        'name' => 'main',
        'filename' => 'file:/srv/www/wp-content/database/site.sqlite?mode=rw&cache=shared&vfs=unix&psow=1',
        'operations' => [
            'shared wp-reader 4',
            'reserved wp-import 9',
            'pending wp-import',
        ],
    ],
    [
        'name' => 'archive',
        'filename' => 'file://localhost/srv/www/wp-content/database/archive%20copy.sqlite?mode=ro&immutable=1',
        'operations' => [
            'shared wp-report 11',
        ],
    ],
]);

echo json_encode([
    'status' => $plan['status'],
    'mainPath' => $plan['next']['sources']['main']['path'],
    'mainHolders' => $plan['next']['sources']['main']['holders'],
    'archivePath' => $plan['next']['sources']['archive']['path'],
    'archiveHolders' => $plan['next']['sources']['archive']['holders'],
    'pendingByte' => $plan['next']['sources']['main']['constants']['pending'],
    'dependencies' => $plan['dependencies'],
    'applicationUse' => 'Track copied Application SQLite file: URI opens as named current sources, then derive SQLite lock-byte ranges from the active source before import or read-only archive inspection.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
