<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOption = [
    'option_id' => 159,
    'option_name' => 'wp_plugin_generated_path_rowid_seek_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules',
];
$nextOption = [
    'option_id' => 159,
    'option_name' => 'wp_plugin_generated_path_rowid_seek_cost',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan(
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
    [['column' => 'path'], ['column' => 'id']],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-seek-cost-current-source-next159',
    'wordpressUse' => 'Copied wp_options JSON rule diagnostics can keep a generated path cursor pinned while xBestIndex-style rowid IN seeks are costed against the current and next JSON table row tape.',
    'currentSeekRowids' => $plan['currentGeneratedPathRowidSeekCost']['seekRowids'],
    'currentMatchedSeekRowids' => $plan['currentGeneratedPathRowidSeekCost']['matchedSeekRowids'],
    'currentMissingSeekRowids' => $plan['currentGeneratedPathRowidSeekCost']['missingSeekRowids'],
    'nextMatchedSeekRowids' => $plan['nextGeneratedPathRowidSeekCost']['matchedSeekRowids'],
    'currentCostClass' => $plan['currentGeneratedPathRowidSeekCost']['costClass'],
    'nextCostClass' => $plan['nextGeneratedPathRowidSeekCost']['costClass'],
    'replanReasons' => $plan['next159ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path and rowid-cost planners',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentSeekRowids'] !== [5, 6, 42]) {
        fwrite(STDERR, "unexpected rowid seek set\n");
        exit(1);
    }
    if ($payload['currentMatchedSeekRowids'] !== [5, 6]) {
        fwrite(STDERR, "unexpected current seek hits\n");
        exit(1);
    }
    if ($payload['currentMissingSeekRowids'] !== [42]) {
        fwrite(STDERR, "unexpected current seek misses\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-seek-rowset-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing rowid seek replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-seek-cost-current-source-next159 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
