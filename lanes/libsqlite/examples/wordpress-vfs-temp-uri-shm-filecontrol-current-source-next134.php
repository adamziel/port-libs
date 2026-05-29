<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext134([
    'open(main, file:/srv/www/wp-content/database/wp-options.sqlite?mode=rw&cache=shared)',
    'file_control(reserve_bytes, 48)',
    'open(temp, file:/srv/www/wp-content/database/wp%20import.sqlite?mode=memory&cache=private)',
    'file_control(chunk_size, 4096)',
    'open(temp-shm, file:/srv/www/wp-content/database/wp%20import.sqlite-shm?mode=memory&cache=private)',
    ['op' => 'shmlock', 'source' => 'temp-shm', 'lock' => 'read0', 'span' => 2, 'mode' => 'shared', 'connection' => 'wp-import'],
    'source(main)',
    'file_control(persist_wal, on)',
    'close(temp-shm)',
    'close(temp)',
]);

echo json_encode([
    'status' => $plan['status'],
    'persistentControls' => $plan['next']['persistent_control_count'],
    'tempOpenHandles' => $plan['next']['temp_open_count'],
    'deletedTempOwners' => $plan['next']['deleted_temp_owners'],
    'tempShmLockStatus' => $plan['events'][5]['status'],
    'tempShmLockReason' => $plan['events'][5]['reason'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
