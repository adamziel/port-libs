<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBulkImportSavepointPlan;

$plan = SQLiteBulkImportSavepointPlan::plan([
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
], [
    [
        'name' => 'core_urls',
        'rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
            ['option_name' => 'blogname', 'option_value' => 'Imported Site', 'autoload' => 'yes'],
        ],
    ],
    [
        'name' => 'plugin_batch',
        'rows' => [
            ['option_id' => 65, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:19:"plugin/plugin.php";}', 'autoload' => 'no'],
            ['option_id' => 131, 'option_name' => 'home', 'option_value' => 'duplicate home', 'autoload' => 'yes'],
        ],
        'on_conflict' => 'rollback',
    ],
], [
    'database_path' => '/tmp/wp-bulk-import.sqlite',
    'page_size' => 1024,
]);

echo json_encode([
    'application_path' => 'wp_options bulk import savepoints',
    'released_batches' => $plan['released_batches'],
    'rolled_back_batches' => $plan['rolled_back_batches'],
    'final_option_names' => $plan['final_option_names'],
    'dirty_pages' => $plan['dirty_pages'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
