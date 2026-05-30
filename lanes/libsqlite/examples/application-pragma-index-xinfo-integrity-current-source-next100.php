<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoIntegrityCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowId): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)', 2),
]);

$pageSize = 1024;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 6), 28, 4);
$header = substr_replace($header, pack('N', 4), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
];
$schemaPage = SQLiteTableLeafPage::assemble(
    array_map(static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)), $schemaRows, array_keys($schemaRows)),
    $pageSize,
    100,
    $header,
);

$pointerMap = str_repeat("\0", $pageSize);
$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 5);

$database = $schemaPage
    . $pointerMap
    . SQLiteTableLeafPage::assemble([], $pageSize)
    . SQLiteTableLeafPage::assemble([], $pageSize)
    . SQLiteTableLeafPage::assemble([], $pageSize)
    . SQLiteTableLeafPage::assemble([], $pageSize);

$page0 = SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name)',
    $database,
    '796e75f2553d88aeff452968c875521a537dba2d',
    'pragma-index-xinfo-integrity-current-source-next100',
    0,
    3,
);
$page1 = SQLitePragmaIndexXinfoIntegrityCurrentSourceYield::page(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name)',
    $database,
    '796e75f2553d88aeff452968c875521a537dba2d',
    'pragma-index-xinfo-integrity-current-source-next100',
    3,
    3,
);

$payload = [
    'scenario' => 'copied wp_options PRAGMA index_xinfo plus integrity current-source next100 pagination',
    'page0' => $page0,
    'page1' => $page1,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($page0['status'] !== 'blocked' || $page0['current']['metadata_rows'] !== 3 || $page0['current']['integrity_errors'] !== 2) {
        throw new RuntimeException('Unexpected PRAGMA index_xinfo integrity current-source summary');
    }
    if (($page1['rows'][0]['status'] ?? null) !== 'integrity_error' || ($page1['rows'][0]['index'] ?? null) !== 'wp_options_name') {
        throw new RuntimeException('Unexpected PRAGMA index_xinfo integrity current-source page handoff');
    }
    echo "application-pragma-index-xinfo-integrity-current-source-next100 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
