<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 224,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next224',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-224-a',
];
$next = [
    'option_id' => 224,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next224',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-224-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXCurrentYieldGuard(
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
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next224',
    'applicationUse' => 'Copied wp_options JSON diagnostics can resume a generated-path json_tree rowid cursor only when the xCurrent fingerprint and active rowid still match the current source; changed next-source settings restart instead of reusing stale projected values.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidXCurrentYieldGuard224']['yieldGuardOpcode'],
    'currentDeliveredRowids' => $plan['currentGeneratedPathRowidXCurrentYieldGuard224']['deliveredRowids'],
    'currentProjectedValue' => $plan['currentGeneratedPathRowidXCurrentYieldGuard224']['activeProjectedColumns']['value'],
    'nextOpcode' => $plan['nextGeneratedPathRowidXCurrentYieldGuard224']['yieldGuardOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidXCurrentYieldGuard224']['yieldGuardReusable'],
    'replanReasons' => $plan['next224ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xCurrent profiles, rowid aliases, and current-source fingerprints',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldGuardDeliverNext224') {
        fwrite(STDERR, "unexpected next224 current opcode\n");
        exit(1);
    }
    if ($payload['currentDeliveredRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next224 delivered rowids\n");
        exit(1);
    }
    if ($payload['currentProjectedValue'] !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected next224 projected value\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldGuardReprepareNext224') {
        fwrite(STDERR, "unexpected next224 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next224 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-yield-guard-source-changed-next224', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next224 source replan reason\n");
        exit(1);
    }

    echo "application-json-table-generated-path-rowid-cost-current-source-next224 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
