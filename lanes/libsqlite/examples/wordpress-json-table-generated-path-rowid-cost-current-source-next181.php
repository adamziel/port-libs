<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 181,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next181',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 17,
];
$nextOption = [
    'option_id' => 181,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next181',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 18,
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext181(
    'json_tree',
    $currentOption,
    $nextOption,
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next181',
    'wordpressUse' => 'Copied wp_options JSON rule diagnostics can resume a generated-path rowid scan and materialize xColumn values from the pinned current-source snapshot while a next source waits for reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentCostClass' => $plan['currentGeneratedPathRowidXColumnSnapshot181']['costClass'],
    'currentMaterializedRows' => $plan['currentGeneratedPathRowidXColumnSnapshot181']['materializedRows'],
    'nextCostClass' => $plan['nextGeneratedPathRowidXColumnSnapshot181']['costClass'],
    'replanReasons' => $plan['next181ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON tree rows plus generated-path rowid current-source cache/yield profiles',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentReaderPolicy'] !== 'materialize-current-json-table-generated-path-rowid-xcolumn-next181') {
        fwrite(STDERR, "unexpected next181 current reader policy\n");
        exit(1);
    }
    if (($payload['currentMaterializedRows'][0]['fullkey'] ?? null) !== '$.rules[1].slug') {
        fwrite(STDERR, "unexpected next181 materialized fullkey\n");
        exit(1);
    }
    if (($payload['currentMaterializedRows'][0]['atom'] ?? null) !== 'cache') {
        fwrite(STDERR, "unexpected next181 materialized atom\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xcolumn-source-snapshot-changed-next181', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next181 source snapshot replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next181 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
