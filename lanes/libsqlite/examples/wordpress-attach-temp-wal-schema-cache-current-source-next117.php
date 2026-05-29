<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
        ],
        'tables' => ['wp_options'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'archive' => [
        'schema_cookie' => 9,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
    ],
];

$statements = [
    ['name' => 'main-indexed-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE autoload = ?'],
    ['name' => 'future-index-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_future_name WHERE option_name = ?'],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name GLOB ?'],
];

$events = [
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext117($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next117');
    assert($plan['event_count'] === 3);
    assert($plan['schema_cookies_next']['main'] === 43);
    assert($plan['schema_cookies_next']['archive'] === 10);
    assert($plan['expired_statements'] === ['main-indexed-reader', 'future-index-reader', 'archive-indexed-reader']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next117 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
