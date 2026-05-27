<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$header = str_repeat("\0", $pageSize);
$header = substr_replace($header, "SQLite format 3\0", 0, 16);
$header = substr_replace($header, pack('n', $pageSize), 16, 2);
$header[18] = "\x01";
$header[19] = "\x01";
$header = substr_replace($header, pack('N', 6), 28, 4);
$header = substr_replace($header, pack('N', 5), 32, 4);
$header = substr_replace($header, pack('N', 2), 36, 4);
$header = substr_replace($header, pack('N', 3), 52, 4);
$header = substr_replace($header, pack('N', 1), 56, 4);

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::FREE_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::FREE_PAGE, 0);

$database = $header
    . $pointerMap
    . SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, 'siteurl'),
        SQLiteTableLeafCell::encode(2, 'home'),
    ], $pageSize)
    . SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(3, 'blogname'),
    ], $pageSize)
    . SQLiteFreelistTrunkPage::assemble(null, [6], $pageSize)
    . str_repeat("\0", $pageSize);

$invalidType = substr_replace($database, chr(9) . pack('N', 3), $pageSize + 5, 5);
$invalidFreeParent = substr_replace($database, chr(SQLitePointerMapEntry::FREE_PAGE) . pack('N', 3), $pageSize + 5, 5);
$invalidBtreeParent = substr_replace($database, chr(SQLitePointerMapEntry::BTREE_PAGE) . pack('N', 0), $pageSize + 5, 5);

echo json_encode([
    'scenario' => 'copied-wp-options-pointermap-integrity-preflight',
    'clean_integrity_check' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $database)['rows'],
    'quick_check_shallow' => SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $invalidType)['rows'],
    'invalid_pointer_map_type' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $invalidType)['rows'],
    'invalid_free_page_parent' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $invalidFreeParent)['rows'],
    'invalid_btree_parent' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $invalidBtreeParent)['rows'],
], JSON_PRETTY_PRINT) . "\n";
