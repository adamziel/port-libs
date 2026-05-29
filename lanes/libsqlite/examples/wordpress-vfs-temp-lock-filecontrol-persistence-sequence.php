<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVfsTempLockFileControlPersistence;

$plan = SQLiteVfsTempLockFileControlPersistence::tempLockFileControlPersistenceSequence(
    [
        ['op' => 'open', 'suffix' => 'stmt-journal', 'delete_on_close' => false],
        ['op' => 'filecontrol', 'control' => 'name_hint', 'value' => 'wp options import'],
        ['op' => 'filecontrol', 'control' => 'chunk_size', 'value' => 8192],
        ['op' => 'lock', 'value' => 'reserved'],
        ['op' => 'close'],
    ],
    [
        'temp_dir' => '/tmp/wp-cache',
        'connection_id' => 'wp import 76',
    ],
);

$summary = [
    'wordpressUse' => 'Track copied wp_options temp statement-journal xFileControl state and lock release across close/reopen boundaries without requiring ext/sqlite.',
    'status' => $plan['status'],
    'events' => array_column($plan['events'], 'status', 'op'),
    'persistentControlCount' => $plan['next']['persistent_control_count'],
    'persistentLockCount' => $plan['next']['persistent_lock_count'],
    'pendingDeleteCount' => $plan['next']['pending_delete_count'],
    'dependencies' => $plan['dependencies'],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
