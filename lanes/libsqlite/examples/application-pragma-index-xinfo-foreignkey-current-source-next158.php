<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$header = static function (int $pageCount, int $largestRootPage) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $largestRootPage), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};
$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'],
];
$schemaCells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);
$pointerMap = str_repeat("\0", $pageSize);
$putPointer = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$dirtyPointerMap = $putPointer($putPointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0), 5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$cleanPointerMap = $putPointer($putPointer($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0), 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
$dirtyDatabase = implode('', [
    SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header(5, 4)),
    $dirtyPointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);
$cleanDatabase = implode('', [
    SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header(5, 5)),
    $cleanPointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

$record = static fn (string $type, string $name, string $table, int $root, string $sql): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'),
    $record('index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE nocase DESC, autoload)'),
    $record('table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'),
]);
$schemas = static function (int $missing): array {
    $missingRows = [];
    for ($i = 1; $i <= $missing; $i++) {
        $missingRows[] = ['rowid' => 'missing-' . $i, 'option_name' => 'missing_' . $i];
    }

    return [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => array_merge(
                [['rowid' => 1, 'option_name' => 'siteurl']],
                $missingRows,
            ),
        ],
        'foreignKeys' => [
            ['id' => 158, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
    ];
};

$page = SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPage158(
    $catalog,
    $dirtyDatabase,
    $schemas(2),
    $catalog,
    $cleanDatabase,
    $schemas(0),
    'PRAGMA main.index_xinfo(wp_options_name)',
    'PRAGMA main.foreign_key_check(wp_options)',
    0,
    158,
    'PRAGMA integrity_check',
    false,
    null,
    $catalog,
    $catalog,
);

if (($argv[1] ?? null) === '--self-test') {
    if ($page['status'] !== 'ok' || $page['current']['foreign_key'] !== 2 || $page['delta']['cleared'] !== true || $page['next_counts']['blockers'] !== 0) {
        fwrite(STDERR, "application-pragma-index-xinfo-foreignkey-current-source-next158 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-index-xinfo-foreignkey-current-source-next158 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-index-xinfo-foreignkey-current-source-next158',
    'applicationUse' => 'Resume copied wp_options index metadata and orphan-option foreign_key_check diagnostics from the same current source before admitting a repaired next database image.',
    'status' => $page['status'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
