<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreepagePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$firstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $firstPage(12);
$pages[2] = str_repeat("\0", 512);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
] as $pageNumber => [$type, $parentPage]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parentPage);
}

foreach ([6 => 7, 7 => 0, 8 => 9, 9 => 10, 10 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(64 + $pageNumber), 508);
}

$plan = SQLiteBTreeOverflowVacuumFreepagePlan::fromOverflowChains(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options-transient-value-table-delete',
            'first_page' => 6,
            'overflow_payload_bytes' => 700,
            'rowids' => [91],
        ],
        [
            'source' => 'wp_options-option-name-index-delete',
            'first_page' => 8,
            'overflow_payload_bytes' => 1300,
            'record_values' => [['_transient_timeout_pointer-map freepage', 91]],
        ],
    ],
    secureDelete: true,
    nextAllocationLimit: 5,
);

echo json_encode([
    'scenario' => 'application-btree-overflow-pointermap-freepage',
    'releasedOverflowPages' => $plan->releasePlan->releasedOverflowPages,
    'currentFreelistPages' => $plan->currentFreelistPages,
    'nextAllocationOrder' => $plan->nextAllocationOrder,
    'pointerMapFreepageRows' => $plan->overflowPointerMapFreepageRows(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
