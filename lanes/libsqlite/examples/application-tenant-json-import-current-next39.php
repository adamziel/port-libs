<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportSavepointPlan;

$plan = SQLiteJsonImportSavepointPlan::plan([
    [
        'tenant_id' => 1,
        'setting_id' => 101,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"modules":["core"]}',
        'load_policy' => 'yes',
        'page_number' => 8,
    ],
    [
        'tenant_id' => 2,
        'setting_id' => 201,
        'key_name' => 'plugin_settings',
        'key_value' => '{"enabled":false,"modules":["core"]}',
        'load_policy' => 'yes',
        'page_number' => 12,
    ],
], [
    [
        'statement' => 'site1_enable_plugin',
        'tenant_id' => 1,
        'key_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'site2_add_forms',
        'tenant_id' => 2,
        'key_name' => 'plugin_settings',
        'function' => 'json_insert',
        'path' => '$.modules[#]',
        'value' => 'forms',
        'wal_frame_index' => 2,
    ],
], [
    'transaction' => 'network_json_import',
    'savepoint' => 'current_next39',
]);

echo json_encode([
    'status' => $plan['status'],
    'applied_setting_keys' => array_column($plan['applied'], 'setting_key'),
    'committed_pages' => $plan['commit']['committed_page_numbers'],
    'rollback_pages' => $plan['rollback_to_savepoint']['restored_page_numbers'],
    'dependency' => 'sqlite-application-multitenant-json-import-current',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
