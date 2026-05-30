<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 207;

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 6), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[5] = SQLiteFreelistTrunkPage::assemble(106, [3, 104], $pageSize);
$pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107, 206], $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 11 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach ([3, 5, 104, 106, 107, 206] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->planOverflowPageAllocation(7, 11, true);

echo json_encode([
    'applicationScenario' => 'wp_options replacement allocates a new overflow chain from current and next freelist trunks',
    'allocatedOverflowPages' => $plan->allocatedPageNumbers,
    'appendedOverflowPages' => $plan->appendedPageNumbers,
    'pointerMapChain' => $plan->allocatedPointerMapEntries(),
    'updatedPointerMapPages' => array_keys($plan->updatedPointerMapPages),
    'remainingFreelistPageCount' => $plan->freelistPageCount,
], JSON_PRETTY_PRINT) . PHP_EOL;
