<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 8), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 8), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 8, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[4] = SQLiteTableLeafPage::assemble([]);
$pages[5] = SQLiteTableLeafPage::assemble([]);
$pages[6] = str_pad(pack('N', 7) . str_repeat('A', 508), 512, "\0");
$pages[7] = str_pad(pack('N', 0) . str_repeat('B', 192), 512, "\0");
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, [], 512);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = (intdiv($pageNumber - 2, 103) * 103) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};
$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
$putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FREE_PAGE, 0);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$leafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(12102, SQLiteRecord::encode([null, '_transient_reused_overflow_page_next121', 'reused', 'yes'])),
]);
$plan = SQLiteBTreeOverflowFreelistVacuumReuseCurrentSourceNextPlan::fromOverflowChains(
    $database,
    [[
        'source' => 'wp-options-delete-large-transient',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [12101],
    ]],
    2,
    3,
    [6 => $leafPage, 7 => $leafPage],
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'reused_page_numbers' => $plan->reusedPageNumbers(),
    'final_freelist_page_numbers' => $plan->databaseAfterReuse->freelistPageNumbers(),
    'reuse_pointer_map_types' => array_column($plan->reuseRows, 'reuse_pointer_map_type'),
], JSON_PRETTY_PRINT) . PHP_EOL;
