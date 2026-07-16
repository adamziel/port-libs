<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'SiteURL', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'siteurl ', 'autoload' => 'yes', 'bytes' => null],
    ['option_id' => 3, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 12],
    ['option_id' => 4, 'option_name' => 'HOME ', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => 'blogname', 'autoload' => 'no', 'bytes' => null],
    ['option_id' => 6, 'option_name' => null, 'autoload' => null, 'bytes' => 0],
];

$sql = 'SELECT option_id, option_name, bytes FROM wp_options ORDER BY option_name COLLATE NOCASE DESC NULLS LAST, bytes NULLS LAST';
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options rows with SQLite ORDER BY collation and explicit NULL placement in native PHP, useful when imports need deterministic case-insensitive option ordering without ext/sqlite.',
    'sql' => $sql,
    'orderedOptionIds' => array_column($rows, 'option_id'),
    'orderedOptionNames' => array_column($rows, 'option_name'),
], JSON_PRETTY_PRINT) . "\n";
