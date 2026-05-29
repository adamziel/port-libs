<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql = null, int $rowid = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_sites', 'wp_sites', 2, 'CREATE TABLE wp_sites(blog_id INTEGER PRIMARY KEY, domain TEXT)', 1),
    $record('table', 'wp_option_names', 'wp_option_names', 3, 'CREATE TABLE wp_option_names(name TEXT COLLATE NOCASE)', 2),
    $record('index', 'wp_option_names_name_u', 'wp_option_names', 4, 'CREATE UNIQUE INDEX wp_option_names_name_u ON wp_option_names(name COLLATE nocase)', 3),
    $record('table', 'wp_broken_parent', 'wp_broken_parent', 7, 'CREATE TABLE wp_broken_parent(code TEXT COLLATE NOCASE)', 4),
    $record('index', 'wp_broken_parent_code', 'wp_broken_parent', 8, 'CREATE INDEX wp_broken_parent_code ON wp_broken_parent(code COLLATE nocase)', 5),
    $record('table', 'wp_options', 'wp_options', 13, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, blog_id INTEGER, option_name TEXT, broken_code TEXT)', 6),
];
$foreignKeys = [
    ['id' => 0, 'table' => 'wp_options', 'parent' => 'wp_sites', 'columns' => ['blog_id' => 'blog_id']],
    ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
    ['id' => 2, 'table' => 'wp_options', 'parent' => 'wp_broken_parent', 'columns' => ['broken_code' => 'code']],
];
$tables = [
    'wp_sites' => [['rowid' => 1, 'blog_id' => 1, 'domain' => 'example.test']],
    'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
    'wp_broken_parent' => [['rowid' => 1, 'code' => 'legacy']],
    'wp_options' => [
        ['rowid' => 101, 'option_id' => 101, 'blog_id' => 1, 'option_name' => 'SITEURL', 'broken_code' => 'legacy'],
        ['rowid' => 102, 'option_id' => 102, 'blog_id' => 404, 'option_name' => 'missing-name', 'broken_code' => null],
        ['rowid' => 103, 'option_id' => 103, 'blog_id' => null, 'option_name' => null, 'broken_code' => 'missing-parent'],
    ],
];

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE DESC, autoload)', 2),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 3),
]);

$pageSize = 1024;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 8), 28, 4);
$header = substr_replace($header, pack('N', 6), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);
$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name, autoload)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload)"],
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
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
]);

$first = SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database,
    $records,
    $foreignKeys,
    $tables,
    '00d5ed7b9dc734b0d9546507a661722feb05840f',
    'pragma-index-foreignkey-integrity-current-source-next',
    0,
    8,
);
$second = SQLitePragmaIndexForeignKeyIntegrityCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $database,
    $records,
    $foreignKeys,
    $tables,
    '00d5ed7b9dc734b0d9546507a661722feb05840f',
    'pragma-index-foreignkey-integrity-current-source-next',
    8,
    16,
    'PRAGMA integrity_check',
    false,
    $first['next'],
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $first['status'] !== 'blocked'
        || $first['current']['rootpage_errors'] !== 1
        || $second['current']['index_blockers'] !== 1
        || $second['current']['foreign_key_violations'] !== 3
        || ($second['rows'][5]['group'] ?? null) !== 'pragma_foreign_key_integrity'
    ) {
        fwrite(STDERR, "wordpress-pragma-index-foreignkey-integrity-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-foreignkey-integrity-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-foreignkey-integrity-current-source-next',
    'wordpressUse' => 'Resume copied wp_options PRAGMA index_list/index_xinfo/rootpage checks and foreign_key_check parent-index diagnostics under one current-source cursor.',
    'status' => $first['status'],
    'source_id' => $first['source_id'],
    'current' => $first['current'],
    'first_page_count' => $first['count'],
    'second_page_count' => $second['count'],
    'blocking' => $first['next']['blocking'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
