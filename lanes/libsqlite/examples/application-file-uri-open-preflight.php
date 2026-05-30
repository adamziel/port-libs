<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFileUri;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$copiedDatabase = SQLiteFileUri::parse('file:/srv/application/wp-content/database/site%20copy.sqlite?mode=rw&cache=shared&immutable=0&application=copy');
$readOnlyInspection = SQLiteFileUri::parse('file://localhost/tmp/wp-content/cache.db?mode=ro&immutable=1&vfs=unix-dotfile');

echo json_encode([
    'copiedDatabase' => [
        'path' => $copiedDatabase['path'],
        'mode' => $copiedDatabase['mode'],
        'cache' => $copiedDatabase['cache'],
        'immutable' => $copiedDatabase['immutable'],
        'unknownParameters' => $copiedDatabase['unknown_parameters'],
    ],
    'readOnlyInspection' => [
        'authority' => $readOnlyInspection['authority'],
        'path' => $readOnlyInspection['path'],
        'mode' => $readOnlyInspection['mode'],
        'immutable' => $readOnlyInspection['immutable'],
        'vfs' => $readOnlyInspection['vfs'],
    ],
    'applicationUse' => 'Decode and validate SQLite file: URI filenames before opening copied Application databases in repair, import, or read-only inspection tools without requiring ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
