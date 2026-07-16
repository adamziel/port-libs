<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteFreelistTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 12), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 6), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 12, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[5] = SQLiteFreelistTrunkPage::assemble(null, [4, 9, 10, 11, 12], $pageSize);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $pointerMapPage = 2;
    if ($pageNumber === $pointerMapPage) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::FREE_PAGE, 0], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pageNumber, $type, $parent);
}
foreach ([4, 9, 10, 11, 12] as $pageNumber) {
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->planFreelistTailTruncation(4);

echo json_encode([
    'applicationUse' => 'Copied wp_options transient cleanup can truncate tail freelist pages while preserving the current auto-vacuum pointer-map boundary for the next live B-tree page.',
    'truncatedPages' => $plan->truncatedPageNumbers,
    'databasePageCount' => $plan->databasePageCount,
    'freelistPageCount' => $plan->freelistPageCount,
    'updatedFreelistPages' => array_keys($plan->updatedFreelistPages),
    'truncatedPointerMapPages' => array_column($plan->truncatedPointerMapEntries, 'page_number'),
    'truncatedPointerMapTypes' => array_column($plan->truncatedPointerMapEntries, 'type_name'),
    'boundaryPointerMapEntry' => $plan->boundaryPointerMapEntry,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
