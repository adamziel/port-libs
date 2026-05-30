<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTenantImportSavepointPlan;

$plan = SQLiteTenantImportSavepointPlan::plan([
    [
        'tenant_id' => 1,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://main.old', 'load_policy' => 'yes'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://main.new', 'load_policy' => 'yes'],
                ['key_name' => 'blogname', 'key_value' => 'Main Import', 'load_policy' => 'yes'],
            ]],
        ],
    ],
    [
        'tenant_id' => 2,
        'current_rows' => [
            ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://child.old', 'load_policy' => 'yes'],
            ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://child.old', 'load_policy' => 'yes'],
        ],
        'batches' => [
            ['name' => 'urls', 'rows' => [
                ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://child.new', 'load_policy' => 'yes'],
                ['key_name' => 'blogdescription', 'key_value' => 'Child imported', 'load_policy' => 'no'],
            ]],
        ],
    ],
], [
    'database_path' => '/tmp/app-tenant-import.sqlite',
    'page_size' => 1024,
    'global_batches' => [
        ['name' => 'network_meta', 'rows' => [
            ['key_name' => 'site_admins', 'key_value' => 'a:1:{i:0;s:5:"admin";}', 'load_policy' => 'no'],
        ]],
    ],
]);

echo json_encode([
    'application_path' => 'tenant key-value import savepoints',
    'tables' => $plan['table_names'],
    'released_tenants' => $plan['released_tenants'],
    'rolled_back_tenants' => $plan['rolled_back_tenants'],
    'global_rows' => array_column($plan['final_rows_by_table']['app_tenant_settings'], 'key_name'),
    'dirty_pages' => $plan['dirty_pages'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
