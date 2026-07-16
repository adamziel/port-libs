<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
        ['option_id' => 2, 'option_name' => 'blogname', 'autoload' => 'yes', 'priority' => 20],
        ['option_id' => 3, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 10],
        ['option_id' => 4, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'priority' => 80],
        ['option_id' => 5, 'option_name' => 'plugin_queue', 'autoload' => 'no', 'priority' => 70],
        ['option_id' => 6, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'priority' => 60],
        ['option_id' => 7, 'option_name' => 'empty_option', 'autoload' => 'no', 'priority' => 0],
    ],
];

$rows = SQLiteSelectSql::execute(
    'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority ASC) AS asc_names, json_group_array(DISTINCT option_name ORDER BY priority DESC) AS desc_names FROM wp_options GROUP BY autoload ORDER BY json_group_array(DISTINCT option_name ORDER BY priority DESC)',
    $tables,
);

$report = [
    'scenario' => 'application-json-aggregate-order-distinct-current-source-next86',
    'groups' => $rows,
    'applicationUse' => 'Copied wp_options diagnostics can sort JSON aggregate inputs DESC before DISTINCT admission, so newer or higher-priority duplicate option rows determine the current-source summary without ext/sqlite.',
];

$expected = [
    ['autoload' => 'no', 'asc_names' => '["empty_option","plugin_rules","plugin_queue"]', 'desc_names' => '["plugin_rules","plugin_queue","empty_option"]'],
    ['autoload' => 'yes', 'asc_names' => '["siteurl","blogname"]', 'desc_names' => '["siteurl","blogname"]'],
];

if ($rows !== $expected) {
    fwrite(STDERR, 'Unexpected JSON aggregate ORDER DISTINCT current-source result: ' . json_encode($rows) . PHP_EOL);
    exit(1);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
