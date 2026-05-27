<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"plugin":{"modes":["dark","light"],"enabled":true}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_jsonb_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'modes' => ['seo', 'forms', 'cache'],
                'enabled' => true,
            ],
        ])),
        'autoload' => 'no',
    ],
];

$summary = SQLiteSelectSql::execute(
    "SELECT option_name, json_type(option_value, '$.plugin.modes') AS mode_type, json_array_length(option_value, '$.plugin.modes') AS mode_count FROM wp_options ORDER BY mode_count DESC",
    ['wp_options' => $rows],
);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
