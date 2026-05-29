<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas141144 = [
    'main' => [
        'schema_cookie' => 141,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 41,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 71,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$statements141144 = [
    ['name' => 'main-options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-indexed-reader', 'sql' => 'SELECT option_value FROM main.wp_options INDEXED BY wp_options_autoload_name WHERE option_name = ?'],
    ['name' => 'archive-active-reader', 'sql' => 'SELECT name FROM archive.wp_terms WHERE slug = ?', 'active' => true],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
];

$plan141144 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext141144(
    $schemas ?? $schemas141144,
    $statements ?? $statements141144,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next141 create temp shadow moves unqualified reader'] = static function (TestRunner $t) use ($plan141144): void {
    $result = $plan141144([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next141-144', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next141', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('main', $result['statements']['main-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['main-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['main-options-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next142 rename indexed by invalidates read plan'] = static function (TestRunner $t) use ($plan141144): void {
    $result = $plan141144([
        ['op' => 'rename_index', 'schema' => 'main', 'from' => 'wp_options_autoload_name', 'to' => 'wp_options_autoload_next142'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(true, $result['statements']['main-indexed-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['main-indexed-reader']['index_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['main-indexed-reader']['index_transitions'][0]['requires_reprepare']);
    $t->same(['main-options-reader', 'main-indexed-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next143 attached wal commit expires active reader on reset'] = static function (TestRunner $t) use ($plan141144): void {
    $result = $plan141144([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 143, 'indexes' => ['wp_terms_name_next143']],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(143, $result['schema_cookies_next']['archive']);
    $t->same(true, $result['statements']['archive-active-reader']['schema_transitions'][0]['schema_cookie_changed']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['archive-active-reader']['next_step_action']);
    $t->same(['archive-active-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next144 detach archive blocks stale writer retry'] = static function (TestRunner $t) use ($plan141144): void {
    $result = $plan141144([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-writer']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-writer']['schema_transitions'][0]['next_found']);
    $t->same(['archive-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next141 144 ignores rolled back attached wal'] = static function (TestRunner $t) use ($plan141144): void {
    $result = $plan141144([
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 144, 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same(['main-options-reader', 'main-indexed-reader', 'archive-active-reader', 'archive-writer'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next141 144 rejects reserved attach schema'] = static function (TestRunner $t) use ($plan141144): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan141144([
        ['op' => 'attach', 'schema' => 'temp', 'tables' => ['wp_shadow']],
    ]));
};

return $tests;
