<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, incoming.column2 AS imported_value, incoming.column3 AS priority FROM wp_options AS o JOIN (VALUES (1, 'https://example.test', 20), (2, 'https://example.test/home', 10), (9, 'unused', 99)) AS incoming ON incoming.column1 = o.option_id ORDER BY priority DESC",
    ['wp_options' => $options],
);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows joined to an inline SQLite VALUES source for bounded import/staging review without requiring ext/sqlite.',
    'sql' => "SELECT o.option_name AS option_name, incoming.column2 AS imported_value, incoming.column3 AS priority FROM wp_options AS o JOIN (VALUES (1, 'https://example.test', 20), (2, 'https://example.test/home', 10), (9, 'unused', 99)) AS incoming ON incoming.column1 = o.option_id ORDER BY priority DESC",
    'matchedOptions' => array_column($rows, 'option_name'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
