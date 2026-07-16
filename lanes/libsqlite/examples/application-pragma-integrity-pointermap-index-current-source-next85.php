<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$pageSize = 512;
$pageCount = 12;
$first = str_repeat("\0", $pageSize);
$first = substr_replace($first, "SQLite format 3\0", 0, 16);
$first = substr_replace($first, pack('n', $pageSize), 16, 2);
$first[18] = "\x01";
$first[19] = "\x01";
$first = substr_replace($first, pack('N', $pageCount), 28, 4);
$first = substr_replace($first, pack('N', 7), 52, 4);
$first = substr_replace($first, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$put = static function (int $pageNumber, int $type, int $parent) use (&$pointerMap): void {
    $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$put(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$put(4, SQLitePointerMapEntry::BTREE_PAGE, 0);
$put(5, SQLitePointerMapEntry::BTREE_PAGE, 4);
$put(6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
$put(7, SQLitePointerMapEntry::BTREE_PAGE, 0);
$put(8, SQLitePointerMapEntry::OVERFLOW_PAGE, 7);
$put(9, SQLitePointerMapEntry::FREE_PAGE, 0);
for ($pageNumber = 10; $pageNumber <= $pageCount; $pageNumber++) {
    $put($pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
}

$pages = [$first, $pointerMap];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $page = str_repeat("\0", $pageSize);
    if (in_array($pageNumber, [4, 5, 7], true)) {
        $page[0] = "\x0a";
        $page = substr_replace($page, pack('n', 0), 3, 2);
        $page = substr_replace($page, pack('n', $pageSize), 5, 2);
        $page[7] = "\0";
    }
    $pages[] = $page;
}

$record = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$records = [
    $record('table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT, option_value TEXT)', 1),
    $record('index', 'wp_options_autoload_idx', 'wp_options', 4, 'CREATE INDEX wp_options_autoload_idx ON wp_options(autoload, option_id)', 2),
    $record('index', 'wp_options_name_idx', 'wp_options', 7, 'CREATE INDEX wp_options_name_idx ON wp_options(option_name)', 3),
];

$page = SQLitePragmaIntegrityPointerMapIndexCurrentSourceYield::page(
    implode('', $pages),
    $records,
    '4a40be6d2187eb244ca9f64f681c230cd6749701',
    'pragma-integrity-pointermap-index-current-source-next85',
    0,
    85,
);

echo json_encode([
    'scenario' => 'application-pragma-integrity-pointermap-index-current-source-next85',
    'status' => $page['status'],
    'current_source' => $page['current']['source'],
    'next_source' => $page['next']['source'],
    'total_rows' => $page['total'],
    'index_pointer_map_errors' => $page['current']['index_pointer_map_errors'],
    'blocking' => $page['next']['blocking'],
    'index_rows' => array_values(array_map(
        static fn (array $row): array => [
            'page' => $row['page'],
            'index' => $row['index'],
            'pointer_map_type' => $row['pointer_map_type'],
            'pointer_map_parent' => $row['pointer_map_parent'],
        ],
        array_filter($page['rows'], static fn (array $row): bool => $row['index'] !== null)
    )),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
