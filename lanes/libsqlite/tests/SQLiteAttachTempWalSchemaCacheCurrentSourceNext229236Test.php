<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas229236 = [
    'main' => [
        'schema_cookie' => 229,
        'tables' => ['wp_options', 'wp_posts', 'wp_users', 'wp_terms', 'wp_termmeta'],
        'indexes' => ['wp_options_name', 'wp_posts_status', 'wp_users_login', 'wp_terms_slug', 'wp_termmeta_key'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 229, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 237, 'commit' => false],
        ],
    ],
    'temp' => [
        'schema_cookie' => 128,
        'tables' => ['wp_options', 'wp_import_stage', 'wp_plugin_stage', 'wp_terms_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_stage_token', 'wp_plugin_stage_slug', 'wp_terms_stage_slug'],
        'temp' => true,
    ],
    'analytics' => [
        'schema_cookie' => 24,
        'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_queue'],
        'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_queue_token'],
        'file' => '/srv/wp/analytics-next229.sqlite',
    ],
    'reporting' => [
        'schema_cookie' => 12,
        'tables' => ['wp_reports', 'wp_reportmeta'],
        'indexes' => ['wp_reports_slug', 'wp_reportmeta_key'],
        'file' => '/srv/wp/reporting-next229.sqlite',
    ],
    'cache' => [
        'schema_cookie' => 4,
        'tables' => ['wp_object_cache'],
        'indexes' => ['wp_object_cache_key'],
        'file' => '/srv/wp/cache-next229.sqlite',
    ],
];

$statements229236 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'main-posts-reader', 'sql' => 'SELECT ID FROM wp_posts INDEXED BY wp_posts_status WHERE post_status = ?', 'active' => true],
    ['name' => 'main-terms-reader', 'sql' => 'SELECT term_id FROM wp_terms INDEXED BY wp_terms_slug WHERE slug = ?'],
    ['name' => 'termmeta-writer', 'sql' => 'UPDATE main.wp_termmeta SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'import-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'terms-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_terms_stage INDEXED BY wp_terms_stage_slug WHERE slug = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'analytics-queue-reader', 'sql' => 'SELECT event_id FROM analytics.wp_event_queue INDEXED BY wp_event_queue_token WHERE token = ?'],
    ['name' => 'reporting-reader', 'sql' => 'SELECT report_id FROM reporting.wp_reports INDEXED BY wp_reports_slug WHERE slug = ?'],
    ['name' => 'reportmeta-writer', 'sql' => 'UPDATE reporting.wp_reportmeta INDEXED BY wp_reportmeta_key SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'cache-reader', 'sql' => 'SELECT cache_value FROM cache.wp_object_cache INDEXED BY wp_object_cache_key WHERE cache_key = ?'],
];

$plan229236 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas229236,
    $statements ?? $statements229236,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next229 main wal commit expires main readers and writer only'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 230, 'table' => 'wp_sitemeta', 'indexes' => ['wp_sitemeta_key'], 'commit' => true],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][7]);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(230, $result['schema_cookies_next']['main']);
    $t->same(['main-options-reader', 'main-posts-reader', 'main-terms-reader', 'termmeta-writer'], $result['expired_statements']);
    $t->same(['termmeta-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next230 temp table drop reveals main options reader'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['temp-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['temp-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-options-reader', 'import-stage-reader', 'terms-stage-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next231 reporting index rename blocks writer and reader retry'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'rename_index', 'schema' => 'reporting', 'from' => 'wp_reportmeta_key', 'to' => 'wp_reportmeta_key_next231'],
    ]);

    $t->same(['reporting'], $result['changed_schemas']);
    $t->same(['reporting-reader', 'reportmeta-writer'], $result['expired_statements']);
    $t->same(['reportmeta-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(false, $result['statements']['reportmeta-writer']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next232 attach late object schema does not expire existing sources'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'attach', 'schema' => 'objectlog', 'schema_cookie' => 2, 'tables' => ['wp_object_log'], 'indexes' => ['wp_object_log_key'], 'file' => '/srv/wp/objectlog-next232.sqlite'],
    ]);

    $t->same(['objectlog'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'cache', 'objectlog', 'reporting'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next233 detach cache expires explicit cache reader'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'detach', 'schema' => 'cache'],
    ]);

    $t->same(['cache'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'reporting'], $result['search_order_next']);
    $t->same(['cache-reader'], $result['expired_statements']);
    $t->same('__detached__', $result['statements']['cache-reader']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next234 analytics queue drop leaves main and reporting stable'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_queue'],
    ]);

    $t->same(['analytics'], $result['changed_schemas']);
    $t->same(['analytics-events-reader', 'analytics-queue-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['main-options-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['reporting-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['analytics-queue-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next235 uncommitted reporting wal frame is ignored'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'wal_commit', 'schema' => 'reporting', 'schema_cookie' => 235, 'table' => 'wp_report_queue', 'commit' => false],
    ]);

    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(12, $result['schema_cookies_next']['reporting']);
};

$tests['attach temp wal schema cache current source next236 combined ddl keeps active readers on current source'] = static function (TestRunner $t) use ($plan229236): void {
    $result = $plan229236([
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_status', 'to' => 'wp_posts_status_next236'],
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage'],
        ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 236, 'table' => 'wp_event_archive', 'commit' => true],
    ]);

    $t->same(['temp', 'main', 'analytics'], $result['changed_schemas']);
    $t->same(['main-posts-reader', 'import-stage-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['main-posts-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['import-stage-reader']['next_step_action']);
    $t->same(false, $result['statements']['main-posts-reader']['index_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['import-stage-reader']['schema_transitions'][0]['next_found']);
};

return $tests;
