<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_json5_settings',
        'option_value' => '{plugin:{enabled:true,}}',
        'flag_value' => '2.9',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_jsonb_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => true]])),
        'flag_value' => new SQLiteBlobValue('8'),
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_generated_settings',
        'option_value' => new SQLiteJsonSubtypeValue('{"plugin":{"enabled":true}}'),
        'flag_value' => true,
    ],
    [
        'option_id' => 4,
        'option_name' => 'plugin_numeric_setting',
        'option_value' => 123,
        'flag_value' => '1abc',
    ],
];

$summary = SQLiteSelectSql::execute(
    'SELECT option_name, json_valid(option_value, flag_value) AS valid FROM wp_options ORDER BY option_id',
    ['wp_options' => $rows],
);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
