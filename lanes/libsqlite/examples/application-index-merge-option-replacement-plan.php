<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$nameLength = 64;
$optionName = $argv[1] ?? str_repeat('z', $nameLength);
$replacementValue = $argv[2] ?? 'fixed-cache';

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 6), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
        'index',
        'wp_options_autoload_name',
        'wp_options',
        3,
        'CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name)',
    ])),
], $pageSize, 100, $firstPage);

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
], $pageSize);

$indexRootPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('g', $nameLength), 100]), $pageSize, null, 4),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('m', $nameLength), 101]), $pageSize, null, 5),
], 6, $pageSize);

$leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat('a', $nameLength), 50])),
], $pageSize);

$middleIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('h', $nameLength), 60])),
], $pageSize);

$rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 2])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 3])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . $leftIndexLeafPage
    . $middleIndexLeafPage
    . $rightIndexLeafPage,
);

$plan = $database->planKeyValueRowReplace($optionName, $replacementValue, 'no');

$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$header = SQLiteHeader::parse($plan->pageImages()[1]);
$indexRecords = array_map(
    static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
    $postDatabase->indexCells(3),
);
$freelistTrunks = $postDatabase->freelistTrunkPages();

echo json_encode([
    'applicationUse' => 'Plan a wp_options replacement that disables autoload, merges an underfilled composite secondary-index leaf when sibling redistribution is impossible, and moves the obsolete index page onto the freelist without the SQLite extension.',
    'plan' => $plan->toArray(),
    'rebalanceSummary' => $plan->btreeRebalanceSummary(),
    'rebalanceActions' => $plan->btreeRebalanceActions(),
    'indexRootPageType' => $postDatabase->pageHeader(3)->pageType,
    'indexRootCellCount' => $postDatabase->pageHeader(3)->cellCount,
    'mergedIndexLeafPages' => [
        4 => $postDatabase->pageHeader(4)->cellCount,
        5 => $postDatabase->pageHeader(5)->cellCount,
    ],
    'freelist' => [
        'firstTrunkPage' => $header->firstFreelistTrunkPage,
        'freelistPageCount' => $header->freelistPageCount,
        'trunkPages' => array_map(static fn ($trunk): array => $trunk->toArray(), $freelistTrunks),
    ],
    'indexRecords' => $indexRecords,
    'replacedOption' => $postDatabase->keyValueRowByIndexedLoadPolicyAndName('no', $optionName)?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
