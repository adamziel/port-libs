<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext;
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
$header = substr_replace($header, pack('N', 5), 28, 4);
$header = substr_replace($header, pack('N', 5), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_plugin_expr', 'wp_options', 5, "CREATE INDEX wp_options_plugin_expr ON wp_options(json_extract(option_value, '$.plugin'), option_name COLLATE nocase)"],
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

$database = implode('', [
    SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, $schemaRows[0][4]),
    $record('index', 'wp_options_plugin_expr', 'wp_options', 5, $schemaRows[1][4]),
]);

$page = SQLitePragmaQuickcheckIndexRootpageCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_plugin_expr)',
    $database,
    0,
    135,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'blocked'
        || $page['quickcheck']['index_xinfo'] !== 3
        || $page['quickcheck']['rootpage_errors'] !== 1
        || $page['quickcheck']['needs_integrity_check'] !== true
        || ($page['rows'][$page['count'] - 1]['quickcheck_requires_integrity_check'] ?? null) !== true
    ) {
        fwrite(STDERR, "application-pragma-quickcheck-index-rootpage-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-quickcheck-index-rootpage-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-quickcheck-index-rootpage-current-source-next',
    'applicationUse' => 'Resume copied wp_options expression-index quick_check pages only while index_xinfo metadata and sqlite_schema rootpage state still identify the same database image.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'quickcheck' => $page['quickcheck'],
    'last_row' => $page['rows'][$page['count'] - 1]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
