<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'orphaned', 'option_value' => null, 'autoload' => null, 'bytes' => 3],
];

$sql = "SELECT option_id, option_name AS name, autoload FROM wp_options ORDER BY coalesce(autoload, 'zz') ASC, length(option_name) DESC, lower(option_name) ASC LIMIT 5";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

$groupedSql = "SELECT autoload, count(*) AS rows, sum(bytes) AS byte_sum FROM wp_options GROUP BY autoload ORDER BY sum(bytes) DESC, count(*) ASC LIMIT 2";
$groupedRows = SQLiteSelectSql::execute($groupedSql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows from bounded SQLite SELECT text whose ORDER BY terms are scalar or aggregate expressions, while hidden sort columns stay out of the returned Application import rows and no ext/sqlite dependency is required.',
    'sql' => $sql,
    'selectedOptionIds' => array_column($rows, 'option_id'),
    'returnedColumns' => $rows === [] ? [] : array_keys($rows[0]),
    'rows' => $rows,
    'groupedSql' => $groupedSql,
    'groupedOrder' => array_column($groupedRows, 'autoload'),
    'groupedRows' => $groupedRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
