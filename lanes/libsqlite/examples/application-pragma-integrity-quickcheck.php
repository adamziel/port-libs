<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$applicationDatabase = $firstPage
    . SQLiteFreelistTrunkPage::assemble(null, [3], $pageSize)
    . str_repeat("\0", $pageSize);

$ok = SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $applicationDatabase);
$corrupt = SQLitePragmaIntegrityCheck::execute(
    'PRAGMA integrity_check(1)',
    substr_replace($applicationDatabase, pack('N', 9), 56, 4),
);

$pointerMapPage = str_repeat("\0", $pageSize);
$pointerMapPage = substr_replace($pointerMapPage, chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0), 0, 5);
$btreeFirstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$btreeFirstPage = substr_replace($btreeFirstPage, pack('N', 3), 28, 4);
$btreeFirstPage = substr_replace($btreeFirstPage, pack('N', 0), 32, 4);
$btreeFirstPage = substr_replace($btreeFirstPage, pack('N', 0), 36, 4);
$btreeDatabase = $btreeFirstPage
    . $pointerMapPage
    . SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, 'siteurl'),
        SQLiteTableLeafCell::encode(2, 'home'),
    ], $pageSize);
$deep = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $btreeDatabase);

$freePointerMapPage = str_repeat("\0", $pageSize);
$freePointerMapPage = substr_replace($freePointerMapPage, chr(SQLitePointerMapEntry::FREE_PAGE) . pack('N', 0), 0, 5);
$freePointerMapPage = substr_replace($freePointerMapPage, chr(SQLitePointerMapEntry::FREE_PAGE) . pack('N', 0), 5, 5);
$freePointerMapPage = substr_replace($freePointerMapPage, chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0), 10, 5);
$freePointerMapFirstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
$freePointerMapFirstPage = substr_replace($freePointerMapFirstPage, pack('N', 3), 32, 4);
$freePointerMapFirstPage = substr_replace($freePointerMapFirstPage, pack('N', 1), 36, 4);
$freePointerMapFirstPage = substr_replace($freePointerMapFirstPage, pack('N', 5), 52, 4);
$freePointerMapDatabase = $freePointerMapFirstPage
    . $freePointerMapPage
    . SQLiteFreelistTrunkPage::assemble(null, [], $pageSize)
    . str_repeat("\0", $pageSize)
    . SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, 'siteurl'),
    ], $pageSize);
$freePointerMap = SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $freePointerMapDatabase);

echo json_encode([
    'scenario' => 'copied-wp-options-integrity-preflight',
    'quick_check' => $ok['rows'],
    'integrity_check_limited' => $corrupt['rows'],
    'integrity_check_deep_btree' => $deep['rows'],
    'integrity_check_pointer_map_freelist' => $freePointerMap['rows'],
], JSON_PRETTY_PRINT) . "\n";
