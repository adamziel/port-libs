<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$baseSchemas = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_status_date'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['wp_options_stage'],
        'indexes' => ['wp_options_stage_name'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 9,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$baseStatements = [
    ['name' => 'main-indexed-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE autoload = ?'],
    ['name' => 'temp-indexed-writer', 'sql' => 'UPDATE temp.wp_options_stage INDEXED BY wp_options_stage_name SET option_value = ? WHERE option_name = ?'],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT option_name FROM archive.wp_options AS ao INDEXED BY wp_archive_option_name WHERE option_name GLOB ?'],
    ['name' => 'future-main-index-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_future_name WHERE option_name = ?'],
];

$baseEvents = [
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_options_stage_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($baseSchemas, $baseStatements, $baseEvents);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['schema_cookies_current']['main'] === 41);
    assert($plan['schema_cookies_next']['main'] === 43);
    assert($plan['expired_statements'] === [
        'main-indexed-reader',
        'temp-indexed-writer',
        'archive-indexed-reader',
        'future-main-index-reader',
    ]);
    assert($plan['statements']['main-indexed-reader']['index_transitions'][0]['current_found'] === true);
    assert($plan['statements']['main-indexed-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['future-main-index-reader']['index_transitions'][0]['current_found'] === false);
    assert($plan['statements']['future-main-index-reader']['index_transitions'][0]['next_found'] === true);

    echo "wordpress-attach-temp-wal-schema-cache-indexed-by-expiry self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
