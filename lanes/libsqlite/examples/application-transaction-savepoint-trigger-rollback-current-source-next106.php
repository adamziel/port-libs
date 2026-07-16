<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 2, 'option_name' => '_transient_timeout_feed', 'option_value' => '1700000000', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 3, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'revision' => 4],
    ['option_id' => 4, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'revision' => 5],
];

$plan = SQLiteTransactionSavepointTriggerRollbackCurrentSourceNextPlan::deleteRows(
    'wp_current_import',
    $rows,
    static fn (array $row): bool => str_starts_with((string) $row['option_name'], '_transient') || $row['option_name'] === 'home',
    [
        [
            'name' => 'wp_options_ad_audit_deleted',
            'timing' => 'after',
            'event' => 'delete',
            'action' => 'audit',
            'values' => ['name' => 'old.option_name'],
        ],
        [
            'name' => 'wp_options_ad_home_rollback',
            'timing' => 'after',
            'event' => 'delete',
            'action' => 'raise',
            'raise' => 'rollback',
            'when' => ['old.option_name', '=', 'home'],
            'reason' => 'home deletion aborts transaction',
        ],
    ],
);

echo json_encode([
    'status' => $plan['status'],
    'savepoint_preserved' => $plan['savepoint_preserved'],
    'current_source_names' => array_column($plan['current_source_rows'], 'option_name'),
    'next_source_names' => array_column($plan['next_source_rows'], 'option_name'),
    'attempted_changes' => $plan['attempted_changes'],
    'changes' => $plan['changes'],
    'rollback_reason' => $plan['rollback_reason'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
