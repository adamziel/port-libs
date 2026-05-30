<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaMigrationTransactionPlan;

$plan = SQLiteSchemaMigrationTransactionPlan::plan('wp_options', [
    ['name' => 'option_id', 'type' => 'INTEGER', 'primary_key' => true],
    ['name' => 'option_name', 'type' => 'VARCHAR(191)', 'not_null' => true],
    ['name' => 'option_value', 'type' => 'LONGTEXT', 'not_null' => true, 'default' => ''],
    ['name' => 'autoload', 'type' => 'VARCHAR(20)', 'not_null' => true, 'default' => 'yes'],
], [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
], [
    'database_path' => '/tmp/wp-schema-migration.sqlite',
    'schema_version' => 42,
    'copy_expressions' => [
        'autoload' => "CASE WHEN autoload IN ('yes','auto','on') THEN 'yes' ELSE 'no' END",
    ],
    'indexes' => [
        'CREATE UNIQUE INDEX option_name ON wp_options(option_name)',
        'CREATE INDEX autoload ON wp_options(autoload)',
    ],
    'triggers' => [
        'CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT 1; END',
    ],
]);

echo json_encode([
    'application_path' => 'wp_options copy-table schema migration transaction',
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
