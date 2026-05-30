<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_bestindex',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-217-a',
];
$next = [
    'option_id' => 217,
    'option_name' => 'wp_plugin_generated_path_rowid_bestindex',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-217-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBestIndex(
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
    'scenario' => 'application-json-table-generated-path-rowid-bestindex',
    'applicationUse' => 'Copied wp_options JSON diagnostics can reuse a generated-path json_tree current-source xBestIndex cursor when rowid range argv, order consumption, and xCurrent materialization are all stable.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'idxNum' => $plan['currentGeneratedPathRowidBestIndexProfile']['idxNum'],
    'idxStr' => $plan['currentGeneratedPathRowidBestIndexProfile']['idxStr'],
    'argvColumns' => $plan['currentGeneratedPathRowidBestIndexProfile']['argvColumns'],
    'acceptedRangeRowids' => $plan['currentGeneratedPathRowidBestIndexProfile']['acceptedRangeRowids'],
    'activeRowid' => $plan['currentGeneratedPathRowidBestIndexProfile']['activeRowid'],
    'orderByConsumed' => $plan['currentGeneratedPathRowidBestIndexProfile']['orderByConsumed'],
    'currentOpcode' => $plan['currentGeneratedPathRowidBestIndexProfile']['xBestIndexOpcode'],
    'currentCostClass' => $plan['currentGeneratedPathRowidBestIndexProfile']['costClass'],
    'nextOpcode' => $plan['nextGeneratedPathRowidBestIndexProfile']['xBestIndexOpcode'],
    'replanReasons' => $plan['generatedPathRowidBestIndexReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid range, alias projection, xCurrent, and current-source fingerprint metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidBestIndexCurrentSource') {
        fwrite(STDERR, "unexpected bestindex current opcode\n");
        exit(1);
    }
    if ($payload['idxNum'] !== 63 || $payload['argvColumns'] !== ['json', 'root', 'generated_path', 'rowid_range']) {
        fwrite(STDERR, "unexpected bestindex xBestIndex argv state\n");
        exit(1);
    }
    if ($payload['acceptedRangeRowids'] !== [7, 8] || $payload['activeRowid'] !== 7) {
        fwrite(STDERR, "unexpected bestindex rowid range state\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidBestIndexReprepare') {
        fwrite(STDERR, "unexpected bestindex next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-bestindex-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing bestindex source replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-bestindex self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
