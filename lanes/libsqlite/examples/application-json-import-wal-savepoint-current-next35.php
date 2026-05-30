<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportWalSavepointPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
    ['option_id' => 70, 'option_name' => 'theme_mods_old', 'option_value' => '{"color":"blue"}', 'autoload' => 'no'],
];

$plan = SQLiteJsonImportWalSavepointPlan::plan($currentRows, [
    [
        'name' => 'plugin_settings',
        'json' => '{"rows":[{"option_name":"plugin_settings","option_value":"{\"enabled\":true}","autoload":"yes"}]}',
        'path' => '$.rows',
    ],
    [
        'name' => 'bad_plugin_payload',
        'json' => '{"rows":[',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/wp-json-import-current-next35.sqlite',
    'page_size' => 1024,
    'journal_mode' => 'wal',
    'sync_mode' => 'normal',
]);

echo json_encode([
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'final_option_names' => $plan['final_option_names'],
    'wal_current_frame' => $plan['wal']['current_frame'],
    'wal_bytes' => $plan['wal']['bytes'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
