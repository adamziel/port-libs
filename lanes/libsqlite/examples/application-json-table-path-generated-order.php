<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 137,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"enabled":true}',
    'scan_root' => '$.rules',
];
$nextOption = [
    'option_id' => 137,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":6},{"slug":"cache","priority":1},{"slug":"forms","priority":4},{"slug":"shop","priority":5}],"enabled":true}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourcePathGeneratedOrder(
    'json_tree',
    $currentOption,
    $nextOption,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path']],
    [
        ['name' => 'priority', 'path' => '$.priority'],
        ['name' => 'slug', 'path' => '$.slug'],
    ],
);

$payload = [
    'scenario' => 'application-json-table-path-generated-order',
    'applicationUse' => 'Copied wp_options JSON rule diagnostics can keep a path-constrained json_tree() cursor and order the resulting object rows by generated JSON keys such as priority and slug when the next option source changes.',
    'selectedPath' => $plan['currentPathGeneratedOrder']['selectedPathSignature'],
    'currentGeneratedKeys' => $plan['currentPathGeneratedOrder']['rowGeneratedKeys'],
    'nextGeneratedKeys' => $plan['nextPathGeneratedOrder']['rowGeneratedKeys'],
    'currentOrderedRowids' => $plan['currentPathGeneratedOrder']['orderedRowids'],
    'nextOrderedRowids' => $plan['nextPathGeneratedOrder']['orderedRowids'],
    'currentCostClass' => $plan['currentPathGeneratedOrder']['costClass'],
    'nextCostClass' => $plan['nextPathGeneratedOrder']['costClass'],
    'replanReasons' => $plan['next137ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table path pushdown, current-source row materialization, and generated JSON-key ordering helpers',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['selectedPath'] !== '2:path:LIKE:"$.rules%"') {
        fwrite(STDERR, "unexpected JSON table path selection\n");
        exit(1);
    }
    if ($payload['currentOrderedRowids'] !== [1, 7, 4]) {
        fwrite(STDERR, "unexpected current generated order\n");
        exit(1);
    }
    if ($payload['nextOrderedRowids'] !== [4, 7, 10, 1]) {
        fwrite(STDERR, "unexpected next generated order\n");
        exit(1);
    }
    if (!in_array('json-table-path-generated-output-order-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing generated order replan reason\n");
        exit(1);
    }

    echo "application-json-table-path-generated-order self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
