<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = '{"plugin":{"enabled":true,"rules":[{"name":"seo"},{"name":"cache"}]},"priority":7}';

$matchingRows = SQLiteJsonTablePlan::projectedRows('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'text'],
], ['rowid', 'key', 'atom', 'root']);

$conflictingRows = SQLiteJsonTablePlan::filteredRows('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin'],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'array'],
]);

$duplicateJsonRows = SQLiteJsonTablePlan::filteredRows('json_each', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'json', 'operator' => '=', 'value' => '{"plugin":{"enabled":false}}'],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin'],
]);

$plan = SQLiteJsonTablePlan::plan('json_tree', [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin'],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'array'],
]);

echo json_encode([
    'matchingDuplicateRootRows' => $matchingRows,
    'conflictingDuplicateRootCount' => count($conflictingRows),
    'conflictingDuplicateJsonCount' => count($duplicateJsonRows),
    'planner' => [
        'arguments' => $plan['arguments'],
        'usedColumns' => array_column($plan['used'], 'column'),
        'residualColumns' => array_column($plan['residual'], 'column'),
        'residualValues' => array_column($plan['residual'], 'value'),
    ],
    'applicationUse' => 'Preview copied wp_options JSON table scans where repeated hidden json/root predicates come from composed query builders: the first usable hidden equality drives json_each/json_tree arguments and later duplicates remain residual filters, so conflicting roots/json inputs do not silently retarget expansion.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
