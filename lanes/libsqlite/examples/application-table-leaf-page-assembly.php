<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$baseUrl = $argv[1] ?? 'https://example.test';

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 2), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$schemaCell = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
    'table',
    'app_settings',
    'app_settings',
    2,
    'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)',
]));
$settingCell = SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([
    null,
    'base_url',
    $baseUrl,
    'yes',
]));

$page1 = SQLiteTableLeafPage::assemble([$schemaCell], 512, 100, $firstPage);
$page2 = SQLiteTableLeafPage::assemble([$settingCell]);
$database = SQLiteDatabase::fromBytes($page1 . $page2);
$settings = array_map(
    static fn (SQLiteKeyValueRow $setting): array => $setting->toArray(),
    $database->keyValueRows(),
);

echo json_encode([
    'applicationUse' => 'Assemble a minimal app_settings table leaf page in PHP for fixture generation or repair preflight without the SQLite extension.',
    'pageSize' => $database->header->pageSize,
    'databasePages' => $database->pageCount(),
    'appSettingsRootPage' => $database->tableRootPage('app_settings'),
    'appSettingsPageCellPointers' => $database->pageHeader(2)->cellPointers($database->page(2)),
    'schemaCellHexPrefix' => bin2hex(substr($schemaCell, 0, 12)),
    'settingCellHexPrefix' => bin2hex(substr($settingCell, 0, 12)),
    'settings' => $settings,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
