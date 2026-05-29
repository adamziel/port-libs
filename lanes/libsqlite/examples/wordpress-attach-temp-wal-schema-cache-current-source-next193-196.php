<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 193, 'tables' => ['wp_options', 'wp_termmeta'], 'indexes' => ['wp_options_name', 'wp_termmeta_key']],
    'temp' => ['schema_cookie' => 96, 'tables' => ['wp_options', 'wp_import_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_stage_token'], 'temp' => true],
    'archive' => ['schema_cookie' => 192, 'tables' => ['wp_comments', 'wp_commentmeta'], 'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'], 'file' => '/srv/wp/archive-next193.sqlite'],
];

$statements = [
    ['name' => 'stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'termmeta-reader', 'sql' => 'SELECT meta_value FROM main.wp_termmeta INDEXED BY wp_termmeta_key WHERE term_id = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_stage_token', 'to' => 'wp_import_stage_token_next193'],
    ['op' => 'attach', 'schema' => 'reporting', 'schema_cookie' => 194, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_key']],
    ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_comments_status_next195'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 196, 'table' => 'wp_termmeta', 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 3);
    assert($plan['changed_schemas'] === ['temp', 'archive', 'reporting']);
    assert($plan['schema_cookies_next']['temp'] === 97);
    assert($plan['schema_cookies_next']['archive'] === 193);
    assert($plan['statements']['stage-reader']['index_transitions'][0]['next_found'] === false);
    assert($plan['search_order_next'] === ['temp', 'main', 'archive', 'reporting']);
    assert(in_array('archive-comments-writer', $plan['write_statements_blocked_before_retry'], true));

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next193-196 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
