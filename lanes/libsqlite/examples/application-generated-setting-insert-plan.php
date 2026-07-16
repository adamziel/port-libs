<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 2), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
        'table',
        'app_settings',
        'app_settings',
        2,
        'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)',
    ])),
], $pageSize, 100, $firstPage);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'base_url', 'https://example.test', 'yes'])),
]);

$database = SQLiteDatabase::fromBytes($schemaPage . $tablePage);
$settingValue = $argv[1] ?? str_repeat('generated-cache-fragment:', 56) . 'done';
$plan = $database->planKeyValueRowInsert(2, 'generated_cache_fixture', $settingValue, 'no');

$pages = [
    1 => $database->page(1),
    2 => $database->page(2),
];
for ($pageNumber = 3; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $emptyPage;
}
foreach ($plan->pageImages() as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$settings = array_map(
    static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
    $postDatabase->keyValueRows(),
);

echo json_encode([
    'applicationUse' => 'Plan a bounded generated app_settings row insert as page images, including overflow page allocation, without the SQLite extension.',
    'plan' => $plan->toArray(),
    'updatedPageNumbers' => array_keys($plan->pageImages()),
    'settings' => $settings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
