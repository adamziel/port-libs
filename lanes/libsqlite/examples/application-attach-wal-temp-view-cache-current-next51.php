<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachWalTempViewCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record('index', 'wp_options_name', 'wp_options', 3, 'CREATE INDEX main.wp_options_name ON wp_options(option_name)', 2),
        $record('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 3),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 4),
        $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id, new.option_name; END", 5),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text, option_value text)', 6),
    ],
);

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x51000001, 0x51010001);
$checksum = SQLiteWal::checksumPair($prefix, false);
$wal = SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true);

$schemaWal = [
    'main' => [
        'wal' => $wal,
        'database_bytes' => $page('main page one') . $page('main page two') . $page('main page three'),
        'database_path' => 'wp-content/database/.ht.sqlite',
        'transactions' => [[
            'pages' => [
                2 => $page('updated active_plugins page'),
                4 => $page('updated option audit page'),
            ],
            'database_page_count' => 4,
            'commit' => true,
        ]],
        'watch_pages' => [2, 4],
        'mode' => 'restart',
    ],
];

$nextMainSchema = [
    $record('table', 'wp_options', 'wp_options', 60, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text, migrated integer)', 60),
    $record('index', 'wp_options_name', 'wp_options', 61, 'CREATE INDEX main.wp_options_name ON wp_options(option_name, autoload)', 61),
    $record('table', 'wp_option_audit', 'wp_option_audit', 62, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 62),
    $record('view', 'autoloaded_options', 'autoloaded_options', 0, "CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options WHERE autoload = 'yes'", 63),
    $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id, new.option_name; END", 64),
];

$plan = SQLiteAttachWalTempViewCachePlan::plan(
    $catalog,
    'main_autoloaded_update',
    $schemaWal,
    ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:11:"plugin.php";}'],
    ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'],
    ['wp_options', 'autoloaded_options'],
    ['wp_options_name'],
    ['main' => $nextMainSchema],
);

echo json_encode([
    'status' => $plan['status'],
    'trigger' => $plan['trigger'],
    'wal_schemas' => $plan['wal_schemas'],
    'changed_tables' => $plan['changed_tables'],
    'changed_indexes' => $plan['changed_indexes'],
    'requires_reprepare' => $plan['requires_reprepare'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
