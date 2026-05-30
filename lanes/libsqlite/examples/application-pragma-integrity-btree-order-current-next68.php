<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;

$headerPage = static function (int $pageCount, int $largestRootPage) use ($pageSize): string {
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

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);

$wpOptionsTable = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(101, 'siteurl'),
    SQLiteTableLeafCell::encode(99, 'home'),
    SQLiteTableLeafCell::encode(145, 'active_plugins'),
], $pageSize);

$wpOptionsNameIndex = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 101]), $pageSize),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 99]), $pageSize),
], $pageSize);

$database = implode('', [
    $headerPage(4, 4),
    $pointerMap,
    $wpOptionsTable,
    $wpOptionsNameIndex,
]);

$integrity = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database);
$quick = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $database);

echo json_encode([
    'scenario' => 'copied wp_options PRAGMA integrity btree key order current-next68',
    'integrity_errors' => $integrity['errors'],
    'integrity_first_row' => $integrity['rows'][0],
    'quick_check' => $quick['rows'][0],
    'application_path' => 'detects copied wp_options table rowid and option_name index key-order corruption before import reuse',
], JSON_PRETTY_PRINT) . "\n";
