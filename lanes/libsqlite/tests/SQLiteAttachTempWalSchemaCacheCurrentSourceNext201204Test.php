<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas201204 = [
    'main' => [
        'schema_cookie' => 201,
        'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'],
        'indexes' => ['wp_options_name', 'wp_posts_date', 'wp_termmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 104,
        'tables' => ['wp_import_stage', 'wp_options'],
        'indexes' => ['wp_import_stage_token', 'wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 200,
        'tables' => ['wp_comments', 'wp_commentmeta'],
        'indexes' => ['wp_comments_post_id', 'wp_commentmeta_key'],
        'file' => '/srv/wp/archive-next201.sqlite',
    ],
];

$statements201204 = [
    ['name' => 'active-main-posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts INDEXED BY wp_posts_date WHERE post_date > ?', 'active' => true],
    ['name' => 'temp-stage-reader', 'sql' => 'SELECT rowid FROM temp.wp_import_stage INDEXED BY wp_import_stage_token WHERE token = ?'],
    ['name' => 'archive-commentmeta-reader', 'sql' => 'SELECT meta_value FROM archive.wp_commentmeta INDEXED BY wp_commentmeta_key WHERE meta_key = ?'],
    ['name' => 'unqualified-comments-reader', 'sql' => 'SELECT comment_ID FROM wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'unqualified-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-comments-writer', 'sql' => 'UPDATE archive.wp_comments SET comment_approved = ? WHERE comment_ID = ?'],
];

$plan201204 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas201204,
    $statements ?? $statements201204,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next201 attach schema changes search order without expiring existing readers'] = static function (TestRunner $t) use ($plan201204): void {
    $result = $plan201204([
        ['op' => 'attach', 'schema' => 'analytics', 'schema_cookie' => 1, 'tables' => ['wp_events'], 'indexes' => ['wp_events_name'], 'file' => '/srv/wp/analytics.sqlite'],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][4]);
    $t->same(['analytics'], $result['changed_schemas']);
    $t->same(['temp', 'main', 'analytics', 'archive'], $result['search_order_next']);
    $t->same([], $result['expired_statements']);
    $t->same('schema_cache_stable', $result['status']);
};

$tests['attach temp wal schema cache current source next202 drop temp table expires temp indexed reader only'] = static function (TestRunner $t) use ($plan201204): void {
    $result = $plan201204([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_import_stage'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(105, $result['schema_cookies_next']['temp']);
    $t->same(['temp-stage-reader', 'unqualified-options-reader'], $result['expired_statements']);
    $t->same(false, $result['statements']['temp-stage-reader']['schema_transitions'][0]['next_found']);
    $t->same('sqlite_schema_then_reprepare_read_statement', $result['statements']['temp-stage-reader']['next_step_action']);
};

$tests['attach temp wal schema cache current source next203 archive index rebuild expires indexed reads and blocks writer'] = static function (TestRunner $t) use ($plan201204): void {
    $result = $plan201204([
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_commentmeta_key'],
        ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_commentmeta_key_v2'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(202, $result['schema_cookies_next']['archive']);
    $t->same(['archive-commentmeta-reader', 'unqualified-comments-reader', 'archive-comments-writer'], $result['expired_statements']);
    $t->same(false, $result['statements']['archive-commentmeta-reader']['index_transitions'][0]['next_found']);
    $t->same(['archive-comments-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next204 active main rename finishes current source then reprepare'] = static function (TestRunner $t) use ($plan201204): void {
    $result = $plan201204([
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_posts', 'to' => 'wp_posts_next204'],
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 204, 'table' => 'wp_postmeta', 'commit' => false],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(['active-main-posts-reader'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['active-main-posts-reader']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-main-posts-reader']['next_step_action']);
    $t->same(false, $result['statements']['active-main-posts-reader']['schema_transitions'][0]['next_found']);
};

return $tests;
