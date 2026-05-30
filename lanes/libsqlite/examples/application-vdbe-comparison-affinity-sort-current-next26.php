<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteVdbeSorterCursor.php';
require_once __DIR__ . '/../src/SQLiteVdbeSortCompare.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$rows = [
    ['option_id' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_02', 'priority' => '02'],
    ['option_id' => 11, 'autoload' => 'YES', 'option_name' => 'Plugin_2 ', 'priority' => 2],
    ['option_id' => 12, 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10'],
    ['option_id' => 13, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => null],
    ['option_id' => 14, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => '1'],
    ['option_id' => 15, 'autoload' => null, 'option_name' => 'network', 'priority' => '3'],
    ['option_id' => 16, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => new SQLiteBlobValue('2')],
];

$trace = SQLiteVdbeSortCompare::sortedRowTrace(
    $rows,
    ['autoload', 'priority', 'option_name', 'option_id'],
    'GCGD',
    ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'],
    [false, false, false, false],
    ['LAST', 'LAST', 'LAST', null]
);

echo json_encode([
    'scenario' => 'application-vdbe-comparison-affinity-sort-current-next26',
    'orderedOptionIds' => array_map(static fn (array $entry): int => $entry['row']['option_id'], $trace),
    'stableTieOptionIds' => array_map(
        static fn (array $entry): int => $entry['row']['option_id'],
        array_values(array_filter($trace, static fn (array $entry): bool => $entry['stableTie']))
    ),
    'firstDecision' => [
        'optionId' => $trace[1]['row']['option_id'],
        'previousSequence' => $trace[1]['previousSequence'],
        'comparison' => $trace[1]['comparison'],
        'decidingStep' => $trace[1]['steps'][array_key_last($trace[1]['steps'])],
    ],
    'lastDecision' => [
        'optionId' => $trace[array_key_last($trace)]['row']['option_id'],
        'decidingStep' => $trace[array_key_last($trace)]['steps'][0],
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;
