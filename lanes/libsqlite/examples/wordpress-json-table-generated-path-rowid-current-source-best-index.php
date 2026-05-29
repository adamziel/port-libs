<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 173,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next173',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 173,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next173',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceBestIndexPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42]],
    ],
    'scan_root',
    [['column' => '_rowid_', 'direction' => 'DESC']],
);

$summary = [
    'scenario' => 'wordpress-json-table-generated-path-rowid-cost-current-source-next173',
    'wordpressUse' => 'Copied wp_options plugin-rule previews can keep a current json_tree() xBestIndex plan pinned when generated path and rowid argv constraints remain usable, then force a next-source reprepare when the generated path points at a different rowset.',
    'idxNum' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex173']['idxNum'],
    'idxStr' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex173']['idxStr'],
    'plannerCost' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex173']['plannerCost'],
    'orderedOutputRowids' => $plan['currentGeneratedPathRowidCurrentSourceBestIndex173']['orderedOutputRowids'],
    'nextIdxNum' => $plan['nextGeneratedPathRowidCurrentSourceBestIndex173']['idxNum'],
    'nextIdxStr' => $plan['nextGeneratedPathRowidCurrentSourceBestIndex173']['idxStr'],
    'nextPlannerCost' => $plan['nextGeneratedPathRowidCurrentSourceBestIndex173']['plannerCost'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next173ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, xFilter, and xBestIndex planner metadata helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['idxNum'] === 7);
    assert($summary['idxStr'] === 'generated-path-rowid-current-source-next173|path|rowid|pinned|scan');
    assert($summary['plannerCost'] === 1);
    assert($summary['orderedOutputRowids'] === [6, 5]);
    assert($summary['nextIdxNum'] === 8);
    assert($summary['nextIdxStr'] === 'generated-path-rowid-current-source-next173|reprepare|eof');
    assert($summary['nextPlannerCost'] === 1000000);
    assert(in_array('json-table-generated-path-rowid-bestindex-cost-changed', $summary['replanReasons'], true));
    echo "wordpress-json-table-generated-path-rowid-cost-current-source-next173 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
