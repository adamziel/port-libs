<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas133136 = [
    'main' => [
        'schema_cookie' => 80,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 81, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 20,
        'tables' => ['wp_options_stage'],
        'indexes' => ['wp_options_stage_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 40,
        'tables' => ['wp_options', 'wp_terms'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements133136 = [
    ['name' => 'future-site-reader', 'sql' => 'SELECT option_value FROM site.wp_2_options WHERE option_name = ?'],
    ['name' => 'archive-indexed-reader', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
    ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'main-indexed-write', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
];

$plan133136 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas133136,
    $statements ?? $statements133136,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next133 attach supplies qualified future reader'] = static function (TestRunner $t) use ($plan133136): void {
    $result = $plan133136([
        ['op' => 'attach', 'schema' => 'site', 'schema_cookie' => 7, 'tables' => ['wp_2_options'], 'indexes' => ['wp_2_options_name']],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(['site'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['future-site-reader']['schema_transitions'][0]['current_schema']);
    $t->same('site', $result['statements']['future-site-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['future-site-reader']['schema_transitions'][0]['next_found']);
    $t->same(['future-site-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next134 detach expires active attached snapshot after current step'] = static function (TestRunner $t) use ($plan133136): void {
    $result = $plan133136([
        ['op' => 'detach', 'schema' => 'archive'],
    ], [
        ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
        ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM main.wp_options WHERE option_name = ?'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-active-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-active-reader']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['archive-active-reader']['next_step_action']);
    $t->same(['archive-active-reader'], $result['active_current_snapshot_statements']);
    $t->same(['main-options-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next135 rename index flips indexed by resolution'] = static function (TestRunner $t) use ($plan133136): void {
    $result = $plan133136([
        ['op' => 'rename_index', 'schema' => 'archive', 'from' => 'wp_archive_option_name', 'to' => 'wp_archive_option_name_next135'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['archive-indexed-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['archive-indexed-reader']['index_transitions'][0]['next_found']);
    $t->same(['archive-indexed-reader', 'archive-active-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next136 committed wal index write blocks stale indexed writer'] = static function (TestRunner $t) use ($plan133136): void {
    $result = $plan133136([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 84, 'indexes' => ['wp_options_pending_name']],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options_stage_shadow', 'commit' => false],
    ], [
        ['name' => 'main-indexed-write', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
        ['name' => 'temp-stage-reader', 'sql' => 'SELECT option_value FROM temp.wp_options_stage WHERE option_name = ?'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(84, $result['schema_cookies_next']['main']);
    $t->same(true, $result['statements']['main-indexed-write']['schema_transitions'][0]['schema_cookie_changed']);
    $t->same(['main-indexed-write'], $result['write_statements_blocked_before_retry']);
    $t->same(['temp-stage-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next133 136 rejects main detach'] = static function (TestRunner $t) use ($plan133136): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan133136([
        ['op' => 'detach', 'schema' => 'main'],
    ]));
};

return $tests;
