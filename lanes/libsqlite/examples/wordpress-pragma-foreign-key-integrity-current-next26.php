<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrity;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    1,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER REFERENCES wp_sites(blog_id), option_name TEXT)'),
        $record('table', 'wp_sites', 'wp_sites', 3, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)'),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_name TEXT PRIMARY KEY REFERENCES wp_option_names(name), option_value TEXT)'),
        $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
    ],
);
$catalog->attach('archive', '/srv/archive.sqlite', [
    $record('table', 'wp_options_archive', 'wp_options_archive', 6, 'CREATE TABLE wp_options_archive(option_name TEXT, blog_id INTEGER REFERENCES wp_blogs(blog_id))'),
    $record('table', 'wp_blogs', 'wp_blogs', 7, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY)'),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_sites' => [
                ['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test'],
            ],
            'wp_options' => [
                ['rowid' => 10, 'option_id' => 10, 'blog_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 11, 'option_id' => 11, 'blog_id' => 9, 'option_name' => 'missing_network_site'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
                ['rowid' => 2, 'name' => 'home'],
            ],
            'wp_options' => [
                ['rowid' => 20, 'option_name' => 'siteurl', 'option_value' => 'https://example.test'],
                ['rowid' => 21, 'option_name' => 'missing_plugin_option', 'option_value' => '1'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_blogs' => [
                ['rowid' => 1, 'blog_id' => 1],
            ],
            'wp_options_archive' => [
                ['rowid' => 30, 'option_name' => 'old_siteurl', 'blog_id' => 1],
                ['rowid' => 31, 'option_name' => 'orphaned_plugin', 'blog_id' => 7],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options_archive', 'parent' => 'wp_blogs', 'columns' => [['child' => 'blog_id', 'parent' => 'blog_id', 'affinity' => 'integer']]],
        ],
    ],
];

$tempCurrent = SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(wp_options)', $schemas, $catalog);
$mainPinned = SQLitePragmaForeignKeyIntegrity::execute('PRAGMA main.foreign_key_check(wp_options)', $schemas, $catalog);
$archive = SQLitePragmaForeignKeyIntegrity::execute('PRAGMA foreign_key_check(wp_options_archive)', $schemas, $catalog);

echo json_encode([
    'scenario' => 'copied wp_options foreign-key integrity preflight',
    'current_target_schema' => $tempCurrent['schema'],
    'current_target_rows' => $tempCurrent['rows'],
    'main_pinned_rows' => $mainPinned['rows'],
    'archive_rows' => $archive['rows'],
    'dependency' => 'native PHP schema-current PRAGMA foreign_key_check planner; no ext/sqlite required',
], JSON_PRETTY_PRINT) . "\n";
