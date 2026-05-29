<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 207,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next207',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-207-a',
];
$next = [
    'option_id' => 207,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next207',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-207-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasLimit(
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
    2,
    9,
    4,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-alias-limit',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can satisfy ORDER BY rowid DESC LIMIT directly from a generated-path json_tree cursor, bounding imported settings previews without a temp sorter while next-source changes force reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidAliasLimit207']['limitOpcode'],
    'currentBoundedRowids' => $plan['currentGeneratedPathRowidAliasLimit207']['boundedRowids'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidAliasLimit207']['estimatedCost'],
    'nextOpcode' => $plan['nextGeneratedPathRowidAliasLimit207']['limitOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidAliasLimit207']['limitReusable'],
    'replanReasons' => $plan['next207ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid alias order and limit planner metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableRowidAliasLimitTopNext207') {
        fwrite(STDERR, "unexpected next207 current opcode\n");
        exit(1);
    }
    if ($payload['currentBoundedRowids'] !== [9, 8]) {
        fwrite(STDERR, "unexpected next207 bounded rowids\n");
        exit(1);
    }
    if ($payload['currentEstimatedCost'] !== 2) {
        fwrite(STDERR, "unexpected next207 bounded cost\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableRowidAliasLimitReprepareNext207') {
        fwrite(STDERR, "unexpected next207 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next207 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-alias-limit-source-changed-next207', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next207 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-alias-limit self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
