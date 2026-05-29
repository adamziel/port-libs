<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 215,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next215',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-215-a',
];
$next = [
    'option_id' => 215,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next215',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-215-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext215(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    5,
    null,
    3,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next215',
    'wordpressUse' => 'Copied wp_options plugin-rule JSON diagnostics can yield an active generated-path json_tree rowid while preserving the next resume rowid and forcing reprepare when the source generation changes.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentYieldOpcode' => $plan['currentGeneratedPathRowidYieldCost215']['yieldOpcode'],
    'emittedRowid' => $plan['currentGeneratedPathRowidYieldCost215']['emittedRowid'],
    'resumeRowid' => $plan['currentGeneratedPathRowidYieldCost215']['resumeRowid'],
    'cursorEofAfterYield' => $plan['currentGeneratedPathRowidYieldCost215']['cursorEofAfterYield'],
    'activeProjectedColumns' => $plan['currentGeneratedPathRowidYieldCost215']['activeProjectedColumns'],
    'currentCostClass' => $plan['currentGeneratedPathRowidYieldCost215']['costClass'],
    'nextYieldOpcode' => $plan['nextGeneratedPathRowidYieldCost215']['yieldOpcode'],
    'replanReasons' => $plan['next215ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xCurrent, range, alias projection, and source-fingerprint metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentYieldOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldContinueNext215') {
        fwrite(STDERR, "unexpected next215 current yield opcode\n");
        exit(1);
    }
    if ($payload['emittedRowid'] !== 7 || $payload['resumeRowid'] !== 8) {
        fwrite(STDERR, "unexpected next215 rowid yield state\n");
        exit(1);
    }
    if (($payload['activeProjectedColumns']['value'] ?? null) !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected next215 projected value\n");
        exit(1);
    }
    if ($payload['nextYieldOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldReprepareNext215') {
        fwrite(STDERR, "unexpected next215 next yield opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-yield-source-changed-next215', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next215 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next215 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
