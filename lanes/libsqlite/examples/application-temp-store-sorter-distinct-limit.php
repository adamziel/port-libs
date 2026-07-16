<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTempStoreSorterBTreePlan;

$rows = [
    ['option_id' => 401, 'option_name' => 'theme_mods_parent', 'autoload' => 'yes', 'bucket' => 'theme', 'priority' => 30],
    ['option_id' => 402, 'option_name' => 'theme_mods_child', 'autoload' => 'yes', 'bucket' => 'theme', 'priority' => 20],
    ['option_id' => 403, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 50],
    ['option_id' => 404, 'option_name' => '_transient_update_plugins', 'autoload' => 'no', 'bucket' => 'plugin', 'priority' => 40],
    ['option_id' => 405, 'option_name' => 'network_settings', 'autoload' => 'yes', 'bucket' => 'network', 'priority' => 60],
    ['option_id' => 406, 'option_name' => 'orphaned_import_marker', 'autoload' => null, 'bucket' => null, 'priority' => 70],
];

$plan = SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows(
    $rows,
    [
        ['column' => 'priority', 'direction' => 'DESC'],
        ['column' => 'option_name', 'collation' => 'NOCASE'],
    ],
    ['bucket'],
    limit: 3,
    offset: 1,
    pageSize: 2048,
    memoryThresholdBytes: 128,
);

echo json_encode([
    'scenario' => 'application-temp-store-sorter-distinct-limit',
    'status' => $plan->spilledToTempBTree ? 'spilled' : 'memory',
    'yieldedOptionIds' => array_column($plan->yieldedRows, 'option_id'),
    'yieldedBuckets' => array_map(static fn (array $row): mixed => $row['bucket'], $plan->yieldedRows),
    'distinctRowsSeen' => $plan->distinctRowsSeen,
    'duplicateRowsSkipped' => $plan->duplicateRowsSkipped,
    'tempPageNumbers' => array_keys($plan->pageImages),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
