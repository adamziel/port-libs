<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 208,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next208',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-208-a',
];
$next = [
    'option_id' => 208,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next208',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-208-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext208(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next208',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can reuse the bounded final-cost rowid tape for a generated-path json_tree cursor after ORDER BY/LIMIT, while a changed source forces reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidFinalCost208']['finalCostOpcode'],
    'currentFinalRowids' => $plan['currentGeneratedPathRowidFinalCost208']['finalRowids'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidFinalCost208']['estimatedCost'],
    'nextOpcode' => $plan['nextGeneratedPathRowidFinalCost208']['finalCostOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidFinalCost208']['finalCostReusable'],
    'replanReasons' => $plan['next208ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid alias order and current-source planner metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidFinalCostReuseNext208') {
        fwrite(STDERR, "unexpected next208 current opcode\n");
        exit(1);
    }
    if ($payload['currentFinalRowids'] !== [8, 7]) {
        fwrite(STDERR, "unexpected next208 final rowids\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 2) {
        fwrite(STDERR, "unexpected next208 estimated cost\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidFinalCostReprepareNext208') {
        fwrite(STDERR, "unexpected next208 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next208 next reusability\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-final-cost-source-changed-next208', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next208 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next208 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
