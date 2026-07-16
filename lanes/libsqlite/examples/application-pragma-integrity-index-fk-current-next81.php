<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield;
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
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 2),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 4, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE nocase)', 3),
    $record('table', 'wp_plugin_codes', 'wp_plugin_codes', 5, 'CREATE TABLE wp_plugin_codes(code TEXT COLLATE NOCASE)', 4),
    $record('index', 'wp_plugin_codes_code', 'wp_plugin_codes', 6, 'CREATE INDEX wp_plugin_codes_code ON wp_plugin_codes(code COLLATE nocase)', 5),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, plugin_code TEXT)', 6),
];

$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => ['option_name' => 'name']],
    ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_plugin_codes', 'columns' => ['plugin_code' => 'code']],
];

$tables = [
    'wp_sites' => [
        ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
    ],
    'wp_option_names' => [
        ['rowid' => 1, 'name' => 'siteurl'],
    ],
    'wp_plugin_codes' => [
        ['rowid' => 1, 'code' => 'akismet'],
    ],
    'wp_options' => [
        ['rowid' => 10, 'option_id' => 10, 'blog_id' => 1, 'option_name' => 'SITEURL', 'plugin_code' => 'akismet'],
        ['rowid' => 11, 'option_id' => 11, 'blog_id' => 404, 'option_name' => 'missing-name', 'plugin_code' => 'missing-code'],
    ],
];

echo json_encode(
    [
        'scenario' => 'copied wp_options PRAGMA integrity/index/FK current-next81',
        'dependency' => 'native PHP PRAGMA integrity/index/foreign-key current-next pagination; no ext/sqlite required',
        'page' => SQLitePragmaIntegrityIndexForeignKeyCurrentNextYield::page(str_repeat("\0", 20), $records, $foreignKeys, $tables, 0, 81),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
