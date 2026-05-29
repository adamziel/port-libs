<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 269, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_comments', 'wp_users'], 'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_postmeta_key', 'wp_comments_post', 'wp_users_login'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 269, 'commit' => true]]],
    'temp' => ['schema_cookie' => 184, 'tables' => ['wp_options', 'wp_import_batch', 'wp_theme_stage', 'wp_comment_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_theme_stage_stylesheet', 'wp_comment_stage_post'], 'temp' => true],
    'analytics' => ['schema_cookie' => 73, 'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup'], 'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_rollup_day'], 'file' => '/srv/wp/analytics-next269.sqlite'],
    'audit' => ['schema_cookie' => 53, 'tables' => ['wp_audit_log', 'wp_audit_queue', 'wp_auditmeta'], 'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token', 'wp_auditmeta_key'], 'file' => '/srv/wp/audit-next269.sqlite'],
    'cache' => ['schema_cookie' => 29, 'tables' => ['wp_object_cache', 'wp_transient_cache'], 'indexes' => ['wp_object_cache_key', 'wp_transient_cache_timeout'], 'file' => '/srv/wp/cache-next269.sqlite'],
    'search' => ['schema_cookie' => 23, 'tables' => ['wp_search_cache'], 'indexes' => ['wp_search_cache_key'], 'file' => '/srv/wp/search-next269.sqlite'],
];

$statements = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_type_status WHERE post_type = ?', 'active' => true],
    ['name' => 'postmeta-writer', 'sql' => 'UPDATE main.wp_postmeta INDEXED BY wp_postmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'comments-reader', 'sql' => 'SELECT comment_ID FROM main.wp_comments INDEXED BY wp_comments_post WHERE comment_post_ID = ?', 'active' => true],
    ['name' => 'import-batch-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_batch INDEXED BY wp_import_batch_token WHERE token = ?', 'active' => true],
    ['name' => 'comment-stage-writer', 'sql' => 'UPDATE temp.wp_comment_stage INDEXED BY wp_comment_stage_post SET payload = ? WHERE post_id = ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'audit-queue-reader', 'sql' => 'SELECT rowid FROM audit.wp_audit_queue INDEXED BY wp_audit_queue_token WHERE token = ?', 'active' => true],
    ['name' => 'auditmeta-writer', 'sql' => 'UPDATE audit.wp_auditmeta INDEXED BY wp_auditmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'cache-reader', 'sql' => 'SELECT cache_value FROM cache.wp_object_cache INDEXED BY wp_object_cache_key WHERE cache_key = ?'],
    ['name' => 'search-reader', 'sql' => 'SELECT cache_value FROM search.wp_search_cache INDEXED BY wp_search_cache_key WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 270, 'table' => 'wp_links', 'indexes' => ['wp_links_visible'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_type_status', 'to' => 'wp_posts_type_status_next271'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_eventmeta'],
    ['op' => 'attach', 'schema' => 'reports', 'schema_cookie' => 11, 'tables' => ['wp_report_cache'], 'indexes' => ['wp_report_cache_key'], 'file' => '/srv/wp/reports-next273.sqlite'],
    ['op' => 'detach', 'schema' => 'search'],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_audit_queue_token', 'to' => 'wp_audit_queue_token_next275'],
    ['op' => 'drop_table', 'schema' => 'cache', 'table' => 'wp_transient_cache'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 276, 'table' => 'wp_event_archive', 'commit' => false],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_batch'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_comments_post', 'to' => 'wp_comments_post_next278'],
    ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 279, 'table' => 'wp_audit_archive', 'indexes' => ['wp_audit_archive_action'], 'commit' => true],
    ['op' => 'attach', 'schema' => 'queue', 'schema_cookie' => 5, 'tables' => ['wp_job_queue'], 'indexes' => ['wp_job_queue_token'], 'file' => '/srv/wp/queue-next280.sqlite'],
    ['op' => 'detach', 'schema' => 'cache'],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_comment_stage'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 284, 'table' => 'wp_termmeta', 'indexes' => ['wp_termmeta_key'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 15);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'audit', 'cache', 'queue', 'reports', 'search']);
    assert($plan['schema_cookies_next']['main'] === 284);
    assert($plan['schema_cookies_next']['temp'] === 187);
    assert($plan['schema_cookies_next']['audit'] === 279);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'audit', 'queue', 'reports']);
    assert(in_array('main-posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('comments-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('import-batch-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('audit-queue-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('postmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert(in_array('comment-stage-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['search-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['temp-options-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next269-284 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
