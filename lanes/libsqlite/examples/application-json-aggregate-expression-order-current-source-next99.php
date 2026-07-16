<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 10, 'bonus' => 0, 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'siteurl', 'priority' => 20, 'bonus' => 10, 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 30, 'bonus' => 0, 'enabled' => 1],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 40, 'bonus' => 5, 'enabled' => 1],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'priority' => 35, 'bonus' => 20, 'enabled' => 1],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'orphaned_transient', 'priority' => 100, 'bonus' => 0, 'enabled' => 0],
    ],
];

$sql = 'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled) AS option_names FROM wp_options GROUP BY autoload ORDER BY autoload';
$rows = SQLiteSelectSql::execute($sql, $tables);

$payload = [
    'applicationUse' => 'Copied wp_options diagnostics can compute JSON aggregate summaries where aggregate-local ORDER BY uses an expression, so import ranking formulas do not have to be materialized as temporary columns before DISTINCT/FILTER processing.',
    'sqlShape' => 'json_group_array(DISTINCT option_name ORDER BY priority + bonus DESC) FILTER (WHERE enabled)',
    'rows' => $rows,
];

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
