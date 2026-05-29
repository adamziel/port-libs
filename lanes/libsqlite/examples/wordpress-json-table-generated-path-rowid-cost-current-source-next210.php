<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 210,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next210',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-210-a',
];
$next = [
    'option_id' => 210,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next210',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-210-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext210(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 9]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    1,
    null,
    3,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
    1,
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next210',
    'wordpressUse' => 'Copied wp_options plugin-rule inspectors can resume a generated-path json_tree rowid range with OFFSET/LIMIT without re-reading skipped rows when the current-source generation and range fingerprint still match.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidOffsetCost210']['offsetOpcode'],
    'currentCostClass' => $plan['currentGeneratedPathRowidOffsetCost210']['costClass'],
    'skippedRowids' => $plan['currentGeneratedPathRowidOffsetCost210']['skippedOffsetRowids'],
    'yieldRowids' => $plan['currentGeneratedPathRowidOffsetCost210']['yieldRowids'],
    'blockedRowids' => $plan['currentGeneratedPathRowidOffsetCost210']['blockedRowidsAfterLimit'],
    'nextOpcode' => $plan['nextGeneratedPathRowidOffsetCost210']['offsetOpcode'],
    'replanReasons' => $plan['next210ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid range planning and current-source OFFSET/LIMIT cost profiles',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableRowidOffsetSkipSeekNext210') {
        fwrite(STDERR, "unexpected next210 current opcode\n");
        exit(1);
    }
    if ($payload['skippedRowids'] !== [6] || $payload['yieldRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next210 skipped/yield rowids\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableRowidOffsetReprepareNext210') {
        fwrite(STDERR, "unexpected next210 reprepare opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-offset-source-changed-next210', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next210 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next210 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
