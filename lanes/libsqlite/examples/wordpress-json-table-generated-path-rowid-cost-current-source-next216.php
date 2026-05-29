<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 216,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_xnext',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-216-a',
];
$next = [
    'option_id' => 216,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_xnext',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-216-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXNext(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [5, 6, 7, 8, 9]],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [6, 8]],
    ],
    'scan_root',
    [['column' => 'rowid', 'direction' => 'ASC']],
    5,
    null,
    3,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-xnext-current-source',
    'wordpressUse' => 'Copied wp_options JSON rule previews can advance a pinned generated-path rowid json_tree cursor through the current-source range with xNext while changed next-source rows force reprepare instead of reusing stale rowids.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceXNext']['xNextOpcode'],
    'currentNextRowid' => $plan['currentGeneratedPathRowidCurrentSourceXNext']['nextRowid'],
    'currentRemainingAfterAdvance' => $plan['currentGeneratedPathRowidCurrentSourceXNext']['remainingRowidsAfterAdvance'],
    'currentCostClass' => $plan['currentGeneratedPathRowidCurrentSourceXNext']['costClass'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceXNext']['xNextOpcode'],
    'replanReasons' => $plan['generatedPathRowidXNextReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid range, xCurrent, and xNext current-source profiles',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidXNextAdvance') {
        fwrite(STDERR, "unexpected xnext current opcode\n");
        exit(1);
    }
    if ($payload['currentNextRowid'] !== 7) {
        fwrite(STDERR, "unexpected xnext current next rowid\n");
        exit(1);
    }
    if ($payload['currentRemainingAfterAdvance'] !== [8]) {
        fwrite(STDERR, "unexpected xnext remaining rowids\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidXNextReprepare') {
        fwrite(STDERR, "unexpected xnext next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xnext-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing xnext source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-xnext-current-source self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
