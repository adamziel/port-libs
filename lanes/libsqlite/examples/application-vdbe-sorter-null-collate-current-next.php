<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$rows = [
    ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_2', 'priority' => null],
    ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10'],
    ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => null],
    ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => '1'],
    ['option_id' => 5, 'autoload' => null, 'option_name' => 'network', 'priority' => '3'],
    ['option_id' => 6, 'autoload' => 'yes', 'option_name' => null, 'priority' => '2'],
    ['option_id' => 7, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => null],
    ['option_id' => 8, 'autoload' => 'YES', 'option_name' => 'Plugin_2 ', 'priority' => '2'],
];

$cursor = SQLiteVdbeSortCompare::cursor(
    $rows,
    ['autoload', 'priority', 'option_id'],
    'GCD',
    ['NOCASE', 'BINARY', 'BINARY'],
    [false, false, false],
    ['LAST', 'LAST', null]
);

$ordered = [];
while (!$cursor->eof()) {
    $current = $cursor->current();
    $ordered[] = $current['option_id'];
    $cursor->next();
}

echo json_encode([
    'scenario' => 'application option import VDBE sorter NULLS/COLLATE current-next',
    'orderedOptionIds' => $ordered,
    'dependencyClosure' => 'no new support component needed; reuses VDBE sort comparison, row-array sorting, and cursor iteration helpers',
], JSON_PRETTY_PRINT) . "\n";
