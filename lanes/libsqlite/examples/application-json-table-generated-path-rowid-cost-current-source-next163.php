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
    'option_id' => 163,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next163',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 163,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_current_source_next163',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostBestIndex(
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
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next163',
    'applicationUse' => 'Copied wp_options plugin-rule previews can admit a pinned json_tree() cursor through generated path plus rowid alias xBestIndex metadata before the next source reparses.',
    'idxNum' => $plan['currentGeneratedPathRowidBestIndex']['idxNum'],
    'idxStr' => $plan['currentGeneratedPathRowidBestIndex']['idxStr'],
    'estimatedRows' => $plan['currentGeneratedPathRowidBestIndex']['estimatedRows'],
    'estimatedCost' => $plan['currentGeneratedPathRowidBestIndex']['estimatedCost'],
    'cursorAdmission' => $plan['currentGeneratedPathRowidBestIndex']['cursorAdmission'],
    'nextCursorAdmission' => $plan['nextGeneratedPathRowidBestIndex']['cursorAdmission'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next163ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, and xBestIndex-style planner metadata helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['idxNum'] === 15);
    assert($summary['idxStr'] === 'path|rowid:_rowid_|order|covering|json-table-generated-path-rowid-point');
    assert($summary['estimatedRows'] === 1);
    assert($summary['estimatedCost'] === 1);
    assert($summary['cursorAdmission'] === 'admit-current-source-generated-path-rowid-cursor');
    assert($summary['nextCursorAdmission'] === 'prepare-json-table-cursor');
    assert(in_array('json-table-generated-path-rowid-best-index-admission-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next163 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
