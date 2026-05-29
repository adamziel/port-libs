<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentResidualCost = [
    'option_id' => 141,
    'option_name' => 'wp_plugin_generated_hidden_residual_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextResidualCost = [
    'option_id' => 141,
    'option_name' => 'wp_plugin_generated_hidden_residual_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$residualCostConstraints = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
];
$residualCostGenerated = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop'], 'usable' => false],
];

$residualCostPlan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedHiddenResidualCost(
    'json_tree',
    $current ?? $currentResidualCost,
    $next ?? $nextResidualCost,
    'option_value',
    'base_root',
    'nested_path',
    $residualCostConstraints,
    [['column' => 'id']],
    $generated ?? $residualCostGenerated,
);

$stableResidualCostPlan = static fn (): array => $residualCostPlan($currentResidualCost, $currentResidualCost);
$usableOnlyResidualCostPlan = static fn (): array => $residualCostPlan($currentResidualCost, $nextResidualCost, [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
]);
$unrunnableResidualCostPlan = static fn (): array => $residualCostPlan($currentResidualCost, array_replace($nextResidualCost, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $residualCostPlan()['function']),
    'records generated hidden residual dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-residual-cost-current-source', $residualCostPlan()['dependencies'], true)),
    'preserves generated hidden cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-cost-current-source-next136', $residualCostPlan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-hidden-residual-cost-source-until-cursor-reset', $residualCostPlan()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-hidden-residual-cost-plan', $residualCostPlan()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-hidden-residual-cost-plan', $stableResidualCostPlan()['nextReaderPolicy']),
    'stable plan has no generated hidden residual reasons' => static fn (TestRunner $t) => $t->same([], $stableResidualCostPlan()['generatedHiddenResidualCostReplanReasons']),
    'current records all generated signatures' => static fn (TestRunner $t) => $t->same(3, count($residualCostPlan()['currentGeneratedHiddenResidualCost']['generatedConstraintSignatures'])),
    'current records two usable generated signatures' => static fn (TestRunner $t) => $t->same(2, count($residualCostPlan()['currentGeneratedHiddenResidualCost']['usableGeneratedConstraintSignatures'])),
    'current records residual generated signature' => static fn (TestRunner $t) => $t->same('generated_slug:value:$.slug:IN:["forms","shop"]', $residualCostPlan()['currentGeneratedHiddenResidualCost']['residualGeneratedConstraintSignatures'][0]),
    'current records residual generated column' => static fn (TestRunner $t) => $t->same(['generated_slug'], $residualCostPlan()['currentGeneratedHiddenResidualCost']['residualGeneratedColumns']),
    'current residual constraint count is one' => static fn (TestRunner $t) => $t->same(1, $residualCostPlan()['currentGeneratedHiddenResidualCost']['residualGeneratedConstraintCount']),
    'current matched row count comes from usable generated constraints' => static fn (TestRunner $t) => $t->same(1, $residualCostPlan()['currentGeneratedHiddenResidualCost']['matchedRowCount']),
    'next matched row count comes from usable generated constraints' => static fn (TestRunner $t) => $t->same(2, $residualCostPlan()['nextGeneratedHiddenResidualCost']['matchedRowCount']),
    'current base effective cost is point cost' => static fn (TestRunner $t) => $t->same(5, $residualCostPlan()['currentGeneratedHiddenResidualCost']['baseEffectiveEstimatedCost']),
    'next base effective cost is generated filter cost' => static fn (TestRunner $t) => $t->same(6, $residualCostPlan()['nextGeneratedHiddenResidualCost']['baseEffectiveEstimatedCost']),
    'current residual penalty applies to matched row' => static fn (TestRunner $t) => $t->same(1, $residualCostPlan()['currentGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'next residual penalty grows with matched rows' => static fn (TestRunner $t) => $t->same(2, $residualCostPlan()['nextGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'current effective cost includes residual penalty' => static fn (TestRunner $t) => $t->same(6, $residualCostPlan()['currentGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'next effective cost includes residual penalty' => static fn (TestRunner $t) => $t->same(8, $residualCostPlan()['nextGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'current cost class records residual point' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-residual-point', $residualCostPlan()['currentGeneratedHiddenResidualCost']['costClass']),
    'next cost class records residual narrow filter' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-residual-narrow-filter', $residualCostPlan()['nextGeneratedHiddenResidualCost']['costClass']),
    'usable-only current inherits base cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-point', $usableOnlyResidualCostPlan()['currentGeneratedHiddenResidualCost']['costClass']),
    'usable-only current has no residual penalty' => static fn (TestRunner $t) => $t->same(0, $usableOnlyResidualCostPlan()['currentGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'usable-only current effective cost remains base cost' => static fn (TestRunner $t) => $t->same(4, $usableOnlyResidualCostPlan()['currentGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'current residual tape preserves slugs' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $residualCostPlan()['currentGeneratedHiddenResidualCost']['residualValueTape'])),
    'next residual tape preserves inserted slug' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms', 'shop'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $residualCostPlan()['nextGeneratedHiddenResidualCost']['residualValueTape'])),
    'current residual tape keeps usable match flags' => static fn (TestRunner $t) => $t->same([false, false, true], array_column($residualCostPlan()['currentGeneratedHiddenResidualCost']['residualValueTape'], 'matched')),
    'next residual tape keeps usable match flags' => static fn (TestRunner $t) => $t->same([false, false, true, true], array_column($residualCostPlan()['nextGeneratedHiddenResidualCost']['residualValueTape'], 'matched')),
    'transition count records residual cost state' => static fn (TestRunner $t) => $t->same(7, count($residualCostPlan()['generatedHiddenResidualCostTransitions'])),
    'usable signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $residualCostPlan()['generatedHiddenResidualCostTransitions'][0]['changed']),
    'residual signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $residualCostPlan()['generatedHiddenResidualCostTransitions'][1]['changed']),
    'matched count transition changes' => static fn (TestRunner $t) => $t->same(true, $residualCostPlan()['generatedHiddenResidualCostTransitions'][2]['changed']),
    'residual penalty transition changes' => static fn (TestRunner $t) => $t->same(true, $residualCostPlan()['generatedHiddenResidualCostTransitions'][3]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $residualCostPlan()['generatedHiddenResidualCostTransitions'][4]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $residualCostPlan()['generatedHiddenResidualCostTransitions'][5]['changed']),
    'residual value tape transition changes' => static fn (TestRunner $t) => $t->same(true, $residualCostPlan()['generatedHiddenResidualCostTransitions'][6]['changed']),
    'reasons include residual row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-row-count-changed', $residualCostPlan()['generatedHiddenResidualCostReplanReasons'], true)),
    'reasons include residual cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-cost-changed', $residualCostPlan()['generatedHiddenResidualCostReplanReasons'], true)),
    'reasons include residual values' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-values-changed', $residualCostPlan()['generatedHiddenResidualCostReplanReasons'], true)),
    'reasons preserve generated hidden rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-rowset-changed', $residualCostPlan()['generatedHiddenResidualCostReplanReasons'], true)),
    'unrunnable next cost class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnableResidualCostPlan()['nextGeneratedHiddenResidualCost']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnableResidualCostPlan()['nextGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'unrunnable next residual penalty is zero' => static fn (TestRunner $t) => $t->same(0, $unrunnableResidualCostPlan()['nextGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'empty generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $residualCostPlan($currentResidualCost, $nextResidualCost, [])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $residualCostPlan($currentResidualCost, $nextResidualCost, [['name' => 'bad', 'source' => 'value', 'path' => '$[#-]']])),
    'bad generated operator rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $residualCostPlan($currentResidualCost, $nextResidualCost, [['name' => 'bad', 'source' => 'value', 'path' => '$.slug', 'operator' => 'LIKE']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated hidden residual cost ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
