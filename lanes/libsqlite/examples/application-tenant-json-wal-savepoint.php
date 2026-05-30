<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTenantJsonWalSavepointPlan;

$sites = [
    [
        'tenant_id' => 1,
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
        'tenant_id' => 2,
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

$plan = SQLiteTenantJsonWalSavepointPlan::plan($sites, [
    'database_path' => '/tmp/sqlite-tenant-json-current.sqlite',
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
    'released_tenants' => $plan['released_tenants'],
    'rolled_back_tenants' => $plan['rolled_back_tenants'],
    'tables' => array_keys($plan['final_rows_by_table']),
    'dirty_pages' => $plan['dirty_pages'],
    'network_wal_frame_count' => $plan['network_wal']['frame_count'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (in_array('--self-test', $argv, true)) {
    if ($plan['released_tenants'] !== [2] || $plan['rolled_back_tenants'] !== [1]) {
        fwrite(STDERR, "unexpected tenant release summary\n");
        exit(1);
    }
    if ($plan['network_wal']['frame_count'] !== 3 || !in_array('sqlite-tenant-json-wal-savepoint', $plan['dependencies'], true)) {
        fwrite(STDERR, "unexpected tenant WAL summary\n");
        exit(1);
    }

    fwrite(STDOUT, "application-tenant-json-wal-savepoint self-test passed\n");
}
