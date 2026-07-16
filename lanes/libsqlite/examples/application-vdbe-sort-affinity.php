<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_10', 'autoload' => 'yes', 'priority' => '10'],
    ['option_id' => 2, 'option_name' => 'Plugin_2', 'autoload' => 'yes', 'priority' => '2'],
    ['option_id' => 3, 'option_name' => 'cache', 'autoload' => 'no', 'priority' => null],
    ['option_id' => 4, 'option_name' => 'cache ', 'autoload' => 'no', 'priority' => '1'],
    ['option_id' => 5, 'option_name' => 'mu_plugin', 'autoload' => 'yes', 'priority' => new SQLiteBlobValue('4')],
];

$ordered = SQLiteVdbeSortCompare::sortRows(
    $rows,
    ['autoload', 'priority', 'option_id'],
    'GCD',
    ['BINARY', 'BINARY', 'BINARY'],
    [false, true, false]
);

echo json_encode([
    'scenario' => 'application option import VDBE sort-key affinity',
    'orderedOptionIds' => array_column($ordered, 'option_id'),
    'dependencyClosure' => 'no new support component needed; reuses scalar comparison, BLOB, and row-array helpers',
], JSON_PRETTY_PRINT) . "\n";
