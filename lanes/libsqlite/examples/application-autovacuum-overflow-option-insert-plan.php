<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
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
$firstPage = substr_replace($firstPage, pack('N', 3), 28, 4);
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

$pointerMapPage = substr_replace(
    str_repeat("\0", $pageSize),
    chr(SQLitePointerMapEntry::ROOT_PAGE) . pack('N', 0),
    0,
    5,
);
$wpOptionsPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes($schemaPage . $pointerMapPage . $wpOptionsPage);
$largeThemeMods = str_repeat('serialized-theme-mod-fragment:', 64) . 'done';
$plan = $database->planKeyValueRowInsert(2, 'theme_mods_twentyfive', $largeThemeMods, 'yes');

$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $pageNumber <= $database->pageCount()
        ? $database->page($pageNumber)
        : str_repeat("\0", $pageSize);
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$overflowEntries = [];
foreach ($plan->overflowPageNumbers as $pageNumber) {
    $overflowEntries[] = $postDatabase->pointerMapEntryForPage($pageNumber)->toArray();
}

echo json_encode([
    'applicationUse' => 'Preflight a large wp_options theme_mods insert for an auto-vacuum SQLite database while updating pointer-map entries for the new overflow chain.',
    'plan' => [
        'updatedPageNumbers' => array_keys($plan->pageImages()),
        'overflowPageNumbers' => $plan->overflowPageNumbers,
        'databasePageCount' => $plan->databasePageCount,
    ],
    'pointerMapEntries' => $overflowEntries,
    'insertedOption' => $postDatabase->tableRowByRowIdByName('wp_options', 2)?->values(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
