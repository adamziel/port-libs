<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 1024;
$header = static function (int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', 8), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelist), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ['table', 'wp_option_names', 'wp_option_names', 7, 'CREATE TABLE wp_option_names(name text primary key)'],
];
$database = static function (array $pointers, int $largestRootPage, int $firstFreelist = 0, int $freelistCount = 0) use ($pageSize, $header, $schemaRows): string {
    $schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));
    $pages = [
        1 => SQLiteTableLeafPage::assemble(
            array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
            $pageSize,
            100,
            $header($largestRootPage, $firstFreelist, $freelistCount),
        ),
        2 => str_repeat("\0", $pageSize),
        3 => SQLiteFreelistTrunkPage::assemble(null, [8], $pageSize),
        4 => SQLiteTableLeafPage::assemble([], $pageSize),
        5 => SQLiteIndexLeafPage::assemble([], $pageSize),
        6 => SQLiteIndexLeafPage::assemble([], $pageSize),
        7 => SQLiteTableLeafPage::assemble([], $pageSize),
        8 => SQLiteTableLeafPage::assemble([], $pageSize),
    ];
    foreach ($pointers as [$pageNumber, $type, $parent]) {
        $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
    }
    ksort($pages);

    return implode('', $pages);
};

$currentDatabase = $database([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::BTREE_PAGE, 7],
], 7, 3, 2);
$nextDatabase = $database([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::FREE_PAGE, 0],
], 7);
$record = static fn (string $type, string $name, string $table, int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, $schemaRows[0][4], 1),
    $record('index', 'wp_options_name', 'wp_options', 5, $schemaRows[1][4], 2),
    $record('table', 'wp_option_names', 'wp_option_names', 7, $schemaRows[2][4], 3),
]);
$schemas = static function (int $missing): array {
    $rows = [['rowid' => 1, 'option_name' => 'siteurl']];
    for ($i = 1; $i <= $missing; $i++) {
        $rows[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
        'main' => [
            'tables' => [
                'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
                'wp_options' => $rows,
            ],
            'foreignKeys' => [
                ['id' => 157, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                    ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
                ]],
            ],
        ],
    ];
};

$result = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage157(
    $catalog,
    $currentDatabase,
    $schemas(3),
    $catalog,
    $nextDatabase,
    $schemas(0),
    'PRAGMA main.index_xinfo(wp_options_name)',
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    157,
    'PRAGMA integrity_check',
    false,
    null,
    $catalog,
    $catalog,
);

assert($result['status'] === 'ok');
assert($result['current']['foreign_key'] === 3);
assert($result['next_counts']['foreign_key'] === 0);
assert($result['delta']['foreign_key_cleared'] === true);
assert($result['delta']['integrity_cleared'] === true);

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA index_xinfo plus foreign_key_check current-source next157',
    'wordpressUse' => 'Resume copied wp_options index metadata and foreign-key diagnostics only when PRAGMA index_xinfo rows, database bytes, schema rows, and FK catalog state still match the current source.',
    'status' => $result['status'],
    'current' => $result['current'],
    'next_counts' => $result['next_counts'],
    'delta' => $result['delta'],
    'dependencyClosure' => 'no new support component needed; reuses native PRAGMA index_xinfo, integrity root diagnostics, foreign_key_check row collection, and attached schema catalog hashing',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
