<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pages = array_fill(1, 112, str_repeat("\0", $pageSize));
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 112), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 7), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;
$pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], $pageSize);
$pages[106] = SQLiteFreelistTrunkPage::assemble(110, [107], $pageSize);
$pages[110] = SQLiteFreelistTrunkPage::assemble(106, [111], $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
foreach ([4, 5, 106, 107, 110, 111] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->freelistTraversalPlan();

echo json_encode([
    'applicationScenario' => 'wp_options repair preflight detects a cyclic current/next freelist trunk before allocating replacement overflow pages',
    'valid' => $plan->isValid(),
    'cycleAtPage' => $plan->cycleAtPage,
    'cyclePath' => $plan->cyclePath,
    'visitedTrunks' => $plan->trunkPageNumbers,
    'visitedLeaves' => $plan->leafPageNumbers,
    'allocationOrderBeforeCycle' => $plan->allocationOrder,
    'errors' => $plan->errors,
], JSON_PRETTY_PRINT) . PHP_EOL;
