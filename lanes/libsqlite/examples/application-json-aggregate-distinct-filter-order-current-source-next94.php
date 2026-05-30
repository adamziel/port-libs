<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 20, 'tie' => 'b', 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'siteurl', 'priority' => 30, 'tie' => 'a', 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 30, 'tie' => 'z', 'enabled' => 1],
        ['option_id' => 4, 'autoload' => 'yes', 'option_name' => 'blogname', 'priority' => 40, 'tie' => 'c', 'enabled' => 0],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 50, 'tie' => 'b', 'enabled' => 1],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'priority' => 50, 'tie' => 'a', 'enabled' => 1],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 45, 'tie' => 'z', 'enabled' => 1],
        ['option_id' => 8, 'autoload' => 'no', 'option_name' => 'empty_option', 'priority' => null, 'tie' => 'n', 'enabled' => 1],
    ],
];

$sql = 'SELECT autoload, json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) AS names FROM wp_options GROUP BY autoload ORDER BY autoload';
$rows = SQLiteSelectSql::execute($sql, $tables);

$report = [
    'scenario' => 'application-json-aggregate-distinct-filter-order-current-source-next94',
    'applicationUse' => 'Copied wp_options diagnostics can compute parser-level JSON aggregate summaries with DISTINCT, FILTER, and multi-term aggregate-local ORDER BY before Application import or repair tooling commits option changes.',
    'sqlShape' => $sql,
    'groups' => $rows,
    'dependency' => 'native PHP SELECT/JSON aggregate execution; no ext/sqlite required',
];

$expected = [
    ['autoload' => 'no', 'names' => '["plugin_queue","plugin_rules","empty_option"]'],
    ['autoload' => 'yes', 'names' => '["siteurl","theme_mods"]'],
];

if ($rows !== $expected) {
    fwrite(STDERR, 'Unexpected JSON aggregate DISTINCT FILTER ORDER current-source result: ' . json_encode($rows) . PHP_EOL);
    exit(1);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
