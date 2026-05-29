<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas118120 = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 99, 'commit' => false],
        ],
        'tables' => ['wp_options'],
        'indexes' => ['wp_options_autoload_name'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['wp_options_stage'],
        'indexes' => ['wp_options_stage_name'],
        'temp' => true,
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 9,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$statements118120 = [
    ['name' => 'main-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?'],
    ['name' => 'temp-writer', 'sql' => 'UPDATE temp.wp_options_stage INDEXED BY wp_options_stage_name SET option_value = ? WHERE option_name = ?'],
    ['name' => 'archive-reader', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name GLOB ?'],
];

$plan118120 = static fn (?array $events = null, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext118120(
    $schemas ?? $schemas118120,
    $statements ?? $statements118120,
    $events ?? [
        ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_rolled_back', 'commit' => false],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_temp_rolled_back', 'commit' => false],
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
    ],
);

$tests = [];

$tests['attach temp wal schema cache current source next118 120 ignores rolled back wal and temp schema writes'] = static function (TestRunner $t) use ($plan118120): void {
    $result = $plan118120();

    $t->same('attach-wal-temp-schema-cache-current-source-next118-120', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next118', $result['dependencies'][0]);
    $t->same(1, $result['event_count']);
    $t->same(41, $result['schema_cookies_next']['main']);
    $t->same(5, $result['schema_cookies_next']['temp']);
    $t->same(10, $result['schema_cookies_next']['archive']);
    $t->same(['archive-reader'], $result['expired_statements']);
    $t->same(['main-reader', 'temp-writer'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next119 keeps committed wal duplicate once'] = static function (TestRunner $t) use ($plan118120): void {
    $result = $plan118120([
        ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_options'],
        ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_options'],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options_stage', 'commit' => false],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(42, $result['schema_cookies_next']['main']);
    $t->same(5, $result['schema_cookies_next']['temp']);
    $t->same(['main-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next120 preserves attach and index handoff changes'] = static function (TestRunner $t) use ($plan118120): void {
    $result = $plan118120([
        ['op' => 'wal_commit', 'schema' => 'main', 'table' => 'wp_discarded', 'commit' => false],
        ['op' => 'attach', 'schema' => 'media', 'schema_cookie' => 3, 'tables' => ['wp_options'], 'indexes' => ['wp_media_option_name']],
        ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ], [
        ['name' => 'future-main-index', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_future_name WHERE option_name = ?'],
        ['name' => 'media-reader', 'sql' => 'SELECT option_name FROM media.wp_options INDEXED BY wp_media_option_name WHERE option_name = ?'],
    ]);

    $t->same(2, $result['event_count']);
    $t->same(['temp', 'main', 'archive', 'media'], $result['search_order_next']);
    $t->same(true, $result['statements']['future-main-index']['index_transitions'][0]['next_found']);
    $t->same('__detached__', $result['statements']['media-reader']['schema_transitions'][0]['current_schema']);
    $t->same('media', $result['statements']['media-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['future-main-index', 'media-reader'], $result['expired_statements']);
};

return $tests;
