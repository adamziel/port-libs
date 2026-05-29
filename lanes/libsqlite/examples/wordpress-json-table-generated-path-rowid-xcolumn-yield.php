<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 190,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next190',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-190-a',
];
$next = [
    'option_id' => 190,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next190',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-190-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXColumnYieldPlan(
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
    null,
    ['id', 'fullkey', 'atom', 'value', 'type'],
    6,
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-xcolumn-yield',
    'wordpressUse' => 'Copied wp_options plugin-rule inspectors can emit a json_tree xColumn row from a generated-path/rowid snapshot only when the pinned current-source generation and final-cost fingerprint still match the materialized row.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentDisposition' => $plan['currentGeneratedPathRowidCurrentSourceYieldRow190']['yieldDisposition'],
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceYieldRow190']['xColumnOpcode'],
    'currentActiveRow' => $plan['currentGeneratedPathRowidCurrentSourceYieldRow190']['activeRow'],
    'currentRemainingRowids' => $plan['currentGeneratedPathRowidCurrentSourceYieldRow190']['remainingRowids'],
    'nextDisposition' => $plan['nextGeneratedPathRowidCurrentSourceYieldRow190']['yieldDisposition'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCurrentSourceYieldRow190']['costClass'],
    'replanReasons' => $plan['next190ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table cursor, generated-path rowid cost, xColumn snapshot, and current-source yield guards',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentDisposition'] !== 'emit-current-source-generated-path-rowid-xcolumn-next190') {
        fwrite(STDERR, "unexpected next190 current disposition\n");
        exit(1);
    }
    if (($payload['currentActiveRow']['fullkey'] ?? null) !== '$.rules[1].priority') {
        fwrite(STDERR, "unexpected next190 active xColumn row\n");
        exit(1);
    }
    if ($payload['currentRemainingRowids'] !== [5]) {
        fwrite(STDERR, "unexpected next190 remaining rowids\n");
        exit(1);
    }
    if ($payload['nextCostClass'] !== 'json-table-generated-path-rowid-xcolumn-stale-source-next190') {
        fwrite(STDERR, "unexpected next190 stale-source cost class\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xcolumn-source-changed-next190', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next190 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-xcolumn-yield self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
