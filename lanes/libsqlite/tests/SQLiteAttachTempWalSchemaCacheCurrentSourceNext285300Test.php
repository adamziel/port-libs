<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas285300 = [
    'main' => [
        'schema_cookie' => 285,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_comments', 'wp_users', 'wp_terms'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_postmeta_key', 'wp_comments_post', 'wp_users_login', 'wp_terms_slug'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 285, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 188,
        'tables' => ['wp_options', 'wp_import_batch', 'wp_theme_stage', 'wp_comment_stage', 'wp_term_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_theme_stage_stylesheet', 'wp_comment_stage_post', 'wp_term_stage_slug'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 76,
        'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup', 'wp_event_archive'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_rollup_day', 'wp_event_archive_action'],
        'file' => '/srv/wp/analytics-next285.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 280,
        'tables' => ['wp_audit_log', 'wp_audit_queue', 'wp_auditmeta', 'wp_audit_archive'],
        'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token', 'wp_auditmeta_key', 'wp_audit_archive_action'],
        'file' => '/srv/wp/audit-next285.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 7,
        'tables' => ['wp_job_queue', 'wp_job_attempts'],
        'indexes' => ['wp_job_queue_token', 'wp_job_attempts_job'],
        'file' => '/srv/wp/queue-next285.sqlite',
    ],
    'reports' => [
        'schema_cookie' => 13,
        'tables' => ['wp_report_cache', 'wp_report_runs'],
        'indexes' => ['wp_report_cache_key', 'wp_report_runs_started'],
        'file' => '/srv/wp/reports-next285.sqlite',
    ],
];

$statements285300 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_type_status WHERE post_type = ?', 'active' => true],
    ['name' => 'term-reader', 'sql' => 'SELECT term_id FROM main.wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'term-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_term_stage INDEXED BY wp_term_stage_slug WHERE slug = ?', 'active' => true],
    ['name' => 'analytics-archive-reader', 'sql' => 'SELECT event_id FROM analytics.wp_event_archive INDEXED BY wp_event_archive_action WHERE action = ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'audit-archive-reader', 'sql' => 'SELECT log_id FROM audit.wp_audit_archive INDEXED BY wp_audit_archive_action WHERE action = ?', 'active' => true],
    ['name' => 'queue-attempts-writer', 'sql' => 'UPDATE queue.wp_job_attempts INDEXED BY wp_job_attempts_job SET status = ? WHERE job_id = ?'],
    ['name' => 'reports-runs-reader', 'sql' => 'SELECT run_id FROM reports.wp_report_runs INDEXED BY wp_report_runs_started WHERE started_at > ?'],
    ['name' => 'posts-writer', 'sql' => 'UPDATE main.wp_posts INDEXED BY wp_posts_type_status SET post_status = ? WHERE post_type = ?'],
];

$plan285300 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas285300,
    $statements ?? $statements285300,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next285-300 combined batch expires current sources'] = static function (TestRunner $t) use ($plan285300): void {
    $result = $plan285300([
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

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][31]);
    $t->same(14, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'metrics', 'queue', 'reports'], $result['changed_schemas']);
    $t->same(300, $result['schema_cookies_next']['main']);
    $t->same(190, $result['schema_cookies_next']['temp']);
    $t->same(78, $result['schema_cookies_next']['analytics']);
    $t->same(281, $result['schema_cookies_next']['audit']);
    $t->same(292, $result['schema_cookies_next']['queue']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'metrics', 'queue'], $result['search_order_next']);
    $t->same(['main-posts-reader', 'term-stage-reader', 'audit-archive-reader'], $result['active_current_snapshot_statements']);
    $t->same(['analytics-meta-writer', 'queue-attempts-writer', 'posts-writer'], $result['write_statements_blocked_before_retry']);
    $t->same('main', $result['statements']['temp-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['term-stage-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['main-posts-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-archive-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['reports-runs-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next285-300 attach detach without referenced statements stays stable'] = static function (TestRunner $t) use ($plan285300): void {
    $result = $plan285300([
        ['op' => 'attach', 'schema' => 'archive', 'schema_cookie' => 3, 'tables' => ['wp_archive_posts'], 'indexes' => ['wp_archive_posts_type'], 'file' => '/srv/wp/archive-next285.sqlite'],
        ['op' => 'detach', 'schema' => 'archive'],
        ['op' => 'attach', 'schema' => 'metrics', 'schema_cookie' => 4, 'tables' => ['wp_metric_cache'], 'indexes' => ['wp_metric_cache_key'], 'file' => '/srv/wp/metrics-next286.sqlite'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(['metrics'], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'metrics', 'queue', 'reports'], $result['search_order_next']);
};

return $tests;
