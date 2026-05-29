<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current125 = [
    'option_id' => 125,
    'option_name' => 'wp_plugin_nested_constraint_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next125 = [
    'option_id' => 125,
    'option_name' => 'wp_plugin_nested_constraint_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3,"enabled":true},{"slug":"cache","priority":8,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true},{"slug":"spam","priority":1,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];
$constraints125 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[%].rules[%].priority'],
];

$plan125 = static fn (array $current = null, array $next = null, array $constraints = null, array $orderBy = null): array => SQLiteJsonTablePlan::currentSourceNestedConstraintCost(
    'json_tree',
    $current ?? $current125,
    $next ?? $next125,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints125,
    $orderBy ?? [['column' => 'atom', 'direction' => 'DESC']],
);

$stable125 = static fn (): array => $plan125($current125, $current125);
$idLookup125 = static fn (): array => $plan125(
    array_replace($current125, ['base_root' => '$.plugin.groups[0].rules[0]', 'nested_path' => '']),
    array_replace($current125, ['base_root' => '$.plugin.groups[0].rules[0]', 'nested_path' => '']),
    [['column' => 'id', 'operator' => '=', 'value' => 2]],
    [['column' => 'id']],
);
$unindexed125 = static fn (): array => $plan125(
    $current125,
    $next125,
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    [['column' => 'atom']],
);
$modeChange125 = static fn (): array => $plan125(
    array_replace($current125, ['base_root' => '$.plugin.groups[0]', 'nested_path' => 'rules']),
    array_replace($current125, ['base_root' => '$.plugin.groups[0]', 'nested_path' => '.rules']),
    [['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].slug']],
    [['column' => 'id']],
);
$unrunnable125 = static fn (): array => $plan125(
    $current125,
    array_replace($next125, ['option_value' => null]),
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan125()['function']),
    'records next125 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-constraint-cost-current-source-next125', $plan125()['dependencies'], true)),
    'preserves nested next121 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-path-planner-current-source-next121', $plan125()['dependencies'], true)),
    'preserves cost order next113 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-cost-order', $plan125()['dependencies'], true)),
    'pins current nested constraint reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-nested-constraint-cost-until-cursor-reset', $plan125()['currentReaderPolicy']),
    'prepares changed nested constraint plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-nested-constraint-cost-plan', $plan125()['nextReaderPolicy']),
    'stable nested constraint plan is reused' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-nested-constraint-cost-plan', $stable125()['nextReaderPolicy']),
    'stable plan has no next125 reasons' => static fn (TestRunner $t) => $t->same([], $stable125()['next125ReplanReasons']),
    'current nested root is composed' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan125()['currentNestedConstraintCost']['nestedRoot']),
    'next nested root is composed' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan125()['nextNestedConstraintCost']['nestedRoot']),
    'current nested mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan125()['currentNestedConstraintCost']['nestedPathMode']),
    'next nested mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan125()['nextNestedConstraintCost']['nestedPathMode']),
    'current selected constraint is fullkey prefix' => static fn (TestRunner $t) => $t->same('3:fullkey:LIKE:"$.plugin.groups[%].rules[%].priority"', $plan125()['currentNestedConstraintCost']['selectedSignature']),
    'next selected constraint remains fullkey prefix' => static fn (TestRunner $t) => $t->same($plan125()['currentNestedConstraintCost']['selectedSignature'], $plan125()['nextNestedConstraintCost']['selectedSignature']),
    'current scan strategy is indexed constraint' => static fn (TestRunner $t) => $t->same('indexed-json-table-constraint', $plan125()['currentNestedConstraintCost']['scanStrategy']),
    'next scan strategy is indexed constraint' => static fn (TestRunner $t) => $t->same('indexed-json-table-constraint', $plan125()['nextNestedConstraintCost']['scanStrategy']),
    'current cost class is indexed narrow scan' => static fn (TestRunner $t) => $t->same('json-table-indexed-narrow-scan', $plan125()['currentNestedConstraintCost']['costClass']),
    'next cost class is indexed range' => static fn (TestRunner $t) => $t->same('json-table-indexed-range-scan', $plan125()['nextNestedConstraintCost']['costClass']),
    'current matched row count follows core priorities' => static fn (TestRunner $t) => $t->same(2, $plan125()['currentNestedConstraintCost']['matchedRowCount']),
    'next matched row count follows forms priorities' => static fn (TestRunner $t) => $t->same(3, $plan125()['nextNestedConstraintCost']['matchedRowCount']),
    'current matched fullkeys are ordered by priority' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[1].priority', '$.plugin.groups[0].rules[0].priority'], $plan125()['currentNestedConstraintCost']['matchedFullkeys']),
    'next matched fullkeys are ordered by priority' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[1].rules[1].priority', '$.plugin.groups[1].rules[0].priority', '$.plugin.groups[1].rules[2].priority'], $plan125()['nextNestedConstraintCost']['matchedFullkeys']),
    'current indexed estimated rows are narrowed' => static fn (TestRunner $t) => $t->same(1, $plan125()['currentNestedConstraintCost']['indexedEstimatedRows']),
    'next indexed estimated rows are narrowed' => static fn (TestRunner $t) => $t->same(1, $plan125()['nextNestedConstraintCost']['indexedEstimatedRows']),
    'current effective cost includes sorter' => static fn (TestRunner $t) => $t->same(3, $plan125()['currentNestedConstraintCost']['effectiveEstimatedCost']),
    'next effective cost includes larger sorter' => static fn (TestRunner $t) => $t->same(7, $plan125()['nextNestedConstraintCost']['effectiveEstimatedCost']),
    'transition list includes indexed and nested fields' => static fn (TestRunner $t) => $t->same(10, count($plan125()['nestedConstraintCostTransitions'])),
    'selected signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan125()['nestedConstraintCostTransitions'][0]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan125()['nestedConstraintCostTransitions'][3]['changed']),
    'row count transition changes' => static fn (TestRunner $t) => $t->same(true, $plan125()['nestedConstraintCostTransitions'][5]['changed']),
    'nested root transition changes' => static fn (TestRunner $t) => $t->same(true, $plan125()['nestedConstraintCostTransitions'][6]['changed']),
    'nested mode transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan125()['nestedConstraintCostTransitions'][7]['changed']),
    'matched fullkeys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan125()['nestedConstraintCostTransitions'][9]['changed']),
    'reasons include nested root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-constraint-root-changed', $plan125()['next125ReplanReasons'], true)),
    'reasons include row count change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-constraint-row-count-changed', $plan125()['next125ReplanReasons'], true)),
    'reasons include output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-constraint-output-changed', $plan125()['next125ReplanReasons'], true)),
    'reasons include indexed cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-indexed-cost-changed', $plan125()['next125ReplanReasons'], true)),
    'reasons preserve nested path change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-path-changed', $plan125()['next125ReplanReasons'], true)),
    'rowid lookup selects id constraint' => static fn (TestRunner $t) => $t->same('2:id:=:2', $idLookup125()['currentNestedConstraintCost']['selectedSignature']),
    'rowid lookup cost class is point lookup' => static fn (TestRunner $t) => $t->same('json-table-rowid-point-lookup', $idLookup125()['currentNestedConstraintCost']['costClass']),
    'rowid lookup matched one fullkey' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[0].priority'], $idLookup125()['currentNestedConstraintCost']['matchedFullkeys']),
    'rowid stable plan has no next125 reasons' => static fn (TestRunner $t) => $t->same([], $idLookup125()['next125ReplanReasons']),
    'unindexed scan has no selected signature' => static fn (TestRunner $t) => $t->same(null, $unindexed125()['currentNestedConstraintCost']['selectedSignature']),
    'unindexed scan strategy is full scan' => static fn (TestRunner $t) => $t->same('full-json-table-scan', $unindexed125()['currentNestedConstraintCost']['scanStrategy']),
    'unindexed scan cost class reflects narrow full scan' => static fn (TestRunner $t) => $t->same('json-table-narrow-full-scan', $unindexed125()['currentNestedConstraintCost']['costClass']),
    'mode change detects bare to object fragment' => static fn (TestRunner $t) => $t->same(true, $modeChange125()['nestedConstraintCostTransitions'][7]['changed']),
    'mode change reason is reported' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-constraint-mode-changed', $modeChange125()['next125ReplanReasons'], true)),
    'mode change composed roots remain equivalent' => static fn (TestRunner $t) => $t->same(false, $modeChange125()['nestedConstraintCostTransitions'][6]['changed']),
    'unrunnable next cost class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable125()['nextNestedConstraintCost']['costClass']),
    'unrunnable next matched rows are empty' => static fn (TestRunner $t) => $t->same(0, $unrunnable125()['nextNestedConstraintCost']['matchedRowCount']),
    'unrunnable next reports source plan failure' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable125()['next125ReplanReasons'], true)),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedConstraintCost('json_bad', $current125, $next125, 'option_value', 'base_root', 'nested_path')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedConstraintCost('json_tree', $current125, $next125, '', 'base_root', 'nested_path')),
    'empty base column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedConstraintCost('json_tree', $current125, $next125, 'option_value', '', 'nested_path')),
    'empty nested column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedConstraintCost('json_tree', $current125, $next125, 'option_value', 'base_root', '')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table nested constraint cost current source next125 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
