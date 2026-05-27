<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteNumericAggregate.php';
require_once __DIR__ . '/../src/SQLiteTextAggregate.php';
require_once __DIR__ . '/../src/SQLiteVdbeSortCompare.php';
require_once __DIR__ . '/../src/SQLiteVdbeWindowAggregateCursor.php';

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$options = [
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'siteurl', 'bytes' => 24, 'active' => 1],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'home', 'bytes' => 24, 'active' => 1],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => '_transient_feed', 'bytes' => 30, 'active' => 0],
    ['blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'network_home', 'bytes' => 40, 'active' => 1],
    ['blog_id' => 2, 'autoload' => 'no', 'option_name' => 'orphan', 'bytes' => 5, 'active' => 1],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $options,
    'bytes',
    ['blog_id'],
    ['autoload', 'option_name'],
    'active',
    1,
    0,
    'D',
    [],
    'GG',
    ['NOCASE', 'BINARY']
);

echo json_encode($cursor->drainSummaries('|'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
