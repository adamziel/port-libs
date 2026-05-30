<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 3, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
]);
$catalog->attach('wp.archive', '/tmp/wp.archive.sqlite', [
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE, blog_id INTEGER, PRIMARY KEY(name, blog_id)) WITHOUT ROWID', 1),
    $record('index', 'wp.archive.option_names.name.u', 'wp_option_names', 6, 'CREATE UNIQUE INDEX "wp.archive.option_names.name.u" ON wp_option_names(name COLLATE NOCASE DESC)', 2),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
]);

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'main-1', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'main-missing', 'option_id' => 2, 'option_name' => 'missing_main'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
    'wp.archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 'archive-parent', 'name' => 'legacy_siteurl', 'blog_id' => 1]],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-missing-1', 'option_id' => 11, 'option_name' => 'missing_1'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 8, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$xinfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('wp.archive.option_names.name.u', 'wp.archive')");
$page = SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalog, 0, 118, 'PRAGMA quick_check');

if (($argv[1] ?? '') === '--self-test') {
    assert($xinfo['schema'] === 'wp.archive');
    assert($xinfo['rows'][0]['coll'] === 'NOCASE');
    assert($page['current']['index_admissions'] === 2);
    assert($page['current']['foreign_key_violations'] === 2);
    assert($page['rows'][2]['schema'] === 'wp.archive');
    assert($page['rows'][2]['index'] === 'wp.archive.option_names.name.u');
    echo "application-pragma-index-xinfo-current-source-next118 self-test passed\n";
    return;
}

echo json_encode([
    'index_schema' => $xinfo['schema'],
    'index_rows' => $xinfo['rows'],
    'integrity_status' => $page['status'],
    'source_id' => $page['source_id'],
    'current' => $page['current'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
