<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
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
$firstPage = substr_replace($firstPage, pack('N', 110), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 110, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_receipt_publication', str_repeat('cache:', 42)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
]);
$pages[105] = str_repeat("\0", 512);
foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
}

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
    108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
    109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
    110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pageNumber, $type, $parent);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafReceiptPublicationFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    2,
    str_repeat('receipt-current-source-', 50),
    3,
    true,
    2,
);
$summary = $plan->publicationSummary();

echo json_encode([
    'scenario' => 'wordpress-btree-vacuum-pointermap-freeblock-current-source-receipt-publication',
    'wordpressUse' => 'After deleting an overflow-backed copied wp_options transient, publish reusable current-source pages only after pointer-map generations and freeblock receipts are commit-ready.',
    'status' => $summary['status'],
    'published_pages' => $summary['published_pages'],
    'pointer_map_publication_pages' => $summary['pointer_map_publication_pages'],
    'freeblock_receipt_pages' => $summary['freeblock_receipt_pages'],
    'reusable_payload_pages' => $summary['reusable_payload_pages'],
    'duplicate_pointer_map_pages' => $summary['duplicate_pointer_map_pages'],
    'published_pages_match_admitted_pages' => $summary['published_pages_match_admitted_pages'],
    'all_pointer_maps_publish_before_payload_reuse' => $summary['all_pointer_maps_publish_before_payload_reuse'],
    'all_freeblock_receipts_visible_before_reuse' => $summary['all_freeblock_receipts_visible_before_reuse'],
    'all_payload_reuse_has_cursor_advance' => $summary['all_payload_reuse_has_cursor_advance'],
    'all_tail_pages_remain_fenced' => $summary['all_tail_pages_remain_fenced'],
], JSON_PRETTY_PRINT) . PHP_EOL;

if (
    $summary['status'] === 'btree-vacuum-pointermap-freeblock-current-source-receipt-publication-ready'
    && $summary['published_pages'] === [2, 3, 105, 106, 105, 107, 108]
    && $summary['pointer_map_publication_pages'] === [2, 105]
    && $summary['freeblock_receipt_pages'] === [2, 3, 105, 106, 107, 108]
    && $summary['reusable_payload_pages'] === [3, 106, 107, 108]
    && $summary['duplicate_pointer_map_pages'] === [105]
    && $summary['published_pages_match_admitted_pages'] === true
    && $summary['all_pointer_maps_publish_before_payload_reuse'] === true
    && $summary['all_freeblock_receipts_visible_before_reuse'] === true
    && $summary['all_payload_reuse_has_cursor_advance'] === true
    && $summary['all_tail_pages_remain_fenced'] === true
) {
    echo "wordpress-btree-vacuum-pointermap-freeblock-current-source-receipt-publication self-test passed\n";
}
