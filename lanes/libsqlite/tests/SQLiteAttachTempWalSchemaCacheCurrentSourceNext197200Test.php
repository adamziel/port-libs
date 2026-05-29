<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas197200 = [
    'main' => [
        'schema_cookie' => 197,
        'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_termmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 100,
        'tables' => ['wp_options', 'wp_import_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_stage_token'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 196,
        'tables' => ['wp_comments', 'wp_commentmeta'],
        'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'],
        'file' => '/srv/wp/archive-next197.sqlite',
    ],
];

$statements197200 = [
    ['name' => 'active-main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_name WHERE option_name = ?', 'active' => true],
    ['name' => 'temp-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?'],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan197200 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext197200(
    $schemas ?? $schemas197200,
    $statements ?? $statements197200,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next197 main index rename lets active current source finish'] = static function (TestRunner $t) use ($plan197200): void {
    $result = $plan197200([
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_options_name', 'to' => 'wp_options_name_next197'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next197-200', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next197', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next193', $result['dependencies'][4]);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(['active-main-options-reader'], $result['active_current_snapshot_statements']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-main-options-reader']['next_step_action']);
    $t->same(false, $result['statements']['active-main-options-reader']['index_transitions'][0]['next_found']);
};

$tests['attach temp wal schema cache current source next198 temp schema write shadows unqualified options'] = static function (TestRunner $t) use ($plan197200, $schemas197200): void {
    $schemas = $schemas197200;
    $schemas['temp']['tables'] = ['wp_import_stage'];
    $result = $plan197200([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_plugin_options'],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ], null, $schemas);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(102, $result['schema_cookies_next']['temp']);
    $t->same('main', $result['statements']['unqualified-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['unqualified-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same('sqlite_schema_then_reprepare_read_statement', $result['statements']['unqualified-options-reader']['next_step_action']);
    $t->same(true, $result['requires_reprepare']);
};

$tests['attach temp wal schema cache current source next199 attached detach blocks archive writer before retry'] = static function (TestRunner $t) use ($plan197200): void {
    $result = $plan197200([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(['temp', 'main'], $result['search_order_next']);
    $t->same(['archive-meta-reader', 'archive-comments-writer'], $result['expired_statements']);
    $t->same(['archive-comments-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next200 committed wal schema cookie expires matching main readers'] = static function (TestRunner $t) use ($plan197200): void {
    $result = $plan197200([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 200, 'table' => 'wp_termmeta', 'commit' => true],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(200, $result['schema_cookies_next']['main']);
    $t->same(['active-main-options-reader'], $result['expired_statements']);
    $t->same(['active-main-options-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next197 200 uncommitted wal and duplicate temp schema write consolidate'] = static function (TestRunner $t) use ($plan197200): void {
    $result = $plan197200([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 200, 'table' => 'wp_termmeta', 'commit' => false],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_plugin_options'],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_plugin_options'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(101, $result['schema_cookies_next']['temp']);
};

return $tests;
