<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$pageCount = 310;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;

foreach ([306 => 307, 307 => 308, 308 => 0, 309 => 310, 310 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat('w', $pageSize - 4);
}

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
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

$putPointerMapEntry(4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry(42, SQLitePointerMapEntry::BTREE_PAGE, 4);
foreach ([306, 307, 308] as $index => $pageNumber) {
    $putPointerMapEntry(
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 42 : $pageNumber - 1,
    );
}
foreach ([309, 310] as $index => $pageNumber) {
    $putPointerMapEntry(
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 4 : 309,
    );
}

$plan = SQLiteBTreeFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options transient value overflow',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
        ],
        [
            'source' => 'wp_options option_name index overflow',
            'obsolete_overflow_page_numbers' => [309, 310],
        ],
    ],
    3,
    2,
    42,
    [
        307 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(10810, SQLiteRecord::encode([null, '_transient_incremental vacuum reuse', 'fresh', 'no'])),
        ]),
        306 => SQLiteIndexLeafPage::assemble([
            SQLiteRecord::encode(['_transient_incremental vacuum reuse', 10810]),
        ]),
    ],
    true,
);

echo json_encode([
    'scenario' => 'wordpress-btree-incremental-vacuum-reuse',
    'allocatedPages' => $plan->allocatedPageNumbers(),
    'vacuumTruncatedPages' => $plan->vacuumPlan->truncatedFreedPointerMapPages(),
    'currentSourceRows' => $plan->incrementalVacuumReuseRows(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
