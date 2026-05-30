<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalViewTriggerPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);
$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x68100001, 0x68200001);
$checksum = SQLiteWal::checksumPair($prefix, false);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 2),
    $record('trigger', 'main_write_then_read', 'wp_options', 0, "CREATE TRIGGER main_write_then_read AFTER UPDATE ON wp_options BEGIN UPDATE wp_options SET option_value = new.option_value WHERE option_id = old.option_id; SELECT new.option_id, new.option_value FROM wp_options WHERE option_id = new.option_id; INSERT INTO wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'after-read', old.option_value, new.option_value); SELECT new.option_id FROM wp_option_audit WHERE option_id = new.option_id; END", 3),
], [
    $record('table', 'wp_option_audit', 'wp_option_audit', 10, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, new_value text)', 4),
    $record('trigger', 'temp_main_bridge_read_after_write', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge_read_after_write AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); SELECT new.option_id, new.option_value FROM wp_option_audit WHERE option_id = new.option_id; INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal', old.option_value, new.option_value); SELECT new.option_id FROM main.wp_option_audit WHERE option_id = new.option_id; END", 5),
]);

$wal = SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true);
$schemaWal = [
    'main' => [
        'wal' => $wal,
        'database_bytes' => $page('main before schema') . $page('main before options') . $page('main before audit'),
        'database_path' => 'wp-content/database/.ht.sqlite',
        'transactions' => [[
            'pages' => [
                2 => $page('main options next image'),
                3 => $page('main audit next image'),
            ],
            'database_page_count' => 3,
            'commit' => true,
        ]],
        'watch_pages' => [2, 3],
        'mode' => 'restart',
    ],
];

$new = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:1:{i:0;s:11:"plugin.php";}', 'autoload' => 'yes'];
$old = ['option_id' => 7, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'];

$main = SQLiteAttachTempWalViewTriggerPlan::plan($catalog, 'main_write_then_read', $schemaWal, $new, $old);
$bridge = SQLiteAttachTempWalViewTriggerPlan::plan($catalog, 'temp_main_bridge_read_after_write', $schemaWal, $new, $old);

echo json_encode([
    'mainReadRoutes' => array_map(
        static fn (array $route): array => [
            'kind' => $route['kind'],
            'schema' => $route['schema'],
            'journal' => $route['journal'],
            'readerBoundary' => $route['reader_boundary'],
            'readAfterWrite' => $route['read_after_write'],
            'priorWriteJournal' => $route['prior_write_journal'],
        ],
        $main['operation_routes'],
    ),
    'tempBridgeRoutes' => array_map(
        static fn (array $route): array => [
            'kind' => $route['kind'],
            'schema' => $route['schema'],
            'journal' => $route['journal'],
            'readerBoundary' => $route['reader_boundary'],
            'readAfterWrite' => $route['read_after_write'],
            'priorWriteJournal' => $route['prior_write_journal'],
        ],
        $bridge['operation_routes'],
    ),
    'boundaries' => $bridge['current_next_boundaries'],
], JSON_PRETTY_PRINT) . PHP_EOL;
