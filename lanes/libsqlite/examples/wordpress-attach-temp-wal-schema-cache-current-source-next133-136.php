<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 80,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 81, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 20,
        'tables' => ['wp_options_stage'],
        'indexes' => ['wp_options_stage_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 40,
        'tables' => ['wp_options', 'wp_terms'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements = [
    ['name' => 'future-site-reader', 'sql' => 'SELECT option_value FROM site.wp_2_options WHERE option_name = ?'],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
    ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'main-indexed-write', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'attach', 'schema' => 'site', 'schema_cookie' => 7, 'tables' => ['wp_2_options'], 'indexes' => ['wp_2_options_name']],
    ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_archive_option_name', 'to' => 'wp_archive_option_name_next135'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 84, 'indexes' => ['wp_options_pending_name']],
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options_stage_shadow', 'commit' => false],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 3);
    assert($plan['changed_schemas'] === ['main', 'archive', 'site']);
    assert($plan['statements']['future-site-reader']['schema_transitions'][0]['next_schema'] === 'site');
    assert($plan['statements']['archive-indexed-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-active-reader']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert($plan['write_statements_blocked_before_retry'] === ['main-indexed-write']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next133-136 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
