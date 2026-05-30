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
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next260', str_repeat('cache:', 42)])),
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
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafReaderHandoffFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    2,
    str_repeat('next260-reader-handoff-freeblock-', 40),
    3,
    true,
    2,
);
$summary = $plan->handoffSummary();

echo json_encode([
    'scenario' => 'application-btree-vacuum-pointermap-freeblock-current-source-next260',
    'applicationUse' => 'After deleting an overflow-backed copied wp_options transient, reader-visible current-source handoff waits for pointer-map snapshots before exposing reusable freeblock pages.',
    'status' => $summary['status'],
    'handoff_pages' => $summary['handoff_pages'],
    'reader_visible_pages' => $summary['reader_visible_pages'],
    'pointer_map_snapshot_pages' => $summary['pointer_map_snapshot_pages'],
    'reusable_freeblock_snapshot_pages' => $summary['reusable_freeblock_snapshot_pages'],
    'reader_visible_pages_by_group' => $summary['reader_visible_pages_by_group'],
    'all_reader_visibility_after_pointer_map' => $summary['all_reader_visibility_after_pointer_map'],
    'all_freeblock_receipts_reader_visible' => $summary['all_freeblock_receipts_reader_visible'],
    'all_tail_pages_blocked_from_reader' => $summary['all_tail_pages_blocked_from_reader'],
    'all_source_epochs_preserved' => $summary['all_source_epochs_preserved'],
], JSON_PRETTY_PRINT) . PHP_EOL;

if (
    $summary['status'] === 'btree-vacuum-pointermap-freeblock-current-source-next260-ready'
    && $summary['handoff_pages'] === [2, 3, 105, 106, 105, 107, 108]
    && $summary['reader_visible_pages'] === [2, 3, 105, 106, 107, 108]
    && $summary['pointer_map_snapshot_pages'] === [2, 105]
    && $summary['reusable_freeblock_snapshot_pages'] === [3, 106, 107, 108]
    && $summary['reader_visible_pages_by_group'] === [1 => [2, 3], 2 => [105, 106], 3 => [105, 107, 108]]
    && $summary['all_reader_visibility_after_pointer_map'] === true
    && $summary['all_freeblock_receipts_reader_visible'] === true
    && $summary['all_tail_pages_blocked_from_reader'] === true
    && $summary['all_source_epochs_preserved'] === true
) {
    echo "application-btree-vacuum-pointermap-freeblock-current-source-next260 self-test passed\n";
}
