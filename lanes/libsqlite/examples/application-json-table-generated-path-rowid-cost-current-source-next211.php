<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 211,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins-next211',
];
$next = [
    'option_id' => 211,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins-next211',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumeCursor(
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
    assert($plan['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode'] === 'OP_JsonTableCurrentSourceResumeAfterRowidNext211');
    assert($plan['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids'] === [8, 9]);
    assert($plan['currentGeneratedPathRowidCurrentSourceResume211']['yieldRowids'] === [8]);
    assert($plan['nextGeneratedPathRowidCurrentSourceResume211']['resumeOpcode'] === 'OP_JsonTableCurrentSourceResumeReprepareNext211');
    echo "application-json-table-generated-path-rowid-cost-current-source-next211 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next211',
    'applicationUse' => 'Copied wp_options active_plugins diagnostics can resume a generated-path json_tree rowid scan after the last yielded rowid while a changed next-source JSON image keeps the restart fence explicit.',
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceResume211']['resumeOpcode'],
    'resumeRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume211']['resumeRowids'],
    'yieldRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume211']['yieldRowids'],
    'deferredRowids' => $plan['currentGeneratedPathRowidCurrentSourceResume211']['deferredRowids'],
    'estimatedCost' => $plan['currentGeneratedPathRowidCurrentSourceResume211']['estimatedCost'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceResume211']['resumeOpcode'],
    'replanReasons' => $plan['next211ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid range/order profiles',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
