<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 157,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta'],
        'indexes' => ['wp_options_autoload_name', 'wp_postmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 57,
        'tables' => ['wp_cache_queue', 'wp_postmeta'],
        'indexes' => ['wp_temp_postmeta_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 87,
        'tables' => ['wp_options_archive', 'wp_posts_archive'],
        'indexes' => ['wp_archive_posts_date'],
        'file' => '/srv/wp/archive-next157.sqlite',
    ],
];

$statements = [
    ['name' => 'shadow-postmeta-reader', 'sql' => 'SELECT meta_value FROM wp_postmeta WHERE post_id = ?'],
    ['name' => 'archive-posts-writer', 'sql' => 'UPDATE archive.wp_posts_archive SET post_title = ? WHERE ID = ?'],
    ['name' => 'network-options-reader', 'sql' => 'SELECT option_value FROM network.wp_options WHERE option_name = ?'],
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?', 'active' => true],
];

$events = [
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_postmeta'],
    ['op' => 'rename_table', 'schema' => 'archive', 'from' => 'wp_posts_archive', 'to' => 'wp_posts_archive_2026'],
    ['op' => 'attach', 'schema' => 'network', 'schema_cookie' => 159, 'tables' => ['wp_options'], 'indexes' => ['wp_options_name'], 'file' => '/srv/wp/network159.sqlite'],
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext157160($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next157-160');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next157');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive', 'network']);
    assert($plan['statements']['shadow-postmeta-reader']['schema_transitions'][0]['next_schema'] === 'main');
    assert($plan['statements']['archive-posts-writer']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['network-options-reader']['schema_transitions'][0]['next_schema'] === 'network');
    assert($plan['statements']['active-options-reader']['index_transitions'][0]['next_found'] === false);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next157-160 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
