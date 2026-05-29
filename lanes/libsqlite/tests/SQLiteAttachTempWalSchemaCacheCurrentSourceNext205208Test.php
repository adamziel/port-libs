<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas205208 = [
    'main' => [
        'schema_cookie' => 205,
        'tables' => ['wp_options', 'wp_posts', 'wp_users'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_users_login'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 205, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 209, 'commit' => false],
        ],
    ],
    'temp' => [
        'schema_cookie' => 108,
        'tables' => ['wp_options', 'wp_upload_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_upload_stage_token'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 204,
        'tables' => ['wp_comments', 'wp_commentmeta'],
        'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'],
        'file' => '/srv/wp/archive-next205.sqlite',
    ],
    'analytics' => [
        'schema_cookie' => 7,
        'tables' => ['wp_events'],
        'indexes' => ['wp_events_name'],
        'file' => '/srv/wp/analytics-next205.sqlite',
    ],
];

$statements205208 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'active-upload-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_upload_stage INDEXED BY wp_upload_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-commentmeta-writer', 'sql' => 'UPDATE archive.wp_commentmeta SET meta_value = ? WHERE meta_key = ?'],
    ['name' => 'unqualified-users-reader', 'sql' => 'SELECT ID FROM wp_users WHERE user_login = ?'],
];

$plan205208 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext205208(
    $schemas ?? $schemas205208,
    $statements ?? $statements205208,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next205 temp option drop reveals main and keeps explicit main stable'] = static function (TestRunner $t) use ($plan205208): void {
    $result = $plan205208([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next205-208', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next205', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next201', $result['dependencies'][4]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['temp-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['temp-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['main-options-reader']['requires_reprepare']);
};

$tests['attach temp wal schema cache current source next206 committed main wal schema cookie expires main readers only'] = static function (TestRunner $t) use ($plan205208): void {
    $result = $plan205208([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 206, 'table' => 'wp_usermeta', 'commit' => true],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(206, $result['schema_cookies_next']['main']);
    $t->same(['main-options-reader', 'unqualified-users-reader'], $result['expired_statements']);
    $t->same(false, in_array('temp-options-reader', $result['expired_statements'], true));
    $t->same('sqlite_schema_then_reprepare_read_statement', $result['statements']['unqualified-users-reader']['next_step_action']);
};

$tests['attach temp wal schema cache current source next207 detach attached schema expires attached writer and reader'] = static function (TestRunner $t) use ($plan205208): void {
    $result = $plan205208([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics'], $result['search_order_next']);
    $t->same(['archive-comments-reader', 'archive-commentmeta-writer'], $result['expired_statements']);
    $t->same('__detached__', $result['statements']['archive-comments-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['archive-commentmeta-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next208 active temp index rename finishes current source then reprepare'] = static function (TestRunner $t) use ($plan205208): void {
    $result = $plan205208([
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_upload_stage_token', 'to' => 'wp_upload_stage_token_next208'],
        ['op' => 'wal_commit', 'schema' => 'temp', 'schema_cookie' => 111, 'table' => 'wp_ignored_stage', 'commit' => false],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(['active-upload-stage-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['active-upload-stage-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-upload-stage-reader']['next_step_action']);
    $t->same(false, $result['statements']['active-upload-stage-reader']['index_transitions'][0]['next_found']);
};

return $tests;
