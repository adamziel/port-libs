<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWordPressJsonSavepointSchemaCurrentPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
];

$plan = SQLiteWordPressJsonSavepointSchemaCurrentPlan::plan($currentRows, [
    [
        'name' => 'create_import_schema',
        'json' => '{"rows":[{"option_name":"plugin_schema_settings","option_value":"{\"enabled\":true}","autoload":"no"}]}',
        'path' => '$.rows',
        'schema_changes' => [[
            'type' => 'table',
            'name' => 'wp_import_batch',
            'tbl_name' => 'wp_import_batch',
            'rootpage' => 8,
            'sql' => 'CREATE TABLE wp_import_batch (id integer primary key, payload text)',
        ]],
    ],
    [
        'name' => 'reject_duplicate_schema',
        'json' => '{"rows":[{"option_name":"plugin_duplicate_settings","option_value":"{\"enabled\":false}","autoload":"no"}]}',
        'path' => '$.rows',
        'schema_changes' => [[
            'type' => 'table',
            'name' => 'wp_options',
            'tbl_name' => 'wp_options',
            'rootpage' => 9,
            'sql' => 'CREATE TABLE wp_options (id integer)',
        ]],
    ],
], [
    'database_path' => '/tmp/wp-json-savepoint-schema-current.sqlite',
    'schema_version' => 41,
    'data_version' => 300,
    'schema' => ['rows' => [
        ['type' => 'table', 'name' => 'wp_options', 'tbl_name' => 'wp_options', 'rootpage' => 2, 'sql' => 'CREATE TABLE wp_options (option_id integer primary key, option_name text unique, option_value text, autoload text)'],
        ['type' => 'index', 'name' => 'option_name', 'tbl_name' => 'wp_options', 'rootpage' => 3, 'sql' => 'CREATE UNIQUE INDEX option_name ON wp_options(option_name)'],
    ]],
]);

echo json_encode([
    'scenario' => 'wordpress-json-savepoint-schema-current',
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'schema_rejected_batches' => $plan['schema_rejected_batches'],
    'schema_version_before' => $plan['schema_version_before'],
    'schema_version' => $plan['schema_version'],
    'data_version_before' => $plan['data_version_before'],
    'data_version' => $plan['data_version'],
    'schema_names' => $plan['schema_names'],
    'released_schema_names' => $plan['released_schema_names'],
    'final_option_names' => $plan['final_option_names'],
    'released_option_names' => $plan['released_option_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
