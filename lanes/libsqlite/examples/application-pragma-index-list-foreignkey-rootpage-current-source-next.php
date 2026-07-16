<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$header = static function (int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 8), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$writePointer = static function (string $page, int $pageNumber, int $type, int $parent) use ($pageSize): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};
$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)'],
    ['index', 'wp_options_name', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['index', 'wp_options_autoload', 'wp_options', 7, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'],
];
$schemaCells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);
$database = static function (bool $clean) use ($pageSize, $header, $writePointer, $schemaCells): string {
    $pointerMap = str_repeat("\0", $pageSize);
    $pointerMap = $writePointer($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $pointerMap = $writePointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $writePointer($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $pointerMap = $writePointer($pointerMap, 7, $clean ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, $clean ? 0 : 6);

    return implode('', [
        SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header($clean ? 7 : 6)),
        $pointerMap,
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteIndexLeafPage::assemble([], $pageSize),
        SQLiteIndexLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
    ]);
};
$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, autoload TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('index', 'wp_options_name', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)', 3),
    $record('index', 'wp_options_autoload', 'wp_options', 7, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)', 4),
]);
$dirtySchemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_option', 'autoload' => 'no'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name']]],
        ],
    ],
];
$cleanSchemas = $dirtySchemas;
$cleanSchemas['main']['tables']['wp_options'] = [['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes']];

$plan = SQLitePragmaIndexListForeignKeyRootpageCurrentSourceNext::currentNextPage(
    $catalog,
    $database(false),
    $dirtySchemas,
    $catalog,
    $database(true),
    $cleanSchemas,
    'PRAGMA main.index_list(wp_options)',
);

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA index_list plus foreign-key rootpage current-source next148',
    'status' => $plan['status'],
    'current_index_rootpage_errors' => $plan['current']['index_rootpage_errors'],
    'current_foreign_key_violations' => $plan['current']['foreign_key_violations'],
    'next_ready' => $plan['next_state']['ready'],
    'cleared' => $plan['delta']['cleared'],
    'indexes' => $plan['next_counts']['indexes'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
