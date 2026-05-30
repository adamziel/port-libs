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
$walHeader = static function () use ($pageSize): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x22334455, 0x66778899);
    $checksum = SQLiteWal::checksumPair($prefix, false);

    return $prefix . pack('N*', $checksum[0], $checksum[1]);
};

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE main.wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE main.wp_option_audit(option_id integer, label text, old_value text, new_value text)', 2),
], [
    $record('table', 'wp_option_audit', 'wp_option_audit', 10, 'CREATE TEMP TABLE wp_option_audit(option_id integer, label text, new_value text)', 3),
    $record('trigger', 'temp_main_bridge', 'wp_options', 0, "CREATE TEMP TRIGGER temp_main_bridge AFTER UPDATE ON main.wp_options BEGIN INSERT INTO wp_option_audit(option_id, label, new_value) VALUES(new.option_id, 'temp-shadow', new.option_value); INSERT INTO main.wp_option_audit(option_id, label, old_value, new_value) VALUES(new.option_id, 'main-wal-bridge', old.option_value, new.option_value); SELECT old.option_name, new.option_value; END", 4),
]);

$plan = SQLiteAttachTempWalViewTriggerPlan::plan($catalog, 'temp_main_bridge', [
    'main' => [
        'wal' => SQLiteWal::parse($walHeader(), null, true),
        'database_bytes' => $page('main before schema') . $page('main before data') . $page('main audit before'),
        'database_path' => 'wp-content/database/.ht.sqlite',
        'transactions' => [[
            'pages' => [
                2 => $page('main active_plugins wal next image'),
                3 => $page('main audit wal next image'),
            ],
            'database_page_count' => 3,
            'commit' => true,
        ]],
        'watch_pages' => [2, 3],
    ],
], [
    'option_id' => 7,
    'option_name' => 'active_plugins',
    'option_value' => 'a:1:{i:0;s:11:"plugin.php";}',
], [
    'option_id' => 7,
    'option_name' => 'active_plugins',
    'option_value' => 'a:0:{}',
]);

echo json_encode([
    'status' => $plan['status'],
    'operationRoutes' => $plan['operation_routes'],
    'currentNextBoundaries' => $plan['current_next_boundaries'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
