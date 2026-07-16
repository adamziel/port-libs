<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 126,
    'option_name' => 'wp_plugin_rule_lookup',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 126,
    'option_name' => 'wp_plugin_rule_lookup',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourcePathHiddenRowidCost(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$payload = [
    'scenario' => 'application-json-table-path-hidden-rowid-cost',
    'applicationUse' => 'Copied wp_options JSON rule diagnostics can combine a path lookup with hidden rowid aliases so json_tree() keeps a one-row lookup cost while current-source cursors remain pinned.',
    'currentCompositeSignature' => $plan['currentPathHiddenRowidCost']['compositeSignature'],
    'nextCompositeSignature' => $plan['nextPathHiddenRowidCost']['compositeSignature'],
    'currentStrategy' => $plan['currentPathHiddenRowidCost']['scanStrategy'],
    'nextStrategy' => $plan['nextPathHiddenRowidCost']['scanStrategy'],
    'currentEffectiveCost' => $plan['currentPathHiddenRowidCost']['effectiveEstimatedCost'],
    'nextEffectiveCost' => $plan['nextPathHiddenRowidCost']['effectiveEstimatedCost'],
    'currentPathRowidTape' => $plan['currentPathHiddenRowidCost']['pathRowidTape'],
    'nextPathRowidTape' => $plan['nextPathHiddenRowidCost']['pathRowidTape'],
    'replanReasons' => $plan['next126ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table path pushdown, rowid alias normalization, and indexed-cost planning',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentStrategy'] !== 'path-rowid-intersection' || $payload['currentEffectiveCost'] !== 1) {
        fwrite(STDERR, "unexpected JSON table path/rowid cost profile\n");
        exit(1);
    }
    if ($payload['currentPathRowidTape'] !== [['path' => '$.rules[1]', 'rowid' => 6]]) {
        fwrite(STDERR, "unexpected current path/rowid tape\n");
        exit(1);
    }
    if (!in_array('source-json-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing current-source replan reason\n");
        exit(1);
    }

    echo "application-json-table-path-hidden-rowid-cost self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
