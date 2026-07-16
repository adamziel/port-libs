<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$rows = [
    ['option_id' => 101, 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10'],
    ['option_id' => 102, 'autoload' => 'yes', 'option_name' => 'Plugin_2', 'priority' => '2'],
    ['option_id' => 103, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => null],
    ['option_id' => 104, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => '1'],
    ['option_id' => 105, 'autoload' => null, 'option_name' => 'network', 'priority' => '3'],
    ['option_id' => 106, 'autoload' => 'YES', 'option_name' => 'plugin_2', 'priority' => new SQLiteBlobValue('2')],
];

$cursor = SQLiteVdbeSortCompare::cursor(
    $rows,
    ['autoload', 'priority', 'option_name', 'option_id'],
    'GCGD',
    ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'],
    [false, false, false, false],
    ['LAST', 'LAST', 'LAST', null],
);

$yielded = [];
while (($row = $cursor->nextRow()) !== null) {
    $priority = $row['priority'];
    $yielded[] = [
        'option_id' => $row['option_id'],
        'autoload' => $row['autoload'],
        'option_name' => $row['option_name'],
        'priority' => $priority instanceof SQLiteBlobValue ? $priority->bytes : $priority,
    ];
}

echo json_encode([
    'scenario' => 'application-vdbe-sort-affinity-current-next',
    'sortedOptionIds' => array_column($yielded, 'option_id'),
    'yieldedRows' => $yielded,
    'cursorPosition' => $cursor->position(),
    'eof' => $cursor->eof(),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
