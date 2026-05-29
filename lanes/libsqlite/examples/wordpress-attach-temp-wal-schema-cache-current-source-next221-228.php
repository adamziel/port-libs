<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 221,
        'tables' => ['wp_options', 'wp_posts', 'wp_users', 'wp_terms'],
        'indexes' => ['wp_options_name', 'wp_posts_status', 'wp_users_login', 'wp_terms_slug'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 221, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 229, 'commit' => false],
        ],
    ],
    'temp' => ['schema_cookie' => 120, 'tables' => ['wp_options', 'wp_import_stage', 'wp_plugin_stage'], 'indexes' => ['wp_temp_options_name', 'wp_import_stage_token', 'wp_plugin_stage_slug'], 'temp' => true],
    'analytics' => ['schema_cookie' => 22, 'tables' => ['wp_events', 'wp_eventmeta'], 'indexes' => ['wp_events_name', 'wp_eventmeta_key'], 'file' => '/srv/wp/analytics-next221.sqlite'],
    'reporting' => ['schema_cookie' => 9, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_slug'], 'file' => '/srv/wp/reporting-next221.sqlite'],
];

$statements = [
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

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext221228($schemas, $statements, [
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage'],
    ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_posts_status', 'to' => 'wp_posts_status_next222'],
    ['op' => 'attach', 'schema' => 'cache', 'schema_cookie' => 3, 'tables' => ['wp_object_cache'], 'indexes' => ['wp_object_cache_key'], 'file' => '/srv/wp/cache-next223.sqlite'],
    ['op' => 'wal_commit', 'schema' => 'analytics', 'schema_cookie' => 224, 'table' => 'wp_event_queue', 'indexes' => ['wp_event_queue_token'], 'commit' => true],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 225, 'table' => 'wp_temp_uncommitted', 'commit' => false],
    ['op' => 'detach', 'schema' => 'reporting'],
    ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_temp_options_autoload'],
    ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_terms'],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next221-228');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next221');
    assert($plan['dependencies'][7] === 'sqlite-attach-temp-wal-schema-cache-current-source-next228');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next220', $plan['dependencies'], true));
    assert($plan['event_count'] === 7);
    assert($plan['changed_schemas'] === ['temp', 'main', 'analytics', 'reporting', 'cache']);
    assert($plan['schema_cookies_next']['main'] === 223);
    assert($plan['schema_cookies_next']['temp'] === 122);
    assert($plan['schema_cookies_next']['analytics'] === 224);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'cache']);
    assert(in_array('main-posts-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('import-stage-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('analytics-eventmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['reporting-reader']['schema_transitions'][0]['next_schema'] === '__detached__');
    assert($plan['statements']['main-posts-reader']['index_transitions'][0]['next_found'] === false);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next221-228 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
