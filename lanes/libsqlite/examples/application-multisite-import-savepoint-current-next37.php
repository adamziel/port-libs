<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteMultisiteImportSavepointPlan;

$plan = SQLiteMultisiteImportSavepointPlan::plan([
    [
        'blog_id' => 1,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.new', 'autoload' => 'yes'],
                ['option_name' => 'blogname', 'option_value' => 'Main Import', 'autoload' => 'yes'],
            ]],
        ],
    ],
    [
        'blog_id' => 2,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://child.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://child.old', 'autoload' => 'yes'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://child.new', 'autoload' => 'yes'],
                ['option_name' => 'blogdescription', 'option_value' => 'Child imported', 'autoload' => 'no'],
            ]],
        ],
    ],
], [
    'database_path' => '/tmp/wp-multisite-import.sqlite',
    'page_size' => 1024,
    'global_batches' => [
        ['name' => 'network_meta', 'rows' => [
            ['option_name' => 'site_admins', 'option_value' => 'a:1:{i:0;s:5:"admin";}', 'autoload' => 'no'],
        ]],
    ],
]);

echo json_encode([
    'application_path' => 'multisite wp_options import savepoints',
    'tables' => $plan['table_names'],
    'released_sites' => $plan['released_sites'],
    'rolled_back_sites' => $plan['rolled_back_sites'],
    'global_rows' => array_column($plan['final_rows_by_table']['wp_sitemeta'], 'option_name'),
    'dirty_pages' => $plan['dirty_pages'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
