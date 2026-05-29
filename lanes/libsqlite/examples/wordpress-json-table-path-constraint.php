<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 23,
    'option_name' => 'wp_plugin_path_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 23,
    'option_name' => 'wp_plugin_path_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$payload = [
    'scenario' => 'wordpress-json-table-path-constraint',
    'wordpressUse' => 'Copied wp_options JSON import tooling can push path-qualified json_tree() constraints into the current-source planner before comparing old and next plugin rule payloads without ext/sqlite.',
    'currentPathSignature' => $plan['currentPathConstraint']['selectedPathSignature'],
    'nextPathSignature' => $plan['nextPathConstraint']['selectedPathSignature'],
    'currentPathRowCount' => $plan['currentPathConstraint']['pathRowCount'],
    'nextPathRowCount' => $plan['nextPathConstraint']['pathRowCount'],
    'currentPathTape' => $plan['currentPathConstraint']['pathTape'],
    'nextPathTape' => $plan['nextPathConstraint']['pathTape'],
    'replanReasons' => $plan['next123ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table planning, current-source validation, indexed visible constraint costs, and row-array filtering',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentPathSignature'] !== '2:path:LIKE:"$.rules%"') {
        fwrite(STDERR, "unexpected path constraint signature\n");
        exit(1);
    }
    if ($payload['currentPathRowCount'] !== 4 || $payload['nextPathRowCount'] !== 6) {
        fwrite(STDERR, "unexpected path row counts\n");
        exit(1);
    }
    if (!in_array('json-table-path-tape-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing path tape replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-path-constraint self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
