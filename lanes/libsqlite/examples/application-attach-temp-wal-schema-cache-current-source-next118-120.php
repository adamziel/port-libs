<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 99, 'commit' => false],
        ],
        'tables' => ['wp_options'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['wp_options_stage'],
        'indexes' => ['wp_options_stage_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 9,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
    ],
];

$statements = [
    ['name' => 'main-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?'],
    ['name' => 'temp-writer', 'sql' => 'UPDATE temp.wp_options_stage INDEXED BY wp_options_stage_name SET option_value = ? WHERE option_name = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name GLOB ?'],
];

$events = [
    ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_rolled_back', 'commit' => false],
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_temp_rolled_back', 'commit' => false],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['event_count'] === 1);
    assert($plan['schema_cookies_next']['main'] === 41);
    assert($plan['schema_cookies_next']['temp'] === 5);
    assert($plan['schema_cookies_next']['archive'] === 10);
    assert($plan['expired_statements'] === ['archive-reader']);
    assert($plan['stable_statements'] === ['main-reader', 'temp-writer']);

    echo "application-attach-temp-wal-schema-cache-current-source-next118-120 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
