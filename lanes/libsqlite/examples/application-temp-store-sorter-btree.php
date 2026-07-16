<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTempStoreSorterBTreePlan;

$rows = [
    ['option_id' => 301, 'option_name' => '_transient_zeta', 'autoload' => 'no', 'option_value' => str_repeat('z', 32)],
    ['option_id' => 302, 'option_name' => 'active_plugins', 'autoload' => 'yes', 'option_value' => 'a:2:{i:0;s:10:"akismet";}'],
    ['option_id' => 303, 'option_name' => 'Active_Plugins', 'autoload' => 'no', 'option_value' => 'case peer'],
    ['option_id' => 304, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'option_value' => str_repeat('update:', 24)],
    ['option_id' => 305, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => 'Example Site'],
    ['option_id' => 306, 'option_name' => 'blogdescription', 'autoload' => 'yes', 'option_value' => 'Just another site'],
];

$plan = SQLiteTempStoreSorterBTreePlan::forRows(
    $rows,
    [
        ['column' => 'option_name', 'collation' => 'NOCASE'],
        ['column' => 'autoload', 'direction' => 'DESC'],
        ['column' => 'option_id'],
    ],
    pageSize: 2048,
    memoryThresholdBytes: 160,
);

echo json_encode([
    'scenario' => 'application-temp-store-sorter-btree',
    'status' => $plan->spilledToTempBTree ? 'spilled' : 'memory',
    'sortedOptionNames' => array_column($plan->sortedRows, 'option_name'),
    'tempPageNumbers' => array_keys($plan->pageImages),
    'runs' => $plan->runs,
    'estimatedMemoryBytes' => $plan->estimatedMemoryBytes,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
