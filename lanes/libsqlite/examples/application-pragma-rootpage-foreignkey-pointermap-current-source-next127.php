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
$header = substr_replace($header, pack('N', 8), 28, 4);
$header = substr_replace($header, pack('N', 8), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ['table', 'wp_options', 'wp_options', 7, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
];
$schemaCells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);

$writePointer = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);
    if ($offset < 0 || $offset + 5 > $pageSize) {
        throw new RuntimeException('pointer-map entry is outside the example page');
    }

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $writePointer($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $writePointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 7);
$pointerMap = $writePointer($pointerMap, 7, SQLitePointerMapEntry::BTREE_PAGE, 8);
$pointerMap = $writePointer($pointerMap, 8, SQLitePointerMapEntry::ROOT_PAGE, 0);

$database = implode('', [
    SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
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
]);
$catalog->attach('archive', '/tmp/wp-archive.sqlite', [
    $record('wp_options', 7),
    $record('wp_option_names', 8),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 'main-siteurl', 'option_id' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'main-missing', 'option_id' => 2, 'option_name' => 'missing_main'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
    'archive' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'legacy_siteurl']],
            'wp_options' => [
                ['rowid' => 'archive-siteurl', 'option_id' => 1, 'option_name' => 'legacy_siteurl'],
                ['rowid' => 'archive-missing', 'option_id' => 2, 'option_name' => 'missing_archive'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$main = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page(
    $database,
    $schemas,
    $catalog,
    "SELECT * FROM pragma_foreign_key_check('main.wp_options')",
);
$archive = SQLitePragmaRootpagePointerMapForeignKeyCurrentSourceNext::page(
    $database,
    $schemas,
    $catalog,
    "SELECT * FROM pragma_foreign_key_check('archive.wp_options')",
);

echo json_encode([
    'scenario' => 'copied wp_options archive rootpage foreign-key pointer-map current source next127',
    'main_child_rootpage' => $main['rows'][0]['child_rootpage'],
    'main_child_rootpage_status' => $main['rows'][0]['child_rootpage_status'],
    'archive_child_rootpage' => $archive['rows'][0]['child_rootpage'],
    'archive_child_rootpage_status' => $archive['rows'][0]['child_rootpage_status'],
    'archive_pointer_map_conflicts' => $archive['current']['pointer_map_conflicts'],
    'archive_blocking' => $archive['next_state']['blocking'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
