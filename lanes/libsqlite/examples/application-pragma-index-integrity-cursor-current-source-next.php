<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexIntegrityCursorCurrentSourceNext;
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
$header = substr_replace($header, pack('N', 6), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)"],
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
$pointerMap = $writePointer($pointerMap, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 4);

$database = implode('', [
    SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, $schemaRows[0][4]),
    $record('index', 'wp_options_name', 'wp_options', 5, $schemaRows[1][4]),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, $schemaRows[2][4]),
]);

$first = SQLitePragmaIndexIntegrityCursorCurrentSourceNext::page($catalog, 'PRAGMA main.index_list(wp_options)', $database, 0, 6);
$second = SQLitePragmaIndexIntegrityCursorCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database,
    6,
    7,
    'PRAGMA integrity_check',
    false,
    $first['next'],
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $first['status'] !== 'blocked'
        || $first['current']['index_list'] !== 2
        || $second['current']['rootpage_errors'] !== 1
        || ($second['rows'][$second['count'] - 1]['page_status'] ?? null) !== 'pointer_map'
    ) {
        fwrite(STDERR, "application-pragma-index-integrity-cursor-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-integrity-cursor-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-integrity-cursor-current-source-next',
    'applicationUse' => 'Resume copied wp_options PRAGMA index_list/index_xinfo/rootpage integrity pagination only when the table index catalog and database image still match the current source.',
    'status' => $first['status'],
    'source_id' => $first['source_id'],
    'current' => $first['current'],
    'first_page_count' => $first['count'],
    'second_page_count' => $second['count'],
    'last_row' => $second['rows'][$second['count'] - 1]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
