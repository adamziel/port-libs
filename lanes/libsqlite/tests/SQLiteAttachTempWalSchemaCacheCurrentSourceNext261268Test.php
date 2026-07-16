<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas261268 = [
    'main' => [
        'schema_cookie' => 261,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_users'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_postmeta_key', 'wp_users_login'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 261, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 168,
        'tables' => ['wp_options', 'wp_import_batch', 'wp_theme_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_theme_stage_stylesheet'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 61,
        'tables' => ['wp_events', 'wp_eventmeta'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key'],
        'file' => '/srv/wp/analytics-next261.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 41,
        'tables' => ['wp_audit_log', 'wp_audit_queue'],
        'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token'],
        'file' => '/srv/wp/audit-next261.sqlite',
    ],
    'cache' => [
        'schema_cookie' => 19,
        'tables' => ['wp_object_cache'],
        'indexes' => ['wp_object_cache_key'],
        'file' => '/srv/wp/cache-next261.sqlite',
    ],
];

$statements261268 = [
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

$plan261268 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas261268,
    $statements ?? $statements261268,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next261 committed postmeta wal expires main writers'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 262, 'table' => 'wp_links', 'indexes' => ['wp_links_visible'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][7]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][15]);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(262, $result['schema_cookies_next']['main']);
    $t->same(['main-posts-reader', 'postmeta-writer', 'users-reader'], $result['expired_statements']);
    $t->same(['postmeta-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next262 temp option drop reveals main fallback'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(['temp-options-reader', 'import-batch-reader', 'theme-stage-reader'], $result['expired_statements']);
    $t->same('main', $result['statements']['temp-options-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next263 analytics meta drop blocks attached writer'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_eventmeta'],
    ]);

    $t->same(['analytics'], $result['changed_schemas']);
    $t->same(['analytics-events-reader', 'analytics-meta-writer'], $result['expired_statements']);
    $t->same(['analytics-meta-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['analytics-meta-writer']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next264 attach reports schema leaves existing cache stable'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'attach', 'schema' => 'reports', 'schema_cookie' => 7, 'tables' => ['wp_report_cache'], 'indexes' => ['wp_report_cache_key'], 'file' => '/srv/wp/reports-next264.sqlite'],
    ]);

    $t->same(['reports'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'cache', 'reports'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next265 detach cache expires explicit cache reader'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'detach', 'schema' => 'cache'],
    ]);

    $t->same(['cache'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'audit'], $result['search_order_next']);
    $t->same(['cache-reader'], $result['expired_statements']);
    $t->same('__detached__', $result['statements']['cache-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next266 audit queue index rename keeps active reader on current'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_audit_queue_token', 'to' => 'wp_audit_queue_token_next266'],
    ]);

    $t->same(['audit'], $result['changed_schemas']);
    $t->same(['audit-log-reader', 'audit-queue-reader'], $result['expired_statements']);
    $t->same(['audit-queue-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['audit-queue-reader']['sqlite_result_on_current_step']);
    $t->same(false, $result['statements']['audit-queue-reader']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next267 uncommitted main wal frame is ignored'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 267, 'table' => 'wp_transient_stage', 'commit' => false],
    ]);

    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(261, $result['schema_cookies_next']['main']);
};

$tests['attach temp wal schema cache current source next268 combined ddl preserves active current source'] = static function (TestRunner $t) use ($plan261268): void {
    $result = $plan261268([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_batch'],
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_type_status', 'to' => 'wp_posts_type_status_next268'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 268, 'table' => 'wp_event_archive', 'commit' => true],
    ]);

    $t->same(['temp', 'main', 'analytics'], $result['changed_schemas']);
    $t->same(['main-posts-reader', 'import-batch-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['main-posts-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['import-batch-reader']['next_step_action']);
    $t->same(false, $result['statements']['main-posts-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['import-batch-reader']['schema_transitions'][0]['next_found']);
};

return $tests;
