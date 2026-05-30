<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext;
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
$header = substr_replace($header, pack('N', 7), 28, 4);
$header = substr_replace($header, pack('N', 7), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ['table', 'wp_term_taxonomy', 'wp_term_taxonomy', 6, 'CREATE TABLE wp_term_taxonomy(term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER)'],
    ['table', 'wp_terms', 'wp_terms', 7, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY)'],
];
$schemaCells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);

$pointerMap = str_repeat("\0", $pageSize);
$writePointer = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize) {
        throw new RuntimeException('pointer-map entry is outside the example page');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};
$pointerMap = $writePointer($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $writePointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 7);
$pointerMap = $writePointer($pointerMap, 7, SQLitePointerMapEntry::ROOT_PAGE, 0);

$database = implode('', [
    SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $name, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    'table',
    $name,
    $name,
    $root,
    'CREATE TABLE ' . $name,
    $root,
);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('wp_options', 4),
    $record('wp_option_names', 5),
    $record('wp_term_taxonomy', 6),
    $record('wp_terms', 7),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_option'],
            ],
            'wp_terms' => [['rowid' => 1, 'term_id' => 1]],
            'wp_term_taxonomy' => [
                ['rowid' => 11, 'term_taxonomy_id' => 11, 'term_id' => 1],
                ['rowid' => 12, 'term_taxonomy_id' => 12, 'term_id' => 404],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
            ['id' => 2, 'table' => 'wp_term_taxonomy', 'parent' => 'wp_terms', 'columns' => [['child' => 'term_id', 'parent' => 'term_id', 'affinity' => 'integer']]],
        ],
    ],
];

$page = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page($database, $schemas, $catalog);

echo json_encode([
    'scenario' => 'copied wp_options/wp_terms pragma rootpage pointer-map foreign-key current source next122',
    'status' => $page['status'],
    'violations' => $page['current']['foreign_key_violations'],
    'pointer_map_conflicts' => $page['current']['pointer_map_conflicts'],
    'blocking' => $page['next_state']['blocking'],
    'first_row' => $page['rows'][0],
    'last_row' => $page['rows'][count($page['rows']) - 1],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
