<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;
use PortLibs\LibSqlite\SQLiteRecord;

$pageSize = 512;

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage = substr_replace($firstPage, pack('N', 8), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 7), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$putPointerMapEntry = static function (string $page, int $pageNumber, int $type, int $parent): string {
    $offset = 5 * ($pageNumber - 3);

    return substr_replace($page, chr($type) . pack('N', $parent), $offset, 5);
};

$pointerMap = str_repeat("\0", $pageSize);
$pointerMap = $putPointerMapEntry($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$pointerMap = $putPointerMapEntry($pointerMap, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
$pointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 3);
$pointerMap = $putPointerMapEntry($pointerMap, 7, SQLitePointerMapEntry::FREE_PAGE, 0);
$pointerMap = $putPointerMapEntry($pointerMap, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

$indexInterior = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'no', 4]), leftChildPage: 4),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'yes', 5]), leftChildPage: 5),
], 6, $pageSize);

$pages = [
    $firstPage,
    $pointerMap,
    $indexInterior,
    SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'no', 4]))], $pageSize),
    SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 'yes', 5]))], $pageSize),
    SQLiteIndexLeafPage::assemble([SQLiteIndexCell::encode(SQLiteRecord::encode(['transient', 'yes', 6]))], $pageSize),
    SQLiteFreelistTrunkPage::assemble(null, [8], $pageSize),
    str_repeat("\0", $pageSize),
];

$validDatabase = implode('', $pages);
$corruptPointerMap = $putPointerMapEntry($pointerMap, 6, SQLitePointerMapEntry::BTREE_PAGE, 5);
$corruptDatabase = implode('', [$firstPage, $corruptPointerMap, ...array_slice($pages, 2)]);

echo json_encode([
    'scenario' => 'copied-wp-options-index-integrity-preflight',
    'valid_index_tree' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $validDatabase)['rows'],
    'right_most_child_parent_mismatch' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check(1)', $corruptDatabase)['rows'],
    'quick_check_skips_deep_pointer_map_child_scan' => SQLitePragmaIntegrityCheck::execute('PRAGMA quick_check', $corruptDatabase)['rows'],
], JSON_PRETTY_PRINT) . "\n";
