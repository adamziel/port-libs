<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSorterYieldCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_A ', 'priority' => null],
    ['rowid' => 2, 'autoload' => 'YES', 'option_name' => 'plugin_a', 'priority' => '10'],
    ['rowid' => 3, 'autoload' => 'yes', 'option_name' => 'plugin_b', 'priority' => '2'],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => null],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => 5],
    ['rowid' => 6, 'autoload' => null, 'option_name' => 'network', 'priority' => 1],
    ['rowid' => 7, 'autoload' => 'yes', 'option_name' => null, 'priority' => 7],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'Plugin_A', 'priority' => 10],
];

$cursor = new SQLiteVdbeSorterYieldCursor(
    $rows,
    ['autoload', 'option_name', 'priority'],
    'GGC',
    ['NOCASE', 'RTRIM', 'BINARY'],
    [false, false, true],
    ['LAST', 'FIRST', 'LAST']
);

echo json_encode([
    'ordered_rowids' => array_column($cursor->remainingRows(), 'rowid'),
    'summaries' => $cursor->drainSummaries(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
