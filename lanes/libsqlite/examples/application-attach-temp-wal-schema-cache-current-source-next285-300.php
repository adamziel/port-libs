<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCachePlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas = [
    'main' => ['schema_cookie' => 285, 'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_comments', 'wp_users', 'wp_terms'], 'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_postmeta_key', 'wp_comments_post', 'wp_users_login', 'wp_terms_slug'], 'wal_frames' => [['page' => 1, 'schema_cookie' => 285, 'commit' => true]]],
    'temp' => ['schema_cookie' => 188, 'tables' => ['wp_options', 'wp_import_batch', 'wp_theme_stage', 'wp_comment_stage', 'wp_term_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_theme_stage_stylesheet', 'wp_comment_stage_post', 'wp_term_stage_slug'], 'temp' => true],
    'analytics' => ['schema_cookie' => 76, 'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup', 'wp_event_archive'], 'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_rollup_day', 'wp_event_archive_action'], 'file' => '/srv/wp/analytics-next285.sqlite'],
    'audit' => ['schema_cookie' => 280, 'tables' => ['wp_audit_log', 'wp_audit_queue', 'wp_auditmeta', 'wp_audit_archive'], 'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token', 'wp_auditmeta_key', 'wp_audit_archive_action'], 'file' => '/srv/wp/audit-next285.sqlite'],
    'queue' => ['schema_cookie' => 7, 'tables' => ['wp_job_queue', 'wp_job_attempts'], 'indexes' => ['wp_job_queue_token', 'wp_job_attempts_job'], 'file' => '/srv/wp/queue-next285.sqlite'],
    'reports' => ['schema_cookie' => 13, 'tables' => ['wp_report_cache', 'wp_report_runs'], 'indexes' => ['wp_report_cache_key', 'wp_report_runs_started'], 'file' => '/srv/wp/reports-next285.sqlite'],
];

$statements = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_type_status WHERE post_type = ?', 'active' => true],
    ['name' => 'term-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_term_stage INDEXED BY wp_term_stage_slug WHERE slug = ?', 'active' => true],
    ['name' => 'analytics-archive-reader', 'sql' => 'SELECT event_id FROM analytics.wp_event_archive INDEXED BY wp_event_archive_action WHERE action = ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'audit-archive-reader', 'sql' => 'SELECT log_id FROM audit.wp_audit_archive INDEXED BY wp_audit_archive_action WHERE action = ?', 'active' => true],
    ['name' => 'queue-attempts-writer', 'sql' => 'UPDATE queue.wp_job_attempts INDEXED BY wp_job_attempts_job SET status = ? WHERE job_id = ?'],
    ['name' => 'reports-runs-reader', 'sql' => 'SELECT run_id FROM reports.wp_report_runs INDEXED BY wp_report_runs_started WHERE started_at > ?'],
    ['name' => 'posts-writer', 'sql' => 'UPDATE main.wp_posts INDEXED BY wp_posts_type_status SET post_status = ? WHERE post_type = ?'],
];

$plan = SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 286, 'table' => 'wp_term_relationships', 'indexes' => ['wp_term_relationships_object'], 'commit' => true],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_term_stage_slug', 'to' => 'wp_term_stage_slug_next287'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_archive'],
    ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 3, 'tables' => ['wp_archive_posts'], 'indexes' => ['wp_archive_posts_type'], 'file' => '/srv/wp/archive-next288.sqlite'],
    ['op' => 'detach', 'schema' => 'reports'],
    ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 291, 'table' => 'wp_job_locks', 'indexes' => ['wp_job_locks_token'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_type_status', 'to' => 'wp_posts_type_status_next293'],
    ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_audit_archive'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 295, 'table' => 'wp_event_rollup_hour', 'indexes' => ['wp_event_rollup_hour_key'], 'commit' => false],
    ['op' => 'attach', 'schema' => 'metrics', 'schema_cookie' => 4, 'tables' => ['wp_metric_cache'], 'indexes' => ['wp_metric_cache_key'], 'file' => '/srv/wp/metrics-next296.sqlite'],
    ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_attempts'],
    ['op' => 'rename_index', 'schema' => 'analytics', 'from' => 'wp_eventmeta_key', 'to' => 'wp_eventmeta_key_next298'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 300, 'table' => 'wp_term_taxonomy', 'indexes' => ['wp_term_taxonomy_taxonomy'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-consolidated');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert($plan['dependencies'][15] === 'sqlite-attach-temp-wal-schema-cache-consolidated');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-consolidated', $plan['dependencies'], true));
    assert($plan['event_count'] === 14);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'audit', 'metrics', 'queue', 'reports']);
    assert($plan['schema_cookies_next']['main'] === 300);
    assert($plan['schema_cookies_next']['temp'] === 190);
    assert($plan['schema_cookies_next']['queue'] === 292);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'audit', 'metrics', 'queue']);
    assert(in_array('main-posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('term-stage-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('audit-archive-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('analytics-meta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert(in_array('queue-attempts-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['reports-runs-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['temp-options-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "application-attach-temp-wal-schema-cache-current-source-next285-300 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
