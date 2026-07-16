<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 48],
    ['option_id' => 4, 'option_name' => 'theme_mods_twentytwentyfour', 'autoload' => 'yes', 'bytes' => 72],
];

$sql = 'SELECT option_name AS name, bytes + option_id AS weight FROM wp_options ORDER BY 2 DESC, 1 LIMIT 3';
$result = SQLiteSelectSql::execute($sql, ['wp_options' => $rows]);

echo json_encode([
    'scenario' => 'application-select-sql-order-ordinal',
    'sql' => $sql,
    'orderedOptionNames' => array_column($result, 'name'),
], JSON_PRETTY_PRINT) . PHP_EOL;
