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
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'cron_lock', '1', 'no'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'stylesheet', 'twentytwentyfive', 'yes'])),
], $pageSize);

$indexRootPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'home', 2]), $pageSize, null, 4),
], 5, $pageSize);
$leftIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 1])),
], $pageSize);
$rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'siteurl', 3])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'stylesheet', 4])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . $leftIndexLeafPage
    . $rightIndexLeafPage,
);

$replacementValue = $argv[1] ?? 'https://fixed.example';
$plan = $database->planKeyValueRowReplace('siteurl', $replacementValue, 'no');

$pages = [
    1 => $database->page(1),
    2 => $database->page(2),
    3 => $database->page(3),
    4 => $database->page(4),
    5 => $database->page(5),
];
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$indexRecords = array_map(
    static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
    $postDatabase->indexCells(3),
);

echo json_encode([
    'applicationUse' => 'Plan a bounded wp_options replacement that moves siteurl from autoload=yes to autoload=no while maintaining a multi-page autoload, option_name index without the SQLite extension.',
    'plan' => $plan->toArray(),
    'updatedPageNumbers' => array_keys($plan->pageImages()),
    'indexRootPageType' => $postDatabase->pageHeader(3)->pageType,
    'compositeIndexRecords' => $indexRecords,
    'indexedSiteurlOption' => $postDatabase->keyValueRowByIndexedLoadPolicyAndName('no', 'siteurl')?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
