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
$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 4, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT, autoload TEXT)', 1),
    $record('index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name) COLLATE nocase, autoload DESC)", 2),
]);
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
    ['index', 'wp_options_value_expr', 'wp_options', 6, "CREATE INDEX wp_options_value_expr ON wp_options(json_extract(option_value, '$.plugin'), lower(option_name), autoload DESC)"],
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
    ]);
};
$currentDatabase = $database($pointerMap([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::BTREE_PAGE, 4],
]));
$nextDatabase = $database($pointerMap([
    [3, SQLitePointerMapEntry::BTREE_PAGE, 4],
    [4, SQLitePointerMapEntry::ROOT_PAGE, 0],
    [6, SQLitePointerMapEntry::ROOT_PAGE, 0],
]));

$summary = SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext::currentNextPage(
    $catalog,
    $catalog,
    'PRAGMA main.index_xinfo(wp_options_value_expr)',
    $currentDatabase,
    $nextDatabase,
);

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['status'] !== 'ok'
        || $summary['current']['quick_check_errors'] !== 1
        || $summary['next_counts']['quick_check_errors'] !== 0
        || $summary['delta']['cleared'] !== true
    ) {
        fwrite(STDERR, "wordpress-pragma-index-rootpage-quickcheck-current-source-next-current-next-page self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-pragma-index-rootpage-quickcheck-current-source-next-current-next-page self-test passed\n");
    exit(0);
}

echo json_encode([
    'scenario' => 'wordpress-pragma-index-rootpage-quickcheck-current-source-next-current-next-page',
    'wordpressUse' => 'Resume copied wp_options expression-index analysis only after PRAGMA quick_check verifies the next database image repaired the index rootpage pointer-map state.',
    'status' => $summary['status'],
    'current_errors' => $summary['current']['quick_check_errors'],
    'next_errors' => $summary['next_counts']['quick_check_errors'],
    'cleared' => $summary['delta']['cleared'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
