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
    'option_id' => 162,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_source_next162',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = array_replace($current, [
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
]);

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceConstraintPlan(
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
    'scenario' => 'application-json-table-generated-path-rowid-cost-current-source-next162',
    'applicationUse' => 'Copied wp_options plugin-rule previews can preserve the SQLite rowid alias chosen by xBestIndex while reusing a generated-path json_tree() source only when path, rowid, order, and source fingerprints remain stable.',
    'idxNum' => $plan['currentGeneratedPathRowidCostSource162']['idxNum'],
    'idxStr' => $plan['currentGeneratedPathRowidCostSource162']['idxStr'],
    'rowidAlias' => $plan['currentGeneratedPathRowidCostSource162']['rowidAlias'],
    'orderByConsumed' => $plan['currentGeneratedPathRowidCostSource162']['orderByConsumed'],
    'estimatedRows' => $plan['currentGeneratedPathRowidCostSource162']['estimatedRows'],
    'estimatedCost' => $plan['currentGeneratedPathRowidCostSource162']['estimatedCost'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next162ReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source, generated-path rowid-cost, rowid alias, and path validation helpers',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['idxNum'] === 7);
    assert($summary['idxStr'] === 'generated-path+rowid-point+orderby');
    assert($summary['rowidAlias'] === '_rowid_');
    assert($summary['orderByConsumed'] === true);
    assert($summary['estimatedRows'] === 1);
    assert($summary['estimatedCost'] === 1);
    assert(in_array('json-table-generated-path-rowid-cost-source-next162-stable-key-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost-current-source-next162 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
