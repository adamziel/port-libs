<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteJsonSchemaWalPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = 'data/app.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header and app_settings root')
    . $page('current enabled_modules')
    . $page('current layout_palette_settings')
    . $page('current load_policy index');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 43, 0x43434343, 0x56565656);
$checksum = SQLiteWal::checksumPair($prefix, false);
$wal = SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true);

$schemaSql = <<<'SQL'
CREATE TABLE app_settings (
  setting_id INTEGER PRIMARY KEY AUTOINCREMENT,
  key_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  key_value TEXT NOT NULL DEFAULT '',
  load_policy TEXT NOT NULL DEFAULT 'yes',
  CHECK (json_valid(key_value) OR key_name NOT IN ('module_json_settings','layout_palette_settings'))
);
CREATE UNIQUE INDEX app_settings_key_name ON app_settings(key_name COLLATE NOCASE);
SQL;

$plan = SQLiteJsonSchemaWalPlan::currentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $schemaSql,
    [
        ['setting_id' => 1, 'key_name' => 'enabled_modules', 'key_value' => '["forms","exporter"]', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'key_name' => 'layout_palette_settings', 'key_value' => '{"nav_items":[]}', 'load_policy' => 'yes'],
    ],
    [
        ['key_name' => 'module_json_settings', 'key_value' => '{"enabled":true}', 'load_policy' => 'no'],
        ['key_name' => 'broken_module_json', 'key_value' => '{"enabled":', 'load_policy' => 'no'],
    ],
    [2, 3, 4, 5],
    ['module_json_settings', 'layout_palette_settings', 'broken_module_json'],
    ['schema_version' => 43, 'data_version' => 7, 'next_rootpage' => 9],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'planned');
    assert($plan['accepted_import_count'] === 1);
    assert($plan['rejected_import_count'] === 1);
    assert($plan['inserted_key_names'] === ['module_json_settings']);
}

echo json_encode([
    'status' => $plan['status'],
    'schema_version_after' => $plan['schema_version_after'],
    'accepted_import_count' => $plan['accepted_import_count'],
    'rejected_rows' => $plan['rejected_rows'],
    'inserted_key_names' => $plan['inserted_key_names'],
    'wal_last_commit_frame' => $plan['wal_last_commit_frame'],
], JSON_PRETTY_PRINT) . "\n";
