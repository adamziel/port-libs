<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteJsonSchemaWalPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$databasePath = 'wp-content/database/.ht.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$databaseBytes = $page('sqlite header and wp_options root')
    . $page('current active_plugins')
    . $page('current theme_mods')
    . $page('current autoload index');

$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 43, 0x43434343, 0x56565656);
$checksum = SQLiteWal::checksumPair($prefix, false);
$wal = SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true);

$schemaSql = <<<'SQL'
CREATE TABLE wp_options (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  option_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  CHECK (json_valid(option_value) OR option_name NOT IN ('plugin_json_settings','theme_mods_twentytwentyfour'))
);
CREATE UNIQUE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE);
SQL;

$plan = SQLiteJsonSchemaWalPlan::currentNext(
    $wal,
    $databaseBytes,
    $databasePath,
    $schemaSql,
    [
        ['option_id' => 1, 'option_name' => 'active_plugins', 'option_value' => '[]', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'theme_mods_twentytwentyfour', 'option_value' => '{"nav_menu_locations":[]}', 'autoload' => 'yes'],
    ],
    [
        ['option_name' => 'plugin_json_settings', 'option_value' => '{"enabled":true}', 'autoload' => 'no'],
        ['option_name' => 'broken_plugin_json', 'option_value' => '{"enabled":', 'autoload' => 'no'],
    ],
    [2, 3, 4, 5],
    ['plugin_json_settings', 'theme_mods_twentytwentyfour', 'broken_plugin_json'],
    ['schema_version' => 43, 'data_version' => 7, 'next_rootpage' => 9],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'planned');
    assert($plan['accepted_import_count'] === 1);
    assert($plan['rejected_import_count'] === 1);
    assert($plan['inserted_names'] === ['plugin_json_settings']);
}

echo json_encode([
    'status' => $plan['status'],
    'schema_version_after' => $plan['schema_version_after'],
    'accepted_import_count' => $plan['accepted_import_count'],
    'rejected_rows' => $plan['rejected_rows'],
    'inserted_names' => $plan['inserted_names'],
    'wal_last_commit_frame' => $plan['wal_last_commit_frame'],
], JSON_PRETTY_PRINT) . "\n";
