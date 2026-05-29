<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas157160 = [
    'main' => [
        'schema_cookie' => 157,
        'tables' => ['wp_options', 'wp_posts', 'wp_postmeta'],
        'indexes' => ['wp_options_autoload_name', 'wp_postmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 57,
        'tables' => ['wp_cache_queue', 'wp_postmeta'],
        'indexes' => ['wp_temp_postmeta_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 87,
        'tables' => ['wp_options_archive', 'wp_posts_archive'],
        'indexes' => ['wp_archive_posts_date'],
        'file' => '/srv/wp/archive-next157.sqlite',
    ],
];

$statements157160 = [
    ['name' => 'shadow-postmeta-reader', 'sql' => 'SELECT meta_value FROM wp_postmeta WHERE post_id = ?'],
    ['name' => 'archive-posts-writer', 'sql' => 'UPDATE archive.wp_posts_archive SET post_title = ? WHERE ID = ?'],
    ['name' => 'network-options-reader', 'sql' => 'SELECT option_value FROM network.wp_options WHERE option_name = ?'],
    ['name' => 'active-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?', 'active' => true],
];

$plan157160 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext157160(
    $schemas ?? $schemas157160,
    $statements ?? $statements157160,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next157 temp shadow drop reveals main postmeta'] = static function (TestRunner $t) use ($plan157160): void {
    $result = $plan157160([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_postmeta'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next157-160', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next157', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['shadow-postmeta-reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['shadow-postmeta-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['shadow-postmeta-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next158 archive rename blocks qualified writer'] = static function (TestRunner $t) use ($plan157160): void {
    $result = $plan157160([
        ['op' => 'rename_table', 'schema' => 'archive', 'from' => 'wp_posts_archive', 'to' => 'wp_posts_archive_2026'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(88, $result['schema_cookies_next']['archive']);
    $t->same(false, $result['statements']['archive-posts-writer']['schema_transitions'][0]['next_found']);
    $t->same('sqlite_schema_before_write_retry', $result['statements']['archive-posts-writer']['next_step_action']);
    $t->same(['archive-posts-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next159 attach network resolves detached qualified reader'] = static function (TestRunner $t) use ($plan157160): void {
    $result = $plan157160([
        ['op' => 'attach', 'schema' => 'network', 'schema_cookie' => 159, 'tables' => ['wp_options'], 'indexes' => ['wp_options_name'], 'file' => '/srv/wp/network159.sqlite'],
    ]);

    $t->same(['network'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['network-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('network', $result['statements']['network-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['network-options-reader']['schema_transitions'][0]['next_found']);
    $t->same(['network-options-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next160 wal index drop lets active current source finish'] = static function (TestRunner $t) use ($plan157160): void {
    $result = $plan157160([
        ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(158, $result['schema_cookies_next']['main']);
    $t->same(true, $result['statements']['active-options-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['active-options-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-options-reader']['next_step_action']);
    $t->same(['active-options-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next157 160 filters rolled back wal schema commits'] = static function (TestRunner $t) use ($plan157160): void {
    $result = $plan157160([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 160, 'indexes' => ['wp_options_next160'], 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_postmeta'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(157, $result['schema_cookies_next']['main']);
    $t->same(58, $result['schema_cookies_next']['temp']);
    $t->same(['shadow-postmeta-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next157 160 rejects duplicate attach'] = static function (TestRunner $t) use ($plan157160): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan157160([
        ['op' => 'attach', 'schema' => 'archive', 'tables' => ['wp_options']],
    ]));
};

return $tests;
