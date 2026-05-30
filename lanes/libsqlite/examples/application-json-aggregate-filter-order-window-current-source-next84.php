<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_size' => 20],
        ['option_id' => 2, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_size' => 12],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'option_size' => 30],
        ['option_id' => 4, 'option_name' => 'plugin_queue', 'autoload' => 'no', 'option_size' => 25],
        ['option_id' => 5, 'option_name' => 'empty_option', 'autoload' => 'no', 'option_size' => 0],
    ],
];

$sql = "SELECT option_id, option_name, json_group_array(option_name ORDER BY option_name) FILTER (WHERE option_size > 0) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_option_names FROM wp_options ORDER BY option_id";
$jsonbSql = "SELECT option_id, jsonb_group_array(option_name ORDER BY option_name) FILTER (WHERE autoload = 'no') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS next_option_names FROM wp_options ORDER BY option_id";

$rows = SQLiteSelectSql::execute($sql, $tables);
$jsonbRows = SQLiteSelectSql::execute($jsonbSql, $tables);

$summary = [
    'scenario' => 'application-json-aggregate-filter-order-window-current-source-next84',
    'sqlShape' => 'json_group_array(value ORDER BY aggregate_key) FILTER (WHERE predicate) OVER (... ROWS BETWEEN CURRENT ROW AND N FOLLOWING)',
    'rows' => $rows,
    'jsonbFirstNoAutoloadFrame' => SQLiteJsonB::decode($jsonbRows[2]['next_option_names']->bytes),
    'applicationUse' => 'Copied wp_options import diagnostics can preview current-row JSON aggregate windows with aggregate-local ORDER BY and FILTER clauses before requiring ext/sqlite.',
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
