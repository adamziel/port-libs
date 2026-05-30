<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 219,
    'option_name' => 'wp_plugin_generated_path_rowid_limit',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-219-a',
];
$next = [
    'option_id' => 219,
    'option_name' => 'wp_plugin_generated_path_rowid_limit',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-219-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceLimitAdmission(
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
    1,
    null,
    3,
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey'],
);

$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-limit',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can keep a generated-path json_tree rowid cursor on the active xCurrent row when ORDER BY rowid plus LIMIT is already satisfied, while a changed next source forces reprepare.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidLimitAdmissionProfile']['limitOpcode'],
    'currentBoundedRowids' => $plan['currentGeneratedPathRowidLimitAdmissionProfile']['boundedRowids'],
    'currentActiveRowid' => $plan['currentGeneratedPathRowidLimitAdmissionProfile']['activeRowid'],
    'currentEstimatedCost' => $plan['currentGeneratedPathRowidLimitAdmissionProfile']['estimatedCost'],
    'nextOpcode' => $plan['nextGeneratedPathRowidLimitAdmissionProfile']['limitOpcode'],
    'replanReasons' => $plan['generatedPathRowidLimitReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path rowid xCurrent, alias ORDER BY, and LIMIT admission metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidLimitCurrent') {
        fwrite(STDERR, "unexpected limit current opcode\n");
        exit(1);
    }
    if ($payload['currentBoundedRowids'] !== [7]) {
        fwrite(STDERR, "unexpected limit bounded rowids\n");
        exit(1);
    }
    if ($payload['currentActiveRowid'] !== 7) {
        fwrite(STDERR, "unexpected limit active rowid\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidLimitReprepare') {
        fwrite(STDERR, "unexpected limit next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-limit-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing limit source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-limit self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
