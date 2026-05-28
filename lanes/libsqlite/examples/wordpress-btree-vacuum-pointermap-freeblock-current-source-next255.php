<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan;
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
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next255', str_repeat('cache:', 42)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
]);
$pages[105] = str_repeat("\0", 512);
foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(74 + ($pageNumber - 105)), 508);
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
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext255Plan::tableLeafFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    2,
    str_repeat('next255-current-source-', 50),
    3,
    true,
    2,
);
$summary = $plan->publicationSummary();

echo json_encode([
    'scenario' => 'wordpress-btree-vacuum-pointermap-freeblock-current-source-next255',
    'wordpressUse' => 'After deleting an overflow-backed copied wp_options transient, publish current-source next pages only after pointer-map and freeblock visibility make payload reuse safe.',
    'status' => $summary['status'],
    'published_pages' => $summary['published_pages'],
    'next_published_pages' => $summary['next_published_pages'],
    'pointer_map_publication_pages' => $summary['pointer_map_publication_pages'],
    'freeblock_payload_publication_pages' => $summary['freeblock_payload_publication_pages'],
    'duplicate_pointer_map_publication_pages' => $summary['duplicate_pointer_map_publication_pages'],
    'published_pages_match_admitted_pages' => $summary['published_pages_match_admitted_pages'],
    'all_payload_publications_wait_for_pointer_maps' => $summary['all_payload_publications_wait_for_pointer_maps'],
    'all_tail_pages_remain_fenced_for_publication' => $summary['all_tail_pages_remain_fenced_for_publication'],
], JSON_PRETTY_PRINT) . PHP_EOL;

if (
    $summary['status'] === 'btree-vacuum-pointermap-freeblock-current-source-next255-ready'
    && $summary['published_pages'] === [2, 3, 105, 106, 105, 107, 108]
    && $summary['next_published_pages'] === [3, 105, 106, 105, 107, 108, null]
    && $summary['pointer_map_publication_pages'] === [2, 105]
    && $summary['freeblock_payload_publication_pages'] === [3, 106, 107, 108]
    && $summary['duplicate_pointer_map_publication_pages'] === [105]
    && $summary['published_pages_match_admitted_pages'] === true
    && $summary['all_payload_publications_wait_for_pointer_maps'] === true
    && $summary['all_tail_pages_remain_fenced_for_publication'] === true
) {
    echo "wordpress-btree-vacuum-pointermap-freeblock-current-source-next255 self-test passed\n";
}
