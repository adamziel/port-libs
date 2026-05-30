<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 17,
    'option_name' => 'wp_plugin_rule_index',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 17,
    'option_name' => 'wp_plugin_rule_index',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4},{"slug":"shop","priority":5}]}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceIndexedConstraintCost(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
        ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%].priority'],
    ],
    'scan_root',
    [['column' => 'id']],
);

$payload = [
    'scenario' => 'application-json-table-indexed-constraint-cost',
    'applicationUse' => 'Copied wp_options JSON import tooling can compare the current and next json_tree() indexed constraint driver before committing changed plugin rule metadata without ext/sqlite.',
    'currentSelectedConstraint' => $plan['currentIndexedConstraintCost']['selectedSignature'],
    'nextSelectedConstraint' => $plan['nextIndexedConstraintCost']['selectedSignature'],
    'currentCostClass' => $plan['currentIndexedConstraintCost']['costClass'],
    'nextCostClass' => $plan['nextIndexedConstraintCost']['costClass'],
    'currentRowCount' => $plan['currentIndexedConstraintCost']['rowCount'],
    'nextRowCount' => $plan['nextIndexedConstraintCost']['rowCount'],
    'replanReasons' => $plan['next119ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table planning, current-source validation, and row-array filtering',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentSelectedConstraint'] !== '4:fullkey:LIKE:"$.rules[%].priority"') {
        fwrite(STDERR, "unexpected indexed JSON table constraint\n");
        exit(1);
    }
    if ($payload['currentCostClass'] !== 'json-table-indexed-narrow-scan' || $payload['nextCostClass'] !== 'json-table-indexed-narrow-scan') {
        fwrite(STDERR, "unexpected indexed JSON table cost class\n");
        exit(1);
    }
    if ($payload['currentRowCount'] !== 2 || $payload['nextRowCount'] !== 4) {
        fwrite(STDERR, "unexpected indexed JSON table row counts\n");
        exit(1);
    }
    if (!in_array('json-table-indexed-row-count-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing indexed row count replan reason\n");
        exit(1);
    }

    echo "application-json-table-indexed-constraint-cost self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
