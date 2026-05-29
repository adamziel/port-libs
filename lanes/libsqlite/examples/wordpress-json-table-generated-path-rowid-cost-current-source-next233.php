<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 233,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next233',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-233-a',
];
$next = [
    'option_id' => 233,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next233',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'next-233-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidYieldNextPlan(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next233',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can advance a generated-path json_tree rowid cursor from the already-yielded xCurrent row only when the yield guard and source fingerprint remain valid; changed next-source settings restart instead of yielding stale rows.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidYieldNext233']['yieldNextOpcode'],
    'currentDeliveredRowids' => $plan['currentGeneratedPathRowidYieldNext233']['deliveredRowids'],
    'currentResumeRowids' => $plan['currentGeneratedPathRowidYieldNext233']['resumeRowids'],
    'currentNextRowid' => $plan['currentGeneratedPathRowidYieldNext233']['nextRowid'],
    'nextOpcode' => $plan['nextGeneratedPathRowidYieldNext233']['yieldNextOpcode'],
    'nextReusable' => $plan['nextGeneratedPathRowidYieldNext233']['yieldNextReusable'],
    'replanReasons' => $plan['next233ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xCurrent yield guards and source fingerprints',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldNextNext233') {
        fwrite(STDERR, "unexpected next233 current opcode\n");
        exit(1);
    }
    if ($payload['currentDeliveredRowids'] !== [7]) {
        fwrite(STDERR, "unexpected next233 delivered rowids\n");
        exit(1);
    }
    if ($payload['currentResumeRowids'] !== [8]) {
        fwrite(STDERR, "unexpected next233 resume rowids\n");
        exit(1);
    }
    if ($payload['currentNextRowid'] !== 8) {
        fwrite(STDERR, "unexpected next233 next rowid\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidYieldNextReprepareNext233') {
        fwrite(STDERR, "unexpected next233 next opcode\n");
        exit(1);
    }
    if ($payload['nextReusable'] !== false) {
        fwrite(STDERR, "unexpected next233 next reuse\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-yield-next-source-changed-next233', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next233 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next233 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
