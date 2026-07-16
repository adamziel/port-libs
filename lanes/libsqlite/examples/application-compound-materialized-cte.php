<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
];

$sql = "WITH picked(name) AS MATERIALIZED (
    SELECT option_name AS name FROM wp_options WHERE autoload = 'yes'
    UNION ALL
    SELECT option_name AS name FROM wp_options WHERE option_name GLOB '_*'
) SELECT name FROM picked WHERE name GLOB '_*'
UNION
SELECT name FROM picked WHERE name = 'siteurl'
ORDER BY name";

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows flowing through SQLite MATERIALIZED CTE syntax and compound SELECT arms without requiring ext/sqlite.',
    'sql' => $sql,
    'selectedNames' => array_column($rows, 'name'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
