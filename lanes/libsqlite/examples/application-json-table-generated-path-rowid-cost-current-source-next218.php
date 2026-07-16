<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 218,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next218',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-218-a',
];
$next = [
    'option_id' => 218,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next218',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-218-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceYield(
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
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next218',
    'applicationUse' => 'Copied wp_options JSON diagnostics can yield xCurrent generated-path rowid output only while the observed current-source generation and fingerprint still match; changed generated paths force reprepare before stale rows reach Application import logic.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'yieldOpcode' => $plan['currentGeneratedPathRowidCurrentSourceYield218']['yieldOpcode'],
    'activeRowid' => $plan['currentGeneratedPathRowidCurrentSourceYield218']['activeRowid'],
    'requestedValues' => $plan['currentGeneratedPathRowidCurrentSourceYield218']['requestedValues'],
    'sourceGenerationMatches' => $plan['currentGeneratedPathRowidCurrentSourceYield218']['sourceGenerationMatches'],
    'sourceFingerprintMatches' => $plan['currentGeneratedPathRowidCurrentSourceYield218']['sourceFingerprintMatches'],
    'nextYieldOpcode' => $plan['nextGeneratedPathRowidCurrentSourceYield218']['yieldOpcode'],
    'replanReasons' => $plan['next218ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid range, alias projection, xCurrent, and source fingerprint metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['yieldOpcode'] !== 'OP_JsonTableGeneratedPathRowidCurrentSourceYieldNext218') {
        fwrite(STDERR, "unexpected next218 current yield opcode\n");
        exit(1);
    }
    if ($payload['activeRowid'] !== 7) {
        fwrite(STDERR, "unexpected next218 active rowid\n");
        exit(1);
    }
    if (($payload['requestedValues']['value'] ?? null) !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected next218 requested value\n");
        exit(1);
    }
    if ($payload['nextYieldOpcode'] !== 'OP_JsonTableGeneratedPathRowidCurrentSourceYieldReprepareNext218') {
        fwrite(STDERR, "unexpected next218 next yield opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-yield-source-changed-next218', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next218 source replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-cost-current-source-next218 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
