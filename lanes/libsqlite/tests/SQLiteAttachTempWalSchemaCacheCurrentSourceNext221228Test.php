<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas221228 = [
    'main' => [
        'schema_cookie' => 221,
        'tables' => ['wp_options', 'wp_posts', 'wp_users', 'wp_terms'],
        'indexes' => ['wp_options_name', 'wp_posts_status', 'wp_users_login', 'wp_terms_slug'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 221, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 229, 'commit' => false],
        ],
    ],
    'temp' => [
        'schema_cookie' => 120,
        'tables' => ['wp_options', 'wp_import_stage', 'wp_plugin_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_stage_token', 'wp_plugin_stage_slug'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 22,
        'tables' => ['wp_events', 'wp_eventmeta'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key'],
        'file' => '/srv/wp/analytics-next221.sqlite',
    ],
    'reporting' => [
        'schema_cookie' => 9,
        'tables' => ['wp_reports'],
        'indexes' => ['wp_reports_slug'],
        'file' => '/srv/wp/reporting-next221.sqlite',
    ],
];

$statements221228 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM wp_posts INDEXED BY wp_posts_status WHERE post_status = ?', 'active' => true],
    ['name' => 'terms-reader', 'sql' => 'SELECT term_id FROM wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'import-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'plugin-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_plugin_stage INDEXED BY wp_plugin_stage_slug WHERE slug = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-eventmeta-writer', 'sql' => 'UPDATE analytics.wp_eventmeta SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'reporting-reader', 'sql' => 'SELECT report_id FROM reporting.wp_reports INDEXED BY wp_reports_slug WHERE slug = ?'],
];

$plan221228 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext221228(
    $schemas ?? $schemas221228,
    $statements ?? $statements221228,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next221 temp import table drop expires active temp reader only'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next221-228', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next221', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next228', $result['dependencies'][7]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(['temp-options-reader', 'import-stage-reader', 'plugin-stage-reader'], $result['expired_statements']);
    $t->same(['import-stage-reader'], $result['active_current_snapshot_statements']);
    $t->same(false, $result['statements']['import-stage-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next222 main indexed by rename lets active cursor finish'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_status', 'to' => 'wp_posts_status_next222'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(['main-options-reader', 'main-posts-reader', 'terms-reader'], $result['expired_statements']);
    $t->same('SQLITE_OK', $result['statements']['main-posts-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['main-posts-reader']['next_step_action']);
    $t->same(false, $result['statements']['main-posts-reader']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next223 attach cache schema appends search order without reprepare'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'attach', 'schema' => 'cache', 'schema_cookie' => 3, 'tables' => ['wp_object_cache'], 'indexes' => ['wp_object_cache_key'], 'file' => '/srv/wp/cache-next223.sqlite'],
    ]);

    $t->same(['cache'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'cache', 'reporting'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next224 analytics wal commit expires attached readers and writer'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 224, 'table' => 'wp_event_queue', 'indexes' => ['wp_event_queue_token'], 'commit' => true],
    ]);

    $t->same(['analytics'], $result['changed_schemas']);
    $t->same(224, $result['schema_cookies_next']['analytics']);
    $t->same(['analytics-events-reader', 'analytics-eventmeta-writer'], $result['expired_statements']);
    $t->same(['analytics-eventmeta-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next225 uncommitted temp wal frame is ignored'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 225, 'table' => 'wp_temp_uncommitted', 'commit' => false],
    ]);

    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(120, $result['schema_cookies_next']['temp']);
};

$tests['attach temp wal schema cache current source next226 reporting detach removes explicit attached reader'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'detach', 'schema' => 'reporting'],
    ]);

    $t->same(['reporting'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics'], $result['search_order_next']);
    $t->same(['reporting-reader'], $result['expired_statements']);
    $t->same('__detached__', $result['statements']['reporting-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next227 temp index creation expires only temp shadow readers'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_temp_options_autoload'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(121, $result['schema_cookies_next']['temp']);
    $t->same(['temp-options-reader', 'import-stage-reader', 'plugin-stage-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['main-options-reader']['requires_reprepare']);
};

$tests['attach temp wal schema cache current source next228 main terms drop does not disturb temp shadow or attached readers'] = static function (TestRunner $t) use ($plan221228): void {
    $result = $plan221228([
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_terms'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(['main-options-reader', 'main-posts-reader', 'terms-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['temp-options-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['analytics-events-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['terms-reader']['schema_transitions'][0]['next_found']);
};

return $tests;
