<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'expected_autoload' => 'yes', 'bytes' => 24, 'expected_bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'expected_autoload' => 'no', 'bytes' => 24, 'expected_bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'expected_autoload' => 'yes', 'bytes' => 9, 'expected_bytes' => 12],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'expected_autoload' => 'no', 'bytes' => 12, 'expected_bytes' => null],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'expected_autoload' => null, 'bytes' => 110, 'expected_bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'expected_autoload' => null, 'bytes' => null, 'expected_bytes' => null],
];

$driftSql = 'SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS DISTINCT FROM expected_autoload ORDER BY id';
$stableSql = 'SELECT option_id AS id, option_name FROM wp_options WHERE autoload IS NOT DISTINCT FROM expected_autoload ORDER BY id';
$byteDriftSql = 'SELECT option_name, bytes FROM wp_options WHERE bytes IS DISTINCT FROM expected_bytes ORDER BY bytes DESC, option_name';

echo json_encode([
    'applicationUse' => 'Preview copied wp_options drift checks using SQLite IS DISTINCT FROM / IS NOT DISTINCT FROM NULL-safe predicates without requiring ext/sqlite.',
    'driftSql' => $driftSql,
    'driftedAutoloadOptions' => array_column(SQLiteSelectSql::execute($driftSql, ['wp_options' => $options]), 'option_name'),
    'stableSql' => $stableSql,
    'stableAutoloadOptions' => array_column(SQLiteSelectSql::execute($stableSql, ['wp_options' => $options]), 'option_name'),
    'byteDriftSql' => $byteDriftSql,
    'byteDriftRows' => SQLiteSelectSql::execute($byteDriftSql, ['wp_options' => $options]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
