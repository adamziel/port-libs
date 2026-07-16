<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
        $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX wp_options_name ON wp_options(option_name)', 2),
    ],
    [
        $record('table', 'wp_temp_options', 'wp_temp_options', 2, 'CREATE TEMP TABLE wp_temp_options(option_name TEXT)', 1),
    ],
);
$catalog->attach('site', '/srv/wp-content/site.sqlite', [
    $record('table', 'wp_options', 'wp_options', 8, 'CREATE TABLE wp_options(blog_id INTEGER, option_name TEXT, autoload TEXT)', 1),
    $record('index', 'site_options_name', 'wp_options', 9, 'CREATE INDEX site_options_name ON wp_options(option_name)', 2),
]);

$snapshot = $catalog->schemaCacheResolutionSnapshot(
    ['wp_new_options', 'wp_options'],
    ['site_new_name'],
    'site',
);

$plan = $catalog->applySchemaDdlCurrentSource(
    'site',
    [
        'CREATE TABLE wp_new_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)',
        'CREATE INDEX site_new_name ON wp_new_options(option_name)',
    ],
    107,
    $snapshot,
    [
        ['id' => 'site-new-options-select', 'schema_cookie' => 107, 'sql' => 'SELECT option_name FROM wp_new_options'],
    ],
);

$summary = [
    'operation' => $plan['operation'],
    'status' => $plan['status'],
    'schema' => $plan['schema'],
    'before_generation' => $plan['before_generation'],
    'after_generation' => $plan['after_generation'],
    'schema_cookie_after' => $plan['ddl_plan']['after_schema_cookie'],
    'invalidated_prepared' => $plan['ddl_plan']['invalidated_prepared'],
    'changed_tables' => $plan['invalidation']['changed_tables'],
    'changed_indexes' => $plan['invalidation']['changed_indexes'],
    'new_table_schema' => $plan['invalidation']['table_changes']['wp_new_options']['after']['schema'],
    'new_table_rootpage' => $plan['invalidation']['table_changes']['wp_new_options']['after']['rootpage'],
    'new_index_schema' => $plan['invalidation']['index_changes']['site_new_name']['after']['schema'],
    'database_schemas' => array_column($plan['database_list'], 'name'),
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'schema_cache_expired');
    assert($summary['schema'] === 'site');
    assert($summary['schema_cookie_after'] === 109);
    assert($summary['invalidated_prepared'] === ['site-new-options-select']);
    assert($summary['changed_tables'] === ['wp_new_options']);
    assert($summary['changed_indexes'] === ['site_new_name']);
    assert($summary['new_table_schema'] === 'site');
    assert($summary['new_table_rootpage'] === 10);
    assert($summary['new_index_schema'] === 'site');
    assert(in_array('sqlite-attach-schema-cache-ddl-current-source', $summary['dependencies'], true));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
