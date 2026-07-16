<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan;

require __DIR__ . '/../../../tools/bootstrap.php';

$currentRows = [
    ['rowid' => 1, 'site' => '1', 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10', 'bytes' => 100, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'autoload' => 'YES', 'option_name' => 'plugin_2', 'priority' => '2', 'bytes' => 20, 'include' => 1],
    ['rowid' => 3, 'site' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_02', 'priority' => '02', 'bytes' => 30, 'include' => 0],
];
$nextRows = [
    ['rowid' => 1, 'site' => '1', 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '01', 'bytes' => 100, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'autoload' => 'YES', 'option_name' => 'plugin_2', 'priority' => '2', 'bytes' => 20, 'include' => 1],
    ['rowid' => 3, 'site' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_02', 'priority' => '02', 'bytes' => 30, 'include' => 0],
    ['rowid' => 4, 'site' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'bytes' => 50, 'include' => 1],
];

$plan = SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan::compare(
    $currentRows,
    $nextRows,
    ['site', 'autoload', 'priority', 'option_name'],
    'rowid',
    [
        'sortAffinities' => ['NUMERIC', 'TEXT', 'NUMERIC', 'TEXT'],
        'sortCollations' => ['BINARY', 'NOCASE', 'BINARY', 'RTRIM'],
        'sortNulls' => [null, 'LAST', 'LAST', null],
        'valueColumn' => 'bytes',
        'partitionColumns' => ['site'],
        'partitionAffinities' => ['NUMERIC'],
        'orderColumns' => ['autoload', 'priority', 'option_name'],
        'orderAffinities' => ['TEXT', 'NUMERIC', 'TEXT'],
        'orderCollations' => ['NOCASE', 'BINARY', 'RTRIM'],
        'filterColumn' => 'include',
        'frameUnit' => 'GROUPS',
        'exclude' => 'CURRENT ROW',
        'following' => 1,
        'separator' => '|',
    ]
);

$result = [
    'scenario' => 'application-vdbe-window-sorter-affinity-current-source-next',
    'applicationUse' => 'Copied wp_options import previews can read VDBE sorter current rows, window frames, and the next sorter row in the same loop before advancing OP_SorterNext, preserving SQLite affinity/collation behavior without ext/sqlite.',
    'status' => $plan['status'],
    'nextOrder' => $plan['nextOrder'],
    'nextLoopFrames' => array_map(
        static fn (array $row): array => ['rowid' => $row['currentRowid'], 'nextRowid' => $row['nextRowid'], 'frameRowids' => $row['frameRowids'], 'groupConcat' => $row['groupConcat']],
        $plan['nextLoop']
    ),
    'dependency' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if ($result['status'] !== 'window-sorter-affinity-current-source-next-changed') {
        fwrite(STDERR, "unexpected VDBE sorter/window status\n");
        exit(1);
    }
    if ($result['nextOrder'] !== [1, 3, 2, 4]) {
        fwrite(STDERR, "unexpected next sorter order\n");
        exit(1);
    }
    if (($result['nextLoopFrames'][2]['groupConcat'] ?? null) !== '100') {
        fwrite(STDERR, "unexpected inserted peer window frame\n");
        exit(1);
    }
    echo "application-vdbe-window-sorter-affinity-current-source-next self-test passed\n";
}

return $result;
