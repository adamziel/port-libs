<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current141 = [
    'option_id' => 141,
    'option_name' => 'wp_plugin_generated_hidden_residual_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next141 = [
    'option_id' => 141,
    'option_name' => 'wp_plugin_generated_hidden_residual_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$constraints141 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
];
$generated141 = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop'], 'usable' => false],
];

$plan141 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedHiddenResidualCostNext141(
    'json_tree',
    $current ?? $current141,
    $next ?? $next141,
    'option_value',
    'base_root',
    'nested_path',
    $constraints141,
    [['column' => 'id']],
    $generated ?? $generated141,
);

$stable141 = static fn (): array => $plan141($current141, $current141);
$usableOnly141 = static fn (): array => $plan141($current141, $next141, [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
]);
$unrunnable141 = static fn (): array => $plan141($current141, array_replace($next141, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan141()['function']),
    'records next141 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-residual-cost-current-source-next141', $plan141()['dependencies'], true)),
    'preserves generated hidden cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-cost-current-source-next136', $plan141()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-hidden-residual-cost-source-until-cursor-reset', $plan141()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-hidden-residual-cost-plan', $plan141()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-hidden-residual-cost-plan', $stable141()['nextReaderPolicy']),
    'stable plan has no next141 reasons' => static fn (TestRunner $t) => $t->same([], $stable141()['next141ReplanReasons']),
    'current records all generated signatures' => static fn (TestRunner $t) => $t->same(3, count($plan141()['currentGeneratedHiddenResidualCost']['generatedConstraintSignatures'])),
    'current records two usable generated signatures' => static fn (TestRunner $t) => $t->same(2, count($plan141()['currentGeneratedHiddenResidualCost']['usableGeneratedConstraintSignatures'])),
    'current records residual generated signature' => static fn (TestRunner $t) => $t->same('generated_slug:value:$.slug:IN:["forms","shop"]', $plan141()['currentGeneratedHiddenResidualCost']['residualGeneratedConstraintSignatures'][0]),
    'current records residual generated column' => static fn (TestRunner $t) => $t->same(['generated_slug'], $plan141()['currentGeneratedHiddenResidualCost']['residualGeneratedColumns']),
    'current residual constraint count is one' => static fn (TestRunner $t) => $t->same(1, $plan141()['currentGeneratedHiddenResidualCost']['residualGeneratedConstraintCount']),
    'current matched row count comes from usable generated constraints' => static fn (TestRunner $t) => $t->same(1, $plan141()['currentGeneratedHiddenResidualCost']['matchedRowCount']),
    'next matched row count comes from usable generated constraints' => static fn (TestRunner $t) => $t->same(2, $plan141()['nextGeneratedHiddenResidualCost']['matchedRowCount']),
    'current base effective cost is point cost' => static fn (TestRunner $t) => $t->same(5, $plan141()['currentGeneratedHiddenResidualCost']['baseEffectiveEstimatedCost']),
    'next base effective cost is generated filter cost' => static fn (TestRunner $t) => $t->same(6, $plan141()['nextGeneratedHiddenResidualCost']['baseEffectiveEstimatedCost']),
    'current residual penalty applies to matched row' => static fn (TestRunner $t) => $t->same(1, $plan141()['currentGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'next residual penalty grows with matched rows' => static fn (TestRunner $t) => $t->same(2, $plan141()['nextGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'current effective cost includes residual penalty' => static fn (TestRunner $t) => $t->same(6, $plan141()['currentGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'next effective cost includes residual penalty' => static fn (TestRunner $t) => $t->same(8, $plan141()['nextGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'current cost class records residual point' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-residual-point', $plan141()['currentGeneratedHiddenResidualCost']['costClass']),
    'next cost class records residual narrow filter' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-residual-narrow-filter', $plan141()['nextGeneratedHiddenResidualCost']['costClass']),
    'usable-only current inherits base cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-point', $usableOnly141()['currentGeneratedHiddenResidualCost']['costClass']),
    'usable-only current has no residual penalty' => static fn (TestRunner $t) => $t->same(0, $usableOnly141()['currentGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'usable-only current effective cost remains base cost' => static fn (TestRunner $t) => $t->same(4, $usableOnly141()['currentGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'current residual tape preserves slugs' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $plan141()['currentGeneratedHiddenResidualCost']['residualValueTape'])),
    'next residual tape preserves inserted slug' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms', 'shop'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $plan141()['nextGeneratedHiddenResidualCost']['residualValueTape'])),
    'current residual tape keeps usable match flags' => static fn (TestRunner $t) => $t->same([false, false, true], array_column($plan141()['currentGeneratedHiddenResidualCost']['residualValueTape'], 'matched')),
    'next residual tape keeps usable match flags' => static fn (TestRunner $t) => $t->same([false, false, true, true], array_column($plan141()['nextGeneratedHiddenResidualCost']['residualValueTape'], 'matched')),
    'transition count records residual cost state' => static fn (TestRunner $t) => $t->same(7, count($plan141()['generatedHiddenResidualCostTransitions'])),
    'usable signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan141()['generatedHiddenResidualCostTransitions'][0]['changed']),
    'residual signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan141()['generatedHiddenResidualCostTransitions'][1]['changed']),
    'matched count transition changes' => static fn (TestRunner $t) => $t->same(true, $plan141()['generatedHiddenResidualCostTransitions'][2]['changed']),
    'residual penalty transition changes' => static fn (TestRunner $t) => $t->same(true, $plan141()['generatedHiddenResidualCostTransitions'][3]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan141()['generatedHiddenResidualCostTransitions'][4]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan141()['generatedHiddenResidualCostTransitions'][5]['changed']),
    'residual value tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan141()['generatedHiddenResidualCostTransitions'][6]['changed']),
    'reasons include residual row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-row-count-changed', $plan141()['next141ReplanReasons'], true)),
    'reasons include residual cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-cost-changed', $plan141()['next141ReplanReasons'], true)),
    'reasons include residual values' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-values-changed', $plan141()['next141ReplanReasons'], true)),
    'reasons preserve generated hidden rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-rowset-changed', $plan141()['next141ReplanReasons'], true)),
    'unrunnable next cost class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable141()['nextGeneratedHiddenResidualCost']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable141()['nextGeneratedHiddenResidualCost']['effectiveEstimatedCost']),
    'unrunnable next residual penalty is zero' => static fn (TestRunner $t) => $t->same(0, $unrunnable141()['nextGeneratedHiddenResidualCost']['residualEvaluationPenalty']),
    'empty generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan141($current141, $next141, [])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan141($current141, $next141, [['name' => 'bad', 'source' => 'value', 'path' => '$[#-]']])),
    'bad generated operator rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan141($current141, $next141, [['name' => 'bad', 'source' => 'value', 'path' => '$.slug', 'operator' => 'LIKE']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated hidden residual cost current source next141 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
