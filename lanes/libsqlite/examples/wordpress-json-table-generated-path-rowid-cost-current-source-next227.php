<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 227,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next227',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-227-a',
];
$next = [
    'option_id' => 227,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next227',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"security","priority":10},{"slug":"search","priority":6}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-227-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceGuard(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next227',
    'wordpressUse' => 'Copied wp_options JSON diagnostics pin a generated-path json_tree rowid cursor to the current source generation and fingerprint before reusing a yielded row.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardOpcode'],
    'currentDeliveredRowids' => $plan['currentGeneratedPathRowidCurrentSourceGuard227']['deliveredRowids'],
    'currentProjectedValue' => $plan['currentGeneratedPathRowidCurrentSourceGuard227']['activeProjectedColumns']['value'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidCurrentSourceGuard227']['currentSourceGuardReusable'],
    'replanReasons' => $plan['next227ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xCurrent/yield guards and source fingerprints',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidCurrentSourceDeliverNext227') {
        fwrite(STDERR, "unexpected next227 current opcode\n");
        exit(1);
    }
    if ($payload['currentDeliveredRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next227 delivered rowids\n");
        exit(1);
    }
    if ($payload['currentProjectedValue'] !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected next227 projected value\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidCurrentSourceReprepareNext227') {
        fwrite(STDERR, "unexpected next227 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next227 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-current-source-generation-changed-next227', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next227 generation replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next227 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
