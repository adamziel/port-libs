<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['site' => 1, 'option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 10, 'ok' => 1],
    ['site' => 1, 'option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 10, 'ok' => '1'],
    ['site' => 1, 'option_id' => 3, 'option_name' => 'cron_lock', 'autoload' => 'no', 'bytes' => 12, 'ok' => 0],
    ['site' => 1, 'option_id' => 4, 'option_name' => 'plugin_rules', 'autoload' => 'no', 'bytes' => 16, 'ok' => 1],
    ['site' => 1, 'option_id' => 5, 'option_name' => 'theme_mods', 'autoload' => 'no', 'bytes' => 16, 'ok' => null],
    ['site' => 1, 'option_id' => 6, 'option_name' => 'transient_feed', 'autoload' => 'yes', 'bytes' => 21, 'ok' => '2'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bytes'],
    'ok',
    0,
    4,
    [],
    [],
    ['NUMERIC'],
    [],
    [],
    [],
    'RANGE'
);

$summaries = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $summary = $cursor->currentSummary();
    $summaries[] = [
        'option_id' => $row['option_id'],
        'option_name' => $row['option_name'],
        'orderKey' => $summary['orderKey'],
        'frameRows' => $summary['frameRows'],
        'filteredRows' => $summary['filteredRows'],
        'total' => $cursor->total(),
        'groupConcat' => $cursor->groupConcat('|'),
    ];
    $cursor->next();
}

echo json_encode([
    'scenario' => 'application-vdbe-window-peer-range-current-next',
    'applicationUse' => 'Evaluate copied wp_options import diagnostics through VDBE-style RANGE CURRENT ROW AND N FOLLOWING aggregate frames, preserving peer groups for equal option payload sizes and applying SQL FILTER truthiness without requiring ext/sqlite.',
    'frameUnit' => 'RANGE',
    'frame' => 'CURRENT ROW AND 4 FOLLOWING',
    'partitionColumns' => ['site'],
    'orderColumns' => ['bytes'],
    'summaries' => $summaries,
    'dependencies' => [
        'native PHP VDBE window cursor',
        'no ext/sqlite runtime dependency',
        'no new support component required',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
