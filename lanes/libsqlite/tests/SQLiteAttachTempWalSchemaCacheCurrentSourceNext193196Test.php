<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas193196 = [
    'main' => [
        'schema_cookie' => 193,
        'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_termmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 96,
        'tables' => ['wp_options', 'wp_import_stage'],
        'indexes' => ['wp_temp_options_name', 'wp_import_stage_token'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 192,
        'tables' => ['wp_comments', 'wp_commentmeta'],
        'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'],
        'file' => '/srv/wp/archive-next193.sqlite',
    ],
];

$statements193196 = [
    ['name' => 'stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?', 'active' => true],
    ['name' => 'archive-meta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'termmeta-reader', 'sql' => 'SELECT meta_value FROM main.wp_termmeta INDEXED BY wp_termmeta_key WHERE term_id = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan193196 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext193196(
    $schemas ?? $schemas193196,
    $statements ?? $statements193196,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next193 temp index rename expires active indexed cursor'] = static function (TestRunner $t) use ($plan193196): void {
    $result = $plan193196([
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_stage_token', 'to' => 'wp_import_stage_token_next193'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next193-196', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next193', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next189', $result['dependencies'][4]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(false, $result['statements']['stage-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['stage-reader']['next_step_action']);
    $t->same(['stage-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next194 attach reporting shard leaves existing readers stable'] = static function (TestRunner $t) use ($plan193196): void {
    $result = $plan193196([
        ['op' => 'attach', 'schema' => 'reporting', 'schema_cookie' => 194, 'tables' => ['wp_reports'], 'indexes' => ['wp_reports_key']],
    ]);

    $t->same(['reporting'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'archive', 'reporting'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next195 archive create index expires writer by cookie'] = static function (TestRunner $t) use ($plan193196): void {
    $result = $plan193196([
        ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_comments_status_next195'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(193, $result['schema_cookies_next']['archive']);
    $t->same(['archive-meta-reader'], $result['retryable_read_statements']);
    $t->same(['archive-comments-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next196 uncommitted wal frame ignored before current-source reset'] = static function (TestRunner $t) use ($plan193196): void {
    $result = $plan193196([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 196, 'table' => 'wp_termmeta', 'commit' => false],
    ]);

    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same(['stage-reader', 'archive-meta-reader', 'termmeta-reader', 'unqualified-options-reader', 'archive-comments-writer'], $result['stable_statements']);
    $t->same(false, $result['requires_reprepare']);
};

$tests['attach temp wal schema cache current source next193 196 duplicate index renames consolidate'] = static function (TestRunner $t) use ($plan193196): void {
    $result = $plan193196([
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_stage_token', 'to' => 'wp_import_stage_token_next193'],
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_stage_token', 'to' => 'wp_import_stage_token_next193'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(97, $result['schema_cookies_next']['temp']);
};

return $tests;
