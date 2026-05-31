<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTenantJsonWalSavepointPlan;

$sites = [
    [
        'tenant_id' => 1,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
        ],
        'json_imports' => [
            [
                'name' => 'plugin_settings',
                'json' => '{"rows":[{"key_name":"main_plugin_settings","key_value":"{\"enabled\":true}","load_policy":"yes"}]}',
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
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://child.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://child.old', 'load_policy' => 'yes'],
        ],
        'json_imports' => [
            [
                'name' => 'plugin_settings',
                'json' => '{"rows":[{"key_name":"child_plugin_settings","key_value":"{\"enabled\":false}","load_policy":"no"}]}',
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
            'json' => '{"rows":[{"key_name":"registration","key_value":"none","load_policy":"no"}]}',
            'path' => '$.rows',
        ],
    ],
    'rollback_network_on_error' => true,
]);

echo json_encode([
    'status' => $plan['status'],
    'released_tenants' => $plan['released_tenants'],
    'rolled_back_tenants' => $plan['rolled_back_tenants'],
    'tables' => array_keys($plan['final_rows_by_table']),
    'dirty_pages' => $plan['dirty_pages'],
    'network_wal_frame_count' => $plan['network_wal']['frame_count'],
    'network_rollback' => $plan['network_rollback'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (in_array('--self-test', $argv, true)) {
    if ($plan['released_tenants'] !== [2] || $plan['rolled_back_tenants'] !== [1]) {
        fwrite(STDERR, "unexpected tenant release summary\n");
        exit(1);
    }
    if ($plan['network_wal']['frame_count'] !== 0 || $plan['network_rollback']['discarded_frame_count'] !== 3) {
        fwrite(STDERR, "unexpected tenant WAL summary\n");
        exit(1);
    }
    if (!in_array('sqlite-tenant-json-wal-savepoint', $plan['dependencies'], true)) {
        fwrite(STDERR, "missing tenant WAL dependency\n");
        exit(1);
    }

    fwrite(STDOUT, "application-tenant-json-wal-savepoint self-test passed\n");
}
