<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas169172 = [
    'main' => [
        'schema_cookie' => 169,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 69,
        'tables' => ['wp_plugin_stage'],
        'indexes' => ['wp_plugin_stage_slug'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 99,
        'tables' => ['wp_options_archive', 'wp_posts_archive'],
        'indexes' => ['wp_archive_options_name', 'wp_archive_posts_date'],
        'file' => '/srv/wp/archive-next169.sqlite',
    ],
];

$statements169172 = [
    ['name' => 'active-archive-posts-index-reader', 'sql' => 'SELECT ID FROM archive.wp_posts_archive INDEXED BY wp_archive_posts_date WHERE post_date > ?', 'active' => true],
    ['name' => 'archive-comments-reader', 'sql' => 'SELECT comment_ID FROM archive.wp_comments WHERE comment_post_ID = ?'],
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'archive-options-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
    ['name' => 'stage-reader', 'sql' => 'SELECT option_name FROM temp.wp_plugin_stage WHERE option_name LIKE ?'],
];

$plan169172 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext169172(
    $schemas ?? $schemas169172,
    $statements ?? $statements169172,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next169 attached index drop expires active indexed reader'] = static function (TestRunner $t) use ($plan169172): void {
    $result = $plan169172([
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_posts_date'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next169-172', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next169', $result['dependencies'][0]);
    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['active-archive-posts-index-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['active-archive-posts-index-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-archive-posts-index-reader']['next_step_action']);
    $t->same(['active-archive-posts-index-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next170 attached wal commit resolves missing qualified table'] = static function (TestRunner $t) use ($plan169172): void {
    $result = $plan169172([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 170, 'table' => 'wp_comments'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(170, $result['schema_cookies_next']['archive']);
    $t->same(false, $result['statements']['archive-comments-reader']['schema_transitions'][0]['current_found']);
    $t->same(true, $result['statements']['archive-comments-reader']['schema_transitions'][0]['next_found']);
    $t->same(['active-archive-posts-index-reader', 'archive-comments-reader'], $result['retryable_read_statements']);
    $t->same(['archive-options-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next171 temp drop exposes main table to unqualified reader'] = static function (TestRunner $t) use ($plan169172): void {
    $schemas = [
        'main' => ['schema_cookie' => 171, 'tables' => ['wp_options'], 'indexes' => ['wp_options_name']],
        'temp' => ['schema_cookie' => 71, 'tables' => ['wp_options'], 'indexes' => [], 'temp' => true],
    ];

    $result = $plan169172([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ], null, $schemas);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['options-reader']['schema_transitions'][0]['resolution_changed']);
    $t->same(['options-reader', 'stage-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next172 detach archive expires active and writer statements'] = static function (TestRunner $t) use ($plan169172): void {
    $result = $plan169172([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['active-archive-posts-index-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-options-writer']['schema_transitions'][0]['next_found']);
    $t->same(['active-archive-posts-index-reader'], $result['active_current_snapshot_statements']);
    $t->same(['archive-options-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(['options-reader', 'stage-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next169 172 rejects unknown attached schema write'] = static function (TestRunner $t) use ($plan169172): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan169172([
        ['op' => 'wal_commit', 'schema' => 'network', 'table' => 'wp_options'],
    ]));
};

return $tests;
