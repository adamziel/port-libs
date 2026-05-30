<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 213,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next213',
];
$next = [
    'option_id' => 213,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next213',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumeStatus(
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
    5,
    7,
    1,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode'] === 'OP_JsonTableCurrentSourceResumeAfterRowidNext213');
    assert($plan['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids'] === [8, 9]);
    assert($plan['currentGeneratedPathRowidCurrentSourceResume213']['yieldRowids'] === [8]);
    assert($plan['currentGeneratedPathRowidCurrentSourceResume213']['lastYieldedResumeRowid'] === 8);
    assert($plan['nextGeneratedPathRowidCurrentSourceResume213']['resumeOpcode'] === 'OP_JsonTableCurrentSourceResumeReprepareNext213');
    echo "application-json-table-generated-path-rowid-cost-current-source-next213 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next213',
    'applicationUse' => 'Copied wp_options active_plugins diagnostics can resume a generated-path json_tree rowid scan after the last yielded rowid while a changed next-source JSON image keeps the restart fence explicit.',
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceResume213']['resumeOpcode'],
    'resumeRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume213']['resumeRowids'],
    'yieldRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume213']['yieldRowids'],
    'lastYieldedResumeRowid' => $plan['currentGeneratedPathRowidCurrentSourceResume213']['lastYieldedResumeRowid'],
    'deferredRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume213']['deferredRowids'],
    'estimatedCost' => $plan['currentGeneratedPathRowidCurrentSourceResume213']['estimatedCost'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceResume213']['resumeOpcode'],
    'replanReasons' => $plan['next213ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid range/order profiles',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
