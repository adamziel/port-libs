<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 42,
    'option_name' => 'wp_plugin_generated_path_rowid_final_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-final-cost-a',
];
$next = [
    'option_id' => 42,
    'option_name' => 'wp_plugin_generated_path_rowid_final_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-final-cost-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidFinalCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'DESC']],
    5,
    9,
    2,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-final-cost',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can reuse the bounded final-cost rowid tape for a generated-path json_tree cursor after ORDER BY/LIMIT, while a changed source forces reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidFinalCost']['finalCostOpcode'],
    'currentFinalRowids' => $plan['currentGeneratedPathRowidFinalCost']['finalRowids'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidFinalCost']['estimatedCost'],
    'nextOpcode' => $plan['nextGeneratedPathRowidFinalCost']['finalCostOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidFinalCost']['finalCostReusable'],
    'replanReasons' => $plan['generatedPathRowidFinalCostReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid alias order and current-source planner metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidFinalCostReuse') {
        fwrite(STDERR, "unexpected final cost current opcode\n");
        exit(1);
    }
    if ($payload['currentFinalRowids'] !== [9, 8, 7, 6, 5]) {
        fwrite(STDERR, "unexpected final cost final rowids\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 5) {
        fwrite(STDERR, "unexpected final cost estimated cost\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidFinalCostReprepare') {
        fwrite(STDERR, "unexpected final cost next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected final cost next reusability\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-final-cost-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing final cost source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-final-cost self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
