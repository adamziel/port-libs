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

$optionName = $argv[1] ?? 'siteurl';

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'wp_options',
        'wp_options',
        4,
        'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)',
    ])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
        'index',
        'wp_options_option_name',
        'wp_options',
        2,
        'CREATE INDEX wp_options_option_name ON wp_options(option_name)',
    ])),
], 512, 100, $firstPage);
$indexRootPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['home', 2]), 512, null, 3),
], 5);
$leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['blogname', 1])),
]);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'blogname', 'Example Site', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentyfive', 'yes'])),
]);
$rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['siteurl', 3])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['stylesheet', 4])),
]);

$database = SQLiteDatabase::fromBytes($schemaPage . $indexRootPage . $leftIndexLeafPage . $tablePage . $rightIndexLeafPage);
$option = $database->optionRowByIndexedName($optionName);
$indexNames = array_map(
    static fn (SQLiteIndexCell $cell): string => $cell->record()->values[0],
    $database->indexCells(2),
);

echo json_encode([
    'applicationUse' => 'Assemble a multi-page wp_options option_name index in PHP and verify indexed lookup without the SQLite extension.',
    'pageSize' => $database->header->pageSize,
    'databasePages' => $database->pageCount(),
    'indexRootPage' => $database->indexRootPageForColumn('wp_options', 'option_name'),
    'indexRootPageType' => $database->pageHeader(2)->pageType,
    'indexRightMostPage' => $database->pageHeader(2)->rightMostPointer,
    'indexOrder' => $indexNames,
    'lookup' => $option?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
