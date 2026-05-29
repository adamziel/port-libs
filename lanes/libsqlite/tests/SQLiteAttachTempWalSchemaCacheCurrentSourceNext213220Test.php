<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas213220 = [
    'main' => [
        'schema_cookie' => 213,
        'tables' => ['wp_options', 'wp_posts', 'wp_users'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_users_login'],
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 213, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 221, 'commit' => false],
        ],
    ],
    'temp' => [
        'schema_cookie' => 112,
        'tables' => ['wp_options', 'wp_upload_stage', 'wp_plugin_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_upload_stage_token', 'wp_plugin_stage_slug'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 212,
        'tables' => ['wp_comments', 'wp_commentmeta'],
        'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'],
        'file' => '/srv/wp/archive-next213.sqlite',
    ],
    'analytics' => [
        'schema_cookie' => 11,
        'tables' => ['wp_events'],
        'indexes' => ['wp_events_name'],
        'file' => '/srv/wp/analytics-next213.sqlite',
    ],
];

$statements213220 = [
    ['name' => 'temp-options-reader', 'sql' => 'SELECT option_value FROM wp_options INDEXED BY wp_temp_options_name WHERE option_name = ?'],
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?'],
    ['name' => 'temp-plugin-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_plugin_stage INDEXED BY wp_plugin_stage_slug WHERE slug = ?', 'active' => true],
    ['name' => 'upload-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_upload_stage INDEXED BY wp_upload_stage_token WHERE token = ?'],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments INDEXED BY wp_comments_post_id WHERE comment_post_ID = ?'],
    ['name' => 'analytics-events-reader', 'sql' => 'SELECT event_id FROM analytics.wp_events INDEXED BY wp_events_name WHERE event_name = ?'],
    ['name' => 'main-users-reader', 'sql' => 'SELECT ID FROM wp_users INDEXED BY wp_users_login WHERE user_login = ?'],
    ['name' => 'archive-commentmeta-writer', 'sql' => 'UPDATE archive.wp_commentmeta SET meta_value = ? WHERE meta_key = ?'],
];

$plan213220 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext213220(
    $schemas ?? $schemas213220,
    $statements ?? $statements213220,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next213 attached analytics index rename expires explicit attached reader'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'rename_index', 'schema' => 'analytics', 'from' => 'wp_events_name', 'to' => 'wp_events_name_next213'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next213-220', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next213', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next220', $result['dependencies'][7]);
    $t->same(['analytics'], $result['changed_schemas']);
    $t->same(['analytics-events-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['analytics-events-reader']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next214 temp schema write keeps main and attached readers current'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'schema_write', 'schema' => 'temp', 'schema_cookie' => 214, 'table' => 'wp_temp_runtime', 'commit' => true],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(214, $result['schema_cookies_next']['temp']);
    $t->same(['temp-options-reader', 'temp-plugin-stage-reader', 'upload-stage-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['main-options-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['archive-comments-reader']['requires_reprepare']);
};

$tests['attach temp wal schema cache current source next215 attach reporting schema updates search order without expiring existing readers'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'attach', 'schema' => 'reporting', 'schema_cookie' => 5, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_slug'], 'file' => '/srv/wp/reporting-next215.sqlite'],
    ]);

    $t->same(['reporting'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'archive', 'reporting'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next216 main table rename expires unqualified main readers but not temp shadow'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_users', 'to' => 'wp_users_next216'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(['main-options-reader', 'main-users-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['temp-options-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['main-users-reader']['schema_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next217 uncommitted wal schema cookie frame is ignored'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 217, 'table' => 'wp_uncommitted', 'commit' => false],
    ]);

    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same([], $result['expired_statements']);
    $t->same(213, $result['schema_cookies_next']['main']);
};

$tests['attach temp wal schema cache current source next218 drop temp stage index lets active cursor finish then reprepare'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'drop_index', 'schema' => 'temp', 'index' => 'wp_plugin_stage_slug'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(['temp-plugin-stage-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['temp-plugin-stage-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['temp-plugin-stage-reader']['next_step_action']);
    $t->same(false, $result['statements']['temp-plugin-stage-reader']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next219 detach archive blocks stale attached writer before retry'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics'], $result['search_order_next']);
    $t->same(['archive-commentmeta-writer'], $result['write_statements_blocked_before_retry']);
    $t->same('__detached__', $result['statements']['archive-commentmeta-writer']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next220 committed wal index addition expires only main schema readers'] = static function (TestRunner $t) use ($plan213220): void {
    $result = $plan213220([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 220, 'table' => 'wp_termmeta', 'indexes' => ['wp_termmeta_key'], 'commit' => true],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(220, $result['schema_cookies_next']['main']);
    $t->same(['main-options-reader', 'main-users-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['temp-options-reader']['requires_reprepare']);
    $t->same(false, $result['statements']['analytics-events-reader']['requires_reprepare']);
};

return $tests;
