<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 229,
        'tables' => ['wp_options', 'wp_posts', 'wp_users', 'wp_terms', 'wp_termmeta'],
        'indexes' => ['wp_options_name', 'wp_posts_status', 'wp_users_login', 'wp_terms_slug', 'wp_termmeta_key'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 229, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 237, 'commit' => false],
        ],
    ],
    'temp' => ['schema_cookie' => 128, 'tables' => ['wp_options', 'wp_import_stage', 'wp_plugin_stage', 'wp_terms_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_stage_token', 'wp_plugin_stage_slug', 'wp_terms_stage_slug'], 'temp' => true],
    'analytics' => ['schema_cookie' => 24, 'tables' => ['wp_events', 'wp_eventmeta', 'wp_event_queue'], 'indexes' => ['wp_events_name', 'wp_eventmeta_key', 'wp_event_queue_token'], 'file' => '/srv/wp/analytics-next229.sqlite'],
    'reporting' => ['schema_cookie' => 12, 'tables' => ['wp_reports', 'wp_reportmeta'], 'indexes' => ['wp_reports_slug', 'wp_reportmeta_key'], 'file' => '/srv/wp/reporting-next229.sqlite'],
    'cache' => ['schema_cookie' => 4, 'tables' => ['wp_object_cache'], 'indexes' => ['wp_object_cache_key'], 'file' => '/srv/wp/cache-next229.sqlite'],
];

$statements = [
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

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext229236($schemas, $statements, [
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 230, 'table' => 'wp_sitemeta', 'indexes' => ['wp_sitemeta_key'], 'commit' => true],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'rename_index', 'schema' => 'reporting', 'from' => 'wp_reportmeta_key', 'to' => 'wp_reportmeta_key_next231'],
    ['op' => 'attach', 'schema' => 'objectlog', 'schema_cookie' => 2, 'tables' => ['wp_object_log'], 'indexes' => ['wp_object_log_key'], 'file' => '/srv/wp/objectlog-next232.sqlite'],
    ['op' => 'detach', 'schema' => 'cache'],
    ['op' => 'drop_table', 'schema' => 'analytics', 'table' => 'wp_event_queue'],
    ['op' => 'wal_commit', 'schema' => 'reporting', 'schema_cookie' => 235, 'table' => 'wp_report_queue', 'commit' => false],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_status', 'to' => 'wp_posts_status_next236'],
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 236, 'table' => 'wp_event_archive', 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next229-236');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next229');
    assert($plan['dependencies'][7] === 'sqlite-attach-temp-wal-schema-cache-current-source-next236');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next228', $plan['dependencies'], true));
    assert($plan['event_count'] === 9);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'reporting', 'cache', 'objectlog']);
    assert($plan['schema_cookies_next']['main'] === 231);
    assert($plan['schema_cookies_next']['temp'] === 130);
    assert($plan['schema_cookies_next']['analytics'] === 236);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'objectlog', 'reporting']);
    assert(in_array('main-posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('import-stage-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('termmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert(in_array('reportmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['cache-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['temp-options-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next229-236 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
