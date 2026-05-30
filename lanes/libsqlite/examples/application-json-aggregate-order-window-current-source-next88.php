<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = SQLiteSelectSql::execute(
    'SELECT option_id, json_group_array(DISTINCT option_name ORDER BY sort_key DESC) FILTER (WHERE enabled) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 3 FOLLOWING) AS option_window FROM wp_options ORDER BY option_id',
    ['wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'sort_key' => 40, 'enabled' => 1],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'sort_key' => 30, 'enabled' => 1],
        ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'sort_key' => 20, 'enabled' => 1],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'sort_key' => 10, 'enabled' => 1],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'sort_key' => 50, 'enabled' => 1],
        ['option_id' => 6, 'autoload' => 'no', 'option_name' => 'empty_option', 'sort_key' => 60, 'enabled' => 0],
    ]],
);

$jsonbRows = SQLiteSelectSql::execute(
    'SELECT option_id, jsonb_group_array(DISTINCT option_name ORDER BY sort_key DESC) OVER (PARTITION BY autoload ORDER BY option_id ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING) AS option_window FROM wp_options ORDER BY option_id',
    ['wp_options' => [
        ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'sort_key' => 40],
        ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'blogname', 'sort_key' => 30],
        ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'sort_key' => 20],
        ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'plugin_queue', 'sort_key' => 10],
        ['option_id' => 5, 'autoload' => 'no', 'option_name' => 'plugin_rules', 'sort_key' => 50],
    ]],
);

$summary = [
    'textWindow' => array_column($rows, 'option_window', 'option_id'),
    'jsonbWindowForPluginRules' => $jsonbRows[2]['option_window'] instanceof SQLiteBlobValue
        ? SQLiteJsonB::decode($jsonbRows[2]['option_window']->bytes)
        : null,
];

if (($argv[1] ?? '') === '--self-test') {
    if (($summary['textWindow'][3] ?? null) !== '["plugin_rules","plugin_queue"]') {
        throw new RuntimeException('Expected filtered distinct JSON aggregate window for plugin_rules');
    }
    if ($summary['jsonbWindowForPluginRules'] !== ['plugin_rules', 'plugin_queue']) {
        throw new RuntimeException('Expected JSONB distinct aggregate window decode for plugin_rules');
    }
    echo "application-json-aggregate-order-window-current-source-next88 self-test passed\n";

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
