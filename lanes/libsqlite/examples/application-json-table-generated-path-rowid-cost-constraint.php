<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 165,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_next165',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 165,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_next165',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostConstraintPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

echo json_encode([
    'option' => $current['option_name'],
    'idxStr' => $plan['currentGeneratedPathRowidCost165']['idxStr'],
    'estimatedRows' => $plan['currentGeneratedPathRowidCost165']['estimatedRows'],
    'estimatedCost' => $plan['currentGeneratedPathRowidCost165']['estimatedCost'],
    'costClass' => $plan['currentGeneratedPathRowidCost165']['costClass'],
    'nextPolicy' => $plan['nextReaderPolicy'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
