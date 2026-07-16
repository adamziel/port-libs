<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 205,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next205',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-205-a',
];
$next = [
    'option_id' => 205,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next205',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-205-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasReverseOrder(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
    5,
    9,
    1,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-alias-reverse-order',
    'applicationUse' => 'Copied wp_options JSON diagnostics can satisfy ORDER BY _rowid_ from the pinned generated-path rowid xColumn cache without running a temp sorter.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOrderOpcode' => $plan['currentGeneratedPathRowidAliasOrder205']['orderOpcode'],
    'currentOrderByConsumed' => $plan['currentGeneratedPathRowidAliasOrder205']['orderByConsumed'],
    'currentOrderedRowids' => $plan['currentGeneratedPathRowidAliasOrder205']['orderedRowids'],
    'currentProjectedColumns' => $plan['currentGeneratedPathRowidAliasOrder205']['orderedAliasTape'][0]['projectedColumns'],
    'nextOrderOpcode' => $plan['nextGeneratedPathRowidAliasOrder205']['orderOpcode'],
    'nextOrderReusable' => $plan['nextGeneratedPathRowidAliasOrder205']['orderReusable'],
    'replanReasons' => $plan['next205ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid alias projection and order planning',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOrderOpcode'] !== 'OP_JsonTableRowidAliasOrderReverseNext205') {
        fwrite(STDERR, "unexpected next205 current order opcode\n");
        exit(1);
    }
    if ($payload['currentOrderByConsumed'] !== true) {
        fwrite(STDERR, "unexpected next205 order consumption\n");
        exit(1);
    }
    if ($payload['currentOrderedRowids'] !== [9, 8, 7, 6, 5]) {
        fwrite(STDERR, "unexpected next205 ordered rowids\n");
        exit(1);
    }
    foreach (['rowid', '_rowid_', 'oid'] as $alias) {
        if (($payload['currentProjectedColumns'][$alias] ?? null) !== 9) {
            fwrite(STDERR, "unexpected next205 {$alias} value\n");
            exit(1);
        }
    }
    if ($payload['nextOrderOpcode'] !== 'OP_JsonTableRowidAliasOrderSorterNext205') {
        fwrite(STDERR, "unexpected next205 next order opcode\n");
        exit(1);
    }
    if ($payload['nextOrderReusable'] !== false) {
        fwrite(STDERR, "unexpected next205 next order reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-order-source-changed-next205', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next205 source replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-alias-reverse-order self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
