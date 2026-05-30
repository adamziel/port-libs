<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityAutoindexYield;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 4096;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 6), 28, 4);
$header = substr_replace($header, pack('N', 6), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values), $pageSize);
$schema = SQLiteTableLeafPage::assemble([
    $schemaCell(['table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, autoload TEXT, UNIQUE(autoload, option_name))'], 1),
    $schemaCell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 4, null], 2),
    $schemaCell(['index', 'sqlite_autoindex_wp_options_2', 'wp_options', 5, null], 3),
    $schemaCell(['index', 'wp_options_autoload_idx', 'wp_options', 6, 'CREATE INDEX wp_options_autoload_idx ON wp_options(autoload)'], 4),
], $pageSize, 100, $header);

$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
$pointerMap = str_repeat("\0", $pageSize);
foreach ([3, 4, 5, 6] as $pageNumber) {
    $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::ROOT_PAGE, 0);
}

$database = implode('', [
    $schema,
    $pointerMap,
    SQLiteTableLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
    SQLiteIndexLeafPage::assemble([], $pageSize),
]);

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA integrity autoindex current/next50',
    'applicationUse' => 'Stream integrity diagnostics for sqlite_autoindex_* UNIQUE/PRIMARY KEY roots during Application SQLite import preflight without ext/sqlite.',
    'page' => SQLitePragmaIntegrityAutoindexYield::page($database, 0, 50),
], JSON_PRETTY_PRINT) . "\n";
