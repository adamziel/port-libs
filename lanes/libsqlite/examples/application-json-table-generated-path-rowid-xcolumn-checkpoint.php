<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 196,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next196',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-196-a',
];
$next = [
    'option_id' => 196,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next196',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-196-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXColumnCheckpoint(
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
    ['key', 'value', 'type', 'id', 'fullkey', 'path'],
);

$payload = [
    'scenario' => 'application-json-table-generated-path-rowid-xcolumn-checkpoint',
    'applicationUse' => 'Copied wp_options JSON inspectors can reuse a generated-path rowid xColumn cache only while the pinned current-source xFilter checkpoint remains valid.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidXColumnCache196']['xColumnOpcode'],
    'currentCachedColumns' => $plan['currentGeneratedPathRowidXColumnCache196']['xColumnTape'][0]['columns'],
    'nextOpcode' => $plan['nextGeneratedPathRowidXColumnCache196']['xColumnOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidXColumnCache196']['cacheReusable'],
    'replanReasons' => $plan['next196ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xFilter checkpoints and xColumn projection materialization',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableXColumnCacheRangeNext196') {
        fwrite(STDERR, "unexpected next196 current opcode\n");
        exit(1);
    }
    if (($payload['currentCachedColumns']['fullkey'] ?? null) !== '$.rules[2].priority') {
        fwrite(STDERR, "unexpected next196 cached fullkey\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableXColumnCacheRestartNext196') {
        fwrite(STDERR, "unexpected next196 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next196 next cache reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xcolumn-cache-source-changed-next196', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next196 source replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-xcolumn-checkpoint self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
