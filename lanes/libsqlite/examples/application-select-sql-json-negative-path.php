<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'option_value' => '{"rules":[{"name":"seo","priority":2,"enabled":true},{"name":"cache","priority":7,"enabled":true}]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'forms_plugin_settings',
        'option_value' => '{"rules":[{"name":"forms","priority":4,"enabled":false},{"name":"media","priority":1,"enabled":true}]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 3,
        'option_name' => 'jsonb_plugin_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'seo', 'priority' => 2, 'enabled' => true],
                ['name' => 'cache', 'priority' => 7, 'enabled' => true],
            ],
        ])),
        'autoload' => 'yes',
    ],
];

$sql = "SELECT option_name, json_extract(option_value, '$.rules[#-1].name') AS last_rule, json_extract(option_value, '$.rules[#-2].name') AS previous_rule, json_extract(option_value, '$.rules[#-1].priority') AS last_priority FROM wp_options WHERE json_extract(option_value, '$.rules[#-1].enabled') = 1 ORDER BY json_extract(option_value, '$.rules[#-1].priority') DESC, option_name ASC";
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'scenario' => 'application-select-sql-json-negative-path',
    'applicationUse' => 'Local-only wp_options diagnostics can execute SQLite SELECT text with json_extract/jsonb_extract reverse array paths such as $.rules[#-1] in projection, WHERE, JOIN, and ORDER BY clauses without requiring ext/sqlite.',
    'sql' => $sql,
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
