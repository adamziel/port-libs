<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWordPressImportJsonSchemaSavepointCurrentNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$plan = SQLiteWordPressImportJsonSchemaSavepointCurrentNextPlan::plan($currentRows, [
    [
        'name' => 'schema_defaults',
        'json' => '{"rows":[{"name":"plugin_import_settings","value":"{\"enabled\":true}"}]}',
        'path' => '$.rows',
    ],
    [
        'name' => 'schema_reject',
        'json' => '{"rows":[{"option_name":"widget_recent","option_value":"not-json"}]}',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/wp-import-json-schema-savepoint-current-next53.sqlite',
    'page_size' => 1024,
    'journal_mode' => 'wal',
    'sync_mode' => 'normal',
]);

echo json_encode([
    'scenario' => 'wordpress-import-json-schema-savepoint-current-next53',
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'schema_rejected_batches' => $plan['schema_rejected_batches'],
    'final_option_names' => $plan['final_option_names'],
    'released_option_names' => $plan['released_option_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'first_next_savepoint' => $plan['batches'][0]['next_savepoint'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
