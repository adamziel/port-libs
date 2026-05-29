<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 145,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 146, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 45,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 75,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements = [
    ['name' => 'report-reader', 'sql' => 'SELECT report_name FROM wp_reports WHERE report_id = ?'],
    ['name' => 'temp-active-reader', 'sql' => 'SELECT payload FROM temp.wp_import_queue WHERE import_key = ?', 'active' => true],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT name FROM archive.wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'attach', 'schema' => 'report', 'schema_cookie' => 145, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_name'], 'file' => '/srv/wp/report.sqlite'],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_queue'],
    ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_terms_slug', 'to' => 'wp_terms_slug_next147'],
    ['op' => 'detach', 'schema' => 'archive'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext145148($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next145-148');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next145');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['report', 'temp', 'archive']);
    assert($plan['statements']['report-reader']['schema_transitions'][0]['next_schema'] === 'report');
    assert($plan['statements']['temp-active-reader']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert($plan['statements']['archive-indexed-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['write_statements_blocked_before_retry'] === ['archive-writer']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next145-148 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
