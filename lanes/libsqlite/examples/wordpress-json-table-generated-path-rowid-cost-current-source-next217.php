<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next217',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-217-a',
];
$next = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next217',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-217-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext217(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next217',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can reuse a generated-path json_tree current-source xBestIndex cursor when rowid range argv, order consumption, and xCurrent materialization are all stable.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'idxNum' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['idxNum'],
    'idxStr' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['idxStr'],
    'argvColumns' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['argvColumns'],
    'acceptedRangeRowids' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['acceptedRangeRowids'],
    'activeRowid' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['activeRowid'],
    'orderByConsumed' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['orderByConsumed'],
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexOpcode'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex217']['costClass'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceBestIndex217']['xBestIndexOpcode'],
    'replanReasons' => $plan['next217ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid range, alias projection, xCurrent, and current-source fingerprint metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidBestIndexCurrentSourceNext217') {
        fwrite(STDERR, "unexpected next217 current opcode\n");
        exit(1);
    }
    if ($payload['idxNum'] !== 63 || $payload['argvColumns'] !== ['json', 'root', 'generated_path', 'rowid_range']) {
        fwrite(STDERR, "unexpected next217 xBestIndex argv state\n");
        exit(1);
    }
    if ($payload['acceptedRangeRowids'] !== [7, 8] || $payload['activeRowid'] !== 7) {
        fwrite(STDERR, "unexpected next217 rowid range state\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidBestIndexReprepareNext217') {
        fwrite(STDERR, "unexpected next217 next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-bestindex-source-changed-next217', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next217 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next217 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
