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
    ['option_id' => 6, 'option_name' => 'orphaned', 'option_value' => null, 'autoload' => null, 'bytes' => 0],
];

$meta = [
    ['option_id' => 1, 'meta_key' => 'public', 'priority' => 10],
    ['option_id' => 2, 'meta_key' => 'public', 'priority' => 20],
    ['option_id' => 3, 'meta_key' => 'private', 'priority' => 30],
    ['option_id' => 5, 'meta_key' => 'plugin', 'priority' => 40],
];

$sql = "SELECT option_id, option_name AS name FROM wp_options ORDER BY option_id LIMIT (SELECT 3 FROM wp_options WHERE autoload = 'yes')";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

$offsetSql = "SELECT option_id, option_name AS name FROM wp_options ORDER BY option_id LIMIT 2 OFFSET (SELECT 2 FROM option_meta WHERE meta_key = 'public')";
$offsetRows = SQLiteSelectSql::execute($offsetSql, ['wp_options' => $options, 'option_meta' => $meta]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options SELECT SQL where LIMIT/OFFSET scalar subqueries resolve against the current SELECT source tables, preserving SQLite import previews without ext/sqlite.',
    'sql' => $sql,
    'selectedOptionIds' => array_column($rows, 'option_id'),
    'rows' => $rows,
    'offsetSql' => $offsetSql,
    'offsetOptionIds' => array_column($offsetRows, 'option_id'),
    'offsetRows' => $offsetRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
