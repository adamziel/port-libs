<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 70,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 15,
        'tables' => ['wp_import_queue', 'wp_options_shadow'],
        'indexes' => ['wp_import_queue_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 30,
        'tables' => ['wp_options', 'wp_terms'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements = [
    ['name' => 'unqualified-options', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-options-indexed', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
    ['name' => 'active-archive-terms', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'main-options-write', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_options_shadow', 'to' => 'wp_options'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
    ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_terms'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 72, 'table' => 'wp_options'],
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options_shadow', 'commit' => false],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext129132($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next129-132');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next129');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['statements']['unqualified-options']['schema_transitions'][0]['next_schema'] === 'temp');
    assert($plan['statements']['archive-options-indexed']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['active-archive-terms']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert($plan['write_statements_blocked_before_retry'] === ['main-options-write']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next129-132 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
