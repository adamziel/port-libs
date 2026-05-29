<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 235,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next235',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-235-a',
];
$next = [
    'option_id' => 235,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next235',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-235-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceYieldPlan(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next235',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can keep a generated-path json_tree rowid yield tape only while the current-source fingerprint and last yielded rowid match; changed next-source settings restart instead of yielding stale plugin rule rows.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceYield']['yieldTapeOpcode'],
    'currentDeliveredRowids' => $plan['currentGeneratedPathRowidCurrentSourceYield']['deliveredRowids'],
    'currentResumeRowids' => $plan['currentGeneratedPathRowidCurrentSourceYield']['resumeRowids'],
    'currentProjectedValue' => $plan['currentGeneratedPathRowidCurrentSourceYield']['activeProjectedColumns']['value'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceYield']['yieldTapeOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidCurrentSourceYield']['yieldTapeReusable'],
    'replanReasons' => $plan['generatedPathRowidCurrentSourceYieldReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xCurrent yield guards, current-source fingerprints, and rowid resume tapes',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldCurrentSource') {
        fwrite(STDERR, "unexpected next235 current opcode\n");
        exit(1);
    }
    if ($payload['currentDeliveredRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next235 delivered rowids\n");
        exit(1);
    }
    if ($payload['currentResumeRowids'] !== [8]) {
        fwrite(STDERR, "unexpected next235 resume rowids\n");
        exit(1);
    }
    if ($payload['currentProjectedValue'] !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected next235 projected value\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldReprepare') {
        fwrite(STDERR, "unexpected next235 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next235 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-yield-current-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next235 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next235 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
