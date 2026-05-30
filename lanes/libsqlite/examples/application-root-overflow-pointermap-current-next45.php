<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 207;

$makeFirstPage = static function (int $firstTrunk, int $freelistCount) use ($pageSize, $pageCount): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 1), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

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

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage(5, 6);
$pages[5] = SQLiteFreelistTrunkPage::assemble(106, [3, 104], $pageSize);
$pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107, 206], $pageSize);

foreach ([5, 106, 3, 104, 107, 206] as $freePage) {
    $putPointerMapEntry($pages, $freePage, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->planRootOverflowPageAllocation(7);
$header = SQLiteHeader::parse($plan->firstPage);

$summary = [
    'scenario' => 'wp_options root btree payload overflow after plugin settings import',
    'allocated_pages' => $plan->allocatedPageNumbers,
    'appended_pages' => $plan->appendedPageNumbers,
    'database_page_count' => $header->databaseSizePages,
    'first_overflow_parent_page' => $plan->allocatedPointerMapEntries()[0]['parent_page_number'],
    'pointer_map_pages' => array_keys($plan->updatedPointerMapPages),
    'pointer_map_chain' => array_map(
        static fn (array $entry): string => $entry['page_number'] . ':' . $entry['type_name'] . ':' . $entry['parent_page_number'],
        $plan->allocatedPointerMapEntries(),
    ),
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
