<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'siteurl', 'bytes' => 10, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'home', 'bytes' => 10, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'blogname', 'bytes' => 20, 'include' => '1'],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'cron', 'bytes' => 30, 'include' => true],
    ['rowid' => 5, 'site' => 1, 'option_name' => 'active_plugins', 'bytes' => 30, 'include' => '2'],
    ['rowid' => 6, 'site' => 2, 'option_name' => 'network_siteurl', 'bytes' => 10, 'include' => 1],
    ['rowid' => 7, 'site' => 2, 'option_name' => 'network_home', 'bytes' => 10, 'include' => ''],
    ['rowid' => 8, 'site' => 2, 'option_name' => 'network_blogname', 'bytes' => 20, 'include' => '1'],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'option_name',
    ['site'],
    ['bytes'],
    'include',
    0,
    1,
    ['INTEGER'],
    [],
    ['NUMERIC'],
    [],
    [],
    [],
    'GROUPS',
    'CURRENT ROW',
);

$preview = [];
while (!$cursor->eof()) {
    $row = $cursor->currentRow();
    $pair = $cursor->currentNextValueFrameSummary(2);
    $preview[] = [
        'rowid' => $row['rowid'],
        'site' => $row['site'],
        'option_name' => $row['option_name'],
        'first_group_value_after_current' => $pair['current']['firstValue'],
        'second_group_value_after_current' => $pair['current']['nthValue'],
        'current_frame_rowids' => $pair['current']['rowids'],
        'next_frame_rowids' => $pair['next']['rowids'] ?? null,
    ];
    $cursor->next();
}

$payload = [
    'scenario' => 'application-vdbe-window-groups-value-exclude-current-next52',
    'applicationUse' => 'Preview copied wp_options rows with VDBE-style first_value(), last_value(), and nth_value() over GROUPS CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW frames, including current/next peeks and SQL FILTER truthiness without ext/sqlite.',
    'window' => 'PARTITION BY site ORDER BY bytes GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW',
    'preview' => $preview,
];

if (($argv[1] ?? null) === '--self-test') {
    $expectedFirst = ['blogname', 'siteurl', 'cron', 'active_plugins', 'cron', 'network_blogname', 'network_siteurl', null];
    $actualFirst = array_column($preview, 'first_group_value_after_current');
    if ($actualFirst !== $expectedFirst) {
        fwrite(STDERR, 'Unexpected VDBE GROUPS value-window preview: ' . json_encode($actualFirst) . PHP_EOL);
        exit(1);
    }

    echo "application-vdbe-window-groups-value-exclude-current-next52 self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
