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

$sql = "SELECT autoload, count(*) AS rows, sum(bytes) AS byte_sum, avg(bytes) AS avg_bytes, group_concat(bytes) AS byte_list FROM wp_options GROUP BY autoload HAVING count(*) >= 2 ORDER BY byte_sum DESC LIMIT 2";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows from bounded SQLite SELECT text through native GROUP BY/HAVING aggregate dispatch, including count, sum, avg, group_concat, ORDER BY, and LIMIT without requiring ext/sqlite.',
    'sql' => $sql,
    'selectedBuckets' => array_column($rows, 'autoload'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
