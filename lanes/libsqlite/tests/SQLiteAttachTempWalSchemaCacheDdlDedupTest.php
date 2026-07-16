<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$baseSchemas = [
    'main' => [
        'schema_cookie' => 40,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 41, 'commit' => true],
        ],
        'tables' => ['wp_options'],
        'indexes' => ['wp_options_autoload_name'],
        'file' => '/srv/wp/current.sqlite',
    ],
    'archive' => [
        'schema_cookie' => 9,
        'tables' => ['wp_options'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive.sqlite',
    ],
];

$baseStatements = [
    ['name' => 'main-indexed-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE autoload = ?'],
    ['name' => 'future-index-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_future_name WHERE option_name = ?'],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name GLOB ?'],
];

$baseEvents = [
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
    ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
];

$planFactory = static fn (?array $events = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $baseSchemas,
    $baseStatements,
    $events ?? $baseEvents,
);

$tests = [];

$tests['attach temp wal schema cache ddl dedup duplicate index ddl advances cookies once'] = static function (TestRunner $t) use ($planFactory): void {
    $result = $planFactory();

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(3, $result['event_count']);
    $t->same(43, $result['schema_cookies_next']['main']);
    $t->same(10, $result['schema_cookies_next']['archive']);
    $t->same(['main-indexed-reader', 'future-index-reader', 'archive-indexed-reader'], $result['expired_statements']);
};

$tests['attach temp wal schema cache ddl dedup duplicate schema write group expires once'] = static function (TestRunner $t) use ($planFactory): void {
    $result = $planFactory([
        ['op' => 'schema_write', 'schema' => 'main', 'table' => 'wp_plugin_state'],
        ['op' => 'schema_write', 'schema' => 'main', 'table' => 'wp_plugin_state'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(42, $result['schema_cookies_next']['main']);
    $t->same(true, $result['statements']['main-indexed-reader']['schema_transitions'][0]['schema_cookie_changed']);
};

$tests['attach temp wal schema cache ddl dedup keeps distinct DDL events ordered'] = static function (TestRunner $t) use ($planFactory): void {
    $result = $planFactory([
        ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
        ['op' => 'create_index', 'schema' => 'main', 'index' => 'wp_options_future_name'],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_options'],
    ]);

    $t->same(3, $result['event_count']);
    $t->same(44, $result['schema_cookies_next']['main']);
    $t->same(false, $result['statements']['main-indexed-reader']['schema_transitions'][0]['next_found']);
};

return $tests;
