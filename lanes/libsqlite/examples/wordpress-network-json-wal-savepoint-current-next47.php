<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteWordPressNetworkJsonWalSavepointPlan;

$sites = [
    [
        'blog_id' => 1,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://main.old', 'autoload' => 'yes'],
        ],
        'json_imports' => [
            [
                'name' => 'plugin_settings',
                'json' => '{"rows":[{"option_name":"main_plugin_settings","option_value":"{\"enabled\":true}","autoload":"yes"}]}',
                'path' => '$.rows',
            ],
            [
                'name' => 'bad_payload',
                'json' => '{"rows":[',
                'path' => '$.rows',
            ],
        ],
    ],
    [
        'blog_id' => 2,
        'current_rows' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://child.old', 'autoload' => 'yes'],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://child.old', 'autoload' => 'yes'],
        ],
        'json_imports' => [
            [
                'name' => 'plugin_settings',
                'json' => '{"rows":[{"option_name":"child_plugin_settings","option_value":"{\"enabled\":false}","autoload":"no"}]}',
                'path' => '$.rows',
            ],
        ],
    ],
];

$plan = SQLiteWordPressNetworkJsonWalSavepointPlan::plan($sites, [
    'database_path' => '/tmp/wp-network-json-current-next47.sqlite',
    'page_size' => 1024,
    'global_json_imports' => [
        [
            'name' => 'network_flags',
            'json' => '{"rows":[{"option_name":"registration","option_value":"none","autoload":"no"}]}',
            'path' => '$.rows',
        ],
    ],
]);

echo json_encode([
    'status' => $plan['status'],
    'released_sites' => $plan['released_sites'],
    'rolled_back_sites' => $plan['rolled_back_sites'],
    'tables' => array_keys($plan['final_rows_by_table']),
    'dirty_pages' => $plan['dirty_pages'],
    'network_wal_frame_count' => $plan['network_wal']['frame_count'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
