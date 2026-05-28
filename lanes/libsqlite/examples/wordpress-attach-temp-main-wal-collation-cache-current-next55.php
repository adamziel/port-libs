<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteAttachTempMainWalCollationCachePlan.php';
require __DIR__ . '/../src/SQLiteAttachTempWalViewTriggerPlan.php';
require __DIR__ . '/../src/SQLiteAttachTempViewTriggerYieldPlan.php';
require __DIR__ . '/../src/SQLiteAttachTempViewTriggerResolution.php';
require __DIR__ . '/../src/SQLiteAttachTempViewCollationPlan.php';
require __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require __DIR__ . '/../src/SQLiteSchemaRecord.php';
require __DIR__ . '/../src/SQLiteWalAppendPlan.php';
require __DIR__ . '/../src/SQLiteWal.php';
require __DIR__ . '/../src/SQLiteWalHeader.php';
require __DIR__ . '/../src/SQLiteWalFrame.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempMainWalCollationCachePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$walHeader = static function () use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x12345678, 0x87654321);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$catalog = new SQLiteAttachedSchemaCatalog(
    [
        $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text COLLATE NOCASE, option_value text COLLATE RTRIM)', 1),
        $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, old_value text, new_value text COLLATE NOCASE)', 2),
        $record('view', 'autoloaded_options', 'autoloaded_options', 0, 'CREATE VIEW main.autoloaded_options AS SELECT option_id, option_name, option_value FROM wp_options', 3),
        $record('trigger', 'main_autoloaded_update', 'autoloaded_options', 0, "CREATE TRIGGER main_autoloaded_update INSTEAD OF UPDATE ON autoloaded_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; INSERT INTO wp_option_audit(option_id, old_value, new_value) VALUES(new.option_id, old.option_value, new.option_value); SELECT new.option_name COLLATE NOCASE; END", 4),
    ],
    [
        $record('table', 'wp_options', 'wp_options', 10, 'CREATE TEMP TABLE wp_options(option_id integer, temp_name text COLLATE WP_LOCALE, option_value text)', 5),
        $record('table', 'wp_option_audit', 'wp_option_audit', 11, 'CREATE TEMP TABLE wp_option_audit(option_id integer, new_value text COLLATE WP_LOCALE)', 6),
        $record('trigger', 'temp_main_bridge', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, new_value) VALUES(new.option_id, new.option_value); INSERT INTO main.wp_option_audit(option_id, old_value, new_value) VALUES(new.option_id, old.option_value, new.option_value); END", 7),
    ],
);

$wal = SQLiteWal::parse($walHeader(), null, true);
$schemaWal = [
    'main' => [
        'wal' => $wal,
        'database_bytes' => $page('main page one') . $page('main page two') . $page('main page three'),
        'database_path' => 'wp-content/database/.ht.sqlite',
        'transactions' => [[
            'pages' => [
                2 => $page('active_plugins next'),
                3 => $page('audit next'),
            ],
            'database_page_count' => 3,
            'commit' => true,
        ]],
        'watch_pages' => [2, 3],
        'mode' => 'restart',
    ],
];

$result = SQLiteAttachTempMainWalCollationCachePlan::plan(
    $catalog,
    ['main_autoloaded_update', 'temp_main_bridge'],
    $schemaWal,
    [
        'temp' => ['schema_cookie' => 3, 'registered_collations' => ['BINARY', 'NOCASE', 'RTRIM', 'WP_LOCALE']],
        'main' => ['schema_cookie' => 20, 'wal_schema_cookie' => 21, 'registered_collations' => ['BINARY', 'NOCASE', 'RTRIM', 'WP_LOCALE']],
    ],
    [
        'main_autoloaded_update' => ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{}'],
        'temp_main_bridge' => ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{}'],
    ],
    [
        'main_autoloaded_update' => ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'],
        'temp_main_bridge' => ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}'],
    ],
);

if ($result['expired_triggers'] !== ['main_autoloaded_update', 'temp_main_bridge']) {
    throw new RuntimeException('Expected main WAL schema-cookie change to expire copied wp_options triggers');
}

echo json_encode([
    'status' => $result['status'],
    'changed_schemas' => $result['changed_schemas'],
    'expired_triggers' => $result['expired_triggers'],
    'route_counts' => $result['route_counts'],
    'main_required_collations' => $result['trigger_plans']['main_autoloaded_update']['required_collations'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
