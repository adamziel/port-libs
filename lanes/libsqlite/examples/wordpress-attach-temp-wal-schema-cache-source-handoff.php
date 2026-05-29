<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 149,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 49,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 79,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements = [
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-posts-active-reader', 'sql' => 'SELECT post_title FROM main.wp_posts WHERE ID = ?', 'active' => true],
    ['name' => 'temp-indexed-reader', 'sql' => 'SELECT payload FROM temp.wp_import_queue INDEXED BY wp_import_queue_key WHERE import_key = ?'],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_posts'],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_queue_key', 'to' => 'wp_import_queue_key_rebuilt'],
    ['op' => 'detach', 'schema' => 'archive'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['statements']['options-reader']['schema_transitions'][0]['next_schema'] === 'temp');
    assert($plan['statements']['main-posts-active-reader']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert($plan['statements']['temp-indexed-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['write_statements_blocked_before_retry'] === ['archive-writer']);

    echo "wordpress-attach-temp-wal-schema-cache-source-handoff self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
