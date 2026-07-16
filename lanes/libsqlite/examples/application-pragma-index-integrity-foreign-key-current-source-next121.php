<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 6), 28, 4);
$header = substr_replace($header, pack('N', 3), 32, 4);
$header = substr_replace($header, pack('N', 2), 36, 4);
$header = substr_replace($header, pack('N', 6), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_free_root', 'wp_free_root', 6, 'CREATE TABLE wp_free_root(id integer primary key)'],
];
$cells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);

$pointerMap = str_repeat("\0", $pageSize);
$writePointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$pointerMap = $writePointer($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $writePointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::ROOT_PAGE, 0);

$database = implode('', [
    SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql = null): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql ?? 'CREATE ' . strtoupper($type) . ' ' . $name,
    $root,
);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'),
    $record('table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name text primary key)'),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
            ],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'orphaned-option', 'option_name' => 'missing_siteurl'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 121, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$page = SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield::page(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name)',
    $database,
    $schemas,
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    121,
    'PRAGMA integrity_check',
    false,
    null,
    $catalog,
);

echo json_encode([
    'scenario' => 'application-pragma-index-integrity-foreign-key-current-source-next121',
    'status' => $page['status'],
    'total' => $page['total'],
    'index_xinfo' => $page['current']['index_xinfo'],
    'integrity_root' => $page['current']['integrity_root'],
    'foreign_key' => $page['current']['foreign_key'],
    'first_message' => $page['rows'][0]['message'] ?? null,
    'last_message' => $page['rows'][$page['count'] - 1]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
