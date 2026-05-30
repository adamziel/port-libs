<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);
$leafInsertLimit = intdiv($pageSize, 4) - 8;
$existingLeafPages = range(9, 9 + $leafInsertLimit - 1);
$databasePageCount = max($existingLeafPages) + 3;

$makeFirstPage = static function (int $databasePageCount, int $firstFreelistTrunkPage, int $freelistPageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databasePageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$pages = array_fill(1, $databasePageCount, $emptyPage);
$pages[1] = $makeFirstPage($databasePageCount, 5, 1 + count($existingLeafPages));
$pages[5] = SQLiteFreelistTrunkPage::assemble(null, $existingLeafPages, $pageSize);

$preDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $preDatabase->planPageFreeList([6, 7, 8], true);
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Preview freeing obsolete wp_options overflow pages when the current SQLite freelist trunk is full; the first freed page becomes a new trunk and secure-delete clears leaf pages before reuse.',
    'freelistBefore' => array_map(
        static fn (SQLiteFreelistTrunkPage $trunkPage): array => $trunkPage->toArray(),
        $preDatabase->freelistTrunkPages(),
    ),
    'freePlan' => $plan->toArray(),
    'freelistAfter' => array_map(
        static fn (SQLiteFreelistTrunkPage $trunkPage): array => $trunkPage->toArray(),
        $postDatabase->freelistTrunkPages(),
    ),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(6),
    'secureDeleteClearedPages' => $plan->clearedPageNumbers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
