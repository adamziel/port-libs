<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan;
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
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next215', str_repeat('cache:', 42)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
]);
$pages[105] = str_repeat("\0", 512);
foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(78 + ($pageNumber - 105)), 508);
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
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext215Plan::tableLeafFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    2,
    str_repeat('next215-current-source-commit-', 50),
    3,
    true,
    2,
);
$summary = $plan->commitSummary();

echo json_encode([
    'scenario' => 'wordpress-btree-vacuum-pointermap-freeblock-current-source-next215',
    'wordpressUse' => 'After deleting an overflow-backed copied wp_options transient and vacuuming tail pages, commit only applied current-source pointer-map/freeblock pages while keeping truncated tail pages fenced.',
    'status' => $summary['status'],
    'committed_pages' => $summary['committed_pages'],
    'committed_pointer_map_pages' => $summary['committed_pointer_map_pages'],
    'committed_payload_pages' => $summary['committed_payload_pages'],
    'commit_row_count' => $summary['commit_row_count'],
    'commit_matches_apply_pages' => $summary['commit_matches_apply_pages'],
    'all_pointer_maps_committed_before_payload' => $summary['all_pointer_maps_committed_before_payload'],
    'all_tail_pages_fenced_for_commit' => $summary['all_tail_pages_fenced_for_commit'],
], JSON_PRETTY_PRINT) . PHP_EOL;

if (
    $summary['status'] === 'btree-vacuum-pointermap-freeblock-current-source-next215-ready'
    && $summary['committed_pages'] === [2, 3, 105, 106, 107, 108]
    && $summary['committed_pointer_map_pages'] === [2, 105]
    && $summary['committed_payload_pages'] === [3, 106, 107, 108]
    && $summary['commit_matches_apply_pages'] === true
    && $summary['all_pointer_maps_committed_before_payload'] === true
    && $summary['all_tail_pages_fenced_for_commit'] === true
) {
    echo "wordpress-btree-vacuum-pointermap-freeblock-current-source-next215 self-test passed\n";
}
