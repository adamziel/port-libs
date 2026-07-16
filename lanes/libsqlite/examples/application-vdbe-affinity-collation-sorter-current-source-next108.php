<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteVdbeAffinityCollationSorterSourcePlan;

$currentRows = [
    ['option_id' => 101, 'autoload' => 'yes', 'option_name' => 'Plugin_10', 'priority' => '10', 'site_id' => 1],
    ['option_id' => 102, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'site_id' => 1],
    ['option_id' => 103, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => null, 'site_id' => 1],
    ['option_id' => 104, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => '1', 'site_id' => 1],
    ['option_id' => 105, 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'site_id' => 1],
    ['option_id' => 106, 'autoload' => 'YES', 'option_name' => 'plugin_02', 'priority' => '02', 'site_id' => 1],
    ['option_id' => 107, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2.0', 'site_id' => 2],
    ['option_id' => 108, 'autoload' => 'no', 'option_name' => 'Cache', 'priority' => '1', 'site_id' => 2],
];

$nextRows = [
    ['option_id' => 101, 'autoload' => 'yes', 'option_name' => 'Plugin_10', 'priority' => '01', 'site_id' => 1],
    ['option_id' => 102, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'site_id' => 1],
    ['option_id' => 104, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => '1', 'site_id' => 1],
    ['option_id' => 105, 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'site_id' => 1],
    ['option_id' => 106, 'autoload' => 'YES', 'option_name' => 'plugin_02', 'priority' => '02', 'site_id' => 1],
    ['option_id' => 107, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2.0', 'site_id' => 2],
    ['option_id' => 108, 'autoload' => 'no', 'option_name' => 'Cache', 'priority' => '1', 'site_id' => 2],
    ['option_id' => 109, 'autoload' => 'yes', 'option_name' => 'plugin_2', 'priority' => '2', 'site_id' => 9],
];

$plan = SQLiteVdbeAffinityCollationSorterSourcePlan::compareSources(
    $currentRows,
    $nextRows,
    ['autoload', 'priority', 'option_name', 'site_id'],
    'option_id',
    'GCGD',
    ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'],
    [false, false, false, true],
    ['LAST', 'LAST', 'LAST', null],
);

echo json_encode([
    'scenario' => 'application-vdbe-affinity-collation-sorter-current-source-next108',
    'currentOrder' => $plan['currentOrder'],
    'nextOrder' => $plan['nextOrder'],
    'inserted' => $plan['inserted'],
    'deleted' => $plan['deleted'],
    'moved' => $plan['moved'],
    'stableTieIds' => $plan['stableTieIds'],
    'changed' => $plan['changed'],
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
