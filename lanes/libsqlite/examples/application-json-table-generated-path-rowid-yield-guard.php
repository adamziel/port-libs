<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 187,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next187',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-187-a',
];
$next = [
    'option_id' => 187,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next187',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-187-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldGuardPlan(
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
    'scenario' => 'application-json-table-generated-path-rowid-yield-guard',
    'applicationUse' => 'Copied wp_options plugin-rule diagnostics can continue a generated-path/rowid json_tree yield only when the observed source generation still matches the pinned current source; changed next sources force xBestIndex reprepare before more rows are yielded.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentDisposition' => $plan['currentGeneratedPathRowidYieldGuard187']['yieldDisposition'],
    'currentRemainingRowids' => $plan['currentGeneratedPathRowidYieldGuard187']['remainingRowids'],
    'currentCostClass' => $plan['currentGeneratedPathRowidYieldGuard187']['costClass'],
    'nextDisposition' => $plan['nextGeneratedPathRowidYieldGuard187']['yieldDisposition'],
    'nextCostClass' => $plan['nextGeneratedPathRowidYieldGuard187']['costClass'],
    'replanReasons' => $plan['next187ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid alias, xColumn final-cost, and current-source yield profiles',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentDisposition'] !== 'continue-current-source-generated-path-rowid-yield-next187') {
        fwrite(STDERR, "unexpected next187 current disposition\n");
        exit(1);
    }
    if ($payload['currentRemainingRowids'] !== [5]) {
        fwrite(STDERR, "unexpected next187 remaining rowids\n");
        exit(1);
    }
    if ($payload['nextCostClass'] !== 'json-table-generated-path-rowid-yield-stale-source-next187') {
        fwrite(STDERR, "unexpected next187 stale-source cost class\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-yield-guard-source-changed-next187', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next187 source guard reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-yield-guard self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
