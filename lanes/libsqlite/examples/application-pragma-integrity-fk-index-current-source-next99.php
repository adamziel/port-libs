<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_posts', 'wp_posts', 2, 'CREATE TABLE wp_posts(ID INTEGER PRIMARY KEY)', 1),
        $record('table', 'wp_postmeta', 'wp_postmeta', 3, 'CREATE TABLE wp_postmeta(meta_id INTEGER PRIMARY KEY, post_id INTEGER)', 2),
    ],
    [
        $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
        $record('index', 'wp_option_names_name_u', 'wp_option_names', 5, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
        $record('table', 'wp_options', 'wp_options', 6, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
    ],
);
$catalog->attach('archive', '/tmp/wp-options-archive.sqlite', [
    $record('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 1),
    $record('index', 'wp_archive_option_names_name_u', 'wp_option_names', 8, 'CREATE UNIQUE INDEX wp_archive_option_names_name_u ON wp_option_names(name COLLATE NOCASE)', 2),
    $record('table', 'wp_options', 'wp_options', 9, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)', 3),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_posts' => [['rowid' => 1, 'ID' => 1]],
            'wp_postmeta' => [
                ['rowid' => 11, 'meta_id' => 11, 'post_id' => 1],
                ['rowid' => 12, 'meta_id' => 12, 'post_id' => 404],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_postmeta', 'parent' => 'wp_posts', 'columns' => [['child' => 'post_id', 'parent' => 'ID']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_id' => 1, 'option_name' => 'SITEURL'],
                ['rowid' => 'temp-2', 'option_id' => 2, 'option_name' => 'missing_temp'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-2', 'option_id' => 2, 'option_name' => 'missing_archive'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'collation' => 'nocase']]],
        ],
    ],
];

$database = str_repeat("\0", 512);
$database = substr_replace($database, "SQLite format 3\0", 0, 16);
$database = substr_replace($database, pack('n', 512), 16, 2);
$database[18] = "\x01";
$database[19] = "\x01";
$database = substr_replace($database, pack('N', 1), 28, 4);
$database = substr_replace($database, pack('N', 1), 56, 4);

$first = SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor($database, $schemas, $catalog, 0, 3, 'PRAGMA quick_check');
$second = SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor(
    $database,
    $schemas,
    $catalog,
    3,
    3,
    'PRAGMA quick_check',
    ['source_id' => $first['source_id'], 'next_offset' => $first['next_offset']],
);

$summary = [
    'scenario' => 'copied wp_options PRAGMA integrity FK index current-source next99 cursor',
    'source_id' => $first['source_id'],
    'first_page' => [
        'count' => $first['count'],
        'next' => $first['next'],
        'schemas' => $first['current']['schemas'],
        'blocking' => $first['next_state']['blocking'],
    ],
    'second_page' => [
        'count' => $second['count'],
        'first_schema' => $second['rows'][0]['schema'],
        'first_rowid' => $second['rows'][0]['rowid'],
        'last_schema' => $second['rows'][2]['schema'],
        'last_rowid' => $second['rows'][2]['rowid'],
    ],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['first_page']['count'] === 3);
    assert($summary['first_page']['next']['offset'] === 3);
    assert($summary['first_page']['schemas'] === ['main', 'temp', 'archive']);
    assert($summary['first_page']['blocking'] === ['foreign_key_check']);
    assert($summary['second_page']['first_schema'] === 'temp');
    assert($summary['second_page']['first_rowid'] === 'temp-2');
    assert($summary['second_page']['last_schema'] === 'archive');
    assert($summary['second_page']['last_rowid'] === 'archive-2');
    echo "application-pragma-integrity-fk-index-current-source-next99 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
