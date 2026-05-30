<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => 'Example Site', 'bytes' => 12],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'option_value' => null, 'bytes' => null],
];

$rows = SQLiteSelectSql::execute(
    'SELECT DISTINCT autoload, option_value AS value FROM wp_options ORDER BY autoload DESC, value LIMIT 4',
    ['wp_options' => $options],
);

echo json_encode([
    'scenario' => 'application-select-sql-distinct',
    'rowCount' => count($rows),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
