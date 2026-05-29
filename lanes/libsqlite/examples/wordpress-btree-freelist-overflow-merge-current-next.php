<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowFreelistMergePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 220;
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
$firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 122), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;

$currentLeaves = range(50, 168);
$pages[5] = SQLiteFreelistTrunkPage::assemble(201, $currentLeaves, $pageSize);
$pages[201] = SQLiteFreelistTrunkPage::assemble(null, [202], $pageSize);

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

foreach ([20, 21, 22] as $pageNumber) {
    $pages[$pageNumber] = str_repeat(chr($pageNumber), $pageSize);
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::OVERFLOW_PAGE, $pageNumber - 1);
}
foreach ([5, 201, 202] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ($currentLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
ksort($pages);

$plan = SQLiteOverflowFreelistMergePlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'deleted wp_options transient row overflow',
            'obsolete_overflow_page_numbers' => [20, 21],
        ],
        [
            'source' => 'deleted option_name index overflow',
            'obsolete_overflow_page_numbers' => [22],
        ],
    ],
    secureDelete: true,
);

echo json_encode($plan->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
