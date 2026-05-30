<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteVdbeSortCompare.php';
require __DIR__ . '/../src/SQLiteAffinityComparison.php';
require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteNumericAggregate.php';
require __DIR__ . '/../src/SQLiteTextAggregate.php';
require __DIR__ . '/../src/SQLiteVdbeWindowAggregateCursor.php';

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Alpha', 'option_bytes' => 10, 'include' => 1],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'alpha', 'option_bytes' => 20, 'include' => 1],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Beta', 'option_bytes' => 30, 'include' => 0],
    ['blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Gamma', 'option_bytes' => 40, 'include' => 1],
    ['blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'gamma', 'option_bytes' => null, 'include' => 1],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'option_bytes',
    ['blog_id', 'autoload'],
    ['option_name'],
    'include',
    1,
    1,
    ['INTEGER', 'TEXT'],
    ['BINARY', 'BINARY'],
    ['TEXT'],
    ['NOCASE'],
    [],
    [],
    'ROWS',
    'CURRENT ROW'
);

echo json_encode([
    'scenario' => 'application-vdbe-sorter-window-exclude-current-next36',
    'rows' => array_map(static function (array $summary): array {
        return [
            'position' => $summary['position'],
            'partition' => $summary['partitionKey'],
            'order' => $summary['orderKey'],
            'frameStart' => $summary['frameStart'],
            'frameEnd' => $summary['frameEnd'],
            'frameRows' => $summary['frameRows'],
            'filteredRows' => $summary['filteredRows'],
            'excludedTotal' => $summary['total'],
            'excludedConcat' => $summary['groupConcat'],
        ];
    }, $cursor->drainSummaries('|')),
    'applicationUse' => 'Copied wp_options ranking/aggregate previews can use VDBE-style sorter current/next frames with NOCASE collation while EXCLUDE CURRENT ROW removes only the current row before aggregate stepping.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
