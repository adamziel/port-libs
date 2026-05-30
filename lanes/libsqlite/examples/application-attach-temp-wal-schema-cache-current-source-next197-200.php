<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 197, 'tables' => ['wp_options', 'wp_termmeta'], 'indexes' => ['wp_options_name', 'wp_termmeta_key']],
    'temp' => ['schema_cookie' => 100, 'tables' => ['wp_options', 'wp_import_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_stage_token'], 'temp' => true],
    'archive' => ['schema_cookie' => 196, 'tables' => ['wp_comments', 'wp_commentmeta'], 'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'], 'file' => '/srv/wp/archive-next197.sqlite'],
];

$statements = [
    ['name' => 'active-main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?', 'active' => true],
    ['name' => 'temp-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?'],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_options_name', 'to' => 'wp_options_name_next197'],
    ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_plugin_options'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 200, 'table' => 'wp_termmeta', 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 201, 'table' => 'wp_posts', 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['main', 'temp', 'archive']);
    assert($plan['schema_cookies_next']['main'] === 200);
    assert($plan['schema_cookies_next']['temp'] === 101);
    assert($plan['search_order_next'] === ['temp', 'main']);
    assert($plan['statements']['active-main-options-reader']['next_step_action'] === 'finish_current_source_then_sqlite_schema_on_reset');
    assert(in_array('archive-comments-writer', $plan['write_statements_blocked_before_retry'], true));

    echo "application-attach-temp-wal-schema-cache-current-source-next197-200 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
