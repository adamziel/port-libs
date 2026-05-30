<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeLeafMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreeLeafMergePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
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
$firstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$leftIndexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_cleanup', 40])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_a', 41])),
], $pageSize);
$rightIndexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_b', 42])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_c', 43])),
], $pageSize);
$parentPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode(['index', 'autoload', 'wp_options', 3, 'CREATE INDEX autoload ON wp_options(autoload, option_name)'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes($firstPage . $pointerMapPage . $leftIndexPage . $rightIndexPage . $parentPage);
$mergePlan = SQLiteBTreeLeafMergePlan::indexLeaf($leftIndexPage, $rightIndexPage, 3, 4, 5, $pageSize);
$application = SQLiteBTreeLeafMergeApplicationPlan::apply($database, $mergePlan, true);

$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($application->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$mergedHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);

echo json_encode([
    'scenario' => 'application-btree-leaf-merge-apply',
    'applicationUse' => 'Apply a copied wp_options autoload-index delete/rebalance preview by materializing the merged leaf page, placing the obsolete sibling on the freelist, and rewriting auto-vacuum pointer-map metadata without requiring ext/sqlite.',
    'application' => $application->toArray(),
    'freelistAfter' => [
        'firstTrunkPage' => $postDatabase->header->firstFreelistTrunkPage,
        'pageCount' => $postDatabase->header->freelistPageCount,
        'pageNumbers' => $postDatabase->freelistPageNumbers(),
    ],
    'obsoleteSiblingPointerMap' => $postDatabase->pointerMapEntryForPage(4)->toArray(),
    'mergedIndexRecords' => array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($postDatabase->page(3), $mergedHeader),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
