<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$nameLength = 64;
$optionName = $argv[1] ?? str_repeat('w', $nameLength);
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
$firstPage = substr_replace($firstPage, pack('N', 10), 28, 4);
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
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, $optionName, 'stale-cache', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
], $pageSize);

$indexRootCells = [];
foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
    $indexRootCells[] = SQLiteIndexCell::encode(
        SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 100 + $index]),
        $pageSize,
        null,
        4 + $index,
    );
}
$indexRootPage = SQLiteIndexInteriorPage::assemble($indexRootCells, 10, $pageSize);

$leftIndexEntries = [];
foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $index => $prefix) {
    $leftIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat($prefix, $nameLength), 50 + $index]));
}
$middleLeafPages = [];
foreach (['h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
    $middleLeafPages[] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 60 + $index])),
    ], $pageSize);
}
$rightIndexEntries = [];
foreach (['r', 's', 't', 'u', 'v'] as $index => $prefix) {
    $rightIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 200 + $index]));
}
$rightIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $optionName, 2]));

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . SQLiteIndexLeafPage::assemble($leftIndexEntries, $pageSize)
    . implode('', $middleLeafPages)
    . SQLiteIndexLeafPage::assemble($rightIndexEntries, $pageSize),
);

$plan = $database->planOptionRowReplace($optionName, $replacementValue, 'no');

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
$indexRecords = array_map(
    static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
    $postDatabase->indexCells(3),
);

echo json_encode([
    'applicationUse' => 'Plan a wp_options replacement that moves an autoloaded option into a full composite secondary-index leaf and grows a full index-interior root, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'indexRootPageType' => $postDatabase->pageHeader(3)->pageType,
    'indexRootCellCount' => $postDatabase->pageHeader(3)->cellCount,
    'newInteriorPages' => [
        12 => [
            'cellCount' => $postDatabase->pageHeader(12)->cellCount,
            'rightMostPointer' => $postDatabase->pageHeader(12)->rightMostPointer,
        ],
        13 => [
            'cellCount' => $postDatabase->pageHeader(13)->cellCount,
            'rightMostPointer' => $postDatabase->pageHeader(13)->rightMostPointer,
        ],
    ],
    'splitDestinationLeafPages' => [
        4 => $postDatabase->pageHeader(4)->cellCount,
        11 => $postDatabase->pageHeader(11)->cellCount,
    ],
    'sourceLeafPage' => [
        10 => $postDatabase->pageHeader(10)->cellCount,
    ],
    'indexRecordCount' => count($indexRecords),
    'replacedOption' => $postDatabase->optionRowByIndexedAutoloadAndName('no', $optionName)?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
