<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'seq' => 1, 'option_name' => 'siteurl', 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'seq' => 2, 'option_name' => 'home', 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'seq' => 3, 'option_name' => 'blogname', 'include' => 1],
    ['rowid' => 4, 'site' => 1, 'seq' => 4, 'option_name' => 'active_plugins', 'include' => true],
    ['rowid' => 5, 'site' => 2, 'seq' => 1, 'option_name' => 'network_siteurl', 'include' => 1],
    ['rowid' => 6, 'site' => 2, 'seq' => 2, 'option_name' => 'network_home', 'include' => null],
    ['rowid' => 7, 'site' => 2, 'seq' => 3, 'option_name' => 'network_plugins', 'include' => '2'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'option_name',
    ['site'],
    ['seq'],
    'include',
    0,
    2,
    'D',
    [],
    'D',
    [],
    [],
    [],
    'ROWS',
    'CURRENT ROW',
);

$preview = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $preview[] = [
        'rowid' => $row['rowid'],
        'option_name' => $row['option_name'],
        'first_following_truthy' => $cursor->firstValue(),
        'last_following_truthy' => $cursor->lastValue(),
        'second_following_truthy' => $cursor->nthValue(2),
        'unfiltered_following_rowids' => array_column($cursor->currentFrameRows(false), 'rowid'),
    ];
    $cursor->next();
}

echo json_encode([
    'scenario' => 'application-vdbe-window-value-frame-exclude-current-next48',
    'applicationUse' => 'Preview copied wp_options rows with VDBE-style first_value(), last_value(), and nth_value() over ROWS CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW frames, applying SQL FILTER truthiness without ext/sqlite.',
    'window' => 'PARTITION BY site ORDER BY seq ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING EXCLUDE CURRENT ROW',
    'preview' => $preview,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
