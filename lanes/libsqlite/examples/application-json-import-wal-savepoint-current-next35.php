<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportWalSavepointPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'active_extensions', 'key_value' => '[]', 'load_policy' => 'yes'],
    ['setting_id' => 70, 'key_name' => 'theme_palette_old', 'key_value' => '{"color":"blue"}', 'load_policy' => 'no'],
];

$plan = SQLiteJsonImportWalSavepointPlan::plan($currentRows, [
    [
        'name' => 'plugin_settings',
        'json' => '{"rows":[{"key_name":"extension_settings","key_value":"{\"enabled\":true}","load_policy":"yes"}]}',
        'path' => '$.rows',
    ],
    [
        'name' => 'bad_plugin_payload',
        'json' => '{"rows":[',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/app-json-import-current-next35.sqlite',
    'page_size' => 1024,
    'journal_mode' => 'wal',
    'sync_mode' => 'normal',
]);

echo json_encode([
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'final_key_names' => $plan['final_key_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'wal_bytes' => $plan['wal']['bytes'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
