<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'SiteURL', 'option_value' => 'https://network.test', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'home   ', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'HOME', 'option_value' => 'https://admin.test', 'autoload' => 'no'],
];

$caseFolded = SQLiteSelectSql::execute(
    "SELECT option_id, option_name FROM wp_options WHERE option_name COLLATE NOCASE = 'SITEURL' ORDER BY option_id",
    ['wp_options' => $options],
);

$trimmed = SQLiteSelectSql::execute(
    "SELECT option_id, option_name FROM wp_options WHERE option_name COLLATE RTRIM = 'home'",
    ['wp_options' => $options],
);

echo json_encode([
    'scenario' => 'application-select-collation-predicate',
    'caseFoldedOptionIds' => array_column($caseFolded, 'option_id'),
    'rtrimOptionIds' => array_column($trimmed, 'option_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
