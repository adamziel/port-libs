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
    ['site_id' => 1, 'blog_id' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['site_id' => 1, 'blog_id' => 2, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://site2.example.test', 'autoload' => 'yes'],
    ['scope' => 'network', 'site_id' => 1, 'meta_id' => 1, 'meta_key' => 'site_name', 'meta_value' => 'Network'],
];

$rows = static fn (array $rows): string => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);

$plan = SQLiteTenantJsonWalImportPlan::plan($currentRows, [
    [
        'name' => 'current_blog_import',
        'blog_id' => 1,
        'json' => $rows([
            ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
        ]),
    ],
    [
        'name' => 'next_blog_preview',
        'blog_id' => 2,
        'release' => false,
        'json' => $rows([
            ['option_name' => 'theme_mods_preview', 'option_value' => '{"palette":["blue"]}', 'autoload' => 'no'],
        ]),
    ],
    [
        'name' => 'network_meta_import',
        'scope' => 'network',
        'json' => $rows([
            ['meta_key' => 'site_settings', 'meta_value' => '{"lang":"en"}'],
        ]),
    ],
], ['database_path' => '/tmp/wp-multisite-json-wal-current-next54.sqlite']);

echo json_encode([
    'status' => $plan['status'],
    'released_batches' => $plan['released_batches'],
    'wal_frame_count' => $plan['wal']['frame_count'],
    'final_keys' => $plan['final_keys'],
    'released_keys' => $plan['released_keys'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
