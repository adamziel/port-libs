<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBusyHandler.php';
require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteOpenPlan.php';
require_once __DIR__ . '/../src/SQLiteLockByteRangePlan.php';
require_once __DIR__ . '/../src/SQLiteVfsUriOpenLockByteCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteVfsUriOpenLockByteCurrentSourceNext;

$plan = SQLiteVfsUriOpenLockByteCurrentSourceNext::plan([
    ['kind' => 'open', 'source' => 'main', 'filename' => 'file://localhost/srv/www/wp-content/database/wp.sqlite?mode=rw&cache=shared&vfs=unix', 'connection' => 'wp-reader'],
    ['kind' => 'open', 'source' => 'import', 'filename' => 'file:/srv/www/wp-content/database/wp.sqlite?mode=rw&cache=private', 'connection' => 'wp-import'],
    ['kind' => 'lock', 'source' => 'main', 'level' => 'shared', 'connection' => 'wp-reader', 'shared_slot' => 4],
    ['kind' => 'lock', 'source' => 'import', 'level' => 'reserved', 'connection' => 'wp-import', 'shared_slot' => 9],
    ['kind' => 'lock', 'source' => 'main', 'level' => 'none', 'connection' => 'wp-reader'],
    ['kind' => 'lock', 'source' => 'import', 'level' => 'exclusive', 'connection' => 'wp-import'],
    ['kind' => 'close', 'source' => 'main', 'connection' => 'wp-reader'],
]);

echo json_encode([
    'status' => $plan['status'],
    'event_count' => count($plan['events']),
    'selected_source' => $plan['next']['selected_source'],
    'path_sources' => $plan['next']['path_sources'],
    'holders' => $plan['next']['sources']['import']['holders'] ?? [],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
