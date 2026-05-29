<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWordPressSchemaJsonSavepointWalPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$plan = SQLiteWordPressSchemaJsonSavepointWalPlan::plan($currentRows, [
    [
        'name' => 'valid_theme_schema',
        'json' => '{"rows":[{"option_name":"theme_mods_modern","option_value":"{\"palette\":[\"blue\"]}","autoload":"no"}]}',
        'path' => '$.rows',
    ],
    [
        'name' => 'invalid_widget_schema',
        'json' => '{"rows":[{"option_name":"widget_recent","option_value":"not-json","autoload":"yes"}]}',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/wp-schema-json-savepoint.sqlite',
    'page_size' => 1024,
    'journal_mode' => 'wal',
    'sync_mode' => 'normal',
]);

echo json_encode([
    'scenario' => 'wordpress-schema-json-savepoint-wal',
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'schema_rejected_batches' => $plan['schema_rejected_batches'],
    'final_option_names' => $plan['final_option_names'],
    'released_option_names' => $plan['released_option_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
