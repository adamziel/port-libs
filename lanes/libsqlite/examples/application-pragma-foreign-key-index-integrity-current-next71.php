<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaForeignKeyIndexIntegrityYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$records = [
    $record('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, source TEXT)', 1),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 3, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE nocase)', 2),
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, plugin_code TEXT)', 3),
    $record('table', 'wp_plugin_codes', 'wp_plugin_codes', 5, 'CREATE TABLE wp_plugin_codes(code TEXT COLLATE NOCASE)', 4),
    $record('index', 'wp_plugin_codes_code', 'wp_plugin_codes', 6, 'CREATE INDEX wp_plugin_codes_code ON wp_plugin_codes(code COLLATE nocase)', 5),
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
        ['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase'],
    ]],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_plugin_codes', 'columns' => [
        ['child' => 'plugin_code', 'parent' => 'code', 'collation' => 'nocase'],
    ]],
];

$tables = [
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl', 'source' => 'core'],
    ],
    'wp_plugin_codes' => [
        ['rowid' => 1, 'code' => 'akismet'],
    ],
    'wp_options' => [
        ['rowid' => 10, 'option_id' => 10, 'option_name' => 'SITEURL', 'plugin_code' => 'akismet'],
        ['rowid' => 11, 'option_id' => 11, 'option_name' => 'missing_plugin_option', 'plugin_code' => 'missing-code'],
    ],
];

echo json_encode(
    SQLitePragmaForeignKeyIndexIntegrityYield::page($records, $foreignKeys, $tables, 0, 71),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
