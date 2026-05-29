<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => [
        'schema_cookie' => 153,
        'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'],
        'indexes' => ['wp_options_autoload_name', 'wp_termmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 53,
        'tables' => ['wp_import_queue', 'wp_options_shadow'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 83,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-next153.sqlite',
    ],
];

$statements = [
    ['name' => 'termmeta-reader', 'sql' => 'SELECT meta_value FROM wp_termmeta WHERE meta_key = ?'],
    ['name' => 'archive-terms-reader', 'sql' => 'SELECT slug FROM archive.wp_terms INDEXED BY wp_terms_slug WHERE term_id = ?', 'active' => true],
    ['name' => 'options-writer', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
    ['name' => 'new-site-reader', 'sql' => 'SELECT option_value FROM site.wp_options WHERE option_name = ?'],
];

$events = [
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_termmeta'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_terms_slug'],
    ['op' => 'attach', 'schema' => 'site', 'schema_cookie' => 155, 'tables' => ['wp_options'], 'indexes' => ['wp_options_name'], 'file' => '/srv/wp/site155.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 156, 'indexes' => ['wp_options_name_next156']],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, $events);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['main', 'archive', 'site']);
    assert($plan['statements']['termmeta-reader']['schema_transitions'][0]['next_found'] === false);
    assert($plan['statements']['archive-terms-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['statements']['new-site-reader']['schema_transitions'][0]['next_schema'] === 'site');
    assert($plan['write_statements_blocked_before_retry'] === ['options-writer']);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next153-156 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
