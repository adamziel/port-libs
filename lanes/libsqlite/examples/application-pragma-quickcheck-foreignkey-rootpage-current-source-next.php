<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext;
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
    ['table', 'wp_option_names', 'wp_option_names', 5, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload DESC)"],
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
$pointerMap = $writePointer($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 4);

$database = implode('', [
    SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, $schemaRows[0][4]),
    $record('table', 'wp_option_names', 'wp_option_names', 5, $schemaRows[1][4]),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, $schemaRows[2][4]),
]);
$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '{"plugin":"core"}', 'autoload' => 'yes'],
                ['rowid' => 2, 'option_id' => 2, 'option_name' => 'missing_plugin', 'option_value' => '{"plugin":"legacy"}', 'autoload' => 'no'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 1, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase']]],
        ],
    ],
];

$page = SQLitePragmaQuickcheckForeignKeyRootpageCurrentSourceNext::page(
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_value_expr)',
    $database,
    $schemas,
    "PRAGMA foreign_key_check('wp_options')",
    0,
    132,
    'PRAGMA quick_check',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'blocked'
        || $page['current']['quick_check_errors'] !== 2
        || $page['current']['foreign_key_violations'] !== 1
        || $page['next_state']['blocking'] !== ['quick_check', 'foreign_key_check', 'rootpage_pointer_map', 'rootpage_integrity']
    ) {
        fwrite(STDERR, "application-pragma-quickcheck-foreignkey-rootpage-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-quickcheck-foreignkey-rootpage-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-quickcheck-foreignkey-rootpage-current-source-next',
    'applicationUse' => 'Gate copied wp_options imports on one current-source cursor that combines expression-index metadata, shallow quick_check rootpage blockers, and foreign_key_check rootpage/pointer-map rows.',
    'status' => $page['status'],
    'source_id' => $page['source_id'],
    'current' => $page['current'],
    'blocking' => $page['next_state']['blocking'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
