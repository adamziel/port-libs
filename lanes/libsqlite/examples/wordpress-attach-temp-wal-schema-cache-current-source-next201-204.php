<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 201, 'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'], 'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_termmeta_key']],
    'temp' => ['schema_cookie' => 104, 'tables' => ['wp_import_stage', 'wp_options'], 'indexes' => ['wp_import_stage_token', 'wp_temp_options_name'], 'temp' => true],
    'archive' => ['schema_cookie' => 200, 'tables' => ['wp_comments', 'wp_commentmeta'], 'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'], 'file' => '/srv/wp/archive-next201.sqlite'],
];

$statements = [
    ['name' => 'active-main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_date WHERE post_date > ?', 'active' => true],
    ['name' => 'temp-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?'],
    ['name' => 'archive-commentmeta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'unqualified-comments-reader', 'sql' => 'SELECT comment_ID FROM wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'attach', 'schema' => 'analytics', 'schema_cookie' => 1, 'tables' => ['wp_events'], 'indexes' => ['wp_events_name'], 'file' => '/srv/wp/analytics.sqlite'],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_commentmeta_key'],
    ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_commentmeta_key_v2'],
    ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_posts', 'to' => 'wp_posts_next204'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 204, 'table' => 'wp_postmeta', 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 5);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive', 'analytics']);
    assert($plan['schema_cookies_next']['main'] === 202);
    assert($plan['schema_cookies_next']['temp'] === 105);
    assert($plan['schema_cookies_next']['archive'] === 202);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'archive']);
    assert(in_array('active-main-posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('archive-comments-writer', $plan['write_statements_blocked_before_retry'], true));

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next201-204 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
