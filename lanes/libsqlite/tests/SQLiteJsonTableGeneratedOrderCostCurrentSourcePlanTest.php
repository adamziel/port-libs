<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSource = [
    'option_id' => 139,
    'option_name' => 'wp_plugin_generated_order_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true,"rank":30},{"slug":"cache","priority":7,"enabled":false,"rank":10},{"slug":"forms","priority":4,"enabled":true,"rank":20}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextSource = [
    'option_id' => 139,
    'option_name' => 'wp_plugin_generated_order_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":8,"enabled":true,"rank":30},{"slug":"cache","priority":1,"enabled":false,"rank":10},{"slug":"forms","priority":4,"enabled":true,"rank":20},{"slug":"shop","priority":5,"enabled":true,"rank":15}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$constraints = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
];
$generatedConstraints = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
];
$generatedOrder = [
    ['name' => 'generated_rank', 'source' => 'value', 'path' => '$.rank', 'direction' => 'ASC'],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'direction' => 'DESC'],
];

$plan139 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraintInput = null,
    ?array $generatedConstraintInput = null,
    ?array $generatedOrderInput = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedOrderCostPlan(
    'json_tree',
    $current ?? $currentSource,
    $next ?? $nextSource,
    'option_value',
    'base_root',
    'nested_path',
    $constraintInput ?? $constraints,
    [['column' => 'id']],
    $generatedConstraintInput ?? $generatedConstraints,
    $generatedOrderInput ?? $generatedOrder,
);

