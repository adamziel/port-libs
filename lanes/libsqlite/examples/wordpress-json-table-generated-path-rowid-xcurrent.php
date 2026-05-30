<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 212,
    'option_name' => 'wp_plugin_generated_path_rowid_xcurrent',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-212-a',
];
$next = [
    'option_id' => 212,
    'option_name' => 'wp_plugin_generated_path_rowid_xcurrent',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-212-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXCurrent(
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
    'scenario' => 'wordpress-json-table-generated-path-rowid-xcurrent',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can pin xCurrent output for a generated-path json_tree rowid range while forcing reprepare when the next source changes generated-path fingerprints.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $plan['currentGeneratedPathRowidXCurrentProfile']['xCurrentOpcode'],
    'activeRowid' => $plan['currentGeneratedPathRowidXCurrentProfile']['activeRowid'],
    'remainingRowids' => $plan['currentGeneratedPathRowidXCurrentProfile']['remainingRowids'],
    'activeProjectedColumns' => $plan['currentGeneratedPathRowidXCurrentProfile']['activeProjectedColumns'],
    'currentCostClass' => $plan['currentGeneratedPathRowidXCurrentProfile']['costClass'],
    'nextOpcode' => $plan['nextGeneratedPathRowidXCurrentProfile']['xCurrentOpcode'],
    'replanReasons' => $plan['generatedPathRowidXCurrentReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid range, alias projection, and current-source fingerprint metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidXCurrent') {
        fwrite(STDERR, "unexpected xcurrent current opcode\n");
        exit(1);
    }
    if ($payload['activeRowid'] !== 7 || $payload['remainingRowids'] !== [8]) {
        fwrite(STDERR, "unexpected xcurrent active rowid state\n");
        exit(1);
    }
    if (($payload['activeProjectedColumns']['value'] ?? null) !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected xcurrent projected value\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidXCurrentReprepare') {
        fwrite(STDERR, "unexpected xcurrent next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xcurrent-source-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing xcurrent source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-xcurrent self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
