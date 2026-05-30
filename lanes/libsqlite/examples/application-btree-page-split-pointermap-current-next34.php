<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
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
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = substr_replace(
    str_repeat("\0", $pageSize),
    chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0),
    0,
    5,
);
$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        3,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);
$rootLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_migration_lock', 'old-lock', 'no'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes($schemaPage . $pointerMapPage . $rootLeafPage);
$replacementValue = str_repeat('expanded-cache-', 28);
$plan = $database->planOptionRowReplace('blogname', $replacementValue, 'no');
$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $pageNumber <= $database->pageCount()
        ? $database->page($pageNumber)
        : str_repeat("\0", $pageSize);
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
ksort($pages);
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$rootHeader = $postDatabase->pageHeader(3);
$rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $rootHeader);
$leftChildPage = $rootCells[0]->leftChildPage;
$rightChildPage = $rootHeader->rightMostPointer;

echo json_encode([
    'scenario' => 'application-btree-page-split-pointermap-current-next34',
    'applicationUse' => 'Preview copied wp_options auto-vacuum table-root split where both new child pages receive btree-page pointer-map entries owned by the current root page.',
    'plan' => $plan->toArray(),
    'root' => [
        'page' => 3,
        'pageType' => $rootHeader->pageType,
        'leftChild' => $leftChildPage,
        'rightMostPointer' => $rightChildPage,
    ],
    'pointerMap' => [
        'root' => $postDatabase->pointerMapEntryForPage(3)->toArray(),
        'leftChild' => $postDatabase->pointerMapEntryForPage($leftChildPage)->toArray(),
        'rightChild' => $postDatabase->pointerMapEntryForPage($rightChildPage)->toArray(),
    ],
    'blogname' => $postDatabase->tableRowByRowIdByName('wp_options', 2)?->values(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
