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
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 145,
    'option_name' => 'wp_plugin_generated_path_rowid_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next = [
    'option_id' => 145,
    'option_name' => 'wp_plugin_generated_path_rowid_cost',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$summary = [
    'scenario' => 'application-json-table-generated-path-rowid-cost',
    'applicationUse' => 'Copied wp_options plugin-setting previews can intersect a generated JSON path with a hidden rowid seek while a next import shifts sibling rules.',
    'currentCostClass' => $plan['currentGeneratedPathRowidCost']['costClass'],
    'currentIntersectedRowids' => $plan['currentGeneratedPathRowidCost']['intersectedRowids'],
    'nextCostClass' => $plan['nextGeneratedPathRowidCost']['costClass'],
    'nextIntersectedRowids' => $plan['nextGeneratedPathRowidCost']['intersectedRowids'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['generatedPathRowidCostReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table generated-path cost and rowid alias constraint planning',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['currentCostClass'] === 'json-table-generated-path-rowid-point');
    assert($summary['currentIntersectedRowids'] === [6]);
    assert($summary['nextCostClass'] === 'json-table-generated-path-rowid-empty');
    assert($summary['nextIntersectedRowids'] === []);
    assert(in_array('json-table-generated-path-rowid-rowset-changed', $summary['replanReasons'], true));
    echo "application-json-table-generated-path-rowid-cost self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
