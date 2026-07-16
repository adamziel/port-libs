<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'score' => 10, 'enabled' => 1, 'option_name' => 'siteurl'],
        ['option_id' => 2, 'autoload' => 'yes', 'score' => 30, 'enabled' => 1, 'option_name' => 'theme_mods'],
        ['option_id' => 3, 'autoload' => 'yes', 'score' => 20, 'enabled' => 1, 'option_name' => 'blogname'],
        ['option_id' => 4, 'autoload' => 'yes', 'score' => 25, 'enabled' => 0, 'option_name' => 'disabled_yes'],
        ['option_id' => 5, 'autoload' => 'no', 'score' => 50, 'enabled' => 1, 'option_name' => 'plugin_rules'],
        ['option_id' => 6, 'autoload' => 'no', 'score' => 40, 'enabled' => 1, 'option_name' => 'plugin_queue'],
        ['option_id' => 7, 'autoload' => 'no', 'score' => 35, 'enabled' => 0, 'option_name' => 'disabled_no'],
        ['option_id' => 8, 'autoload' => 'no', 'score' => 30, 'enabled' => 1, 'option_name' => 'plugin_rules'],
        ['option_id' => 9, 'autoload' => 'no', 'score' => 20, 'enabled' => 1, 'option_name' => null],
    ],
];

$rows = SQLiteSelectSql::execute(
    'SELECT option_id, json_group_array(option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS option_window FROM wp_options ORDER BY option_id',
    $tables,
);

$jsonbRows = SQLiteSelectSql::execute(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY score DESC ROWS BETWEEN CURRENT ROW AND 4 FOLLOWING) AS option_window FROM wp_options ORDER BY option_id',
    $tables,
);

$summary = [
    'sourceOrderedWindow' => array_column($rows, 'option_window', 'option_id'),
    'pluginJsonbWindow' => $jsonbRows[4]['option_window'] instanceof SQLiteBlobValue
        ? SQLiteJsonB::decode($jsonbRows[4]['option_window']->bytes)
        : null,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['sourceOrderedWindow'][2] ?? null) !== '["theme_mods","blogname"]') {
        throw new RuntimeException('Expected descending current-source frame for theme_mods');
    }
    if (($summary['sourceOrderedWindow'][6] ?? null) !== '["plugin_queue","plugin_rules"]') {
        throw new RuntimeException('Expected descending current-source frame for plugin_queue');
    }
    if ($summary['pluginJsonbWindow'] !== ['plugin_rules', 'plugin_queue', null]) {
        throw new RuntimeException('Expected JSONB DISTINCT window to preserve first descending source occurrence');
    }
    echo "application-json-aggregate-window-filter-order-current-source-next104 self-test passed\n";

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
