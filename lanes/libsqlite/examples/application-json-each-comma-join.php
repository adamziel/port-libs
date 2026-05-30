<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_name' => 'site_plugin_settings', 'autoload' => 'yes', 'option_value' => '{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}],"flags":["network","beta"]}'],
    ['option_name' => 'theme_plugin_settings', 'autoload' => 'yes', 'option_value' => '{"rules":[{"name":"forms","priority":4},{"name":"media","priority":1}],"flags":["theme"]}'],
    ['option_name' => 'broken_settings', 'autoload' => 'no', 'option_value' => null],
];

$priorityRows = SQLiteSelectSql::execute(
    "SELECT wp_options.option_name AS option_name, json_tree.atom AS priority FROM wp_options, json_tree(wp_options.option_value, '$.rules') WHERE json_tree.key = 'priority' ORDER BY priority DESC",
    ['wp_options' => $options],
);
$flagRows = SQLiteSelectSql::execute(
    "SELECT wp_options.option_name AS option_name, flags.atom AS flag FROM wp_options, json_each(wp_options.option_value, '$.flags') AS flags WHERE wp_options.autoload = 'yes' ORDER BY option_name, flag",
    ['wp_options' => $options],
);
$leftRows = SQLiteSelectSql::execute(
    "SELECT wp_options.option_name AS option_name, first_flag.atom AS first_flag FROM wp_options LEFT JOIN json_each(wp_options.option_value, '$.flags') AS first_flag ON first_flag.key = 0 ORDER BY option_name",
    ['wp_options' => $options],
);

echo json_encode([
    'scenario' => 'application-json-each-comma-join',
    'top_priority' => $priorityRows[0] ?? null,
    'priority_count' => count($priorityRows),
    'flags' => $flagRows,
    'left_join_first_flags' => $leftRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
