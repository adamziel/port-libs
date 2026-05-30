<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreeInteriorMergePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$payload = static fn (string $prefix, int $rowid): string => SQLiteRecord::encode(['yes', str_repeat($prefix, 48), $rowid]);
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 10), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    7 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    9 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$leftPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($payload('b', 20), $pageSize, null, 6),
], 7, $pageSize);
$rightPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($payload('h', 80), $pageSize, null, 8),
    SQLiteIndexCell::encode($payload('k', 110), $pageSize, null, 9),
], 10, $pageSize);
$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . SQLiteIndexInteriorPage::assemble([SQLiteIndexCell::encode($payload('f', 60), $pageSize, null, 4)], 5, $pageSize)
    . $leftPage
    . $rightPage
    . str_repeat("\0", $pageSize * 5),
);

$mergePlan = SQLiteBTreeInteriorMergePlan::indexInterior(
    $leftPage,
    $rightPage,
    4,
    5,
    3,
    $payload('f', 60),
    $pageSize,
);
$application = SQLiteBTreeInteriorMergeApplicationPlan::apply($database, $mergePlan, true);
$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($application->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$mergedHeader = $postDatabase->pageHeader(4);
$mergedCells = SQLiteIndexCell::parsePageCells($postDatabase->page(4), $mergedHeader, $pageSize);

echo json_encode([
    'applicationUse' => 'Apply a wp_options autoload index parent merge after delete underflow, freeing the obsolete sibling and rewriting auto-vacuum pointer-map ownership without the SQLite extension.',
    'mergePlan' => $mergePlan->toArray(),
    'application' => $application->toArray(),
    'mergedIndexParent' => [
        'page' => 4,
        'cellCount' => $mergedHeader->cellCount,
        'leftChildren' => array_map(static fn (SQLiteIndexCell $cell): int => $cell->leftChildPage, $mergedCells),
        'rightMostPointer' => $mergedHeader->rightMostPointer,
        'records' => array_map(static fn (SQLiteIndexCell $cell): array => $cell->record()->values, $mergedCells),
    ],
    'freelist' => [
        'firstTrunkPage' => $postDatabase->header->firstFreelistTrunkPage,
        'pageCount' => $postDatabase->header->freelistPageCount,
        'pages' => $postDatabase->freelistPageNumbers(),
    ],
    'pointerMap' => [
        'obsoletePage' => $postDatabase->pointerMapEntryForPage(5)->toArray(),
        'movedChildPages' => [
            8 => $postDatabase->pointerMapEntryForPage(8)->toArray(),
            9 => $postDatabase->pointerMapEntryForPage(9)->toArray(),
            10 => $postDatabase->pointerMapEntryForPage(10)->toArray(),
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
