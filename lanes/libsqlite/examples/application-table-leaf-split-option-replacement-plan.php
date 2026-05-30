<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$optionName = $argv[1] ?? 'blogname';
$replacementValue = $argv[2] ?? str_repeat('expanded-cache-', 28);

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 4), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        2,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
], $pageSize, 100, $firstPage);

$tableRootPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(3, 3),
], 4, $pageSize);
$leftTableLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'blogname', 'Stale Site', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_migration_lock', 'old-lock', 'no'])),
], $pageSize);
$rightTableLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'template', 'twentytwentyfive', 'yes'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tableRootPage
    . $leftTableLeafPage
    . $rightTableLeafPage,
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
$options = array_map(
    static fn (SQLiteOptionRow $option): array => $option->toArray(),
    $postDatabase->optionRows(),
);
$parentCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(2), $postDatabase->pageHeader(2));

echo json_encode([
    'applicationUse' => 'Plan a wp_options replacement that grows large enough to split a table leaf below an interior table root, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'tableRootPageType' => $postDatabase->pageHeader(2)->pageType,
    'tableRootSeparators' => array_map(
        static fn (SQLiteTableInteriorCell $cell): array => [
            'leftChildPage' => $cell->leftChildPage,
            'maxRowid' => $cell->key,
        ],
        $parentCells,
    ),
    'rightMostPage' => $postDatabase->pageHeader(2)->rightMostPointer,
    'splitLeafPages' => [
        3 => $postDatabase->pageHeader(3)->cellCount,
        5 => $postDatabase->pageHeader(5)->cellCount,
    ],
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
