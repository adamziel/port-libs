<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPointerMapFreelistYield;

$pageSize = 512;
$pageCount = 60;
$first = str_repeat("\0", $pageSize);
$first = substr_replace($first, "SQLite format 3\0", 0, 16);
$first = substr_replace($first, pack('n', $pageSize), 16, 2);
$first[18] = "\x01";
$first[19] = "\x01";
$first = substr_replace($first, pack('N', $pageCount), 28, 4);
$first = substr_replace($first, pack('N', 3), 52, 4);
$first = substr_replace($first, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
$putPointerMapEntry = static function (int $pageNumber, int $type, int $parent) use (&$pointerMap): void {
    $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};
$putPointerMapEntry(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 0);
}

$pages = [$first, $pointerMap];
for ($pageNumber = 3; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[] = str_repeat("\0", $pageSize);
}

$firstPage = SQLitePragmaIntegrityPointerMapFreelistYield::page(implode('', $pages), 0, 48);
$secondPage = SQLitePragmaIntegrityPointerMapFreelistYield::page(implode('', $pages), $firstPage['next_offset'] ?? 48, 48);

echo json_encode([
    'scenario' => 'copied wp_options pragma integrity pointer-map/freelist current next48',
    'applicationUse' => 'Paginate deep PRAGMA integrity_check pointer-map and freelist diagnostics during a copied Application SQLite import repair without requiring ext/sqlite.',
    'firstPage' => [
        'count' => $firstPage['count'],
        'total' => $firstPage['total'],
        'next_offset' => $firstPage['next_offset'],
        'first' => $firstPage['rows'][0],
        'last' => $firstPage['rows'][47],
    ],
    'secondPage' => [
        'count' => $secondPage['count'],
        'complete' => $secondPage['complete'],
        'first' => $secondPage['rows'][0],
        'last' => $secondPage['rows'][8],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
