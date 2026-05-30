<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonExtract.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteTenantJsonWalCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteTenantJsonWalCurrentNextPlan;

$currentRows = [
    ['scope' => 'tenant', 'tenant_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes'],
    ['scope' => 'tenant', 'tenant_id' => 2, 'key_name' => 'siteurl', 'key_value' => 'https://sub.example.test', 'load_policy' => 'yes'],
    ['scope' => 'global', 'group_id' => 1, 'key_name' => 'site_name', 'key_value' => 'Example Network'],
];

$tenantJson = json_encode(['rows' => [
    ['key_name' => 'plugin_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'yes'],
]], JSON_THROW_ON_ERROR);
$globalJson = json_encode(['rows' => [
    ['key_name' => 'global_plugins', 'key_value' => '["cache/cache.php"]'],
]], JSON_THROW_ON_ERROR);

$plan = SQLiteTenantJsonWalCurrentNextPlan::plan($currentRows, [
    [
        'scope' => 'tenant',
        'tenant_id' => 2,
        'json' => $tenantJson,
        'path' => '$.rows',
    ],
    [
        'scope' => 'global',
        'group_id' => 1,
        'json' => $globalJson,
        'path' => '$.rows',
    ],
    [
        'scope' => 'global',
        'group_id' => 1,
        'json' => '{"rows":[',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/app-global-json-current-next.sqlite',
    'page_size' => 1024,
    'first_frame' => 40,
]);

echo json_encode([
    'scenario' => 'application-tenant-json-wal-current-next',
    'planned' => [
        'status' => $plan['status'],
        'releasedBatches' => $plan['released_batches'],
        'rolledBackBatches' => $plan['rolled_back_batches'],
        'walCurrentFrame' => $plan['wal']['current_frame'],
        'walFrameCount' => $plan['wal']['frame_count'],
        'tables' => array_values(array_unique(array_column($plan['batches'], 'table'))),
        'nextRowsVisible' => $plan['reader_visibility']['next_rows_visible'],
    ],
    'applicationUse' => 'A tenant settings JSON import can stage tenant and global rows through WAL current/next frame accounting while malformed global payloads roll back without advancing the reader-visible WAL frame.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
