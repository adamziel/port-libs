<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
];

$sql = "WITH wanted(option_id, reason) AS (VALUES (1, 'core-url'), (4, 'transient-review'), (9, 'missing')) SELECT wanted.option_id AS option_id, wp_options.option_name AS option_name, wanted.reason AS reason FROM wanted LEFT JOIN wp_options ON wanted.option_id = wp_options.option_id ORDER BY option_id";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows against a SQLite VALUES-backed CTE, matching import/staging ID lists without ext/sqlite.',
    'sql' => $sql,
    'matchedOptions' => array_values(array_filter(array_column($rows, 'option_name'), 'is_string')),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
