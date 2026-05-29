<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan;

$schemas129132 = [
    'main' => [
        'schema_cookie' => 70,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name'],
    ],
    'temp' => [
        'schema_cookie' => 15,
        'tables' => ['wp_import_queue', 'wp_options_shadow'],
        'indexes' => ['wp_import_queue_name'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 30,
        'tables' => ['wp_options', 'wp_terms'],
        'indexes' => ['wp_archive_option_name'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements129132 = [
    ['name' => 'temp-shadow', 'sql' => 'SELECT option_value FROM wp_options_shadow WHERE option_name = ?'],
    ['name' => 'archive-options-indexed', 'sql' => 'SELECT option_name FROM archive.wp_options INDEXED BY wp_archive_option_name WHERE option_name = ?'],
    ['name' => 'archive-terms', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?'],
    ['name' => 'main-options-write', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
];

$plan129132 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext129132(
    $schemas ?? $schemas129132,
    $statements ?? $statements129132,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next129 temp rename moves unqualified prepared reader'] = static function (TestRunner $t) use ($plan129132): void {
    $result = $plan129132([
        ['op' => 'rename_table', 'schema' => 'temp', 'from' => 'wp_options_shadow', 'to' => 'wp_options'],
    ], [
        ['name' => 'unqualified-options', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
        ['name' => 'old-temp-shadow', 'sql' => 'SELECT option_value FROM wp_options_shadow WHERE option_name = ?'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next129-132', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next129', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('main', $result['statements']['unqualified-options']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['unqualified-options']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['old-temp-shadow']['schema_transitions'][0]['next_found']);
    $t->same(['unqualified-options', 'old-temp-shadow'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next130 drop attached index invalidates indexed by reader'] = static function (TestRunner $t) use ($plan129132): void {
    $result = $plan129132([
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_archive_option_name'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(true, $result['statements']['archive-options-indexed']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['archive-options-indexed']['index_transitions'][0]['next_found']);
    $t->same(['archive-options-indexed', 'archive-terms'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next131 attached table drop expires active snapshot only after current step'] = static function (TestRunner $t) use ($plan129132): void {
    $result = $plan129132([
        ['op' => 'drop_table', 'schema' => 'archive', 'table' => 'wp_terms'],
    ], [
        ['name' => 'active-archive-terms', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
        ['name' => 'main-options-write', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(false, $result['statements']['active-archive-terms']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-archive-terms']['next_step_action']);
    $t->same(['active-archive-terms'], $result['active_current_snapshot_statements']);
    $t->same(['main-options-write'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next132 committed main wal write blocks stale write retry'] = static function (TestRunner $t) use ($plan129132): void {
    $result = $plan129132([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 72, 'table' => 'wp_options'],
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options_shadow', 'commit' => false],
    ], [
        ['name' => 'main-options-write', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
        ['name' => 'temp-shadow', 'sql' => 'SELECT option_value FROM wp_options_shadow WHERE option_name = ?'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(72, $result['schema_cookies_next']['main']);
    $t->same(['main-options-write'], $result['write_statements_blocked_before_retry']);
    $t->same(['temp-shadow'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next129 132 rejects detached drop'] = static function (TestRunner $t) use ($plan129132): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan129132([
        ['op' => 'drop_table', 'schema' => 'missing', 'table' => 'wp_options'],
    ]));
};

return $tests;
