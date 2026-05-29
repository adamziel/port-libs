<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext;
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
    ['index', 'wp_options_name_expr', 'wp_options', 5, "CREATE INDEX wp_options_name_expr ON wp_options(lower(option_name), json_extract(option_value, '$.plugin'))"],
    ['index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'],
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
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, $schemaRows[0][4]),
    $record('index', 'wp_options_name_expr', 'wp_options', 5, $schemaRows[1][4]),
    $record('index', 'wp_options_autoload', 'wp_options', 6, $schemaRows[2][4]),
]);

$page = SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_name_expr)',
    $database,
    0,
    129,
    'PRAGMA quick_check(1)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'blocked'
        || $page['current']['index_xinfo'] !== 3
        || $page['current']['quick_check_errors'] !== 1
        || ($page['rows'][$page['count'] - 1]['source'] ?? null) !== 'quick_check'
    ) {
        fwrite(STDERR, "wordpress-pragma-index-rootpage-quickcheck-current-source-next-page self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-rootpage-quickcheck-current-source-next-page self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-rootpage-quickcheck-current-source-next-page',
    'wordpressUse' => 'Resume copied wp_options expression-index metadata together with bounded PRAGMA quick_check rootpage blockers from the same current database image.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current' => $page['current'],
    'first_index_row' => $page['rows'][0]['message'] ?? null,
    'first_quick_check_row' => $page['rows'][$page['count'] - 1]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
