<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 20, 'tie' => 'b', 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'siteurl', 'priority' => 30, 'tie' => 'a', 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'yes', 'option_name' => 'theme_mods', 'priority' => 10, 'tie' => 'z', 'enabled' => 1],
        ['option_id' => 4, 'autoload' => 'yes', 'option_name' => 'blogname', 'priority' => 40, 'tie' => 'c', 'enabled' => 0],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 50, 'tie' => 'a', 'enabled' => 1],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'priority' => 50, 'tie' => 'b', 'enabled' => 1],
        ['option_id' => 7, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'priority' => 45, 'tie' => 'z', 'enabled' => 1],
    ],
];

$sql = "SELECT option_id, json_group_array(DISTINCT option_name ORDER BY priority DESC, tie ASC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS option_window FROM wp_options ORDER BY option_id";
$rows = SQLiteSelectSql::execute($sql, $tables);

$result = [
    'scenario' => 'application-json-aggregate-distinct-order-window-current-source-next90',
    'applicationUse' => 'Copied wp_options diagnostics can compute parser-level JSON aggregate windows with DISTINCT, FILTER, and multi-term aggregate ORDER BY independently from the window frame order before Application imports commit option summaries.',
    'sqlShape' => $sql,
    'windows' => array_map(
        static fn (array $row): array => [
            'option_id' => $row['option_id'],
            'option_window' => $row['option_window'],
        ],
        $rows,
    ),
    'dependency' => 'native PHP SELECT/JSON aggregate window execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    if (($result['windows'][0]['option_window'] ?? null) !== '["siteurl","theme_mods"]') {
        fwrite(STDERR, "unexpected first JSON aggregate window\n");
        exit(1);
    }
    if (($result['windows'][4]['option_window'] ?? null) !== '["plugin_rules","plugin_queue"]') {
        fwrite(STDERR, "unexpected plugin JSON aggregate window\n");
        exit(1);
    }

    echo "application-json-aggregate-distinct-order-window-current-source-next90 self-test passed\n";
}

return $result;
