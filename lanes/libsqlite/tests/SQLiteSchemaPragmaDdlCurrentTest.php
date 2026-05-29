<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaPragmaDdlCurrent;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$records = static fn (): array => [
    $record('table', 'wp_options', 'wp_options', 2, "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT 'yes')", 1),
    $record('index', 'wp_options_autoload', 'wp_options', 3, "CREATE INDEX wp_options_autoload ON wp_options(autoload) WHERE autoload = 'yes'", 2),
    $record('view', 'wp_autoloaded_options', 'wp_autoloaded_options', 0, "CREATE VIEW wp_autoloaded_options AS SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes'", 3),
    $record('trigger', 'wp_options_ai', 'wp_options', 0, "CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN SELECT count(*) FROM wp_options; END", 4),
];

$state = static fn (): array => [
    'main' => ['schema_version' => 41, 'data_version' => 9, 'change_counter' => 9],
    'temp' => ['schema_version' => 5, 'data_version' => 2, 'change_counter' => 2],
];

$plan = static fn (?array $ddl = null, ?array $pragmaState = null, string $schema = 'main'): array => SQLiteSchemaPragmaDdlCurrent::apply(
    $records(),
    $ddl ?? [
        'ALTER TABLE wp_options RENAME TO wp_site_options',
        "CREATE INDEX wp_site_options_name ON wp_site_options(option_name) WHERE autoload = 'yes'",
    ],
    $pragmaState ?? $state(),
    $schema,
    [
        ['id' => 'autoload-reader-current', 'schema_cookie' => 41, 'sql' => 'SELECT option_name FROM wp_options WHERE autoload = ?'],
        ['id' => 'already-stale-reader', 'schema_cookie' => 40, 'sql' => 'SELECT option_id FROM wp_options'],
        ['id' => 'future-reader', 'schema_cookie' => 43, 'sql' => 'SELECT option_id FROM wp_site_options'],
    ],
);

