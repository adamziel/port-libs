<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas269284 = [
    'main' => [
        'schema_cookie' => 269,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_comments', 'wp_users'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_postmeta_key', 'wp_comments_post', 'wp_users_login'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 269, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 184,
        'tables' => ['wp_options', 'wp_import_batch', 'wp_theme_stage', 'wp_comment_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_theme_stage_stylesheet', 'wp_comment_stage_post'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 73,
        'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_rollup_day'],
        'file' => '/srv/wp/analytics-next269.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 53,
        'tables' => ['wp_audit_log', 'wp_audit_queue', 'wp_auditmeta'],
        'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token', 'wp_auditmeta_key'],
        'file' => '/srv/wp/audit-next269.sqlite',
    ],
    'cache' => [
        'schema_cookie' => 29,
        'tables' => ['wp_object_cache', 'wp_transient_cache'],
        'indexes' => ['wp_object_cache_key', 'wp_transient_cache_timeout'],
        'file' => '/srv/wp/cache-next269.sqlite',
    ],
    'search' => [
        'schema_cookie' => 23,
        'tables' => ['wp_search_cache'],
        'indexes' => ['wp_search_cache_key'],
        'file' => '/srv/wp/search-next269.sqlite',
    ],
];

$statements269284 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_type_status WHERE post_type = ?', 'active' => true],
    ['name' => 'postmeta-writer', 'sql' => 'UPDATE main.wp_postmeta INDEXED BY wp_postmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'comments-reader', 'sql' => 'SELECT comment_ID FROM main.wp_comments INDEXED BY wp_comments_post WHERE comment_post_ID = ?', 'active' => true],
    ['name' => 'users-reader', 'sql' => 'SELECT ID FROM main.wp_users INDEXED BY wp_users_login WHERE user_login = ?'],
    ['name' => 'import-batch-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_batch INDEXED BY wp_import_batch_token WHERE token = ?', 'active' => true],
    ['name' => 'theme-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_theme_stage INDEXED BY wp_theme_stage_stylesheet WHERE stylesheet = ?'],
    ['name' => 'comment-stage-writer', 'sql' => 'UPDATE temp.wp_comment_stage INDEXED BY wp_comment_stage_post SET payload = ? WHERE post_id = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'analytics-rollup-reader', 'sql' => 'SELECT day_key FROM analytics.wp_event_rollup INDEXED BY wp_event_rollup_day WHERE day_key = ?'],
    ['name' => 'audit-log-reader', 'sql' => 'SELECT log_id FROM audit.wp_audit_log INDEXED BY wp_audit_log_action WHERE action = ?'],
    ['name' => 'audit-queue-reader', 'sql' => 'SELECT rowid FROM audit.wp_audit_queue INDEXED BY wp_audit_queue_token WHERE token = ?', 'active' => true],
    ['name' => 'auditmeta-writer', 'sql' => 'UPDATE audit.wp_auditmeta INDEXED BY wp_auditmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'cache-reader', 'sql' => 'SELECT cache_value FROM cache.wp_object_cache INDEXED BY wp_object_cache_key WHERE cache_key = ?'],
    ['name' => 'transient-cache-reader', 'sql' => 'SELECT cache_value FROM cache.wp_transient_cache INDEXED BY wp_transient_cache_timeout WHERE timeout < ?'],
    ['name' => 'search-reader', 'sql' => 'SELECT cache_value FROM search.wp_search_cache INDEXED BY wp_search_cache_key WHERE cache_key = ?'],
];

$plan269284 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext269284(
    $schemas ?? $schemas269284,
    $statements ?? $statements269284,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next269-284 combined batch preserves active readers'] = static function (TestRunner $t) use ($plan269284): void {
    $result = $plan269284([
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

    $t->same('attach-wal-temp-schema-cache-current-source-next269-284', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next269', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next284', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next268', $result['dependencies'][23]);
    $t->same(15, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'cache', 'queue', 'reports', 'search'], $result['changed_schemas']);
    $t->same(284, $result['schema_cookies_next']['main']);
    $t->same(187, $result['schema_cookies_next']['temp']);
    $t->same(74, $result['schema_cookies_next']['analytics']);
    $t->same(279, $result['schema_cookies_next']['audit']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'queue', 'reports'], $result['search_order_next']);
    $t->same(['main-posts-reader', 'comments-reader', 'import-batch-reader', 'audit-queue-reader'], $result['active_current_snapshot_statements']);
    $t->same(true, in_array('postmeta-writer', $result['write_statements_blocked_before_retry'], true));
    $t->same(true, in_array('analytics-meta-writer', $result['write_statements_blocked_before_retry'], true));
    $t->same(true, in_array('comment-stage-writer', $result['write_statements_blocked_before_retry'], true));
    $t->same(true, in_array('auditmeta-writer', $result['write_statements_blocked_before_retry'], true));
    $t->same('__detached__', $result['statements']['search-reader']['schema_transitions'][0]['next_schema']);
    $t->same('__detached__', $result['statements']['cache-reader']['schema_transitions'][0]['next_schema']);
    $t->same('main', $result['statements']['temp-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['main-posts-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['comments-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['import-batch-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next269-284 attach only remains stable'] = static function (TestRunner $t) use ($plan269284): void {
    $result = $plan269284([
        ['op' => 'attach', 'schema' => 'reports', 'schema_cookie' => 11, 'tables' => ['wp_report_cache'], 'indexes' => ['wp_report_cache_key'], 'file' => '/srv/wp/reports-next269.sqlite'],
        ['op' => 'attach', 'schema' => 'queue', 'schema_cookie' => 5, 'tables' => ['wp_job_queue'], 'indexes' => ['wp_job_queue_token'], 'file' => '/srv/wp/queue-next270.sqlite'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(['queue', 'reports'], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'cache', 'queue', 'reports', 'search'], $result['search_order_next']);
};

return $tests;
