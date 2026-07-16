<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistReusePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 260;
$releasedPages = [20, 21, 22, 106, 107];
$existingLeaves = array_values(array_filter(
    range(130, 252),
    static fn (int $pageNumber): bool => $pageNumber !== 208,
));

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
$firstPage = substr_replace($firstPage, pack('N', count($existingLeaves) + 1), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, $pageSize);

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

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 8 => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach ($existingLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ($releasedPages as $index => $pageNumber) {
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 4 : $releasedPages[$index - 1],
    );
    $pages[$pageNumber] = str_repeat(chr(65 + $index), $pageSize);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteOverflowFreelistReusePlan::fromDeleteResults(
    $database,
    [
        [
            'source' => 'wp_options-transient-table-overflow-current-next51',
            'obsolete_overflow_page_numbers' => [20, 21, 22],
            'rowids' => [41],
        ],
        [
            'source' => 'wp_options-option-name-index-overflow-current-next51',
            'obsolete_overflow_page_numbers' => [106, 107],
            'record_values' => [['_transient_reuse_current_next51', 41]],
        ],
    ],
    5,
    4,
    true,
);

foreach ($plan->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Replace a copied wp_options transient row and option_name index entry whose obsolete overflow chains are immediately reused for the next large option value without ext/sqlite.',
    'reusePlan' => $plan->toArray(),
    'freelistAfter' => [
        'first_trunk_page' => $postDatabase->header->firstFreelistTrunkPage,
        'page_count' => $postDatabase->header->freelistPageCount,
        'next_allocation_order' => $postDatabase->freelistAllocationOrder(6),
    ],
    'pointerMapAfter' => array_combine(
        array_map('strval', $plan->replacementOverflowPageNumbers()),
        array_map(
            static fn (int $pageNumber): array => $postDatabase->pointerMapEntryForPage($pageNumber)->toArray(),
            $plan->replacementOverflowPageNumbers(),
        ),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
