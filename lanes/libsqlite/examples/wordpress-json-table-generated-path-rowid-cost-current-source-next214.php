<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 214,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next214',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}]}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-214-a',
];
$next = [
    'option_id' => 214,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next214',
    'option_value' => '{"rules":[{"slug":"seo","priority":3}]}',
    'generated_path' => '$.rules[0]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-214-b',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext214(
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
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey', 'parent'],
    ['rowid', '_rowid_', 'oid', 'value', 'type', 'fullkey', 'parent'],
);

$xColumn = $plan['currentGeneratedPathRowidCurrentSourceXColumn214'];
$payload = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next214',
    'wordpressUse' => 'Copied wp_options JSON diagnostics can satisfy repeated json_tree xColumn reads from the active generated-path rowid current cursor without rebuilding the next-source cursor.',
    'currentReaderPolicy' => $plan['currentReaderPolicy'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'currentOpcode' => $xColumn['xColumnOpcode'],
    'activeRowid' => $xColumn['activeRowid'],
    'columnReads' => $xColumn['columnReads'],
    'cacheHitCount' => $xColumn['cacheHitCount'],
    'aliasReadCount' => $xColumn['aliasReadCount'],
    'currentCostClass' => $xColumn['costClass'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceXColumn214']['xColumnOpcode'],
    'replanReasons' => $plan['next214ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid range, alias projection, xCurrent, and projection-cache metadata',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($payload['currentOpcode'] !== 'OP_JsonTableGeneratedPathRowidXColumnCacheNext214') {
        fwrite(STDERR, "unexpected next214 current opcode\n");
        exit(1);
    }
    if ($payload['activeRowid'] !== 7 || $payload['cacheHitCount'] !== 7 || $payload['aliasReadCount'] !== 3) {
        fwrite(STDERR, "unexpected next214 xColumn cache state\n");
        exit(1);
    }
    if (($payload['columnReads'][3]['value'] ?? null) !== '{"slug":"forms","priority":4}') {
        fwrite(STDERR, "unexpected next214 value read\n");
        exit(1);
    }
    if ($payload['nextOpcode'] !== 'OP_JsonTableGeneratedPathRowidXColumnReprepareNext214') {
        fwrite(STDERR, "unexpected next214 next opcode\n");
        exit(1);
    }
    if (!in_array('json-table-generated-path-rowid-xcolumn-source-changed-next214', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing next214 source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next214 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
