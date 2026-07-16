<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$reservedBytes = 12;
$usableSize = $pageSize - $reservedBytes;
$emptyPage = str_repeat("\0", $pageSize);

$makeFirstPage = static function (int $databasePageCount, int $firstFreelistTrunkPage, int $freelistPageCount) use ($pageSize, $reservedBytes): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = chr($reservedBytes);
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $databasePageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$schemaPage = static function (string $firstPage) use ($pageSize, $usableSize): string {
    return SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
            'table',
            'wp_options',
            'wp_options',
            2,
            'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
        ])),
    ], $pageSize, 100, $firstPage, $usableSize);
};

$preDatabase = SQLiteDatabase::fromBytes(
    $schemaPage($makeFirstPage(7, 5, 4))
    . $emptyPage
    . $emptyPage
    . $emptyPage
    . SQLiteFreelistTrunkPage::assemble(null, [3, 7, 4], $pageSize, $usableSize)
    . $emptyPage
    . $emptyPage,
);

$optionValue = $argv[1] ?? str_repeat('planned reusable transient:', 40);
$optionPayload = SQLiteRecord::encode([
    null,
    'planned_freelist_cache',
    $optionValue,
    'yes',
]);
$localPayloadLength = SQLiteTableLeafCell::localPayloadLength(strlen($optionPayload), $usableSize);
$overflowPayload = substr($optionPayload, $localPayloadLength);
$requiredOverflowPages = SQLiteOverflowPage::requiredPageCount(strlen($overflowPayload), $pageSize, $usableSize);
$allocationPlan = $preDatabase->planPageAllocation($requiredOverflowPages, false);
$chosenPages = $allocationPlan->allocatedPageNumbers;
if (count($chosenPages) !== 2) {
    throw new RuntimeException('This example fixture expects an option value that uses exactly two reusable overflow pages.');
}

$optionCell = SQLiteTableLeafCell::encode(1, $optionPayload, $usableSize, $chosenPages[0]);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages($overflowPayload, $chosenPages, $pageSize, $usableSize);

$postPages = [];
for ($pageNumber = 1; $pageNumber <= 7; $pageNumber++) {
    $postPages[$pageNumber] = $emptyPage;
}
$postPages[1] = $allocationPlan->firstPage;
$postPages[2] = SQLiteTableLeafPage::assemble([$optionCell], $pageSize, 0, null, $usableSize);
foreach ($allocationPlan->updatedFreelistPages as $pageNumber => $page) {
    $postPages[$pageNumber] = $page;
}
foreach ($overflowPages as $pageNumber => $page) {
    $postPages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $postPages));
$option = $postDatabase->keyValueRows()[0] ?? null;

echo json_encode([
    'applicationUse' => 'Inspect SQLite freelist trunk metadata, choose reusable pages for a large wp_options value, update the freelist metadata, and parse the repaired image without the SQLite extension.',
    'freelistBefore' => array_map(
        static fn (SQLiteFreelistTrunkPage $trunkPage): array => $trunkPage->toArray(),
        $preDatabase->freelistTrunkPages(),
    ),
    'allocationPlan' => $allocationPlan->toArray(),
    'chosenOverflowPages' => $chosenPages,
    'overflowNextPointers' => array_map(
        static fn (string $page): int => unpack('N', substr($page, 0, 4))[1],
        $overflowPages,
    ),
    'freelistAfter' => array_map(
        static fn (SQLiteFreelistTrunkPage $trunkPage): array => $trunkPage->toArray(),
        $postDatabase->freelistTrunkPages(),
    ),
    'lookup' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
