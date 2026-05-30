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
$autoindexCount = 6;
$pageCount = $autoindexCount + 3;

$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', $pageCount), 28, 4);
$header = substr_replace($header, pack('N', $pageCount), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$schemaCell = static fn (array $values, int $rowId): string => SQLiteTableLeafCell::encode($rowId, SQLiteRecord::encode($values), $pageSize);
$schemaRecords = [
    $schemaCell(['table', 'wp_options', 'wp_options', 3, 'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, autoload TEXT, blog_id INTEGER, UNIQUE(autoload, option_name), UNIQUE(blog_id, option_name))'], 1),
];
for ($i = 1; $i <= $autoindexCount; $i++) {
    $schemaRecords[] = $schemaCell(['index', 'sqlite_autoindex_wp_options_' . $i, 'wp_options', $i + 3, null], $i + 1);
}

$pointerMap = str_repeat("\0", $pageSize);
$putPointerMapEntry = static fn (string $page, int $pageNumber, int $type, int $parent): string => substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pointerMap = $putPointerMapEntry($pointerMap, $pageNumber, SQLitePointerMapEntry::ROOT_PAGE, 0);
}

$pages = [
    1 => SQLiteTableLeafPage::assemble($schemaRecords, $pageSize, 100, $header),
    2 => $pointerMap,
    3 => SQLiteTableLeafPage::assemble([], $pageSize),
];
for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = SQLiteIndexLeafPage::assemble([], $pageSize);
}
ksort($pages);

$database = implode('', $pages);
$firstPage = SQLitePragmaIntegrityAutoindexYield::page($database, 0, 4);
$secondPage = SQLitePragmaIntegrityAutoindexYield::page($database, $firstPage['next_offset'] ?? 0, 4);

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA integrity autoindex pointer-map root current/next55',
    'applicationUse' => 'Stream UNIQUE autoindex integrity rows with current/next root-page cursor metadata and auto-vacuum pointer-map root ownership during Application SQLite import preflight without ext/sqlite.',
    'first_page' => $firstPage,
    'second_page' => $secondPage,
], JSON_PRETTY_PRINT) . "\n";
