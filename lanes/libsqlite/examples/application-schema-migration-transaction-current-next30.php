<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaMigrationTransactionPlan;

$plan = SQLiteSchemaMigrationTransactionPlan::plan('app_settings', [
    ['name' => 'setting_id', 'type' => 'INTEGER', 'primary_key' => true],
    ['name' => 'key_name', 'type' => 'VARCHAR(191)', 'not_null' => true],
    ['name' => 'key_value', 'type' => 'LONGTEXT', 'not_null' => true, 'default' => ''],
    ['name' => 'load_policy', 'type' => 'VARCHAR(20)', 'not_null' => true, 'default' => 'eager'],
], [
    ['setting_id' => 1, 'key_name' => 'site_url', 'key_value' => 'https://example.test', 'load_policy' => 'eager'],
    ['setting_id' => 2, 'key_name' => 'home_url', 'key_value' => 'https://example.test', 'load_policy' => 'eager'],
    ['setting_id' => 65, 'key_name' => 'active_modules', 'key_value' => '[]', 'load_policy' => 'lazy'],
], [
    'database_path' => '/tmp/app-schema-migration.sqlite',
    'schema_version' => 42,
    'copy_expressions' => [
        'load_policy' => "CASE WHEN load_policy IN ('eager','auto','on') THEN 'eager' ELSE 'lazy' END",
    ],
    'indexes' => [
        'CREATE UNIQUE INDEX key_name ON app_settings(key_name)',
        'CREATE INDEX load_policy ON app_settings(load_policy)',
    ],
    'triggers' => [
        'CREATE TRIGGER app_settings_ai AFTER INSERT ON app_settings BEGIN SELECT 1; END',
    ],
]);

echo json_encode([
    'application_path' => 'app_settings copy-table schema migration transaction',
    'begin_mode' => $plan['begin']['mode'],
    'row_count' => $plan['row_count'],
    'schema_version_after' => $plan['schema_version_after'],
    'copy_columns' => $plan['copy_columns'],
    'statement_ops' => array_column($plan['statements'], 'op'),
    'dirty_pages' => $plan['dirty_pages'],
    'sync_targets' => array_column($plan['sync_sequence'], 'target'),
    'rollback' => $plan['rollback'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
