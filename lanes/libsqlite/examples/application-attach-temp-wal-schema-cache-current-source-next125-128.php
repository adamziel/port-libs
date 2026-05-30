<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 60,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 11,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 20,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-a.sqlite',
    ],
];

$statements = [
    ['name' => 'archive-old-index', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
    ['name' => 'archive-new-index', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_slug WHERE option_name = ?'],
    ['name' => 'temp-indexed-write', 'sql' => 'UPDATE temp.wp_import_queue INDEXED BY wp_import_queue_status SET status = ? WHERE option_name = ?'],
];

$events = [
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 1, 'tables' => ['wp_options'], 'indexes' => ['wp_archive_option_slug'], 'file' => '/srv/wp/archive-b.sqlite'],
    ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_import_queue_status'],
    ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_options', 'commit' => false],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 3);
    assert($plan['changed_schemas'] === ['temp', 'archive']);
    assert($plan['statements']['archive-old-index']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-new-index']['index_transitions'][0]['next_found'] === true);
    assert($plan['statements']['temp-indexed-write']['index_transitions'][0]['next_found'] === true);
    assert($plan['write_statements_blocked_before_retry'] === ['temp-indexed-write']);

    echo "application-attach-temp-wal-schema-cache-current-source-next125-128 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
