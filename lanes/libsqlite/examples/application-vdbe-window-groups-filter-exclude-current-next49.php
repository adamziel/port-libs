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
    ['rowid' => 1, 'site' => 1, 'option_name' => 'alpha', 'autoload' => 'yes', 'bytes' => 10, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'alpha', 'autoload' => 'no', 'bytes' => 10, 'ok' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'beta', 'autoload' => 'yes', 'bytes' => 10, 'ok' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'cron', 'autoload' => 'no', 'bytes' => 20, 'ok' => null],
    ['rowid' => 5, 'site' => 1, 'option_name' => 'cron', 'autoload' => 'yes', 'bytes' => 20, 'ok' => true],
    ['rowid' => 6, 'site' => 1, 'option_name' => 'theme', 'autoload' => 'yes', 'bytes' => 30, 'ok' => '0.5'],
    ['rowid' => 7, 'site' => 1, 'option_name' => 'theme', 'autoload' => 'no', 'bytes' => 30, 'ok' => '0'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $options,
    'bytes',
    ['site'],
    ['bytes', 'option_name'],
    'ok',
    0,
    1,
    ['INTEGER'],
    [],
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'NOCASE'],
    [false, false],
    [],
    'GROUPS',
    'CURRENT ROW',
);

$summary = [
    'scenario' => 'application-vdbe-window-groups-filter-exclude-current-next49',
    'sqlShape' => "sum(bytes) FILTER (WHERE ok) OVER (PARTITION BY site ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW)",
    'rows' => [],
];

while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $pair = $cursor->currentNextFrameRows(true);
    $summary['rows'][] = [
        'rowid' => $row['rowid'],
        'currentFilteredIds' => array_column($pair['current'], 'rowid'),
        'nextFilteredIds' => $pair['next'] === null ? null : array_column($pair['next'], 'rowid'),
        'currentFilteredRows' => $cursor->currentNextSummary()['current']['filteredRows'],
        'nextFilteredRows' => $cursor->currentNextSummary()['next']['filteredRows'] ?? null,
    ];
    $cursor->next();
}

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['rows'][0]['currentFilteredIds'] === [3]);
    assert($summary['rows'][0]['nextFilteredIds'] === [1, 3]);
    assert($summary['rows'][5]['currentFilteredIds'] === []);
    assert($summary['rows'][6]['nextFilteredIds'] === null);
    echo "application-vdbe-window-groups-filter-exclude-current-next49 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
