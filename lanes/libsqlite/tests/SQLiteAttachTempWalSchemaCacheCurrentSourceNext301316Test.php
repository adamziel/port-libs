<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas301316 = [
    'main' => [
        'schema_cookie' => 300,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta', 'wp_comments', 'wp_users', 'wp_terms', 'wp_term_taxonomy'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status_next293', 'wp_postmeta_key', 'wp_comments_post', 'wp_users_login', 'wp_terms_slug', 'wp_term_taxonomy_taxonomy'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 300, 'commit' => true],
        ],
    ],
    'temp' => [
        'schema_cookie' => 190,
        'tables' => ['wp_import_batch', 'wp_theme_stage', 'wp_comment_stage', 'wp_term_stage'],
        'indexes' => ['wp_import_batch_token', 'wp_theme_stage_stylesheet', 'wp_comment_stage_post', 'wp_term_stage_slug_next287'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 78,
        'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key_next298', 'wp_event_rollup_day'],
        'file' => '/srv/wp/analytics-next301.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 281,
        'tables' => ['wp_audit_log', 'wp_audit_queue', 'wp_auditmeta'],
        'indexes' => ['wp_audit_log_action', 'wp_audit_queue_token', 'wp_auditmeta_key'],
        'file' => '/srv/wp/audit-next301.sqlite',
    ],
    'queue' => [
        'schema_cookie' => 292,
        'tables' => ['wp_job_queue', 'wp_job_locks'],
        'indexes' => ['wp_job_queue_token', 'wp_job_locks_token'],
        'file' => '/srv/wp/queue-next301.sqlite',
    ],
    'metrics' => [
        'schema_cookie' => 4,
        'tables' => ['wp_metric_cache'],
        'indexes' => ['wp_metric_cache_key'],
        'file' => '/srv/wp/metrics-next301.sqlite',
    ],
];

$statements301316 = [
    ['name' => 'term-tax-reader', 'sql' => 'SELECT term_taxonomy_id FROM main.wp_term_taxonomy INDEXED BY wp_term_taxonomy_taxonomy WHERE taxonomy = ?', 'active' => true],
    ['name' => 'temp-theme-reader', 'sql' => 'SELECT stylesheet FROM temp.wp_theme_stage INDEXED BY wp_theme_stage_stylesheet WHERE status = ?', 'active' => true],
    ['name' => 'analytics-rollup-reader', 'sql' => 'SELECT day FROM analytics.wp_event_rollup INDEXED BY wp_event_rollup_day WHERE day >= ?'],
    ['name' => 'analytics-meta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta INDEXED BY wp_eventmeta_key_next298 SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'audit-queue-reader', 'sql' => 'SELECT queue_id FROM audit.wp_audit_queue INDEXED BY wp_audit_queue_token WHERE token = ?', 'active' => true],
    ['name' => 'queue-lock-reader', 'sql' => 'SELECT lock_id FROM queue.wp_job_locks INDEXED BY wp_job_locks_token WHERE token = ?'],
    ['name' => 'metrics-cache-writer', 'sql' => 'UPDATE metrics.wp_metric_cache INDEXED BY wp_metric_cache_key SET payload = ? WHERE cache_key = ?'],
    ['name' => 'campaign-reader', 'sql' => 'SELECT campaign_id FROM campaign.wp_campaigns INDEXED BY wp_campaigns_slug WHERE slug = ?'],
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'users-writer', 'sql' => 'UPDATE main.wp_users INDEXED BY wp_users_login SET user_status = ? WHERE user_login = ?'],
];

$plan301316 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext301316(
    $schemas ?? $schemas301316,
    $statements ?? $statements301316,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next301-316 combined batch follows next285-300'] = static function (TestRunner $t) use ($plan301316): void {
    $result = $plan301316([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 301, 'table' => 'wp_site_health', 'indexes' => ['wp_site_health_status'], 'commit' => true],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_theme_stage_stylesheet', 'to' => 'wp_theme_stage_stylesheet_next302'],
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_rollup'],
        ['op' => 'attach', 'schema' => 'campaign', 'schema_cookie' => 5, 'tables' => ['wp_campaigns'], 'indexes' => ['wp_campaigns_slug'], 'file' => '/srv/wp/campaign-next304.sqlite'],
        ['op' => 'detach', 'schema' => 'metrics'],
        ['op' => 'wal_commit', 'schema' => 'queue', 'schema_cookie' => 306, 'table' => 'wp_job_claims', 'indexes' => ['wp_job_claims_token'], 'commit' => true],
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_comment_stage'],
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_users_login', 'to' => 'wp_users_login_next308'],
        ['op' => 'drop_table', 'schema' => 'audit', 'table' => 'wp_audit_queue'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 310, 'table' => 'wp_event_session', 'indexes' => ['wp_event_session_user'], 'commit' => false],
        ['op' => 'attach', 'schema' => 'search', 'schema_cookie' => 6, 'tables' => ['wp_search_docs'], 'indexes' => ['wp_search_docs_post'], 'file' => '/srv/wp/search-next311.sqlite'],
        ['op' => 'drop_table', 'schema' => 'queue', 'table' => 'wp_job_locks'],
        ['op' => 'rename_index', 'schema' => 'analytics', 'from' => 'wp_eventmeta_key_next298', 'to' => 'wp_eventmeta_key_next313'],
        ['op' => 'detach', 'schema' => 'search'],
        ['op' => 'wal_commit', 'schema' => 'campaign', 'schema_cookie' => 316, 'table' => 'wp_campaignmeta', 'indexes' => ['wp_campaignmeta_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next301-316', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next301', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next316', $result['dependencies'][15]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next300', $result['dependencies'][31]);
    $t->same(14, $result['event_count']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'campaign', 'metrics', 'queue'], $result['changed_schemas']);
    $t->same(302, $result['schema_cookies_next']['main']);
    $t->same(192, $result['schema_cookies_next']['temp']);
    $t->same(80, $result['schema_cookies_next']['analytics']);
    $t->same(282, $result['schema_cookies_next']['audit']);
    $t->same(307, $result['schema_cookies_next']['queue']);
    $t->same(316, $result['schema_cookies_next']['campaign']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'campaign', 'queue'], $result['search_order_next']);
    $t->same(['term-tax-reader', 'temp-theme-reader', 'audit-queue-reader'], $result['active_current_snapshot_statements']);
    $t->same(['analytics-meta-writer', 'metrics-cache-writer', 'users-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['temp-theme-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['audit-queue-reader']['schema_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['metrics-cache-writer']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['campaign-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next301-316 transient attach detach stays stable'] = static function (TestRunner $t) use ($plan301316): void {
    $result = $plan301316([
        ['op' => 'attach', 'schema' => 'scratch', 'schema_cookie' => 2, 'tables' => ['wp_scratch'], 'indexes' => ['wp_scratch_key'], 'file' => '/srv/wp/scratch-next301.sqlite'],
        ['op' => 'detach', 'schema' => 'scratch'],
        ['op' => 'attach', 'schema' => 'warehouse', 'schema_cookie' => 8, 'tables' => ['wp_warehouse_cache'], 'indexes' => ['wp_warehouse_cache_key'], 'file' => '/srv/wp/warehouse-next302.sqlite'],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(['warehouse'], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'metrics', 'queue', 'warehouse'], $result['search_order_next']);
};

return $tests;