$cases = [
    'status ok' => [static fn (): mixed => $plan()['status'], 'ok'],
    'schema main' => [static fn (): mixed => $plan()['schema'], 'main'],
    'ddl plan before cookie from pragma' => [static fn (): mixed => $plan()['ddl_plan']['before_schema_cookie'], 41],
    'ddl plan after cookie increments by changed ddl' => [static fn (): mixed => $plan()['ddl_plan']['after_schema_cookie'], 43],
    'schema delta tracks changed ddl only' => [static fn (): mixed => $plan()['schema_delta'], 2],
    'schema changed true' => [static fn (): mixed => $plan()['schema_changed'], true],
    'before schema version value' => [static fn (): mixed => $plan()['pragma_before']['schema_version']['value'], 41],
    'after schema version value' => [static fn (): mixed => $plan()['pragma_after']['schema_version']['value'], 43],
    'after schema version reason' => [static fn (): mixed => $plan()['pragma_after']['schema_version']['reason'], 'local_schema_ddl'],
    'before data version value' => [static fn (): mixed => $plan()['pragma_before']['data_version']['value'], 9],
    'after data version remains local current' => [static fn (): mixed => $plan()['pragma_after']['data_version']['value'], 9],
    'local data version unchanged for same connection ddl' => [static fn (): mixed => $plan()['local_data_version_changed'], false],
    'header before schema cookie' => [static fn (): mixed => $plan()['header_before']['schema_cookie'], 41],
    'header before change counter' => [static fn (): mixed => $plan()['header_before']['file_change_counter'], 9],
    'header after schema cookie' => [static fn (): mixed => $plan()['header_after']['schema_cookie'], 43],
    'header after change counter increments with schema cookie' => [static fn (): mixed => $plan()['header_after']['file_change_counter'], 11],
    'version state schema dirty' => [static fn (): mixed => $plan()['version_state']['main']['schema_dirty'], true],
    'version state data dirty false' => [static fn (): mixed => $plan()['version_state']['main']['data_dirty'], false],
    'invalidates stale and current statements' => [static fn (): mixed => $plan()['invalidated_prepared'], ['autoload-reader-current', 'already-stale-reader']],
    'first operation rename' => [static fn (): mixed => $plan()['ddl_plan']['operations'][0]['kind'], 'alter_table_rename'],
    'second operation create index' => [static fn (): mixed => $plan()['ddl_plan']['operations'][1]['kind'], 'create_index'],
    'renamed table pragma sample exists' => [static fn (): mixed => array_key_exists('table_xinfo:wp_site_options', $plan()['pragma_samples']), true],
    'renamed table pragma has four columns' => [static fn (): mixed => count($plan()['pragma_samples']['table_xinfo:wp_site_options']['rows']), 4],
    'renamed table pragma option name preserved' => [static fn (): mixed => $plan()['pragma_samples']['table_xinfo:wp_site_options']['rows'][1]['name'], 'option_name'],
    'renamed table index list sample exists' => [static fn (): mixed => array_key_exists('index_list:wp_site_options', $plan()['pragma_samples']), true],
    'renamed table has two indexes after create' => [static fn (): mixed => count($plan()['pragma_samples']['index_list:wp_site_options']['rows']), 2],
    'created partial index is visible' => [static fn (): mixed => $plan()['pragma_samples']['index_list:wp_site_options']['rows'][1]['name'], 'wp_site_options_name'],
    'created partial index partial flag' => [static fn (): mixed => $plan()['pragma_samples']['index_list:wp_site_options']['rows'][1]['partial'], 1],
    'dependency schema reparse' => [static fn (): mixed => $plan()['dependencies'][0], 'schema-sql-reparse'],
    'dependency schema cookie' => [static fn (): mixed => $plan()['dependencies'][1], 'sqlite-schema-cookie'],
    'dependency catalog' => [static fn (): mixed => $plan()['dependencies'][2], 'pragma-schema-catalog'],
    'dependency schema data version' => [static fn (): mixed => $plan()['dependencies'][3], 'pragma-schema-data-version'],
    'no op create existing keeps delta zero' => [static fn (): mixed => $plan(['CREATE TABLE IF NOT EXISTS wp_options(id INTEGER)'])['schema_delta'], 0],
    'no op create existing keeps schema version' => [static fn (): mixed => $plan(['CREATE TABLE IF NOT EXISTS wp_options(id INTEGER)'])['pragma_after']['schema_version']['value'], 41],
    'no op create existing reports current reason' => [static fn (): mixed => $plan(['CREATE TABLE IF NOT EXISTS wp_options(id INTEGER)'])['pragma_after']['schema_version']['reason'], 'current'],
    'no op create existing keeps header change counter' => [static fn (): mixed => $plan(['CREATE TABLE IF NOT EXISTS wp_options(id INTEGER)'])['header_after']['file_change_counter'], 9],
    'drop table removes pragma rows' => [static fn (): mixed => $plan(['DROP TABLE wp_options'])['pragma_samples']['table_xinfo:wp_options']['rows'], []],
    'drop table removes index list rows' => [static fn (): mixed => $plan(['DROP TABLE wp_options'])['pragma_samples']['index_list:wp_options']['rows'], []],
    'drop table schema delta one' => [static fn (): mixed => $plan(['DROP TABLE wp_options'])['schema_delta'], 1],
    'drop table header cookie bumps once' => [static fn (): mixed => $plan(['DROP TABLE wp_options'])['header_after']['schema_cookie'], 42],
    'add column sample has new column' => [static fn (): mixed => $plan(['ALTER TABLE wp_options ADD COLUMN option_group TEXT DEFAULT "default"'])['pragma_samples']['table_xinfo:wp_options']['rows'][4]['name'], 'option_group'],
    'add column sample default value' => [static fn (): mixed => $plan(['ALTER TABLE wp_options ADD COLUMN option_group TEXT DEFAULT "default"'])['pragma_samples']['table_xinfo:wp_options']['rows'][4]['dflt_value'], '"default"'],
    'create table sample has columns' => [static fn (): mixed => count($plan(['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY, option_id INTEGER, meta_key TEXT)'])['pragma_samples']['table_xinfo:wp_optionmeta']['rows']), 3],
    'temp schema uses temp version' => [static fn (): mixed => $plan(['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY)'], null, 'temp')['ddl_plan']['before_schema_cookie'], 5],
    'temp schema bumps only temp state' => [static fn (): mixed => $plan(['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY)'], null, 'temp')['version_state']['temp']['schema_version'], 6],
    'temp schema preserves main state' => [static fn (): mixed => $plan(['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY)'], null, 'temp')['version_state']['main']['schema_version'], 41],
    'custom schema state seeds missing schema' => [static fn (): mixed => $plan(['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY)'], ['main' => ['schema_version' => 2]], 'archive')['ddl_plan']['before_schema_cookie'], 0],
    'custom missing schema after value' => [static fn (): mixed => $plan(['CREATE TABLE wp_optionmeta(meta_id INTEGER PRIMARY KEY)'], ['main' => ['schema_version' => 2]], 'archive')['pragma_after']['schema_version']['value'], 1],
    'ddl plan records preserve renamed table' => [static fn (): mixed => $plan()['ddl_plan']['records'][0]->name, 'wp_site_options'],
    'ddl plan table count' => [static fn (): mixed => $plan()['ddl_plan']['table_count'], 1],
    'ddl plan index count' => [static fn (): mixed => $plan()['ddl_plan']['index_count'], 2],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['schema pragma ddl current ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
