<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas137140 = [
    'main' => [
        'schema_cookie' => 90,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 91, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 30,
        'tables' => ['wp_options'],
        'indexes' => ['wp_temp_options_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 50,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements137140 = [
    ['name' => 'temp-shadow-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-qualified-reader', 'sql' => 'SELECT post_title FROM main.wp_posts WHERE post_type = ?'],
    ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'main-indexed-writer', 'sql' => 'UPDATE main.wp_options INDEXED BY wp_options_autoload_name SET option_value = ? WHERE option_name = ?'],
];

$plan137140 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext137140(
    $schemas ?? $schemas137140,
    $statements ?? $statements137140,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next137 drop temp shadow falls through to main'] = static function (TestRunner $t) use ($plan137140): void {
    $result = $plan137140([
        ['op' => 'drop_table', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next137-140', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next137', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('temp', $result['statements']['temp-shadow-reader']['schema_transitions'][0]['current_schema']);
    $t->same('main', $result['statements']['temp-shadow-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['temp-shadow-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next138 rename main table invalidates qualified reader'] = static function (TestRunner $t) use ($plan137140): void {
    $result = $plan137140([
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_posts', 'to' => 'wp_posts_next138'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same('main', $result['statements']['main-qualified-reader']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['main-qualified-reader']['schema_transitions'][0]['next_found']);
    $t->same(['main-qualified-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next139 drop indexed by blocks stale writer retry'] = static function (TestRunner $t) use ($plan137140): void {
    $result = $plan137140([
        ['op' => 'drop_index', 'schema' => 'main', 'index' => 'wp_options_autoload_name'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(true, $result['statements']['main-indexed-writer']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['main-indexed-writer']['index_transitions'][0]['next_found']);
    $t->same(['main-indexed-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next140 archive create index expires active snapshot on reset'] = static function (TestRunner $t) use ($plan137140): void {
    $result = $plan137140([
        ['op' => 'create_index', 'schema' => 'archive', 'index' => 'wp_terms_slug_next140'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['archive-active-reader']['schema_transitions'][0]['schema_cookie_changed']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['archive-active-reader']['next_step_action']);
    $t->same(['archive-active-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next137 140 ignores rolled back temp write'] = static function (TestRunner $t) use ($plan137140): void {
    $result = $plan137140([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options_stage', 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same(['temp-shadow-reader', 'main-qualified-reader', 'archive-active-reader', 'main-indexed-writer'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next137 140 rejects missing create index target'] = static function (TestRunner $t) use ($plan137140): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan137140([
        ['op' => 'create_index', 'schema' => 'missing', 'index' => 'wp_missing_idx'],
    ]));
};

return $tests;
