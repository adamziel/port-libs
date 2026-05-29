<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNext;
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
$header = substr_replace($header, pack('N', 8), 28, 4);
$header = substr_replace($header, pack('N', 8), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'],
    ['table', 'wp_option_names', 'wp_option_names', 8, 'CREATE TABLE wp_option_names(name text primary key)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name COLLATE NOCASE)'],
    ['index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload DESC)'],
];
$schemaCells = array_map(
    static fn (array $row, int $index): string => SQLiteTableLeafCell::encode($index + 1, SQLiteRecord::encode($row)),
    $schemaRows,
    array_keys($schemaRows),
);
$pointerMap = static function (array $entries) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    foreach ($entries as [$pageNumber, $type, $parent]) {
        $page = substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
    }

    return $page;
};
$database = static function (string $pointerMapPage) use ($schemaCells, $header, $pageSize): string {
    return implode('', [
        SQLiteTableLeafPage::assemble($schemaCells, $pageSize, 100, $header),
        $pointerMapPage,
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteIndexLeafPage::assemble([], $pageSize),
        SQLiteIndexLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
    ]);
};

$currentDatabase = $database($pointerMap([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));
$nextDatabase = $database($pointerMap([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));

$foreignKeys = [
    ['id' => 141, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
        ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
    ]],
];
$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
                ['rowid' => 'stale-transient', 'option_name' => '_transient_missing', 'autoload' => 'no'],
            ],
        ],
        'foreignKeys' => $foreignKeys,
    ],
];
$cleanSchemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [['rowid' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes']],
        ],
        'foreignKeys' => $foreignKeys,
    ],
];

$record = static fn (string $type, string $name, string $table, int $root, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    'CREATE ' . strtoupper($type) . ' ' . $name,
    $rowid,
);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 1),
    $record('table', 'wp_option_names', 'wp_option_names', 8, 2),
    $record('index', 'wp_options_name', 'wp_options', 5, 3),
    $record('index', 'wp_options_autoload', 'wp_options', 6, 4),
]);

$result = SQLitePragmaIndexQuickCheckForeignKeyCurrentSourceNext::currentNextPage(
    $catalog,
    $catalog,
    'PRAGMA main.index_list(wp_options)',
    $currentDatabase,
    $schemas,
    $nextDatabase,
    $cleanSchemas,
    'PRAGMA foreign_key_check(wp_options)',
    'PRAGMA quick_check(wp_options)',
);

if (
    $result['status'] !== 'ok'
    || $result['current']['index_root_errors'] !== 3
    || $result['current']['integrity_root'] !== 2
    || $result['current']['foreign_key'] !== 1
    || $result['next_state']['ready'] !== true
) {
    fwrite(STDERR, "wordpress-pragma-index-quickcheck-foreignkey-current-source-next self-test failed\n");
    exit(1);
}

fwrite(STDOUT, "wordpress-pragma-index-quickcheck-foreignkey-current-source-next self-test passed\n");
echo json_encode([
    'scenario' => 'copied wp_options PRAGMA index_list quick_check foreign_key_check current-source repair',
    'wordpressUse' => 'Resume copied WordPress option-index analysis only when index catalog metadata, quick_check root blockers, and foreign_key_check rows all match the current source before continuing an import repair.',
    'status' => $result['status'],
    'current_index_root_errors' => $result['current']['index_root_errors'],
    'current_quick_check_root' => $result['current']['integrity_root'],
    'current_foreign_key' => $result['current']['foreign_key'],
    'next_ready' => $result['next_state']['ready'],
    'delta_blockers' => $result['delta']['total_blockers'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
