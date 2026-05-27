<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') as $sourceFile) {
    require_once $sourceFile;
}

use PortLibs\LibSqlite\SQLiteSelectSql;

$wpOptions = [
    [
        'option_name' => 'plugin_contact_settings',
        'option_value' => '{"phones":["704-555-0101","919-555-0102"]}',
    ],
    [
        'option_name' => 'plugin_corrupt_settings',
        'option_value' => '{bad',
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name, json_each.value AS phone
       FROM wp_options, json_each(wp_options.option_value, '$.phones')
      WHERE json_error_position(wp_options.option_value) = 0
        AND json_each.value LIKE '704-%'
      ORDER BY option_name",
    ['wp_options' => $wpOptions],
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