$stable139 = static fn (): array => $plan139($currentSource, $currentSource);
$point139 = static fn (): array => $plan139(
    $currentSource,
    $currentSource,
    $constraints,
    [['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => '=', 'value' => 'seo']],
    $generatedOrder,
);
$empty139 = static fn (): array => $plan139(
    $currentSource,
    $currentSource,
    $constraints,
    [['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [40, 50]]],
    $generatedOrder,
);
$desc139 = static fn (): array => $plan139(
    $currentSource,
    $nextSource,
    $constraints,
    $generatedConstraints,
    [['name' => 'generated_rank', 'source' => 'value', 'path' => '$.rank', 'direction' => 'DESC']],
);
$unrunnable139 = static fn (): array => $plan139(
    $currentSource,
    array_replace($nextSource, ['option_value' => null]),
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan139()['function']),
    'records generated order cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-order-cost-current-source-plan', $plan139()['dependencies'], true)),
    'preserves next136 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-cost-current-source-next136', $plan139()['dependencies'], true)),
    'pins current reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-order-cost-source-until-cursor-reset', $plan139()['currentReaderPolicy']),
    'prepares changed next reader' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-order-cost-plan', $plan139()['nextReaderPolicy']),
    'stable reader reuses plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-order-cost-plan', $stable139()['nextReaderPolicy']),
    'stable reasons are empty' => static fn (TestRunner $t) => $t->same([], $stable139()['generatedOrderCostReplanReasons']),
    'generated order terms normalize' => static fn (TestRunner $t) => $t->same($generatedOrder, $plan139()['currentGeneratedOrderCost']['generatedOrderBy']),
    'current filtered row count comes from generated constraints' => static fn (TestRunner $t) => $t->same(1, $plan139()['currentGeneratedOrderCost']['filteredRowCount']),
    'next filtered row count includes inserted rule' => static fn (TestRunner $t) => $t->same(2, $plan139()['nextGeneratedOrderCost']['filteredRowCount']),
    'current ordered rowid is forms rule' => static fn (TestRunner $t) => $t->same([11], $plan139()['currentGeneratedOrderCost']['orderedRowids']),
    'next ordered rowids sort shop before forms by generated rank' => static fn (TestRunner $t) => $t->same([16, 11], $plan139()['nextGeneratedOrderCost']['orderedRowids']),
    'current ordered fullkey is forms rule' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $plan139()['currentGeneratedOrderCost']['orderedFullkeys']),
    'next ordered fullkeys include inserted shop first' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[3]', '$.plugin.groups[0].rules[2]'], $plan139()['nextGeneratedOrderCost']['orderedFullkeys']),
    'current generated key is rank slug' => static fn (TestRunner $t) => $t->same([[20, 'forms']], $plan139()['currentGeneratedOrderCost']['orderedGeneratedKeys']),
    'next generated keys sort rank then slug' => static fn (TestRunner $t) => $t->same([[15, 'shop'], [20, 'forms']], $plan139()['nextGeneratedOrderCost']['orderedGeneratedKeys']),
    'current point does not require sorter' => static fn (TestRunner $t) => $t->same(false, $plan139()['currentGeneratedOrderCost']['requiresGeneratedSorter']),
    'next multi row requires sorter' => static fn (TestRunner $t) => $t->same(true, $plan139()['nextGeneratedOrderCost']['requiresGeneratedSorter']),
    'current first ordered rowid tracked' => static fn (TestRunner $t) => $t->same(11, $plan139()['currentGeneratedOrderCost']['firstOrderedRowid']),
    'next first ordered rowid tracked' => static fn (TestRunner $t) => $t->same(16, $plan139()['nextGeneratedOrderCost']['firstOrderedRowid']),
    'next last ordered rowid tracked' => static fn (TestRunner $t) => $t->same(11, $plan139()['nextGeneratedOrderCost']['lastOrderedRowid']),
    'current filter cost carried forward' => static fn (TestRunner $t) => $t->same($plan139()['currentGeneratedHiddenCost']['effectiveEstimatedCost'], $plan139()['currentGeneratedOrderCost']['filterEffectiveCost']),
    'current sort penalty is zero for point row' => static fn (TestRunner $t) => $t->same(0, $plan139()['currentGeneratedOrderCost']['generatedSortPenalty']),
    'next sort penalty includes two terms and two rows' => static fn (TestRunner $t) => $t->same(4, $plan139()['nextGeneratedOrderCost']['generatedSortPenalty']),
    'next effective cost adds sort penalty' => static fn (TestRunner $t) => $t->same($plan139()['nextGeneratedOrderCost']['filterEffectiveCost'] + 4, $plan139()['nextGeneratedOrderCost']['effectiveEstimatedCost']),
    'current cost class is generated order point' => static fn (TestRunner $t) => $t->same('json-table-generated-order-point', $plan139()['currentGeneratedOrderCost']['costClass']),
    'next cost class is generated order narrow sort' => static fn (TestRunner $t) => $t->same('json-table-generated-order-narrow-sort', $plan139()['nextGeneratedOrderCost']['costClass']),
    'point equality has seo rowid' => static fn (TestRunner $t) => $t->same([1], $point139()['currentGeneratedOrderCost']['orderedRowids']),
    'point equality cost class is point' => static fn (TestRunner $t) => $t->same('json-table-generated-order-point', $point139()['currentGeneratedOrderCost']['costClass']),
    'empty generated filter has no ordered rowids' => static fn (TestRunner $t) => $t->same([], $empty139()['currentGeneratedOrderCost']['orderedRowids']),
    'empty generated filter cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-order-empty', $empty139()['currentGeneratedOrderCost']['costClass']),
    'descending rank puts forms before shop in next source' => static fn (TestRunner $t) => $t->same([11, 16], $desc139()['nextGeneratedOrderCost']['orderedRowids']),
    'transition count records generated order cost state' => static fn (TestRunner $t) => $t->same(8, count($plan139()['generatedOrderCostTransitions'])),
    'root transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan139()['generatedOrderCostTransitions'][0]['changed']),
    'order terms transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan139()['generatedOrderCostTransitions'][1]['changed']),
    'row count transition changes' => static fn (TestRunner $t) => $t->same(true, $plan139()['generatedOrderCostTransitions'][2]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan139()['generatedOrderCostTransitions'][3]['changed']),
    'ordered keys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan139()['generatedOrderCostTransitions'][4]['changed']),
    'sorter transition changes' => static fn (TestRunner $t) => $t->same(true, $plan139()['generatedOrderCostTransitions'][5]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan139()['generatedOrderCostTransitions'][6]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan139()['generatedOrderCostTransitions'][7]['changed']),
    'reasons include generated order row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-order-row-count-changed', $plan139()['generatedOrderCostReplanReasons'], true)),
    'reasons include generated order output' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-order-output-changed', $plan139()['generatedOrderCostReplanReasons'], true)),
    'reasons include generated order keys' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-order-keys-changed', $plan139()['generatedOrderCostReplanReasons'], true)),
    'reasons include generated order sorter' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-order-sorter-changed', $plan139()['generatedOrderCostReplanReasons'], true)),
    'reasons include generated order cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-order-cost-changed', $plan139()['generatedOrderCostReplanReasons'], true)),
    'reasons preserve generated hidden row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-row-count-changed', $plan139()['generatedOrderCostReplanReasons'], true)),
    'unrunnable next cost class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable139()['nextGeneratedOrderCost']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable139()['nextGeneratedOrderCost']['effectiveEstimatedCost']),
    'unrunnable next has no ordered rowids' => static fn (TestRunner $t) => $t->same([], $unrunnable139()['nextGeneratedOrderCost']['orderedRowids']),
    'empty generated order is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan139($currentSource, $nextSource, $constraints, $generatedConstraints, [])),
    'bad generated order direction is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan139($currentSource, $nextSource, $constraints, $generatedConstraints, [['name' => 'bad', 'source' => 'value', 'path' => '$.rank', 'direction' => 'SIDEWAYS']])),
    'bad generated order source is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan139($currentSource, $nextSource, $constraints, $generatedConstraints, [['name' => 'bad', 'source' => 'missing', 'path' => '$.rank']])),
    'bad generated order path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan139($currentSource, $nextSource, $constraints, $generatedConstraints, [['name' => 'bad', 'source' => 'value', 'path' => '$[#-]']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated order cost current source plan ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
