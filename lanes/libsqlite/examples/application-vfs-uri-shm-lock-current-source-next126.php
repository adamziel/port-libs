<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::planUriShmFileControlLocks([
    'open(main, file://localhost/srv/www/wp-content/database/wp%20cache.sqlite?mode=rw&cache=shared)',
    'open(shm, file://localhost/srv/www/wp-content/database/wp%20cache.sqlite-shm?mode=rw&cache=shared)',
    ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-admin'],
    ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'shared', 'connection' => 'wp-cron'],
    ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'exclusive', 'connection' => 'wp-admin'],
    ['op' => 'shmlock', 'lock' => 'read0', 'mode' => 'unlock', 'connection' => 'wp-cron'],
    'source(main)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
]);

$summary = [
    'scenario' => 'application-vfs-uri-shm-lock-current-source-next126',
    'applicationUse' => 'Model copied Application SQLite file: URI SHM byte-lock ownership across wp-admin/wp-cron connections, preserving shared-reader compatibility, writer blockers, and stale current-source data-version detection without ext/sqlite.',
    'dependency' => 'vfs-uri-shm-filecontrol-lock-current-source-next126',
    'blockedExclusiveStatus' => $plan['events'][4]['status'],
    'blockedExclusiveOwners' => $plan['events'][4]['blocking_connections'],
    'readerOwnersAfterCronUnlock' => $plan['events'][5]['next']['handles']['vfs87-2']['shm_lock_owners']['read0'],
    'staleShmDataVersion' => $plan['events'][9]['stale_current_source'],
    'currentGeneration' => $plan['events'][9]['value'],
    'openBySource' => $plan['next']['open_by_source'],
    'persistentConnectionCount' => $plan['next']['persistent_shm_connection_count'],
];

assert($summary['blockedExclusiveStatus'] === 'busy');
assert($summary['blockedExclusiveOwners'] === ['wp-cron']);
assert($summary['readerOwnersAfterCronUnlock'] === ['wp-admin']);
assert($summary['staleShmDataVersion'] === true);
assert($summary['currentGeneration'] === 2);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
