<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan;

$schemas153156 = [
    'main' => [
        'schema_cookie' => 153,
        'tables' => ['wp_options', 'wp_posts', 'wp_termmeta'],
        'indexes' => ['wp_options_autoload_name', 'wp_termmeta_key'],
    ],
    'temp' => [
        'schema_cookie' => 53,
        'tables' => ['wp_import_queue', 'wp_options_shadow'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 83,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-next153.sqlite',
    ],
];

$statements153156 = [
    ['name' => 'termmeta-reader', 'sql' => 'SELECT meta_value FROM wp_termmeta WHERE meta_key = ?'],
    ['name' => 'archive-terms-reader', 'sql' => 'SELECT slug FROM archive.wp_terms INDEXED BY wp_terms_slug WHERE term_id = ?', 'active' => true],
    ['name' => 'options-writer', 'sql' => 'UPDATE main.wp_options SET option_value = ? WHERE option_name = ?'],
    ['name' => 'new-site-reader', 'sql' => 'SELECT option_value FROM site.wp_options WHERE option_name = ?'],
];

$plan153156 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCacheCurrentSourceNext92Plan::currentSourceNext153156(
    $schemas ?? $schemas153156,
    $statements ?? $statements153156,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next153 main drop expires unqualified termmeta reader'] = static function (TestRunner $t) use ($plan153156): void {
    $result = $plan153156([
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_termmeta'],
    ]);

    $t->same('attach-wal-temp-schema-cache-current-source-next153-156', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-current-source-next153', $result['dependencies'][0]);
    $t->same(['main'], $result['changed_schemas']);
    $t->same(false, $result['statements']['termmeta-reader']['schema_transitions'][0]['next_found']);
    $t->same(['termmeta-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next154 archive index drop lets active reader finish'] = static function (TestRunner $t) use ($plan153156): void {
    $result = $plan153156([
        ['op' => 'drop_index', 'schema' => 'archive', 'index' => 'wp_terms_slug'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same(84, $result['schema_cookies_next']['archive']);
    $t->same(true, $result['statements']['archive-terms-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['archive-terms-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['archive-terms-reader']['next_step_action']);
    $t->same(['archive-terms-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next155 attach site resolves prepared qualified reader'] = static function (TestRunner $t) use ($plan153156): void {
    $result = $plan153156([
        ['op' => 'attach', 'schema' => 'site', 'schema_cookie' => 155, 'tables' => ['wp_options'], 'indexes' => ['wp_options_name'], 'file' => '/srv/wp/site155.sqlite'],
    ]);

    $t->same(['site'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['new-site-reader']['schema_transitions'][0]['current_schema']);
    $t->same('site', $result['statements']['new-site-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['new-site-reader']['schema_transitions'][0]['next_found']);
    $t->same(['new-site-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next156 main wal create index blocks stale writer until retry'] = static function (TestRunner $t) use ($plan153156): void {
    $result = $plan153156([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 156, 'indexes' => ['wp_options_name_next156']],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(156, $result['schema_cookies_next']['main']);
    $t->same(true, $result['statements']['options-writer']['requires_reprepare']);
    $t->same(['options-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache current source next153 156 filters rolled back wal commits'] = static function (TestRunner $t) use ($plan153156): void {
    $result = $plan153156([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 156, 'commit' => false],
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_termmeta'],
    ]);

    $t->same(1, $result['event_count']);
    $t->same(154, $result['schema_cookies_next']['main']);
    $t->same(['termmeta-reader', 'options-writer'], $result['expired_statements']);
};

$tests['attach temp wal schema cache current source next153 156 rejects duplicate attach'] = static function (TestRunner $t) use ($plan153156): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan153156([
        ['op' => 'attach', 'schema' => 'archive', 'tables' => ['wp_options']],
    ]));
};

return $tests;
