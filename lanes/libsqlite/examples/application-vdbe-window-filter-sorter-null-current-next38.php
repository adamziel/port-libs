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
    ['blog_id' => 1, 'autoload' => null, 'option_name' => 'zeta', 'bytes' => 5, 'active' => 1],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => null, 'bytes' => 7, 'active' => 0],
    ['blog_id' => 1, 'autoload' => 'YES', 'option_name' => 'alpha', 'bytes' => null, 'active' => null],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'Alpha ', 'bytes' => 11, 'active' => '1'],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'beta', 'bytes' => 13, 'active' => '0'],
    ['blog_id' => 1, 'autoload' => 'yes', 'option_name' => 'gamma', 'bytes' => 17, 'active' => '2'],
    ['blog_id' => 2, 'autoload' => null, 'option_name' => 'network', 'bytes' => 19, 'active' => 1],
    ['blog_id' => 2, 'autoload' => 'no', 'option_name' => null, 'bytes' => 23, 'active' => '0'],
    ['blog_id' => 2, 'autoload' => 'no', 'option_name' => 'cache', 'bytes' => null, 'active' => 1],
    ['blog_id' => 2, 'autoload' => 'yes', 'option_name' => 'Network', 'bytes' => 29, 'active' => -1],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $options,
    'bytes',
    ['blog_id'],
    ['autoload', 'option_name'],
    'active',
    1,
    1,
    'D',
    [],
    'GG',
    ['NOCASE', 'RTRIM'],
    [false, false],
    ['FIRST', 'FIRST']
);

$summary = [
    'scenario' => 'application-vdbe-window-filter-sorter-null-current-next38',
    'rowids' => [],
    'filterPassed' => [],
    'totals' => [],
    'nextOrderKeys' => [],
];

foreach ($cursor->drainSummaries('|') as $row) {
    $summary['rowids'][] = $row['value'];
    $summary['filterPassed'][] = $row['currentFilterPassed'];
    $summary['totals'][] = $row['total'];
    $summary['nextOrderKeys'][] = $row['nextOrderKey'];
}

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['filterPassed'] === [true, false, false, true, false, true, true, false, true, true]);
    assert($summary['totals'] === [5.0, 5.0, 11.0, 11.0, 28.0, 17.0, 19.0, 19.0, 29.0, 29.0]);
    assert($summary['nextOrderKeys'][0] === ['YES', 'alpha']);
    assert($summary['nextOrderKeys'][9] === null);
    echo "application-vdbe-window-filter-sorter-null-current-next38 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
