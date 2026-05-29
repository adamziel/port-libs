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
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_name' => 'wp_plugin_generated_hidden_residual_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next = [
    'option_name' => 'wp_plugin_generated_hidden_residual_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$plan = SQLiteJsonTablePlan::currentSourceGeneratedHiddenResidualCost(
    'json_tree',
    $current,
    $next,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ],
    [['column' => 'id']],
    [
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
        ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
        ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop'], 'usable' => false],
    ],
);

$summary = [
    'scenario' => 'wordpress-json-table-generated-hidden-residual-cost',
    'wordpressUse' => 'Copied wp_options JSON import diagnostics can keep generated hidden constraints usable while preserving an unusable generated slug predicate as a residual filter with an explicit cost penalty before the next option payload is committed.',
    'currentCostClass' => $plan['currentGeneratedHiddenResidualCost']['costClass'],
    'nextCostClass' => $plan['nextGeneratedHiddenResidualCost']['costClass'],
    'currentResidualPenalty' => $plan['currentGeneratedHiddenResidualCost']['residualEvaluationPenalty'],
    'nextResidualPenalty' => $plan['nextGeneratedHiddenResidualCost']['residualEvaluationPenalty'],
    'currentResidualSlugs' => array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $plan['currentGeneratedHiddenResidualCost']['residualValueTape']),
    'nextResidualSlugs' => array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $plan['nextGeneratedHiddenResidualCost']['residualValueTape']),
    'replanReasons' => $plan['generatedHiddenResidualCostReplanReasons'],
    'dependencyClosure' => 'no new support component needed; reuses native JSON table current-source planning, generated hidden cost filtering, and residual predicate costing',
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['currentCostClass'] !== 'json-table-generated-hidden-residual-point'
        || $summary['nextCostClass'] !== 'json-table-generated-hidden-residual-narrow-filter'
        || $summary['currentResidualPenalty'] !== 1
        || $summary['nextResidualPenalty'] !== 2
        || $summary['nextResidualSlugs'] !== ['seo', 'cache', 'forms', 'shop']
        || !in_array('json-table-generated-hidden-residual-cost-changed', $summary['replanReasons'], true)
    ) {
        fwrite(STDERR, "wordpress-json-table-generated-hidden-residual-cost self-test failed\n");
        exit(1);
    }

    echo "wordpress-json-table-generated-hidden-residual-cost self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
