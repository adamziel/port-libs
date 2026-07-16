<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => 10, 'rank' => 20, 'tie' => 'b', 'enabled' => 1, 'option_name' => 'siteurl'],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 20, 'rank' => 40, 'tie' => 'a', 'enabled' => 1, 'option_name' => 'theme_mods'],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 20, 'rank' => 30, 'tie' => 'z', 'enabled' => 1, 'option_name' => 'siteurl'],
        ['option_id' => 4, 'autoload' => 'yes', 'score' => 30, 'rank' => 10, 'tie' => 'c', 'enabled' => 0, 'option_name' => 'blogname'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => 5, 'rank' => 50, 'tie' => 'a', 'enabled' => 1, 'option_name' => 'plugin_rules'],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 5, 'rank' => 45, 'tie' => 'b', 'enabled' => 1, 'option_name' => 'plugin_queue'],
        ['option_id' => 7, 'autoload' => 'no', 'score' => 15, 'rank' => 35, 'tie' => 'z', 'enabled' => 1, 'option_name' => 'plugin_rules'],
    ],
];

$sql = 'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY rank DESC, tie ASC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score) AS option_window FROM wp_options ORDER BY option_id';
$rows = SQLiteSelectSql::execute($sql, $tables);

$result = [
    'scenario' => 'application-json-aggregate-default-window-current-source-next100',
    'applicationUse' => 'Copied wp_options diagnostics can use SQLite default JSON aggregate window frames without spelling ROWS/RANGE clauses, preserving DISTINCT, FILTER, and aggregate-local ORDER BY for import summaries.',
    'sqlShape' => $sql,
    'windows' => array_map(
        static fn (array $row): array => [
            'option_id' => $row['option_id'],
            'option_window' => $row['option_window'],
        ],
        $rows,
    ),
    'dependency' => 'native PHP SELECT SQL and JSON aggregate window execution; no ext/sqlite required',
];

if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    if (($result['windows'][1]['option_window'] ?? null) !== '["theme_mods","siteurl"]') {
        fwrite(STDERR, "unexpected default RANGE peer JSON aggregate window\n");
        exit(1);
    }
    if (($result['windows'][4]['option_window'] ?? null) !== '["plugin_rules","plugin_queue"]') {
        fwrite(STDERR, "unexpected plugin JSON aggregate window\n");
        exit(1);
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

return $result;
