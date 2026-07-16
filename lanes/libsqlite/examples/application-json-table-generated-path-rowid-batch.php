<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 183,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins',
];
$next = [
    'option_id' => 183,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidBatch(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6]],
    ],
    'scan_root',
    [['column' => 'rowid']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidCurrentSourceBatch183']['batchOpcode'] === 'yield-partial-current-source-generated-path-rowid-batch-next183');
    assert($plan['currentGeneratedPathRowidCurrentSourceBatch183']['batchRowids'] === [5]);
    assert($plan['currentGeneratedPathRowidCurrentSourceBatch183']['remainingRowids'] === [6]);
    assert($plan['nextGeneratedPathRowidCurrentSourceBatch183']['batchOpcode'] === 'restart-next-source-generated-path-rowid-batch-next183');
    assert(in_array('json-table-generated-path-rowid-batch-next183-rowset-changed', $plan['next183ReplanReasons'], true));
    echo "application-json-table-generated-path-rowid-batch self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-batch',
    'applicationUse' => 'Copied wp_options active_plugins JSON diagnostics can emit a bounded current-source json_tree batch after generated-path/rowid materialization while preserving the next-source replan fence.',
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceBatch183']['batchOpcode'],
    'currentBatchRowids' => $plan['currentGeneratedPathRowidCurrentSourceBatch183']['batchRowids'],
    'remainingRowids' => $plan['currentGeneratedPathRowidCurrentSourceBatch183']['remainingRowids'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceBatch183']['batchOpcode'],
    'replanReasons' => $plan['next183ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid materialization and current-source planner profiles',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
