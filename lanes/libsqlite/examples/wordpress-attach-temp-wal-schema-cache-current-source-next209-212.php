<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteAttachWalTempStatementLifecyclePlan.php';

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas = [
    'main' => [
        'schema_cookie' => 209,
        'tables' => ['wp_options', 'wp_posts', 'wp_users'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_users_login'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 209, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 213, 'commit' => false],
        ],
    ],
    'temp' => ['schema_cookie' => 108, 'tables' => ['wp_options', 'wp_upload_stage'], 'indexes' => ['wp_temp_options_name', 'wp_upload_stage_token'], 'temp' => true],
    'archive' => ['schema_cookie' => 208, 'tables' => ['wp_comments', 'wp_commentmeta'], 'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'], 'file' => '/srv/wp/archive-next209.sqlite'],
    'analytics' => ['schema_cookie' => 7, 'tables' => ['wp_events'], 'indexes' => ['wp_events_name'], 'file' => '/srv/wp/analytics-next209.sqlite'],
];

$statements = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'active-upload-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_upload_stage INDEXED BY wp_upload_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-commentmeta-writer', 'sql' => 'UPDATE archive.wp_commentmeta SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'unqualified-users-reader', 'sql' => 'SELECT ID FROM wp_users WHERE user_login = ?'],
];

$plan = SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext209212($schemas, $statements, [
    ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 210, 'table' => 'wp_usermeta', 'commit' => true],
    ['op' => 'detach', 'schema' => 'archive'],
    ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_upload_stage_token', 'to' => 'wp_upload_stage_token_next212'],
    ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 111, 'table' => 'wp_ignored_stage', 'commit' => false],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['operation'] === 'attach-wal-temp-schema-cache-current-source-next209-212');
    assert($plan['dependencies'][0] === 'sqlite-attach-temp-wal-schema-cache-current-source-next209');
    assert(in_array('sqlite-attach-temp-wal-schema-cache-current-source-next201', $plan['dependencies'], true));
    assert($plan['event_count'] === 4);
    assert($plan['changed_schemas'] === ['temp', 'main', 'archive']);
    assert($plan['schema_cookies_next']['main'] === 210);
    assert($plan['schema_cookies_next']['temp'] === 110);
    assert($plan['search_order_next'] === ['temp', 'main', 'analytics']);
    assert(in_array('active-upload-stage-reader', $plan['active_current_snapshot_statements'], true));
    assert(in_array('archive-commentmeta-writer', $plan['write_statements_blocked_before_retry'], true));
    assert($plan['statements']['temp-options-reader']['schema_transitions'][0]['next_schema'] === 'main');

    echo "wordpress-attach-temp-wal-schema-cache-current-source-next209-212 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
