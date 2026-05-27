<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVarint.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteSchemaRecord.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteIndexColumn.php';
require_once __DIR__ . '/../src/SQLiteCreateTable.php';
require_once __DIR__ . '/../src/SQLitePragmaIntegrityCheck.php';
require_once __DIR__ . '/../src/SQLitePragmaIntegrityAutoindexYield.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$pageCount = 8;

$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', 7), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$put = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$pointerMap = str_repeat("\0", $pageSize);
foreach ([3, 4, 5, 6, 7, 8] as $pageNumber) {
    $pointerMap = $put($pointerMap, $pageNumber, in_array($pageNumber, [4, 5, 7], true) ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, in_array($pageNumber, [4, 5, 7], true) ? 0 : 4);
}
$pointerMap = $put($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 4);

$schemaSql = "CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, autoload TEXT, option_value TEXT, option_hash TEXT GENERATED ALWAYS AS (lower(option_name)) STORED UNIQUE, UNIQUE(autoload, option_name))";
$schemaRows = [
    ['table', 'wp_options', 'wp_options', 4, $schemaSql],
    ['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 5, null],
    ['index', 'sqlite_autoindex_wp_options_3', 'wp_options', 7, null],
    ['index', 'sqlite_autoindex_wp_options_4', 'wp_options', 8, null],
];

$cells = [];
foreach ($schemaRows as $rowId => $values) {
    $cells[] = SQLiteTableLeafCell::encode($rowId + 1, SQLiteRecord::encode($values));
}

$pages = [
    1 => SQLiteTableLeafPage::assemble($cells, $pageSize, 100, $header),
    2 => $pointerMap,
];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = SQLiteTableLeafPage::assemble([], $pageSize);
}
ksort($pages);

$first = SQLitePragmaIntegrityAutoindexYield::page(implode('', $pages), 0, 1);
$next = $first['next_offset'] === null
    ? ['complete' => true, 'rows' => []]
    : SQLitePragmaIntegrityAutoindexYield::page(implode('', $pages), $first['next_offset'], 1);

echo json_encode([
    'scenario' => 'copied wp_options pragma integrity autoindex current next51',
    'first_count' => $first['count'],
    'first_next_offset' => $first['next_offset'],
    'next_complete' => $next['complete'],
    'sources' => array_column(array_merge($first['rows'], $next['rows']), 'source'),
    'indexes' => array_column(array_merge($first['rows'], $next['rows']), 'index'),
], JSON_PRETTY_PRINT) . PHP_EOL;
