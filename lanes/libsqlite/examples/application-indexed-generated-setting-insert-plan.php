<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

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
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'app_settings',
        'app_settings',
        2,
        'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)',
    ])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([
        'index',
        'app_settings_key_name',
        'app_settings',
        3,
        'CREATE INDEX app_settings_key_name ON app_settings(key_name)',
    ])),
], $pageSize, 100, $firstPage);

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'base_url', 'https://example.test', 'yes'])),
], $pageSize);
$indexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['base_url', 1])),
], $pageSize);

$database = SQLiteDatabase::fromBytes($schemaPage . $tablePage . $indexPage);
$settingValue = $argv[1] ?? 'https://example.test/landing';
$plan = $database->planKeyValueRowInsert(2, 'landing_url', $settingValue, 'yes');

$pages = [
    1 => $database->page(1),
    2 => $database->page(2),
    3 => $database->page(3),
];
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$settings = array_map(
    static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
    $postDatabase->keyValueRows(),
);
$indexRecords = array_map(
    static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
    $postDatabase->indexCells(3),
);

echo json_encode([
    'applicationUse' => 'Plan a bounded generated app_settings row insert while maintaining a single-leaf key_name index, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'updatedPageNumbers' => array_keys($plan->pageImages()),
    'indexRecords' => $indexRecords,
    'indexedLandingSetting' => $postDatabase->keyValueRowByIndexedName('landing_url')?->toArray(),
    'settings' => $settings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
