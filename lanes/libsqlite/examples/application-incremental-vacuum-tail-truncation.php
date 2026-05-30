<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);

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

$pages = [
    1 => $makeFirstPage(10, 8, 5),
    2 => $emptyPage,
    3 => $emptyPage,
    4 => $emptyPage,
    5 => SQLiteFreelistTrunkPage::assemble(null, [4, 9, 10], $pageSize),
    6 => $emptyPage,
    7 => $emptyPage,
    8 => SQLiteFreelistTrunkPage::assemble(5, [], $pageSize),
    9 => $emptyPage,
    10 => $emptyPage,
];

$preDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $preDatabase->planFreelistTailTruncation(4);
$postPages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $postPages[$pageNumber] = $pages[$pageNumber];
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    if ($pageNumber <= $plan->databasePageCount) {
        $postPages[$pageNumber] = $page;
    }
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));

echo json_encode([
    'applicationUse' => 'Preview SQLite incremental_vacuum-style file shrink after deleting transient wp_options pages, removing only contiguous free tail pages and preserving lower reusable freelist pages.',
    'freelistBefore' => array_map(
        static fn (SQLiteFreelistTrunkPage $trunkPage): array => $trunkPage->toArray(),
        $preDatabase->freelistTrunkPages(),
    ),
    'truncatePlan' => $plan->toArray(),
    'freelistAfter' => array_map(
        static fn (SQLiteFreelistTrunkPage $trunkPage): array => $trunkPage->toArray(),
        $postDatabase->freelistTrunkPages(),
    ),
    'pageCountAfter' => $postDatabase->pageCount(),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
