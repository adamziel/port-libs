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
    'option_id' => 167,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next167',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 167,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next167',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCurrentSourceFilterPlan(
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
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next167',
    'applicationUse' => 'Copied wp_options plugin-rule previews can bind generated path and rowid argv into a pinned json_tree() xFilter cursor, then force a fresh next-source filter when imported JSON changes.',
    'filterOpcode' => $plan['currentGeneratedPathRowidCurrentSourceFilter']['filterOpcode'],
    'argvColumns' => $plan['currentGeneratedPathRowidCurrentSourceFilter']['argvColumns'],
    'argvValues' => $plan['currentGeneratedPathRowidCurrentSourceFilter']['argvValues'],
    'orderedOutputRowids' => $plan['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputRowids'],
    'orderedOutputPaths' => $plan['currentGeneratedPathRowidCurrentSourceFilter']['orderedOutputPaths'],
    'nextFilterOpcode' => $plan['nextGeneratedPathRowidCurrentSourceFilter']['filterOpcode'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next167ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, ORDER, and xFilter planner metadata helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['filterOpcode'] === 'xFilter-generated-path-rowid-current-source-pinned');
    assert($summary['argvColumns'] === ['path', 'id']);
    assert($summary['orderedOutputRowids'] === [6, 5]);
    assert($summary['orderedOutputPaths'] === ['$.rules[1]', '$.rules[1]']);
    assert($summary['nextFilterOpcode'] === 'xFilter-generated-path-rowid-current-source-reprepare');
    assert(in_array('json-table-generated-path-rowid-current-source-filter-rowset-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next167 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
