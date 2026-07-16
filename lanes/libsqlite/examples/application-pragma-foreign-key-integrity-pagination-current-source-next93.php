<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_option_names', 'wp_option_names', 2, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
        $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
    ],
    [
        $record('table', 'wp_option_names', 'wp_option_names', 4, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
        $record('table', 'wp_options', 'wp_options', 5, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
    ],
);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'),
    $record('table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 2, 'option_name' => 'missing-main'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name']]],
        ],
    ],
    'temp' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'temp-1', 'option_name' => 'siteurl'],
                ['rowid' => 'temp-2', 'option_name' => 'missing-temp'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy-siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-1', 'option_name' => 'legacy-siteurl'],
                ['rowid' => 'archive-2', 'option_name' => 'missing-archive'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 3, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name']]],
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

$page = SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield::page(
    $database,
    $schemas,
    $catalog,
    "SELECT * FROM pragma_foreign_key_check('wp_options')",
    0,
    93,
    'PRAGMA quick_check',
    $catalog->schemaGeneration(),
);
$archive = SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceYield::page(
    $database,
    $schemas,
    $catalog,
    'PRAGMA archive.foreign_key_check(wp_options)',
    0,
    93,
    'PRAGMA quick_check',
    $catalog->schemaGeneration(),
);

echo json_encode([
    'scenario' => 'application-pragma-foreign-key-integrity-pagination-current-source-next93',
    'implicit_schema' => $page['current_source']['schema'],
    'implicit_target_source' => $page['current_source']['target_source'],
    'implicit_rows' => array_column($page['rows'], 'rowid'),
    'archive_schema' => $archive['current_source']['schema'],
    'archive_rows' => array_column($archive['rows'], 'rowid'),
    'resume' => $page['next'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
