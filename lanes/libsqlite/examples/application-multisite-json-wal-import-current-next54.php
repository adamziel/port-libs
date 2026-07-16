<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteTenantJsonWalImportPlan.php';

use PortLibs\LibSqlite\SQLiteTenantJsonWalImportPlan;

$currentRows = [
    ['group_id' => 1, 'tenant_id' => 1, 'setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['group_id' => 1, 'tenant_id' => 2, 'setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://site2.example.test', 'load_policy' => 'yes'],
    ['scope' => 'global', 'group_id' => 1, 'setting_id' => 1, 'key_name' => 'app_name', 'key_value' => 'Global'],
];

$rows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);

$plan = SQLiteTenantJsonWalImportPlan::plan($currentRows, [
    [
        'name' => 'current_tenant_import',
        'tenant_id' => 1,
        'json' => $rows([
            ['key_name' => 'module_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
        ]),
    ],
    [
        'name' => 'next_tenant_preview',
        'tenant_id' => 2,
        'release' => false,
        'json' => $rows([
            ['key_name' => 'module_profile_preview', 'key_value' => '{"palette":["blue"]}', 'load_policy' => 'no'],
        ]),
    ],
    [
        'name' => 'global_settings_import',
        'scope' => 'global',
        'json' => $rows([
            ['key_name' => 'system_settings', 'key_value' => '{"lang":"en"}'],
        ]),
    ],
], ['database_path' => '/tmp/app-tenant-json-wal-current-next54.sqlite']);

echo json_encode([
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'wal_frame_count' => $plan['wal']['frame_count'],
    'final_keys' => $plan['final_keys'],
    'released_keys' => $plan['released_keys'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
