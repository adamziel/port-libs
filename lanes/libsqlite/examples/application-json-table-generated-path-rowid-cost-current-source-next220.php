<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 220,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next220',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4},{"slug":"security","priority":9}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
    'source_generation' => 'current-220-a',
];
$next = array_replace($current, [
    'option_value' => '{"rules":[{"slug":"seo","priority":3}],"meta":{"autoload":"no"}}',
    'generated_path' => '$.rules[0]',
    'source_generation' => 'next-220-b',
]);

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidXRowid(
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

$summary = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next220',
    'applicationUse' => 'Copied wp_options plugin-rule diagnostics can read json_tree xRowid from a pinned generated-path row while preserving rowid/_rowid_/oid alias agreement and forcing a reprepare when the next source changes.',
    'currentRowid' => $plan['currentGeneratedPathRowidXRowid220']['rowidValue'],
    'currentOpcode' => $plan['currentGeneratedPathRowidXRowid220']['xRowidOpcode'],
    'nextOpcode' => $plan['nextGeneratedPathRowidXRowid220']['xRowidOpcode'],
    'aliasConsistent' => $plan['currentGeneratedPathRowidXRowid220']['aliasConsistent'],
    'replanReasons' => $plan['next220ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path current-source rowid alias materialization',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentRowid'] === 7);
    assert($summary['currentOpcode'] === 'OP_JsonTableGeneratedPathRowidXRowidNext220');
    assert($summary['nextOpcode'] === 'OP_JsonTableGeneratedPathRowidXRowidReprepareNext220');
    assert($summary['aliasConsistent'] === true);
    assert(in_array('json-table-generated-path-rowid-xrowid-alias-changed-next220', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next220 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
