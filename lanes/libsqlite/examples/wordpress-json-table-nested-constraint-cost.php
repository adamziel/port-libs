<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 125,
    'option_name' => 'wp_plugin_nested_constraint_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7}]},{"name":"forms","rules":[{"slug":"forms","priority":4}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_id' => 125,
    'option_name' => 'wp_plugin_nested_constraint_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6},{"slug":"spam","priority":1}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceNestedConstraintCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[%].rules[%].priority'],
    ],
    [['column' => 'atom', 'direction' => 'DESC']],
);

$summary = [
    'operation' => 'json-table-nested-constraint-cost-current-source-next125',
    'optionName' => $current['option_name'],
    'currentRoot' => $plan['currentNestedConstraintCost']['nestedRoot'],
    'nextRoot' => $plan['nextNestedConstraintCost']['nestedRoot'],
    'currentSelectedConstraint' => $plan['currentNestedConstraintCost']['selectedSignature'],
    'nextSelectedConstraint' => $plan['nextNestedConstraintCost']['selectedSignature'],
    'currentMatchedRows' => $plan['currentNestedConstraintCost']['matchedRowCount'],
    'nextMatchedRows' => $plan['nextNestedConstraintCost']['matchedRowCount'],
    'currentEffectiveCost' => $plan['currentNestedConstraintCost']['effectiveEstimatedCost'],
    'nextEffectiveCost' => $plan['nextNestedConstraintCost']['effectiveEstimatedCost'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next125ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'wordpressUse' => 'A copied wp_options plugin setting can keep a base JSON root and per-request nested fragment; this previews when json_tree() visible constraints should be replanned because the nested fragment changes row count, output fullkeys, and indexed cost.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentRoot'] !== '$.plugin.groups[0].rules' || $summary['nextRoot'] !== '$.plugin.groups[1].rules') {
        fwrite(STDERR, "expected nested constraint roots\n");
        exit(1);
    }
    if ($summary['currentMatchedRows'] !== 2 || $summary['nextMatchedRows'] !== 3) {
        fwrite(STDERR, "expected nested priority row counts\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-nested-constraint-cost-current-source-next125', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected next125 dependency marker\n");
        exit(1);
    }
}
