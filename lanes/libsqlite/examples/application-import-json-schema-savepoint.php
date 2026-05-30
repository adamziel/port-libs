<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteImportJsonSchemaSavepointPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'app_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'enabled_modules', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['setting_id' => 70, 'key_name' => 'ui_theme_old', 'key_value' => '{"color":"blue"}', 'load_policy' => 'no'],
];

$plan = SQLiteImportJsonSchemaSavepointPlan::plan($currentRows, [
    [
        'name' => 'schema_defaults',
        'json' => '{"rows":[{"name":"plugin_import_settings","value":"{\"enabled\":true}"}]}',
        'path' => '$.rows',
    ],
    [
        'name' => 'schema_reject',
        'json' => '{"rows":[{"key_name":"widget_recent","key_value":"not-json"}]}',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/app-import-json-schema-savepoint.sqlite',
    'page_size' => 1024,
    'journal_mode' => 'wal',
    'sync_mode' => 'normal',
]);

echo json_encode([
    'scenario' => 'application-import-json-schema-savepoint',
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'schema_rejected_batches' => $plan['schema_rejected_batches'],
    'final_key_names' => $plan['final_key_names'],
    'released_key_names' => $plan['released_key_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'first_next_savepoint' => $plan['batches'][0]['next_savepoint'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
