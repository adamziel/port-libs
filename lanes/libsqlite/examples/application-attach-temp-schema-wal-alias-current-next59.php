<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x59000001, 0x59000002);
$checksum = SQLiteWal::checksumPair($prefix, false);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 1),
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text)', 2),
    $record('trigger', 'wp_temp_schema_alias_flush', 'wp_options', 0, "CREATE TRIGGER wp_temp_schema_alias_flush AFTER UPDATE ON wp_options BEGIN INSERT INTO sqlite_temp_schema(type, name, tbl_name, rootpage, sql) VALUES('table', 'wp_import_shadow', 'wp_import_shadow', 11, new.option_value); INSERT INTO main.sqlite_master(type, name, tbl_name, rootpage, sql) VALUES('index', 'main_option_autoload', 'wp_options', 5, new.option_value); END", 3),
], [
    $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TEMP TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 4),
]);

$plan = SQLiteAttachTempWalSchemaTriggerPlan::plan(
    $catalog,
    'wp_temp_schema_alias_flush',
    [
        'main' => [
            'wal' => SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true),
            'database_bytes' => $page('main before schema') . $page('main before options'),
            'database_path' => 'wp-content/database/.ht.sqlite',
            'transactions' => [[
                'pages' => [1 => $page('main schema next'), 2 => $page('main options next')],
                'database_page_count' => 2,
                'commit' => true,
            ]],
            'watch_pages' => [1, 2],
        ],
    ],
    [
        'main' => ['schema_cookie' => 40, 'tables' => ['sqlite_schema', 'wp_options'], 'file' => '/srv/wp/current.sqlite'],
        'temp' => ['schema_cookie' => 8, 'tables' => ['sqlite_schema'], 'file' => ''],
    ],
    ['wp_options', 'main.wp_options'],
    ['option_id' => 42, 'option_name' => 'active_plugins', 'option_value' => 'CREATE INDEX option_autoload ON wp_options(autoload)'],
    ['option_id' => 42, 'option_name' => 'active_plugins', 'option_value' => 'old'],
);

echo json_encode([
    'status' => $plan['status'],
    'schema_write_schemas' => $plan['schema_write_schemas'],
    'schema_cookies_next' => $plan['schema_cache']['schema_cookies_next'],
    'reprepare_schemas' => $plan['reprepare_schemas'],
    'wal_schemas' => $plan['wal_schemas'],
    'temp_schemas' => $plan['temp_schemas'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
