<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReleasePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 260;
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
$firstPage = substr_replace($firstPage, pack('N', 8), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 121), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, range(130, 249), $pageSize);
$releasedPages = [20, 21, 22, 106, 107];

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
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

$putPointerMapEntry(8, SQLitePointerMapEntry::FREE_PAGE, 0);
foreach (range(130, 249) as $pageNumber) {
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ($releasedPages as $index => $pageNumber) {
    $putPointerMapEntry(
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 4 : $releasedPages[$index - 1],
    );
    $pages[$pageNumber] = str_repeat(chr(65 + $index), $pageSize);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteOverflowFreelistReleasePlan::fromDeleteResults($database, [
    [
        'source' => 'wp_options-transient-table-overflow',
        'obsolete_overflow_page_numbers' => [20, 21, 22],
        'rowids' => [17],
    ],
    [
        'source' => 'wp_options-option-name-index-overflow',
        'obsolete_overflow_page_numbers' => [106, 107],
        'record_values' => [['_transient_spill', 17]],
    ],
], true);
foreach ($plan->freePlan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Delete copied wp_options transient table/index overflow chains when the first freelist trunk is full, spilling the first obsolete overflow page into a new freelist trunk and rewriting auto-vacuum pointer-map entries without ext/sqlite.',
    'releasedOverflowPages' => $plan->releasedOverflowPages,
    'newTrunkPages' => $plan->freePlan->newTrunkPageNumbers,
    'newTrunkLeafPages' => $postDatabase->freelistTrunkPages()[0]->leafPageNumbers,
    'firstFreelistTrunkPage' => $postDatabase->header->firstFreelistTrunkPage,
    'freelistPageCount' => $postDatabase->header->freelistPageCount,
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(6),
    'pointerMapTypes' => array_map(
        static fn (int $pageNumber): string => $postDatabase->pointerMapEntryForPage($pageNumber)->typeName(),
        $plan->releasedOverflowPages,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
