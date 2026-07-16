<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteVdbeWindowAggregateCursor.php';
require_once __DIR__ . '/../src/SQLiteVdbeSortCompare.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['blog_id' => 1, 'bucket' => 1.0, 'option_name' => 'siteurl', 'value' => 'siteurl', 'autoload' => 1],
    ['blog_id' => 1, 'bucket' => 1.0, 'option_name' => 'home', 'value' => 'home', 'autoload' => 0],
    ['blog_id' => 1, 'bucket' => 1.5, 'option_name' => 'blogname', 'value' => 'blogname', 'autoload' => 1],
    ['blog_id' => 1, 'bucket' => 2.0, 'option_name' => 'theme_mods', 'value' => null, 'autoload' => 1],
    ['blog_id' => 1, 'bucket' => 2.0, 'option_name' => 'rewrite_rules', 'value' => 'rewrite_rules', 'autoload' => null],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'value',
    ['blog_id'],
    ['bucket'],
    'autoload',
    0,
    1,
    'D',
    [],
    'D',
    [],
    [],
    [],
    'GROUPS',
);

echo json_encode([
    'scenario' => 'application_vdbe_window_value_groups_range_current_next50',
    'first_values' => array_column($cursor->drainSummaries(), 'firstValue'),
], JSON_PRETTY_PRINT) . PHP_EOL;
