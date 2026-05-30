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
$optionName = $argv[1] ?? str_repeat('z', $nameLength);
$optionValue = $argv[2] ?? 'nonroot-parent-grown-value';

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 12), 28, 4);
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
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
], $pageSize);

$indexRootPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(
        SQLiteRecord::encode(['yes', str_repeat('{', $nameLength), 900]),
        $pageSize,
        null,
        4,
    ),
], 12, $pageSize);

$lowerParentCells = [];
foreach (['g', 'i', 'k', 'm', 'o', 'q'] as $index => $prefix) {
    $lowerParentCells[] = SQLiteIndexCell::encode(
        SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 100 + $index]),
        $pageSize,
        null,
        5 + $index,
    );
}
$lowerParentPage = SQLiteIndexInteriorPage::assemble($lowerParentCells, 11, $pageSize);

$leafPages = [];
foreach (['a', 'h', 'j', 'l', 'n', 'p'] as $index => $prefix) {
    $leafPages[] = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 50 + $index])),
    ], $pageSize);
}

$targetLeafEntries = [];
foreach (['r', 's', 't', 'u', 'v', 'w'] as $index => $prefix) {
    $targetLeafEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat($prefix, $nameLength), 200 + $index]));
}

$rightRootLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', str_repeat('~', $nameLength), 901])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . $lowerParentPage
    . implode('', $leafPages)
    . SQLiteIndexLeafPage::assemble($targetLeafEntries, $pageSize)
    . $rightRootLeafPage,
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
    'applicationUse' => 'Plan a generated wp_options insert that splits a full secondary-index leaf, splits its full non-root index parent, and updates the root without the SQLite extension.',
    'plan' => $plan->toArray(),
    'indexRootPage' => [
        'type' => $postDatabase->pageHeader(3)->pageType,
        'cellCount' => $postDatabase->pageHeader(3)->cellCount,
        'rightMostPointer' => $postDatabase->pageHeader(3)->rightMostPointer,
    ],
    'splitParentPages' => [
        4 => [
            'cellCount' => $postDatabase->pageHeader(4)->cellCount,
            'rightMostPointer' => $postDatabase->pageHeader(4)->rightMostPointer,
        ],
        14 => [
            'cellCount' => $postDatabase->pageHeader(14)->cellCount,
            'rightMostPointer' => $postDatabase->pageHeader(14)->rightMostPointer,
        ],
    ],
    'splitLeafPages' => [
        11 => $postDatabase->pageHeader(11)->cellCount,
        13 => $postDatabase->pageHeader(13)->cellCount,
    ],
    'indexRecordCount' => count($indexRecords),
    'insertedOption' => $postDatabase->keyValueRowByIndexedLoadPolicyAndName('yes', $optionName)?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
