<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 90,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 91, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 30,
        'tables' => ['wp_options'],
        'indexes' => ['wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 50,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements = [
    ['name' => 'temp-shadow-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-qualified-reader', 'sql' => 'SELECT post_title FROM main.wp_posts WHERE post_type = ?'],
    ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'main-indexed-writer', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_posts', 'to' => 'wp_posts_next138'],
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_terms_slug_next140'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext137140($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next137-140');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next137');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['statements']['temp-shadow-reader']['schema_transitions'][0]['next_schema'] === 'main');
    assert($plan['statements']['main-qualified-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['main-indexed-writer']['index_transitions'][0]['next_found'] === false);
    assert($plan['active_current_snapshot_statements'] === ['archive-active-reader']);
    assert($plan['write_statements_blocked_before_retry'] === ['main-indexed-writer']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next137-140 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
