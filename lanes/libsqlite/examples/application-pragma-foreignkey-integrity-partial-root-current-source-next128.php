<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield;
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
    ['table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, autoload text)'],
    ['index', 'wp_options_name', 'wp_options', 5, 'CREATE UNIQUE INDEX wp_options_name ON wp_options(option_name)'],
    ['index', 'wp_options_autoload', 'wp_options', 6, 'CREATE INDEX wp_options_autoload ON wp_options(autoload)'],
    ['table', 'wp_posts', 'wp_posts', 7, 'CREATE TABLE wp_posts(ID integer primary key, post_title text)'],
    ['index', 'wp_posts_title', 'wp_posts', 12, 'CREATE INDEX wp_posts_title ON wp_posts(post_title)'],
];
$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values));

$pointerMap = str_repeat("\0", $pageSize);
foreach ([
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
] as [$pageNumber, $type, $parent]) {
    $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
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
    5 => SQLiteIndexLeafPage::assemble([], $pageSize),
    6 => SQLiteIndexLeafPage::assemble([], $pageSize),
    7 => SQLiteTableLeafPage::assemble([], $pageSize),
    8 => SQLiteTableLeafPage::assemble([], $pageSize),
];
ksort($pages);
$database = implode('', $pages);

$schemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'missing-1', 'option_name' => 'missing_1'],
                ['rowid' => 'missing-2', 'option_name' => 'missing_2'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 128, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, 'CREATE ' . strtoupper($type) . ' ' . $name, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 8),
    $record('table', 'wp_posts', 'wp_posts', 7),
]);

$result = SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield::page(
    $database,
    $schemas,
    'PRAGMA foreign_key_check(wp_options)',
    0,
    128,
    null,
    $catalog,
    'PRAGMA integrity_check(wp_options)',
);

if (($argv[1] ?? '') === '--self-test') {
    $ok = $result['total'] === 4
        && $result['current']['integrity_root'] === 2
        && $result['current']['foreign_key'] === 2
        && $result['current_source']['integrity_scope'] === 'table'
        && $result['current_source']['integrity_target'] === 'wp_options'
        && $result['rows'][0]['name'] === 'wp_options'
        && $result['rows'][2]['kind'] === 'foreign_key_check';
    if (!$ok) {
        fwrite(STDERR, "application-pragma-foreignkey-integrity-partial-root-current-source-next128 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-pragma-foreignkey-integrity-partial-root-current-source-next128 self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'application-pragma-foreignkey-integrity-partial-root-current-source-next128',
    'applicationUse' => 'Inspect copied wp_options root-page integrity and foreign-key blockers without paging unrelated wp_posts root diagnostics.',
    'status' => $result['status'],
    'total' => $result['total'],
    'integrity_root' => $result['current']['integrity_root'],
    'foreign_key' => $result['current']['foreign_key'],
    'integrity_scope' => $result['current_source']['integrity_scope'],
    'integrity_target' => $result['current_source']['integrity_target'],
    'first_messages' => array_map(static fn (array $row): string => $row['message'], array_slice($result['rows'], 0, 4)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
