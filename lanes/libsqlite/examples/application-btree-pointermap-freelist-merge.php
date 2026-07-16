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
$existingLeaves = [130, 131, 132];
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', $firstTrunk), 32, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1 + count($existingLeaves)), 36, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);
$pages[$firstTrunk] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), $offset, 5);
};

$tablePayload = str_repeat('wp-options-merge-table:', 58);
$indexPayload = str_repeat('wp-options-merge-index:', 38);
$tablePages = SQLiteOverflowPage::encodeChainAtPages($tablePayload, [20, 22, 106], $pageSize);
$indexPages = SQLiteOverflowPage::encodeChainAtPages($indexPayload, [107, 21], $pageSize);

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], $firstTrunk => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach ($existingLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ([20, 107] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4);
}
foreach ([22 => 20, 106 => 22, 21 => 107] as $pageNumber => $parentPageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::OVERFLOW_PAGE, $parentPageNumber);
}
foreach ($tablePages + $indexPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$release = SQLiteOverflowFreelistReleasePlan::fromOverflowChains(
    $database,
    [
        [
            'source' => 'wp_options-table-overflow',
            'first_page' => 20,
            'overflow_payload_bytes' => strlen($tablePayload),
            'rowids' => [52],
        ],
        [
            'source' => 'wp_options-option-name-overflow',
            'first_page' => 107,
            'overflow_payload_bytes' => strlen($indexPayload),
            'record_values' => [['_transient_merge_current', 52]],
        ],
    ],
    true,
);

foreach ($release->freePlan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Delete copied wp_options overflow-backed transient table and option_name index cells, then merge the obsolete current next-pointer overflow pages into the existing freelist trunk while auto-vacuum pointer-map entries become free-page.',
    'releasedOverflowPages' => $release->releasedOverflowPages,
    'existingFreelistTrunks' => $release->freePlan->existingTrunkPageNumbers(),
    'newFreelistTrunks' => $release->freePlan->newTrunkPageNumbers,
    'freelistAfter' => $postDatabase->freelistPageNumbers(),
    'allocationOrderPreview' => $postDatabase->freelistAllocationOrder(8),
    'pointerMapTypes' => array_map(
        static fn (int $pageNumber): string => $postDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
        $release->releasedOverflowPages,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
