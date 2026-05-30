<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 261, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users'], 'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_postmeta_key', 'wp_users_login'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 261, 'commit' => true]]],
    'temp' => ['schema_cookie' => 168, 'tables' => ['wp_options', 'wp_import_batch', 'wp_theme_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_theme_stage_stylesheet'], 'temp' => true],
    'analytics' => ['schema_cookie' => 61, 'tables' => ['wp_events', 'wp_eventmeta'], 'indexes' => ['wp_events_name', 'wp_eventmeta_key'], 'file' => '/srv/wp/analytics-next261.sqlite'],
    'audit' => ['schema_cookie' => 41, 'tables' => ['wp_audit_log', 'wp_audit_queue'], 'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token'], 'file' => '/srv/wp/audit-next261.sqlite'],
    'cache' => ['schema_cookie' => 19, 'tables' => ['wp_object_cache'], 'indexes' => ['wp_object_cache_key'], 'file' => '/srv/wp/cache-next261.sqlite'],
];

$statements = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_type_status WHERE post_type = ?', 'active' => true],
    ['name' => 'postmeta-writer', 'sql' => 'UPDATE main.wp_postmeta INDEXED BY wp_postmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'users-reader', 'sql' => 'SELECT ID FROM main.wp_users INDEXED BY wp_users_login WHERE user_login = ?'],
    ['name' => 'import-batch-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_batch INDEXED BY wp_import_batch_token WHERE token = ?', 'active' => true],
    ['name' => 'theme-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_theme_stage INDEXED BY wp_theme_stage_stylesheet WHERE stylesheet = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'audit-log-reader', 'sql' => 'SELECT log_id FROM audit.wp_audit_log INDEXED BY wp_audit_log_action WHERE action = ?'],
    ['name' => 'audit-queue-reader', 'sql' => 'SELECT rowid FROM audit.wp_audit_queue INDEXED BY wp_audit_queue_token WHERE token = ?', 'active' => true],
    ['name' => 'cache-reader', 'sql' => 'SELECT cache_value FROM cache.wp_object_cache INDEXED BY wp_object_cache_key WHERE cache_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 262, 'table' => 'wp_links', 'indexes' => ['wp_links_visible'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_eventmeta'],
    ['op' => 'attach', 'schema' => 'reports', 'schema_cookie' => 7, 'tables' => ['wp_report_cache'], 'indexes' => ['wp_report_cache_key'], 'file' => '/srv/wp/reports-next264.sqlite'],
    ['op' => 'detach', 'schema' => 'cache'],
    ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_audit_queue_token', 'to' => 'wp_audit_queue_token_next266'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 267, 'table' => 'wp_transient_stage', 'commit' => false],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_batch'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_type_status', 'to' => 'wp_posts_type_status_next268'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 268, 'table' => 'wp_event_archive', 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][7] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'audit', 'cache', 'reports']);
    assert($plan['schema_cookies_next']['main'] === 263);
    assert($plan['schema_cookies_next']['temp'] === 170);
    assert($plan['schema_cookies_next']['analytics'] === 268);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'audit', 'reports']);
    assert(in_array('main-posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('import-batch-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('postmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert(in_array('analytics-meta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['cache-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['temp-options-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "application-attach-temp-wal-schema-cache-current-source-next261-268 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
