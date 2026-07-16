<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$sourceHandoffSchemas = [
    'main' => [
        'schema_cookie' => 149,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_autoload_name', 'wp_posts_type_status'],
    ],
    'temp' => [
        'schema_cookie' => 49,
        'tables' => ['wp_import_queue'],
        'indexes' => ['wp_import_queue_key'],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 79,
        'tables' => ['wp_options_archive', 'wp_terms'],
        'indexes' => ['wp_archive_option_name', 'wp_terms_slug'],
        'file' => '/srv/wp/archive-current.sqlite',
    ],
];

$sourceHandoffStatements = [
    ['name' => 'options-reader', 'sql' => 'SELECT option_value FROM wp_options WHERE option_name = ?'],
    ['name' => 'main-posts-active-reader', 'sql' => 'SELECT post_title FROM main.wp_posts WHERE ID = ?', 'active' => true],
    ['name' => 'temp-indexed-reader', 'sql' => 'SELECT payload FROM temp.wp_import_queue INDEXED BY wp_import_queue_key WHERE import_key = ?'],
    ['name' => 'archive-writer', 'sql' => 'UPDATE archive.wp_options_archive SET option_value = ? WHERE option_name = ?'],
];

$sourceHandoffPlan = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $sourceHandoffSchemas,
    $statements ?? $sourceHandoffStatements,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache source handoff temp table shadows unqualified option reader'] = static function (TestRunner $t) use ($sourceHandoffPlan): void {
    $result = $sourceHandoffPlan([
        ['op' => 'schema_write', 'schema' => 'temp', 'table' => 'wp_options'],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same('main', $result['statements']['options-reader']['schema_transitions'][0]['current_schema']);
    $t->same('temp', $result['statements']['options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(['options-reader', 'temp-indexed-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache source handoff main wal drop lets active reader finish snapshot'] = static function (TestRunner $t) use ($sourceHandoffPlan): void {
    $result = $sourceHandoffPlan([
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_posts'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(150, $result['schema_cookies_next']['main']);
    $t->same('main', $result['statements']['main-posts-active-reader']['schema_transitions'][0]['current_schema']);
    $t->same(false, $result['statements']['main-posts-active-reader']['schema_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['main-posts-active-reader']['next_step_action']);
    $t->same(['main-posts-active-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache source handoff temp rename index expires indexed temp reader'] = static function (TestRunner $t) use ($sourceHandoffPlan): void {
    $result = $sourceHandoffPlan([
        ['op' => 'rename_index', 'schema' => 'temp', 'from' => 'wp_import_queue_key', 'to' => 'wp_import_queue_key_rebuilt'],
    ]);

    $t->same(['temp'], $result['changed_schemas']);
    $t->same(true, $result['statements']['temp-indexed-reader']['index_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['temp-indexed-reader']['index_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['temp-indexed-reader']['index_transitions'][0]['requires_reprepare']);
    $t->same(['temp-indexed-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache source handoff detach archive blocks stale archive writer'] = static function (TestRunner $t) use ($sourceHandoffPlan): void {
    $result = $sourceHandoffPlan([
        ['op' => 'detach', 'schema' => 'archive'],
    ]);

    $t->same(['archive'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['archive-writer']['schema_transitions'][0]['next_schema']);
    $t->same(false, $result['statements']['archive-writer']['schema_transitions'][0]['next_found']);
    $t->same(['archive-writer'], $result['write_statements_blocked_before_retry']);
};

$tests['attach temp wal schema cache source handoff ignores rolled back wal frames'] = static function (TestRunner $t) use ($sourceHandoffPlan): void {
    $result = $sourceHandoffPlan([
        ['op' => 'wal_commit', 'schema' => 'main', 'schema_cookie' => 152, 'commit' => false],
        ['op' => 'wal_commit', 'schema' => 'archive', 'schema_cookie' => 152, 'commit' => false],
    ]);

    $t->same('schema_cache_stable', $result['status']);
    $t->same(0, $result['event_count']);
    $t->same([], $result['changed_schemas']);
    $t->same(['options-reader', 'main-posts-active-reader', 'temp-indexed-reader', 'archive-writer'], $result['stable_statements']);
};

$tests['attach temp wal schema cache source handoff rejects detach of source schema'] = static function (TestRunner $t) use ($sourceHandoffPlan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $sourceHandoffPlan([
        ['op' => 'detach', 'schema' => 'main'],
    ]));
};

return $tests;
