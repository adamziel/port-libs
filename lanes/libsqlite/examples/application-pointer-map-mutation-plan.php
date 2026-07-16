<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 7), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        3,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
    7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$wpOptionsPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);
$childPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'transient_cleanup_marker', 'obsolete-overflow-tail', 'no'])),
], $pageSize);
$firstOverflowPage = substr_replace(str_repeat("\0", $pageSize), pack('N', 6) . 'theme mod payload', 0, 21);
$secondOverflowPage = substr_replace(str_repeat("\0", $pageSize), pack('N', 0) . 'tail to free', 0, 16);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $pointerMapPage
    . $wpOptionsPage
    . $childPage
    . $firstOverflowPage
    . $secondOverflowPage
    . str_repeat("\0", $pageSize),
);

$freePlan = $database->planPageFree(6);
$pages = [];
for ($pageNumber = 1; $pageNumber <= $freePlan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($freePlan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$postHeader = SQLiteHeader::parse($freePlan->pageImages()[1]);

echo json_encode([
    'applicationUse' => 'Plan an auto-vacuum-safe wp_options repair preflight that frees an obsolete overflow page and updates SQLite pointer-map metadata before any future page moves or reuse.',
    'plan' => [
        'freedPageNumbers' => $freePlan->freedPageNumbers,
        'updatedPageNumbers' => array_keys($freePlan->pageImages()),
        'databasePageCount' => $freePlan->databasePageCount,
    ],
    'freelist' => [
        'firstTrunkPage' => $postHeader->firstFreelistTrunkPage,
        'freelistPageCount' => $postHeader->freelistPageCount,
        'pageNumbers' => $postDatabase->freelistPageNumbers(),
    ],
    'pointerMapEntryForFreedPage' => $postDatabase->pointerMapEntryForPage(6)->toArray(),
    'wpOptions' => array_map(
        static fn ($option): array => $option->toArray(),
        $postDatabase->keyValueRows(),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
