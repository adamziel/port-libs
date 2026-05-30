<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['option_id' => 1, 'option_name' => 'alpha_cache', 'autoload' => 'yes', 'bytes' => 10, 'include' => 1],
    ['option_id' => 2, 'option_name' => 'alpha_cache', 'autoload' => 'no', 'bytes' => 10, 'include' => 0],
    ['option_id' => 3, 'option_name' => 'beta_cache', 'autoload' => 'yes', 'bytes' => 10, 'include' => 1],
    ['option_id' => 4, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 20, 'include' => 0],
    ['option_id' => 5, 'option_name' => 'cron_lock', 'autoload' => 'yes', 'bytes' => 20, 'include' => 1],
    ['option_id' => 6, 'option_name' => 'theme_mods', 'autoload' => 'yes', 'bytes' => 30, 'include' => 1],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    [],
    ['bytes', 'option_name'],
    'include',
    0,
    1,
    [],
    [],
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'BINARY'],
    [],
    [],
    'GROUPS',
    'CURRENT ROW',
);

$summary = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $summary[] = [
        'option_id' => $row['option_id'],
        'option_name' => $row['option_name'],
        'peerBytesExcludingCurrent' => $cursor->sum(),
        'filteredFrameRows' => $cursor->currentSummary()['filteredRows'],
        'frameOptionIds' => array_column($cursor->currentFrameRows(true), 'option_id'),
    ];
    $cursor->next();
}

echo json_encode([
    'sqlShape' => "sum(bytes) FILTER (WHERE include) OVER (ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW)",
    'applicationUse' => 'Preview copied wp_options import diagnostics through VDBE-style GROUPS CURRENT ROW AND N FOLLOWING frames, combining FILTER truthiness with EXCLUDE CURRENT ROW peer handling without requiring ext/sqlite.',
    'rows' => $summary,
], JSON_PRETTY_PRINT) . PHP_EOL;
