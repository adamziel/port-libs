<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'bucket' => 'autoload', 'priority' => 20, 'enabled' => 1, 'option_name' => 'siteurl'],
        ['option_id' => 2, 'autoload' => 'yes', 'bucket' => 'autoload', 'priority' => 10, 'enabled' => 1, 'option_name' => 'home'],
        ['option_id' => 3, 'autoload' => 'yes', 'bucket' => 'network', 'priority' => 30, 'enabled' => 1, 'option_name' => 'blogname'],
        ['option_id' => 4, 'autoload' => 'yes', 'bucket' => 'network', 'priority' => 5, 'enabled' => 0, 'option_name' => 'disabled_network'],
        ['option_id' => 5, 'autoload' => 'yes', 'bucket' => 'theme', 'priority' => 40, 'enabled' => 1, 'option_name' => 'theme_mods'],
        ['option_id' => 6, 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 50, 'enabled' => 1, 'option_name' => 'plugin_rules'],
        ['option_id' => 7, 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 45, 'enabled' => 1, 'option_name' => 'plugin_queue'],
        ['option_id' => 8, 'autoload' => 'no', 'bucket' => 'transient', 'priority' => 35, 'enabled' => 1, 'option_name' => '_transient_feed'],
    ],
];

$rows = SQLiteSelectSql::execute(
    'SELECT option_id, json_group_array(option_name ORDER BY priority DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY bucket RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS option_summary FROM wp_options ORDER BY option_id',
    $tables,
);

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        1 => '["siteurl","home"]',
        2 => '["siteurl","home"]',
        3 => '["blogname","siteurl","home"]',
        4 => '["blogname","siteurl","home"]',
        5 => '["theme_mods","blogname","siteurl","home"]',
        6 => '["plugin_rules","plugin_queue"]',
        7 => '["plugin_rules","plugin_queue"]',
        8 => '["plugin_rules","plugin_queue","_transient_feed"]',
    ];
    foreach ($rows as $row) {
        if (($expected[$row['option_id']] ?? null) !== $row['option_summary']) {
            fwrite(STDERR, 'Unexpected text RANGE JSON aggregate summary for option_id ' . $row['option_id'] . PHP_EOL);
            exit(1);
        }
    }
    echo "application-json-aggregate-text-range-window self-test passed\n";
    exit(0);
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
