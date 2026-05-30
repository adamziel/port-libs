<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'kind' => 'core', 'bytes' => 20],
        ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => 'Port Fixture', 'autoload' => 'yes', 'kind' => 'core', 'bytes' => 12],
        ['option_id' => 3, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'kind' => 'plugin', 'bytes' => 30],
        ['option_id' => 4, 'option_name' => 'plugin_rules', 'option_value' => new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'autoload' => 'no', 'kind' => 'plugin', 'bytes' => 30],
        ['option_id' => 5, 'option_name' => 'plugin_queue', 'option_value' => '{"pending":2}', 'autoload' => 'no', 'kind' => 'plugin', 'bytes' => 25],
        ['option_id' => 6, 'option_name' => 'empty_option', 'option_value' => null, 'autoload' => 'no', 'kind' => 'empty', 'bytes' => 0],
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT autoload FROM wp_options GROUP BY autoload HAVING json_group_array(DISTINCT option_name ORDER BY option_name) LIKE '%plugin_queue%' ORDER BY json_group_array(option_name ORDER BY option_id) DESC",
    $tables,
);

$report = [
    'scenario' => 'application-json-aggregate-grouped-current-next82',
    'matchedAutoloadGroups' => array_map(static fn (array $row): mixed => $row['autoload'], $rows),
    'applicationUse' => 'Copied wp_options import diagnostics can filter and order grouped rows by JSON aggregate summaries without projecting those summaries or requiring ext/sqlite.',
];

if ($report['matchedAutoloadGroups'] !== ['no']) {
    fwrite(STDERR, 'Unexpected grouped JSON aggregate current-source result: ' . json_encode($report['matchedAutoloadGroups']) . PHP_EOL);
    exit(1);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
