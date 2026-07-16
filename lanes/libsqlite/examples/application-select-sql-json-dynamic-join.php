<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'option_value' => '{"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":false}]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_plugin_settings',
        'option_value' => '{"rules":[{"name":"forms","priority":4,"enabled":true},{"name":"media","priority":1,"enabled":true}]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 3,
        'option_name' => 'empty_plugin_settings',
        'option_value' => '{"rules":[],"groups":[{"name":"empty","rules":[]}]}',
        'autoload' => 'no',
    ],
];

$prioritySql = "SELECT o.option_name AS option_name, j.fullkey AS fullkey, j.atom AS priority FROM wp_options AS o JOIN json_tree(o.option_value, '$.rules') AS j ON j.key = 'priority' WHERE j.atom >= 4 ORDER BY priority DESC, option_name ASC";
$priorityRows = SQLiteSelectSql::execute($prioritySql, ['wp_options' => $options]);

$leftJoinSql = "SELECT o.option_id AS id, o.option_name AS option_name, j.key AS json_key FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.missing') AS j ON j.key = 'name' ORDER BY id";
$leftJoinRows = SQLiteSelectSql::execute($leftJoinSql, ['wp_options' => $options]);

$nestedLeftJoinSql = "SELECT o.option_name AS option_name, g.key AS group_index, r.rowid AS rule_rowid, r.atom AS rule_name FROM wp_options AS o LEFT JOIN json_each(o.option_value, '$.groups') AS g ON g.type = 'object' LEFT JOIN json_each(g.value, '$.rules') AS r ON r.atom IS NOT NULL ORDER BY option_name, group_index, rule_name";
$nestedLeftJoinRows = SQLiteSelectSql::execute($nestedLeftJoinSql, ['wp_options' => $options]);

echo json_encode([
    'scenario' => 'application-select-sql-json-dynamic-join',
    'applicationUse' => 'Local-only wp_options diagnostics can join each copied option row to json_tree/json_each using that row option_value as the JSON argument, preserving INNER, LEFT, nested LEFT JOIN, rowid-alias NULL-extension, ORDER BY, and NULL-extension behavior without requiring ext/sqlite.',
    'prioritySql' => $prioritySql,
    'priorityRows' => $priorityRows,
    'leftJoinSql' => $leftJoinSql,
    'leftJoinRows' => $leftJoinRows,
    'nestedLeftJoinSql' => $nestedLeftJoinSql,
    'nestedLeftJoinRows' => $nestedLeftJoinRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
