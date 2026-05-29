<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempSchemaCachePlan;

$schemas165168 = [
    'main' => [
        'schema_cookie' => 165,
        'tables' => ['wp_options', 'wp_posts', 'wp_terms'],
        'indexes' => ['wp_options_name', 'wp_posts_date'],
    ],
    'temp' => [
        'schema_cookie' => 65,
        'tables' => ['wp_plugin_stage'],
        'indexes' => [],
        'temp' => true,
    ],
    'archive' => [
        'schema_cookie' => 95,
        'tables' => ['wp_terms_archive'],
        'indexes' => ['wp_archive_terms_slug'],
        'file' => '/srv/wp/archive-next165.sqlite',
    ],
];

$statements165168 = [
    ['name' => 'active-temp-stage-index-reader', 'sql' => 'SELECT option_name FROM temp.wp_plugin_stage INDEXED BY wp_plugin_stage_slug WHERE option_name = ?', 'active' => true],
    ['name' => 'posts-delete-writer', 'sql' => 'DELETE FROM wp_posts WHERE post_status = ?'],
    ['name' => 'network-options-reader', 'sql' => 'SELECT option_value FROM network.wp_options WHERE option_name = ?'],
    ['name' => 'terms-reader', 'sql' => 'SELECT term_id FROM wp_terms WHERE slug = ?'],
    ['name' => 'archive-terms-reader', 'sql' => 'SELECT term_id FROM archive.wp_terms_archive WHERE slug = ?'],
];

$plan165168 = static fn (array $events, ?array $statements = null, ?array $schemas = null): array => SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan(
    $schemas ?? $schemas165168,
    $statements ?? $statements165168,
    $events,
);

$tests = [];

$tests['attach temp wal schema cache current source next165 temp create index expires active indexed reader'] = static function (TestRunner $t) use ($plan165168): void {
    $result = $plan165168([
        ['op' => 'create_index', 'schema' => 'temp', 'index' => 'wp_plugin_stage_slug'],
    ]);

    $t->same('attach-wal-temp-schema-cache-consolidated', $result['operation']);
    $t->same('sqlite-attach-temp-wal-schema-cache-consolidated', $result['dependencies'][0]);
    $t->same(['temp'], $result['changed_schemas']);
    $t->same(false, $result['statements']['active-temp-stage-index-reader']['index_transitions'][0]['current_found']);
    $t->same(true, $result['statements']['active-temp-stage-index-reader']['index_transitions'][0]['next_found']);
    $t->same('finish_current_source_then_sqlite_schema_on_reset', $result['statements']['active-temp-stage-index-reader']['next_step_action']);
    $t->same(['active-temp-stage-index-reader'], $result['active_current_snapshot_statements']);
};

$tests['attach temp wal schema cache current source next166 drop main table blocks writer retry'] = static function (TestRunner $t) use ($plan165168): void {
    $result = $plan165168([
        ['op' => 'drop_table', 'schema' => 'main', 'table' => 'wp_posts'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same('main', $result['statements']['posts-delete-writer']['schema_transitions'][0]['current_schema']);
    $t->same(true, $result['statements']['posts-delete-writer']['schema_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['posts-delete-writer']['schema_transitions'][0]['next_found']);
    $t->same(['posts-delete-writer'], $result['write_statements_blocked_before_retry']);
    $t->same(['terms-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next167 attach network resolves qualified reader'] = static function (TestRunner $t) use ($plan165168): void {
    $result = $plan165168([
        ['op' => 'attach', 'schema' => 'network', 'schema_cookie' => 167, 'tables' => ['wp_options'], 'indexes' => ['wp_network_options_name'], 'file' => '/srv/wp/network-next167.sqlite'],
    ]);

    $t->same(['network'], $result['changed_schemas']);
    $t->same('__detached__', $result['statements']['network-options-reader']['schema_transitions'][0]['current_schema']);
    $t->same(false, $result['statements']['network-options-reader']['schema_transitions'][0]['current_found']);
    $t->same('network', $result['statements']['network-options-reader']['schema_transitions'][0]['next_schema']);
    $t->same(true, $result['statements']['network-options-reader']['schema_transitions'][0]['next_found']);
    $t->same(['network-options-reader'], $result['retryable_read_statements']);
};

$tests['attach temp wal schema cache current source next168 rename main table leaves archive qualified reader stable'] = static function (TestRunner $t) use ($plan165168): void {
    $result = $plan165168([
        ['op' => 'rename_table', 'schema' => 'main', 'from' => 'wp_terms', 'to' => 'wp_terms_2026'],
    ]);

    $t->same(['main'], $result['changed_schemas']);
    $t->same(true, $result['statements']['terms-reader']['schema_transitions'][0]['current_found']);
    $t->same(false, $result['statements']['terms-reader']['schema_transitions'][0]['next_found']);
    $t->same(true, $result['statements']['archive-terms-reader']['schema_transitions'][0]['current_found']);
    $t->same(true, $result['statements']['archive-terms-reader']['schema_transitions'][0]['next_found']);
    $t->same(['active-temp-stage-index-reader', 'network-options-reader', 'archive-terms-reader'], $result['stable_statements']);
};

$tests['attach temp wal schema cache current source next165 168 rejects duplicate attach schema'] = static function (TestRunner $t) use ($plan165168): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan165168([
        ['op' => 'attach', 'schema' => 'archive', 'tables' => ['wp_options']],
    ]));
};

return $tests;
