<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan;
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
    ['table', 'wp_option_names', 'wp_option_names', 6, 'CREATE TABLE wp_option_names(name text primary key)'],
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
        SQLiteTableLeafPage::assemble([], $pageSize),
        SQLiteIndexLeafPage::assemble([], $pageSize),
        SQLiteTableLeafPage::assemble([], $pageSize),
    ]);
};

$currentDatabase = $database($pointerMap([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::BTREE_PAGE, 3],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));
$nextDatabase = $database($pointerMap([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [5, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [7, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [8, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));

$record = static fn (string $type, string $name, string $table, int $root): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, 'CREATE ' . strtoupper($type) . ' ' . $name, $root);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4),
    $record('table', 'wp_option_names', 'wp_option_names', 6),
]);

$currentSchemas = [
    'main' => [
        'tables' => [
            'wp_option_names' => [['rowid' => 1, 'name' => 'siteurl']],
            'wp_options' => [
                ['rowid' => 1, 'option_name' => 'siteurl'],
                ['rowid' => 'missing-autoload', 'option_name' => '_transient_missing'],
            ],
        ],
        'foreignKeys' => [
            ['id' => 140, 'table' => 'wp_options', 'parent' => 'wp_option_names', 'columns' => [
                ['child' => 'option_name', 'parent' => 'name', 'affinity' => 'text', 'collation' => 'nocase'],
            ]],
        ],
    ],
];
$nextSchemas = $currentSchemas;
$nextSchemas['main']['tables']['wp_options'] = [['rowid' => 1, 'option_name' => 'siteurl']];

$page = SQLitePragmaForeignKeyIntegrityRootpageCurrentSourceNextPlan::page(
    $currentDatabase,
    $currentSchemas,
    $catalog,
    $nextDatabase,
    $nextSchemas,
    $catalog,
    'PRAGMA foreign_key_check(wp_options)',
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $page['status'] !== 'ok'
        || $page['current']['foreign_key_violations'] !== 1
        || $page['current']['pointer_map_conflicts'] !== 2
        || $page['next_counts']['foreign_key_violations'] !== 0
        || $page['delta']['cleared'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-foreignkey-integrity-rootpage-current-source-next self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-foreignkey-integrity-rootpage-current-source-next self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-foreignkey-integrity-rootpage-current-source-next',
    'wordpressUse' => 'Resume a copied wp_options foreign_key_check and rootpage/pointer-map preflight only after the repaired database image and catalog clear both FK rows and rootpage blockers.',
    'status' => $page['status'],
    'current' => $page['current'],
    'next_counts' => $page['next_counts'],
    'delta' => $page['delta'],
    'first_message' => $page['rows'][0]['message'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
