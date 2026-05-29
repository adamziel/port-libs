<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas121124 = [
    'main' => [
        'schema_cookie' => 50,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status_date'],
    ],
    'temp' => [
        'schema_cookie' => 7,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 12,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
    ],
];

$statements121124 = [
    ['name' => 'unqualified-options', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'temp-options', 'sql' => 'SELECT option_value FROM temp.wp_options WHERE option_name = ?'],
    ['name' => 'archive-active', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?', 'active' => true],
    ['name' => 'main-write-indexed', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
];

$plan121124 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext121124(
    $schemas ?? $schemas121124,
    $statements ?? $statements121124,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next121 temp create shadows unqualified main'] = static function (TestRunner $t) use ($plan121124): void {
    $result = $plan121124([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next121-124', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next121', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('main', $result['statements']['unqualified-options']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['unqualified-options']['schema_transitions'][0]['next_schema']);
    $t->same(['unqualified-options', 'temp-options'], $result['expired_statements']);
    $t->same(['archive-active', 'main-write-indexed'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next122 temp drop restores main lookup'] = static function (TestRunner $t) use ($plan121124, $schemas121124): void {
    $withTempOptions = $schemas121124;
    $withTempOptions['temp']['schema_cookie'] = 8;
    $withTempOptions['temp']['tables'][] = 'wp_options';

    $result = $plan121124([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ], [
        ['name' => 'unqualified-shadowed', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
        ['name' => 'temp-qualified', 'sql' => 'SELECT option_value FROM temp.wp_options WHERE option_name = ?'],
    ], $withTempOptions);

    $t->same('temp', $result['statements']['unqualified-shadowed']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['unqualified-shadowed']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['temp-qualified']['schema_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['temp-qualified']['schema_transitions'][0]['next_found']);
    $t->same(['unqualified-shadowed', 'temp-qualified'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next123 detach active statement finishes current snapshot'] = static function (TestRunner $t) use ($plan121124): void {
    $result = $plan121124([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(['archive-active'], $result['active_current_snapshot_statements']);
    $t->same('SQLITE_OK', $result['statements']['archive-active']['sqlite_result_on_current_step']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['archive-active']['next_step_action']);
    $t->same('__detached__', $result['statements']['archive-active']['schema_transitions'][0]['next_schema']);
};

$tests['attach temp wal schema cache current source next124 rename table and index blocks stale write retry'] = static function (TestRunner $t) use ($plan121124): void {
    $result = $plan121124([
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_posts', 'to' => 'wp_posts_next'],
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_options_autoload_name', 'to' => 'wp_options_autoload_name_next'],
    ], [
        ['name' => 'posts-reader', 'sql' => 'SELECT ID FROM main.wp_posts WHERE post_type = ?'],
        ['name' => 'main-write-indexed', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(false, $result['statements']['posts-reader']['schema_transitions'][0]['next_found']);
    $t->same(false, $result['statements']['main-write-indexed']['index_transitions'][0]['next_found']);
    $t->same(['posts-reader'], $result['retryable_read_statements']);
    $t->same(['main-write-indexed'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next121 124 rejects empty rename target'] = static function (TestRunner $t) use ($plan121124): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan121124([
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_posts', 'to' => ''],
    ]));
};

return $tests;
