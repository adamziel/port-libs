<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 50,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status_date'],
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 12,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
    ],
];

$statements = [
    ['name' => 'unqualified-options', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-active', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?', 'active' => true],
    ['name' => 'main-write-indexed', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_options_autoload_name', 'to' => 'wp_options_autoload_name_next'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext121124($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next121-124');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next121');
    assert($plan['statements']['unqualified-options']['schema_transitions'][0]['current_schema'] === 'main');
    assert($plan['statements']['unqualified-options']['schema_transitions'][0]['next_schema'] === 'temp');
    assert($plan['statements']['archive-active']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert($plan['statements']['main-write-indexed']['index_transitions'][0]['next_found'] === false);
    assert($plan['write_statements_blocked_before_retry'] === ['main-write-indexed']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next121-124 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
