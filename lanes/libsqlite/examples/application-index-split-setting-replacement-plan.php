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
$settingName = $argv[1] ?? str_repeat('z', 70);
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
$firstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
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
        'app_settings_load_policy_key_name',
        'app_settings',
        3,
        'CREATE INDEX app_settings_load_policy_key_name ON app_settings(load_policy, key_name)',
    ])),
], $pageSize, 100, $firstPage);

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'cache_lock', '1', 'no'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'landing_url', 'https://example.test/dashboard', 'yes'])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, $settingName, 'stale-cache', 'yes'])),
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'style_profile', 'twentytwentyfive', 'yes'])),
], $pageSize);

$indexRootPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'landing_url', 2]), $pageSize, null, 4),
], 5, $pageSize);

$leftIndexEntries = [];
foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $index => $prefix) {
    $leftIndexEntries[] = SQLiteIndexCell::encode(SQLiteRecord::encode(['no', str_repeat($prefix, 70), 10 + $index]));
}

$leftIndexLeafPage = SQLiteIndexLeafPage::assemble($leftIndexEntries, $pageSize);
$rightIndexLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'style_profile', 4])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', $settingName, 3])),
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $schemaPage
    . $tablePage
    . $indexRootPage
    . $leftIndexLeafPage
    . $rightIndexLeafPage,
);

$plan = $database->planKeyValueRowReplace($settingName, $replacementValue, 'no');

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
    'applicationUse' => 'Plan an app_settings replacement that changes load_policy and splits a full same-depth composite secondary-index leaf, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'indexRootPageType' => $postDatabase->pageHeader(3)->pageType,
    'indexRootCellCount' => $postDatabase->pageHeader(3)->cellCount,
    'updatedIndexLeafPages' => [
        4 => $postDatabase->pageHeader(4)->cellCount,
        5 => $postDatabase->pageHeader(5)->cellCount,
        6 => $postDatabase->pageHeader(6)->cellCount,
    ],
    'indexRecords' => $indexRecords,
    'replacedSetting' => $postDatabase->keyValueRowByIndexedLoadPolicyAndName('no', $settingName)?->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
