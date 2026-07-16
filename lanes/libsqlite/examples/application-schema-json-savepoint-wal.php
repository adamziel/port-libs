<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSchemaJsonSavepointWalPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'enabled_modules', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['setting_id' => 70, 'key_name' => 'module_profile_old', 'key_value' => '{"color":"blue"}', 'load_policy' => 'no'],
];

$plan = SQLiteSchemaJsonSavepointWalPlan::plan($currentRows, [
    [
        'name' => 'valid_module_schema',
        'json' => '{"rows":[{"key_name":"module_profile_modern","key_value":"{\"palette\":[\"blue\"]}","load_policy":"no"}]}',
        'path' => '$.rows',
    ],
    [
        'name' => 'invalid_component_schema',
        'json' => '{"rows":[{"key_name":"component_recent","key_value":"not-json","load_policy":"yes"}]}',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/app-schema-json-savepoint.sqlite',
    'page_size' => 1024,
    'journal_mode' => 'wal',
    'sync_mode' => 'normal',
]);

echo json_encode([
    'scenario' => 'application-schema-json-savepoint-wal',
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'schema_rejected_batches' => $plan['schema_rejected_batches'],
    'final_key_names' => $plan['final_key_names'],
    'released_key_names' => $plan['released_key_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
