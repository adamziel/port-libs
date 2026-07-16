<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2,"autoload":true},{"name":"cache","priority":7,"autoload":false},{"name":"forms","priority":4,"autoload":true}],"flags":["alpha","beta"]}}';
$options = [
    ['option_id' => 1, 'option_name' => 'plugin_settings', 'option_value' => $settings, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
];

$prioritySql = "SELECT key, atom AS priority, fullkey FROM json_tree('{$settings}', '$.plugin.rules') WHERE type = 'integer' ORDER BY priority DESC LIMIT 2";
$priorityRows = SQLiteSelectSql::execute($prioritySql, []);

$joinSql = "SELECT o.option_name AS option_name, j.key AS json_key, j.atom AS enabled FROM wp_options AS o JOIN json_tree('{$settings}', '$.plugin.rules') AS j ON j.type = 'true' WHERE o.option_id = 1 ORDER BY json_key";
$joinRows = SQLiteSelectSql::execute($joinSql, ['wp_options' => $options]);

echo json_encode([
    'scenario' => 'application-select-sql-json-table',
    'applicationUse' => 'Local-only wp_options diagnostics can execute bounded SELECT SQL text whose FROM clause is json_tree()/json_each(), preserving JSON table predicates, ordering, joins, and NULL-extension behavior without requiring ext/sqlite.',
    'prioritySql' => $prioritySql,
    'priorityRows' => $priorityRows,
    'joinSql' => $joinSql,
    'joinRows' => $joinRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
