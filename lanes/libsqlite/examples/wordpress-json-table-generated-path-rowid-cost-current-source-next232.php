<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 232,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next232',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-232-a',
];
$next = [
    'option_id' => 232,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next232',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"security","priority":10},{"slug":"search","priority":6}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-232-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceBatch(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [7, 8]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    5,
    null,
    3,
    ['rowid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next232',
    'wordpressUse' => 'Copied wp_options JSON diagnostics keep a generated-path json_tree rowid batch pinned to the current source token before next-source rows are admitted.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceBatch232']['batchOpcode'],
    'currentBatchRowids' => $plan['currentGeneratedPathRowidCurrentSourceBatch232']['batchRowids'],
    'currentFullkey' => $plan['currentGeneratedPathRowidCurrentSourceBatch232']['batchProjectedColumns']['fullkey'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceBatch232']['batchOpcode'],
    'replanReasons' => $plan['next232ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses JSON table generated-path rowid xCurrent/yield guards and current-source fingerprints',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidCurrentSourceBatchDeliverNext232') {
        fwrite(STDERR, "unexpected next232 current opcode\n");
        exit(1);
    }
    if ($payload['currentBatchRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next232 batch rowids\n");
        exit(1);
    }
    if ($payload['currentFullkey'] !== '$.rules[2]') {
        fwrite(STDERR, "unexpected next232 fullkey\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidCurrentSourceBatchReprepareNext232') {
        fwrite(STDERR, "unexpected next232 next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-batch-admission-changed-next232', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next232 admission replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next232 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
