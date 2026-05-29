<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentRows = [
    ['option_id' => 1, 'site' => '1', 'autoload' => 'yes', 'option_name' => 'Plugin_A ', 'priority' => '02', 'bytes' => 20, 'enabled' => 1],
    ['option_id' => 2, 'site' => 1, 'autoload' => 'YES', 'option_name' => 'plugin_a', 'priority' => 2, 'bytes' => 10, 'enabled' => '1'],
    ['option_id' => 3, 'site' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_b', 'priority' => '10', 'bytes' => 30, 'enabled' => 0],
    ['option_id' => 4, 'site' => 1, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => null, 'bytes' => 5, 'enabled' => 1],
    ['option_id' => 5, 'site' => '01', 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'bytes' => 40, 'enabled' => 1],
    ['option_id' => 6, 'site' => 2, 'autoload' => 'yes', 'option_name' => 'network', 'priority' => '1', 'bytes' => 12, 'enabled' => 1],
    ['option_id' => 7, 'site' => 2, 'autoload' => 'YES', 'option_name' => 'network ', 'priority' => 1, 'bytes' => null, 'enabled' => 1],
    ['option_id' => 8, 'site' => 2, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => '4', 'bytes' => 8, 'enabled' => '0'],
];

$nextRows = $currentRows;
$nextRows[0]['priority'] = '20';
unset($nextRows[2]);
$nextRows = array_values($nextRows);
$nextRows[] = ['option_id' => 9, 'site' => 2, 'autoload' => 'yes', 'option_name' => 'network', 'priority' => '1.0', 'bytes' => 18, 'enabled' => 1];

$plan = SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan::compareWindowSources(
    $currentRows,
    $nextRows,
    'option_id',
    'bytes',
    ['site'],
    ['autoload', 'priority', 'option_name'],
    'enabled',
    1,
    1,
    'C',
    ['BINARY'],
    'GCG',
    ['NOCASE', 'BINARY', 'RTRIM'],
    [false, false, false],
    ['LAST', 'LAST', 'LAST']
);

$summary = [
    'scenario' => 'wordpress-vdbe-affinity-sorter-window-current-source-next',
    'wordpressUse' => 'Copied wp_options imports can compare current and next VDBE sorter/window sources with SQLite affinity, collation, FILTER, and non-advancing current/next frame summaries before replacing option rows.',
    'status' => $plan['status'],
    'currentOrder' => $plan['currentOrder'],
    'nextOrder' => $plan['nextOrder'],
    'inserted' => $plan['inserted'],
    'deleted' => $plan['deleted'],
    'movedCount' => count($plan['moved']),
    'changed' => $plan['changed'],
    'currentFalseFilterFrame' => $plan['currentWindows'][3]['filteredFrameRowids'],
    'nextInsertedWindow' => [
        'rowid' => $plan['nextWindows'][7]['rowid'],
        'frameRowids' => $plan['nextWindows'][7]['frameRowids'],
        'groupConcat' => $plan['nextWindows'][7]['groupConcat'],
    ],
    'dependencyClosure' => 'no new support component needed; reuses native VDBE sorter, affinity comparison, collation, and window current/next cursor helpers',
];

if (($summary['status'] ?? null) !== 'vdbe-affinity-sorter-window-current-source-next-ready'
    || $summary['inserted'] !== [9]
    || $summary['deleted'] !== [3]
    || $summary['nextInsertedWindow']['groupConcat'] !== '18'
) {
    fwrite(STDERR, "wordpress-vdbe-affinity-sorter-window-current-source-next self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
