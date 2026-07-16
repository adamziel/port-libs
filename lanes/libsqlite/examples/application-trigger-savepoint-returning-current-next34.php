<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSavepointStack.php';
require_once __DIR__ . '/../src/SQLiteSavepointTriggerRollbackPlan.php';

use PortLibs\LibSqlite\SQLiteSavepointTriggerRollbackPlan;

$rowsBeforeImport = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'level' => 0, 'autoload' => 'yes'],
];
$savepointRows = [
    ...$rowsBeforeImport,
    ['option_id' => 3, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$incoming = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$recursiveAuditTrigger = [
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 3],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
];
$rollbackTrigger = [
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'option_name', 'operator' => '=', 'value' => 'plugin_seed:child'],
    'rollback' => true,
];

$plan = SQLiteSavepointTriggerRollbackPlan::insertRows(
    $rowsBeforeImport,
    $savepointRows,
    $incoming,
    [$recursiveAuditTrigger, $rollbackTrigger],
    ['option_name'],
    'wp_plugin_import',
    [
        'returning' => ['name' => 'option_name', 'autoload' => 'autoload'],
        'wal_start_frame' => 4,
        'wal_frames' => [
            ['frame_index' => 5, 'page_number' => 3],
            ['frame_index' => 6, 'page_number' => 4, 'commit_frame' => true],
        ],
    ]
);

echo json_encode([
    'rolled_back' => $plan['rolled_back_to_savepoint'],
    'current_option_names' => array_column($plan['rows'], 'option_name'),
    'returning_rows' => $plan['returning_rows'],
    'attempted_returning_rows' => $plan['attempted_returning_rows'],
    'rollback_reason' => $plan['rollback_reason'],
    'rollback_to_wal_frame' => $plan['rollback_to_wal_frame'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
