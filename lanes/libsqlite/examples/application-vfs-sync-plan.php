<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsSyncPlan;

$database = '/srv/www/wp-content/database/.ht.sqlite';

$plans = [
    'rollbackCommitDelete' => SQLiteVfsSyncPlan::rollbackCommitSequence($database, 'full'),
    'rollbackCommitPersistPsow' => SQLiteVfsSyncPlan::rollbackCommitSequence($database, 'normal', true, true),
    'walFrameSync' => [
        SQLiteVfsSyncPlan::forPath($database . '-wal', 'wal', 'normal', true),
        SQLiteVfsSyncPlan::forPath(dirname($database), 'directory', 'normal', false, true),
    ],
    'readOnlyArchive' => [
        SQLiteVfsSyncPlan::forPath('/srv/www/wp-content/database/archive.sqlite', 'database', 'full', false, false, true),
    ],
];

echo json_encode([
    'scenario' => 'application-vfs-sync-plan',
    'applicationUse' => 'Plan SQLite VFS xSync flags for copied Application database, rollback-journal, WAL, and directory handles before native PHP file-handle writes are promoted to durable transaction commits without requiring ext/sqlite.',
    'plans' => array_map(static fn (array $steps): array => array_map(static fn (array $step): array => [
        'status' => $step['status'],
        'target' => $step['target'],
        'path' => $step['path'],
        'mode' => $step['mode'],
        'flags' => $step['flags'],
        'flagNames' => $step['flag_names'],
        'durable' => $step['durable'],
        'dataOnly' => $step['data_only'],
        'directory' => $step['directory'],
        'reason' => $step['reason'],
        'dependencies' => $step['dependencies'],
    ], $steps), $plans),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
