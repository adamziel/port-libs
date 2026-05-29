<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas253260 = [
    'main' => [
        'schema_cookie' => 253,
        'tables' => ['wp_options', 'wp_posts', 'wp_usermeta', 'wp_terms', 'wp_term_relationships'],
        'indexes' => ['wp_options_name', 'wp_posts_type_status', 'wp_usermeta_key', 'wp_terms_slug', 'wp_term_relationships_object'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 253, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 253, 'commit' => false],
        ],
    ],
    'temp' => [
        'schema_cookie' => 152,
        'tables' => ['wp_options', 'wp_import_batch', 'wp_rewrite_stage', 'wp_user_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_batch_token', 'wp_rewrite_stage_name', 'wp_user_stage_login'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 39,
        'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_rollup'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_rollup_day'],
        'file' => '/srv/wp/analytics-next253.sqlite',
    ],
    'audit' => [
        'schema_cookie' => 25,
        'tables' => ['wp_audit_log', 'wp_auditmeta'],
        'indexes' => ['wp_audit_log_action', 'wp_auditmeta_key'],
        'file' => '/srv/wp/audit-next253.sqlite',
    ],
    'search' => [
        'schema_cookie' => 17,
        'tables' => ['wp_search_cache'],
        'indexes' => ['wp_search_cache_key'],
        'file' => '/srv/wp/search-next253.sqlite',
    ],
];

$statements253260 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'posts-reader', 'sql' => 'SELECT ID FROM wp_posts INDEXED BY wp_posts_type_status WHERE post_status = ?', 'active' => true],
    ['name' => 'usermeta-writer', 'sql' => 'UPDATE main.wp_usermeta SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'terms-reader', 'sql' => 'SELECT term_id FROM main.wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'import-batch-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_batch INDEXED BY wp_import_batch_token WHERE token = ?', 'active' => true],
    ['name' => 'rewrite-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_rewrite_stage INDEXED BY wp_rewrite_stage_name WHERE option_name = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-rollup-reader', 'sql' => 'SELECT day_key FROM analytics.wp_event_rollup INDEXED BY wp_event_rollup_day WHERE day_key = ?'],
    ['name' => 'audit-reader', 'sql' => 'SELECT log_id FROM audit.wp_audit_log INDEXED BY wp_audit_log_action WHERE action = ?'],
    ['name' => 'auditmeta-writer', 'sql' => 'UPDATE audit.wp_auditmeta INDEXED BY wp_auditmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'search-reader', 'sql' => 'SELECT cache_value FROM search.wp_search_cache INDEXED BY wp_search_cache_key WHERE cache_key = ?'],
];

$plan253260 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext253260(
    $schemas ?? $schemas253260,
    $statements ?? $statements253260,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next253 main wal commit expires main statements'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 254, 'table' => 'wp_postmeta', 'indexes' => ['wp_postmeta_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next253-260', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next253', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next260', $result['dependencies'][7]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next252', $result['dependencies'][15]);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(254, $result['schema_cookies_next']['main']);
    $t->same(['main-options-reader', 'posts-reader', 'usermeta-writer', 'terms-reader'], $result['expired_statements']);
    $t->same(['usermeta-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next254 temp options drop reveals main options source'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['temp-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['temp-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-options-reader', 'import-batch-reader', 'rewrite-stage-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next255 audit index rename blocks audit writer'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'rename_index', 'schema' => 'audit', 'from' => 'wp_auditmeta_key', 'to' => 'wp_auditmeta_key_next255'],
    ]);

    $t->same(['audit'], $result['changed_schemas']);
    $t->same(['audit-reader', 'auditmeta-writer'], $result['expired_statements']);
    $t->same(['auditmeta-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['auditmeta-writer']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next256 attach queue schema leaves prepared sources stable'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'attach', 'schema' => 'queue', 'schema_cookie' => 3, 'tables' => ['wp_job_queue'], 'indexes' => ['wp_job_queue_token'], 'file' => '/srv/wp/queue-next256.sqlite'],
    ]);

    $t->same(['queue'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'audit', 'queue', 'search'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next257 detach search expires explicit search reader'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'detach', 'schema' => 'search'],
    ]);

    $t->same(['search'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'audit'], $result['search_order_next']);
    $t->same(['search-reader'], $result['expired_statements']);
    $t->same('__detached__', $result['statements']['search-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next258 analytics rollup drop leaves main and audit stable'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_rollup'],
    ]);

    $t->same(['analytics'], $result['changed_schemas']);
    $t->same(['analytics-events-reader', 'analytics-rollup-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['main-options-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['audit-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['analytics-rollup-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next259 uncommitted audit wal frame is ignored'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'wal_commit', 'schema' => 'audit', 'schema_cookie' => 259, 'table' => 'wp_audit_queue', 'commit' => false],
    ]);

    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(25, $result['schema_cookies_next']['audit']);
};

$tests['attach temp wal schema cache current source next260 combined ddl keeps active readers on current source'] = static function (TestRunner $t) use ($plan253260): void {
    $result = $plan253260([
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_type_status', 'to' => 'wp_posts_type_status_next260'],
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_batch'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 260, 'table' => 'wp_event_archive', 'commit' => true],
    ]);

    $t->same(['temp', 'main', 'analytics'], $result['changed_schemas']);
    $t->same(['posts-reader', 'import-batch-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['posts-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['import-batch-reader']['next_step_action']);
    $t->same(false, $result['statements']['posts-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['import-batch-reader']['schema_transitions'][0]['next_found']);
};

return $tests;
