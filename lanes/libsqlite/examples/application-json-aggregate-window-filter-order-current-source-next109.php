<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => null, 'enabled' => 1, 'option_name' => 'siteurl'],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 30, 'enabled' => 1, 'option_name' => 'theme_mods'],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 20, 'enabled' => 1, 'option_name' => 'blogname'],
        ['option_id' => 4, 'autoload' => 'no', 'score' => null, 'enabled' => 1, 'option_name' => 'plugin_rules'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => 40, 'enabled' => 1, 'option_name' => 'plugin_queue'],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 30, 'enabled' => 1, 'option_name' => 'plugin_rules'],
    ],
];

$rows = SQLiteSelectSql::execute(
    'SELECT option_id, json_group_array(option_name ORDER BY score ASC NULLS LAST, option_id DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN 9 PRECEDING AND CURRENT ROW) AS option_summary FROM wp_options ORDER BY option_id',
    $tables,
);

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        1 => '["siteurl"]',
        2 => '["theme_mods","siteurl"]',
        3 => '["blogname","theme_mods","siteurl"]',
        4 => '["plugin_rules"]',
        5 => '["plugin_queue","plugin_rules"]',
        6 => '["plugin_rules","plugin_queue","plugin_rules"]',
    ];
    foreach ($rows as $row) {
        if (($expected[$row['option_id']] ?? null) !== $row['option_summary']) {
            fwrite(STDERR, 'Unexpected JSON aggregate window summary for option_id ' . $row['option_id'] . PHP_EOL);
            exit(1);
        }
    }
    echo "application-json-aggregate-window-filter-order-current-source-next109 self-test passed\n";
    exit(0);
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
