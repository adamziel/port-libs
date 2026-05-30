<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 1),
    $record('table', 'wp_blogs', 'wp_blogs', 5, 'CREATE TABLE wp_blogs(blog_id INTEGER PRIMARY KEY)', 2),
    $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT REFERENCES wp_option_names(name) ON UPDATE CASCADE, blog_id INTEGER REFERENCES wp_blogs(blog_id), option_value TEXT, autoload TEXT)', 3),
    $record('index', 'wp_options_name_autoload', 'wp_options', 7, 'CREATE INDEX wp_options_name_autoload ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 4),
]);
$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_blogs' => [['rowid' => 1, 'blog_id' => 1]],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'blog_id' => 1, 'autoload' => 'yes'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_option_name', 'blog_id' => 1, 'autoload' => 'no'],
                ['rowid' => 3, 'option_id' => 3, 'option_name' => 'siteurl', 'blog_id' => 404, 'autoload' => 'no'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_blogs', 'columns' => [['child' => 'blog_id', 'parent' => 'blog_id', 'affinity' => 'integer']]],
        ],
    ],
];

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::page155(
    $catalog,
    $schemas,
    'PRAGMA main.index_xinfo(wp_options_name_autoload)',
    'PRAGMA main.foreign_key_list(wp_options)',
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    20,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($page['status'] === 'ok');
    assert($page['current']['index_xinfo'] === 3);
    assert($page['current']['foreign_key_list'] === 2);
    assert($page['current']['foreign_key_check'] === 2);
    assert($page['rows'][0]['phase'] === 'index_xinfo');
    assert($page['rows'][5]['phase'] === 'foreign_key_list');
    assert($page['rows'][7]['phase'] === 'foreign_key_check');
    echo "application-pragma-index-xinfo-foreignkey-current-source-next155 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA index_xinfo plus foreign_key_list/check current-source next155',
    'status' => $page['status'],
    'total' => $page['total'],
    'counts' => $page['current'],
    'source' => $page['current_source'],
    'first_rows' => array_slice($page['rows'], 0, 8),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
