<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 234,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next234',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-234-a',
];
$next = [
    'option_id' => 234,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next234',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"security","priority":10}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-234-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidResumePlan(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next234',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can advance a generated-path json_tree rowid cursor with xNext only when the delivered rowid tape and yield-guard fingerprint still match the pinned current source.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidXNextResume']['xNextResumeOpcode'],
    'currentAdvancedRowids' => $plan['currentGeneratedPathRowidXNextResume']['advancedRowids'],
    'currentPendingRowids' => $plan['currentGeneratedPathRowidXNextResume']['pendingRowids'],
    'nextOpcode' => $plan['nextGeneratedPathRowidXNextResume']['xNextResumeOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidXNextResume']['xNextResumeReusable'],
    'replanReasons' => $plan['generatedPathRowidXNextResumeReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid yield guards, rowid aliases, and current-source fingerprints',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidXNextResume') {
        fwrite(STDERR, "unexpected next234 current opcode\n");
        exit(1);
    }
    if ($payload['currentAdvancedRowids'] !== [8]) {
        fwrite(STDERR, "unexpected next234 advanced rowids\n");
        exit(1);
    }
    if ($payload['currentPendingRowids'] !== []) {
        fwrite(STDERR, "unexpected next234 pending rowids\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidXNextReprepare') {
        fwrite(STDERR, "unexpected next234 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next234 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xnext-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next234 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next234 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
