<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 184,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next184',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 18401,
];
$next = [
    'option_id' => 184,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next184',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18402,
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidFinalCostPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
    null,
    6,
    ['id', 'fullkey', 'atom', 'value', 'type'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-final-cost',
    'wordpressUse' => 'Copied wp_options plugin-rule diagnostics can admit a final generated-path/rowid JSON table xColumn snapshot only when the current source, rowid aliases, projection, and cost profile are still covering; changed next sources force reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentCostClass' => $plan['currentGeneratedPathRowidFinalCost184']['costClass'],
    'currentSelectedRowids' => $plan['currentGeneratedPathRowidFinalCost184']['selectedRowids'],
    'currentDisposition' => $plan['currentGeneratedPathRowidFinalCost184']['cursorDisposition'],
    'nextCostClass' => $plan['nextGeneratedPathRowidFinalCost184']['costClass'],
    'nextDisposition' => $plan['nextGeneratedPathRowidFinalCost184']['cursorDisposition'],
    'replanReasons' => $plan['next184ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid alias, xColumn snapshot, and current-source cost profiles',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentCostClass'] !== 'json-table-generated-path-rowid-final-cost-covering-point-next184') {
        fwrite(STDERR, "unexpected next184 current cost class\n");
        exit(1);
    }
    if ($payload['currentSelectedRowids'] !== [5]) {
        fwrite(STDERR, "unexpected next184 current selected rowids\n");
        exit(1);
    }
    if ($payload['nextCostClass'] !== 'json-table-generated-path-rowid-final-cost-reprepare-next184') {
        fwrite(STDERR, "unexpected next184 next cost class\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-final-cost-admission-changed-next184', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next184 admission replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-final-cost self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
