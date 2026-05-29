<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteFileUri.php';
require_once __DIR__ . '/../src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php';

use PortLibs\LibSqlite\SQLiteVfsShmFileControlLockCurrentSourcePlan;

$plan = SQLiteVfsShmFileControlLockCurrentSourcePlan::planShmUriFileControlLocks([
    'open(main, file://localhost/srv/www/wp-content/database/wp%20range.sqlite?mode=rw&cache=shared)',
    'open(shm, file://localhost/srv/www/wp-content/database/wp%20range.sqlite-shm?mode=rw&cache=shared)',
    ['op' => 'shmlock', 'lock' => 'read0', 'span' => 3, 'mode' => 'shared', 'connection' => 'wp-admin'],
    ['op' => 'shmlock', 'lock' => 'read2', 'span' => 2, 'mode' => 'exclusive', 'connection' => 'wp-cron'],
    ['op' => 'shmlock', 'lock' => 'read1', 'span' => 2, 'mode' => 'unlock', 'connection' => 'wp-admin'],
    ['op' => 'shmlock', 'lock' => 'read2', 'span' => 2, 'mode' => 'exclusive', 'connection' => 'wp-cron'],
    'source(main)',
    'file_control(persist_wal, on)',
    'source(shm)',
    'file_control(data_version)',
]);

$summary = [
    'scenario' => 'wordpress-vfs-shm-uri-lock-range-current-source-next131',
    'wordpressUse' => 'Model copied WordPress SQLite WAL shared-memory xShmLock ranges for multi-slot read-mark and checkpoint locks, preserving atomic conflict handling, URI current-source routing, and stale data-version checks without ext/sqlite.',
    'dependency' => 'vfs-shm-uri-filecontrol-lock-current-source-next131',
    'initialRangeLocks' => $plan['events'][2]['locks'],
    'blockedStatus' => $plan['events'][3]['status'],
    'blockedLocks' => array_keys($plan['events'][3]['blocking_locks']),
    'exclusiveAfterUnlock' => $plan['events'][5]['status'],
    'exclusiveLocks' => $plan['events'][5]['locks'],
    'staleDataVersion' => $plan['events'][9]['stale_current_source'],
    'finalLockCount' => $plan['next']['shm_lock_count'],
];

assert($summary['initialRangeLocks'] === ['read0', 'read1', 'read2']);
assert($summary['blockedStatus'] === 'busy');
assert($summary['blockedLocks'] === ['read2']);
assert($summary['exclusiveAfterUnlock'] === 'ok');
assert($summary['exclusiveLocks'] === ['read2', 'read3']);
assert($summary['staleDataVersion'] === true);
assert($summary['finalLockCount'] === 3);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
