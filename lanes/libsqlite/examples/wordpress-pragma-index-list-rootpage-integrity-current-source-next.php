<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext;
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
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)'],
    ['index', 'wp_options_autoload_partial', 'wp_options', 6, "CREATE INDEX wp_options_autoload_partial ON wp_options(autoload, option_name) WHERE autoload = 'yes'"],
    ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null],
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
$pointerMap = $writePointer($pointerMap, 7, SQLitePointerMapEntry::ROOT_PAGE, 0);

$database = implode('', [
    SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, ?string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, $schemaRows[0][4]),
    $record('index', 'wp_options_name', 'wp_options', 5, $schemaRows[1][4]),
    $record('index', 'wp_options_autoload_partial', 'wp_options', 6, $schemaRows[2][4]),
    $record('index', 'sqlite_autoindex_wp_options_1', 'wp_options', 7, null),
]);

$page = SQLitePragmaIndexListRootpageIntegrityCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database,
    0,
    139,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'blocked'
        || $page['current']['index_list'] !== 3
        || $page['current']['unique_indexes'] !== 2
        || $page['current']['partial_indexes'] !== 1
        || $page['current']['rootpage_errors'] !== 1
        || ($page['rows'][$page['count'] - 2]['page_status'] ?? null) !== 'pointer_map'
    ) {
        fwrite(STDERR, "wordpress-pragma-index-list-rootpage-integrity-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-list-rootpage-integrity-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-list-rootpage-integrity-current-source-next',
    'wordpressUse' => 'Resume copied wp_options repair only when PRAGMA index_list metadata and sqlite_schema rootpage integrity agree for the table and every listed index in the current database image.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current' => $page['current'],
    'blocked_rootpage' => $page['rows'][$page['count'] - 2]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
