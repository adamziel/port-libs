<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteIndexLeafPage.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLitePragmaRowCursor.php';
require_once __DIR__ . '/../src/SQLitePragmaSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require_once __DIR__ . '/../src/SQLitePragmaIntegrityCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaForeignKeyIntegrity.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexXinfoIntegrityRootYield.php';
require_once __DIR__ . '/../src/SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaIntegrityIndexRootpageCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaIndexIntegrityCursorCurrentSourceNext.php';
require_once __DIR__ . '/../src/SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name TEXT PRIMARY KEY)', 2),
    $record('index', 'wp_options_name', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 3),
    $record('index', 'wp_options_json_expr', 'wp_options', 7, "CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 4),
]);

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
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_options_name', 'wp_options', 6, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name, autoload)'],
    ['index', 'wp_options_json_expr', 'wp_options', 7, "CREATE INDEX wp_options_json_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload DESC)"],
];
$schemaCell = static fn (array $values, int $rowid): string => SQLiteTableLeafCell::encode($rowid, SQLiteRecord::encode($values));
$pointerMap = str_repeat("\0", $pageSize);
$putPointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
foreach ([[3, SQLitePointerMapEntry::BTREE_PAGE, 4], [4, SQLitePointerMapEntry::ROOT_PAGE, 0], [5, SQLitePointerMapEntry::ROOT_PAGE, 0], [6, SQLitePointerMapEntry::ROOT_PAGE, 0], [7, SQLitePointerMapEntry::BTREE_PAGE, 4]] as $entry) {
    $pointerMap = $putPointer($pointerMap, $entry[0], $entry[1], $entry[2]);
}
$pages = [
    1 => SQLiteTableLeafPage::assemble(
        array_map(static fn (array $row, int $index): string => $schemaCell($row, $index + 1), $schemaRows, array_keys($schemaRows)),
        $pageSize,
        100,
        $header,
    ),
    2 => $pointerMap,
    3 => SQLiteTableLeafPage::assemble([], $pageSize),
    4 => SQLiteTableLeafPage::assemble([], $pageSize),
    5 => SQLiteTableLeafPage::assemble([], $pageSize),
    6 => SQLiteIndexLeafPage::assemble([], $pageSize),
    7 => SQLiteIndexLeafPage::assemble([], $pageSize),
];
$database = implode('', $pages);
$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_plugin', 'option_value' => '{"plugin":"missing"}', 'autoload' => 'no'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$first = SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database,
    $schemas,
    'PRAGMA foreign_key_check',
    0,
    6,
);
$second = SQLitePragmaQuickcheckIndexForeignKeyCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database,
    $schemas,
    'PRAGMA foreign_key_check',
    6,
    138,
    'PRAGMA quick_check',
    false,
    $first['next'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($first['next'] === ['source_id' => $first['source_id'], 'offset' => 6]);
    assert($second['status'] === 'blocked');
    assert($second['current']['quick_check_errors'] === 1);
    assert($second['current']['foreign_key_violations'] === 1);
    assert($second['rows'][6]['phase'] === 'foreign_key_check');
    echo "wordpress-pragma-quickcheck-index-foreignkey-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'copied wp_options quick_check plus index_list/index_xinfo and foreign_key_check current-source pagination',
    'source_id' => $first['source_id'],
    'current' => $second['current'],
    'next_state' => $second['next_state'],
    'first_page' => [
        'count' => $first['count'],
        'next' => $first['next'],
        'rows' => $first['rows'],
    ],
    'second_page' => [
        'count' => $second['count'],
        'complete' => $second['complete'],
        'rows' => $second['rows'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
