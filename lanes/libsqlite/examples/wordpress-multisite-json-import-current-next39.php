<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWordPressJsonImportSavepointPlan;

$plan = SQLiteWordPressJsonImportSavepointPlan::plan([
    [
        'blog_id' => 1,
        'option_id' => 101,
        'option_name' => 'plugin_settings',
        'option_value' => '{"enabled":false,"modules":["core"]}',
        'autoload' => 'yes',
        'page_number' => 8,
    ],
    [
        'blog_id' => 2,
        'option_id' => 201,
        'option_name' => 'plugin_settings',
        'option_value' => '{"enabled":false,"modules":["core"]}',
        'autoload' => 'yes',
        'page_number' => 12,
    ],
], [
    [
        'statement' => 'site1_enable_plugin',
        'blog_id' => 1,
        'option_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'site2_add_forms',
        'blog_id' => 2,
        'option_name' => 'plugin_settings',
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
    'applied_option_keys' => array_column($plan['applied'], 'option_key'),
    'committed_pages' => $plan['commit']['committed_page_numbers'],
    'rollback_pages' => $plan['rollback_to_savepoint']['restored_page_numbers'],
    'dependency' => 'sqlite-wordpress-multisite-json-import-current',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
