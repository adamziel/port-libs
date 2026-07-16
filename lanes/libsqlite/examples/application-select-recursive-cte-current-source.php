<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no'],
];

$sql = "WITH RECURSIVE wanted(id) AS (VALUES (1) UNION ALL SELECT id + 1 FROM wanted WHERE id < 4) SELECT option_id, option_name FROM wp_options WHERE option_id IN (SELECT id FROM wanted) ORDER BY option_id";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows filtered by parser-level SQLite WITH RECURSIVE current-frontier CTE execution without requiring ext/sqlite.',
    'sql' => $sql,
    'optionNames' => array_column($rows, 'option_name'),
    'rowCount' => count($rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
