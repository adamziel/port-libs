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
$optionName = $argv[1] ?? str_repeat('z', 70);
$optionValue = $argv[2] ?? 'parent-grown-value';

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
        'wp_options_option_name',
        'wp_options',
        3,
        'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
    ])),
], $pageSize, 100, $firstPage);

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);

$indexRootCells = [];
foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
    $indexRootCells[] = SQLiteIndexCell::encode(
        SQLiteRecord::encode([str_repeat($prefix, 70), 100 + $index]),
        $pageSize,
        null,
        4 + $index,
    );
}
$indexRootPage = SQLiteIndexInteriorPage::assemble($indexRootCells, 10, $pageSize);

$leafPages = [];
foreach (['a', 'h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
    $leafPages[] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 50 + $index])),
    ], $pageSize);
}

$rightLeafCells = [];
foreach (['r', 's', 't', 'u', 'v', 'w'] as $index => $prefix) {
    $rightLeafCells[] = SQLiteIndexCell::encode(SQLiteRecord::encode([str_repeat($prefix, 70), 200 + $index]));
}

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . implode('', $leafPages)
    . SQLiteIndexLeafPage::assemble($rightLeafCells, $pageSize),
);

$plan = $database->planKeyValueRowInsert(2, $optionName, $optionValue, 'yes');

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
    'applicationUse' => 'Plan a generated wp_options insert that splits a full option_name leaf and grows a full index-interior root, without the SQLite extension.',
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
    'splitLeafPages' => [
        10 => $postDatabase->pageHeader(10)->cellCount,
        11 => $postDatabase->pageHeader(11)->cellCount,
    ],
    'indexRecordCount' => count($indexRecords),
    'insertedOption' => $postDatabase->keyValueRowByIndexedName($optionName)?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
