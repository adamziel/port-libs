<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no'],
    ],
    'import_option_meta' => [
        ['option_id' => 1, 'meta_key' => 'source_url', 'meta_value' => 'https://legacy.example'],
        ['option_id' => 2, 'meta_key' => 'source_url', 'meta_value' => 'https://legacy.example'],
        ['option_id' => 3, 'meta_key' => 'source_title', 'meta_value' => 'Legacy Title'],
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT option_name FROM wp_options WHERE EXISTS (SELECT 1 FROM (SELECT option_id, meta_key FROM import_option_meta WHERE meta_key LIKE 'source_%') AS derived_meta WHERE derived_meta.option_id = wp_options.option_id) ORDER BY option_id",
    $tables,
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
