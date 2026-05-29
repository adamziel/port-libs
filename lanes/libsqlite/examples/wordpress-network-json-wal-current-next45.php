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
require __DIR__ . '/../src/SQLiteWordPressNetworkJsonWalCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteWordPressNetworkJsonWalCurrentNextPlan;

$currentRows = [
    ['scope' => 'blog', 'blog_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['scope' => 'blog', 'blog_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://sub.example.test', 'autoload' => 'yes'],
    ['scope' => 'network', 'site_id' => 1, 'meta_key' => 'site_name', 'meta_value' => 'Example Network'],
];

$blogJson = json_encode(['rows' => [
    ['option_name' => 'plugin_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'yes'],
]], JSON_THROW_ON_ERROR);
$networkJson = json_encode(['rows' => [
    ['meta_key' => 'network_plugins', 'meta_value' => '["akismet/akismet.php"]'],
]], JSON_THROW_ON_ERROR);

$plan = SQLiteWordPressNetworkJsonWalCurrentNextPlan::plan($currentRows, [
    [
        'scope' => 'blog',
        'blog_id' => 2,
        'json' => $blogJson,
        'path' => '$.rows',
    ],
    [
        'scope' => 'network',
        'site_id' => 1,
        'json' => $networkJson,
        'path' => '$.rows',
    ],
    [
        'scope' => 'network',
        'site_id' => 1,
        'json' => '{"rows":[',
        'path' => '$.rows',
    ],
], [
    'database_path' => '/tmp/wp-network-json-current-next45.sqlite',
    'page_size' => 1024,
    'first_frame' => 40,
]);

echo json_encode([
    'scenario' => 'wordpress-network-json-wal-current-next45',
    'planned' => [
        'status' => $plan['status'],
        'releasedBatches' => $plan['released_batches'],
        'rolledBackBatches' => $plan['rolled_back_batches'],
        'walCurrentFrame' => $plan['wal']['current_frame'],
        'walFrameCount' => $plan['wal']['frame_count'],
        'tables' => array_values(array_unique(array_column($plan['batches'], 'table'))),
        'nextRowsVisible' => $plan['reader_visibility']['next_rows_visible'],
    ],
    'wordpressUse' => 'A multisite wp_options/wp_sitemeta JSON import can stage blog and network rows through WAL current/next frame accounting while malformed network payloads roll back without advancing the reader-visible WAL frame.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
