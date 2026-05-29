<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas = [
    'main' => [
        'schema_cookie' => 213,
        'tables' => ['wp_options', 'wp_posts', 'wp_users'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_users_login'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 213, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 221, 'commit' => false],
        ],
    ],
    'temp' => ['schema_cookie' => 112, 'tables' => ['wp_options', 'wp_upload_stage', 'wp_plugin_stage'], 'indexes' => ['wp_temp_options_name', 'wp_upload_stage_token', 'wp_plugin_stage_slug'], 'temp' => true],
    'archive' => ['schema_cookie' => 212, 'tables' => ['wp_comments', 'wp_commentmeta'], 'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'], 'file' => '/srv/wp/archive-next213.sqlite'],
    'analytics' => ['schema_cookie' => 11, 'tables' => ['wp_events'], 'indexes' => ['wp_events_name'], 'file' => '/srv/wp/analytics-next213.sqlite'],
];

$statements = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'temp-plugin-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_plugin_stage INDEXED BY wp_plugin_stage_slug WHERE slug = ?', 'active' => true],
    ['name' => 'upload-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_upload_stage INDEXED BY wp_upload_stage_token WHERE token = ?'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'main-users-reader', 'sql' => 'SELECT ID FROM wp_users INDEXED BY wp_users_login WHERE user_login = ?'],
    ['name' => 'archive-commentmeta-writer', 'sql' => 'UPDATE archive.wp_commentmeta SET meta_value = ? WHERE meta_key = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext213220($schemas, $statements, [
    ['op' => 'rename_index', 'schema' => 'analytics', 'from' => 'wp_events_name', 'to' => 'wp_events_name_next213'],
    ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 214, 'table' => 'wp_temp_runtime', 'commit' => true],
    ['op' => 'attach', 'schema' => 'reporting', 'schema_cookie' => 5, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_slug'], 'file' => '/srv/wp/reporting-next215.sqlite'],
    ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_users', 'to' => 'wp_users_next216'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 217, 'table' => 'wp_uncommitted', 'commit' => false],
    ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_plugin_stage_slug'],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 220, 'table' => 'wp_termmeta', 'indexes' => ['wp_termmeta_key'], 'commit' => true],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next213-220');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next213');
    assert($plan['dependencies'][7] === 'sqlite-attach-temp-wal-schema-cache-current-source-next220');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next209', $plan['dependencies'], true));
    assert($plan['event_count'] === 7);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive', 'analytics', 'reporting']);
    assert($plan['schema_cookies_next']['main'] === 220);
    assert($plan['schema_cookies_next']['temp'] === 215);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics', 'reporting']);
    assert(in_array('temp-plugin-stage-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('archive-commentmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['analytics-events-reader']['index_transitions'][0]['next_found'] === false);

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next213-220 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
