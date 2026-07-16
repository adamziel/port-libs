<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReleasePlan;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 260;
$firstTrunk = 8;
$existingLeaves = range(130, 249);
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
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
$firstPage = substr_replace($firstPage, pack('N', $firstTrunk), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 1 + count($existingLeaves)), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;
$pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

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

foreach ($existingLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ([20, 107] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
}
foreach ([22 => 20, 106 => 22, 21 => 107] as $pageNumber => $parentPageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::OVERFLOW_PAGE, $parentPageNumber);
}

$tablePayload = str_repeat('table-current-next:', 60);
$indexPayload = str_repeat('index-current-next:', 35);
foreach (SQLiteOverflowPage::encodeChainAtPages($tablePayload, [20, 22, 106], $pageSize)
    + SQLiteOverflowPage::encodeChainAtPages($indexPayload, [107, 21], $pageSize) as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$release = SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
    $database,
    [
        [
            'source' => 'wp_options-table-current-overflow',
            'first_page' => 20,
            'overflow_payload_bytes' => strlen($tablePayload),
            'rowids' => [41],
        ],
        [
            'source' => 'wp_options-option-name-current-overflow',
            'first_page' => 107,
            'overflow_payload_bytes' => strlen($indexPayload),
            'record_values' => [['_transient_current_next', 41]],
        ],
    ],
    true,
);

foreach ($release->freePlan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Release copied wp_options table/index overflow chains by following current SQLite next-page pointers, then secure-delete freelist leaves and rewrite auto-vacuum pointer-map entries without ext/sqlite.',
    'releasedOverflowPages' => $release->releasedOverflowPages,
    'freelistAfter' => $postDatabase->freelistPageNumbers(),
    'allocationPreview' => $postDatabase->freelistAllocationOrder(7),
    'secureDeleteClearedPages' => $release->freePlan->clearedPageNumbers,
    'pointerMapTypes' => array_map(
        static fn (int $pageNumber): string => $postDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
        $release->releasedOverflowPages,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
