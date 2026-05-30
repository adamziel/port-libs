<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['option_id' => 1, 'site' => 1, 'bucket' => 1.0, 'option_name' => 'siteurl', 'bytes' => 10, 'include' => 1],
    ['option_id' => 2, 'site' => 1, 'bucket' => 1.0, 'option_name' => 'home', 'bytes' => 20, 'include' => 0],
    ['option_id' => 3, 'site' => 1, 'bucket' => 1.25, 'option_name' => 'blogname', 'bytes' => 30, 'include' => 1],
    ['option_id' => 4, 'site' => 1, 'bucket' => 1.5, 'option_name' => 'active_plugins', 'bytes' => 40, 'include' => true],
    ['option_id' => 5, 'site' => 1, 'bucket' => 2.0, 'option_name' => '_transient_a', 'bytes' => null, 'include' => null],
    ['option_id' => 6, 'site' => 1, 'bucket' => 2.0, 'option_name' => '_transient_b', 'bytes' => 60, 'include' => '2'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bucket'],
    'include',
    0.0,
    0.5,
    'D',
    [],
    'D',
    [],
    [],
    [],
    'RANGE',
    'CURRENT ROW',
);

$summary = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $yield = $cursor->currentYieldSummary('option_id', '|');
    $summary[] = [
        'option_id' => $row['option_id'],
        'option_name' => $row['option_name'],
        'rawRangeOptionIds' => $yield['rawFrameRowids'],
        'excludedOptionIds' => $yield['excludedRowids'],
        'filteredOptionIds' => $yield['filteredRowids'],
        'filteredBytes' => $yield['sum'],
        'nextOptionId' => $yield['nextRowid'],
    ];
    $cursor->next();
}

echo json_encode([
    'sqlShape' => "sum(bytes) FILTER (WHERE include) OVER (PARTITION BY site ORDER BY bucket RANGE BETWEEN CURRENT ROW AND 0.5 FOLLOWING EXCLUDE CURRENT ROW)",
    'applicationUse' => 'Preview copied wp_options import rows through VDBE-style RANGE current-next yields, exposing raw frame rows, EXCLUDE removals, and FILTER-selected aggregate rows without ext/sqlite.',
    'rows' => $summary,
], JSON_PRETTY_PRINT) . PHP_EOL;
