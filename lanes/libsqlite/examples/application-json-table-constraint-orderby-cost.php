<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 124,
    'option_name' => 'wp_plugin_rule_priorities',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 124,
    'option_name' => 'wp_plugin_rule_priorities',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceConstraintOrderByCost(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ],
    'scan_root',
    [['column' => 'key'], ['column' => 'atom', 'direction' => 'DESC']],
);

$payload = [
    'scenario' => 'application-json-table-constraint-orderby-cost',
    'applicationUse' => 'Copied wp_options JSON rule diagnostics can reuse a pushed constant key prefix for ORDER BY key, atom DESC and charge only the suffix block-sort cost while the current json_tree() source remains pinned.',
    'currentPrefix' => $plan['currentPartialOrderCost']['consumedPrefixColumns'],
    'nextPrefix' => $plan['nextPartialOrderCost']['consumedPrefixColumns'],
    'currentSuffix' => $plan['currentPartialOrderCost']['suffixColumns'],
    'nextSuffix' => $plan['nextPartialOrderCost']['suffixColumns'],
    'currentBlockSortPenalty' => $plan['currentPartialOrderCost']['blockSortPenalty'],
    'nextBlockSortPenalty' => $plan['nextPartialOrderCost']['blockSortPenalty'],
    'currentSortSavings' => $plan['currentPartialOrderCost']['sortSavings'],
    'nextSortSavings' => $plan['nextPartialOrderCost']['sortSavings'],
    'currentEffectiveCost' => $plan['currentPartialOrderCost']['effectiveEstimatedCost'],
    'nextEffectiveCost' => $plan['nextPartialOrderCost']['effectiveEstimatedCost'],
    'currentRowOrder' => $plan['currentPartialOrderCost']['rowOrder'],
    'nextRowOrder' => $plan['nextPartialOrderCost']['rowOrder'],
    'replanReasons' => $plan['next124ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table planner, visible constraint coverage, and row-array ordering',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentPrefix'] !== ['key'] || $payload['currentSuffix'] !== ['atom']) {
        fwrite(STDERR, "unexpected partial ORDER BY prefix/suffix\n");
        exit(1);
    }
    if ($payload['currentBlockSortPenalty'] !== 6 || $payload['nextBlockSortPenalty'] !== 8) {
        fwrite(STDERR, "unexpected partial ORDER BY block-sort penalty\n");
        exit(1);
    }
    if ($payload['currentRowOrder'] !== [6, 9, 3] || $payload['nextRowOrder'] !== [6, 12, 9, 3]) {
        fwrite(STDERR, "unexpected JSON table partial ORDER BY row order\n");
        exit(1);
    }
    if (!in_array('json-table-partial-order-cost-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing partial ORDER BY cost replan reason\n");
        exit(1);
    }

    echo "application-json-table-constraint-orderby-cost self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
