<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 180,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
    'source_generation' => 'current-active-plugins',
];
$next = [
    'option_id' => 180,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
    'source_generation' => 'next-active-plugins',
];

$plan = SQLiteJsonTablePlan::generatedPathRowidMaterializationPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => '_rowid_', 'operator' => '=', 'value' => '6'],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode'] === 'materialize-current-source-covered-rowset-next180');
    assert($plan['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids'] === [6]);
    assert($plan['nextGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode'] === 'materialize-reset-stale-current-source-next180');
    assert(in_array('json-table-generated-path-rowid-materialization-next180-rowset-changed', $plan['next180ReplanReasons'], true));
    echo "application-json-table-generated-path-rowid-materialization self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-json-table-generated-path-rowid-materialization',
    'applicationUse' => 'Copied wp_options active_plugins JSON diagnostics can drain a pinned generated-path/rowid json_tree xFilter program into current-source materialized rows while forcing changed next-source rowsets through reprepare.',
    'currentOpcode' => $plan['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode'],
    'nextOpcode' => $plan['nextGeneratedPathRowidCurrentSourceMaterialization180']['materializationOpcode'],
    'materializedRowids' => $plan['currentGeneratedPathRowidCurrentSourceMaterialization180']['materializedRowids'],
    'replanReasons' => $plan['next180ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path, rowid, xBestIndex, xFilter, and current-source planner profiles',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
