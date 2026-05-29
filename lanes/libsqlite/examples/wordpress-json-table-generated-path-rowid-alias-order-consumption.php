<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 206,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next206',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-206-a',
];
$next = [
    'option_id' => 206,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next206',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-206-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasOrderConsumption(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-alias-order-consumption',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can consume ORDER BY rowid aliases from a generated-path json_tree cursor without adding a temp sorter, while source changes force reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidAliasOrder206']['aliasOrderOpcode'],
    'currentOrderByConsumed' => $plan['currentGeneratedPathRowidAliasOrder206']['orderByConsumed'],
    'currentOrderedRowids' => $plan['currentGeneratedPathRowidAliasOrder206']['orderedRowids'],
    'nextOpcode' => $plan['nextGeneratedPathRowidAliasOrder206']['aliasOrderOpcode'],
    'nextOrderByConsumed' => $plan['nextGeneratedPathRowidAliasOrder206']['orderByConsumed'],
    'replanReasons' => $plan['next206ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid alias projection and planner metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableRowidAliasOrderConsumeNext206') {
        fwrite(STDERR, "unexpected next206 current opcode\n");
        exit(1);
    }
    if ($payload['currentOrderByConsumed'] !== true) {
        fwrite(STDERR, "unexpected next206 current order consumption\n");
        exit(1);
    }
    if ($payload['currentOrderedRowids'] !== [9, 8, 7, 6, 5]) {
        fwrite(STDERR, "unexpected next206 rowid order\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableRowidAliasOrderReprepareNext206') {
        fwrite(STDERR, "unexpected next206 next opcode\n");
        exit(1);
    }
    if ($payload['nextOrderByConsumed'] !== false) {
        fwrite(STDERR, "unexpected next206 next order consumption\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-alias-order-source-changed-next206', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next206 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-alias-order-consumption self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
