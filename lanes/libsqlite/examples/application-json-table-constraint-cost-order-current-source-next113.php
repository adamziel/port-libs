<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceConstraintCostOrder(
    'json_each',
    $currentOption,
    $nextOption,
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    'scan_root',
    [['column' => 'key', 'direction' => 'DESC']],
);

$payload = [
    'scenario' => 'application-json-table-constraint-cost-order-current-source-next113',
    'applicationUse' => 'Copied wp_options JSON diagnostics can compare current and next json_each() constraint plans, including whether ORDER BY can stream from rowid order or must allocate a sorter, before import tooling commits changed plugin rules without ext/sqlite.',
    'currentCostClass' => $plan['currentCostOrder']['costClass'],
    'nextCostClass' => $plan['nextCostOrder']['costClass'],
    'currentEffectiveCost' => $plan['currentCostOrder']['effectiveEstimatedCost'],
    'nextEffectiveCost' => $plan['nextCostOrder']['effectiveEstimatedCost'],
    'currentRowOrder' => $plan['currentCostOrder']['rowOrder'],
    'nextRowOrder' => $plan['nextCostOrder']['rowOrder'],
    'replanReasons' => $plan['costOrderReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table planner, residual filtering, and row-array ordering',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentCostClass'] !== 'runnable-json-table-sort-required' || $payload['nextCostClass'] !== 'runnable-json-table-sort-required') {
        fwrite(STDERR, "unexpected JSON table cost class\n");
        exit(1);
    }
    if ($payload['currentRowOrder'] !== [3, 2, 1] || $payload['nextRowOrder'] !== [4, 3, 2, 1]) {
        fwrite(STDERR, "unexpected JSON table row order\n");
        exit(1);
    }
    if (!in_array('json-table-output-order-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing output order replan reason\n");
        exit(1);
    }

    echo "application-json-table-constraint-cost-order-current-source-next113 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
