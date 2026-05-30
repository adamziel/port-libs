<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"rules":[{"name":"warm","priority":1},{"name":"serve","priority":9}],"channels":["alpha","stable"]}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'validate', 'priority' => 3],
                ['name' => 'notify', 'priority' => 8],
            ],
            'channels' => ['beta'],
        ])),
        'autoload' => 'no',
    ],
];

$result = SQLiteSelectSql::execute(
    "SELECT option_name, option_value -> '$.rules' -> -1 ->> 'name' AS last_rule, option_value -> '$.channels' ->> -1 AS last_channel FROM wp_options ORDER BY option_id",
    ['wp_options' => $rows],
);

$payload = [
    'applicationUse' => 'Evaluate copied wp_options JSON settings with SQLite 3.47-style negative integer RHS JSON operators, so the latest rule/channel can be inspected without ext/sqlite.',
    'query' => "SELECT option_name, option_value -> '$.rules' -> -1 ->> 'name' AS last_rule, option_value -> '$.channels' ->> -1 AS last_channel FROM wp_options ORDER BY option_id",
    'rows' => $result,
];

echo json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL;
