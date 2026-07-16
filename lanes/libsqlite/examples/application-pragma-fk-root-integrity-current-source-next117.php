<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
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
    ['index', 'wp_options_alias', 'wp_options', 4, 'CREATE INDEX wp_options_alias ON wp_options(autoload)'],
    ['table', 'wp_free_root', 'wp_free_root', 6, 'CREATE TABLE wp_free_root(id integer primary key)'],
];

$schemaCells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);

$pointerMap = str_repeat("\0", $pageSize);
$writePointer = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$pointerMap = $writePointer($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $writePointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::ROOT_PAGE, 0);

$database = implode('', [
    SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
]);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [
                ['rowid' => 1, 'name' => 'siteurl'],
            ],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'stale-transient', 'option_name' => '_transient_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 117, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$page = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page(
    $database,
    $schemas,
    'PRAGMA main.foreign_key_check(wp_options)',
);

echo json_encode([
    'status' => $page['status'],
    'total' => $page['total'],
    'integrity_root' => $page['current']['integrity_root'],
    'foreign_key' => $page['current']['foreign_key'],
    'first_message' => $page['rows'][0]['message'] ?? null,
    'last_message' => $page['rows'][$page['count'] - 1]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
