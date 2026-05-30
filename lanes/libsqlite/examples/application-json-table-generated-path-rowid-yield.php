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
    'option_id' => 169,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next169',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 169,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next169',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostYieldPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

$summary = [
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next169',
    'applicationUse' => 'Copied wp_options plugin-rule previews can carry generated path plus _rowid_ xFilter bindings into a pinned json_tree() yield tape, then force the shifted next source to reprepare.',
    'admission' => $plan['currentGeneratedPathRowidYield169']['admission'],
    'yieldMode' => $plan['currentGeneratedPathRowidYield169']['yieldMode'],
    'orderedRowids' => $plan['currentGeneratedPathRowidYield169']['orderedRowids'],
    'argvProgram' => $plan['currentGeneratedPathRowidYield169']['argvProgram'],
    'estimatedRows' => $plan['currentGeneratedPathRowidYield169']['estimatedRows'],
    'estimatedCost' => $plan['currentGeneratedPathRowidYield169']['estimatedCost'],
    'nextAdmission' => $plan['nextGeneratedPathRowidYield169']['admission'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next169ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, seek, and planner metadata helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['admission'] === 'admit-current-source-generated-path-rowid-yield');
    assert($summary['yieldMode'] === 'point-or-range-yield');
    assert($summary['orderedRowids'] === [6]);
    assert($summary['argvProgram'] === ['argv1:path', 'argv2:rowid']);
    assert($summary['estimatedRows'] === 1);
    assert($summary['estimatedCost'] === 1);
    assert($summary['nextAdmission'] === 'prepare-json-table-generated-path-rowid-yield');
    assert(in_array('json-table-generated-path-rowid-yield-next169-admission-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next169 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
