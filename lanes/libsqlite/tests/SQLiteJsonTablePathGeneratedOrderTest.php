<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current137 = [
    'option_id' => 137,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"enabled":true}',
    'scan_root' => '$.rules',
];
$next137 = [
    'option_id' => 137,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":6},{"slug":"cache","priority":1},{"slug":"forms","priority":4},{"slug":"shop","priority":5}],"enabled":true}',
    'scan_root' => '$.rules',
];
$generatedOrder137 = [
    ['name' => 'priority', 'path' => '$.priority'],
    ['name' => 'slug', 'path' => '$.slug'],
];

$plan137 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder(
    'json_tree',
    $current137,
    $next137,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path']],
    $generatedOrder137,
);

$desc137 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder(
    'json_tree',
    $current137,
    $next137,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path']],
    [
        ['name' => 'priority', 'path' => '$.priority', 'direction' => 'DESC'],
        ['name' => 'slug', 'path' => '$.slug'],
    ],
);

$single137 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder(
    'json_tree',
    $current137,
    $current137,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'key', 'operator' => '=', 'value' => 0],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path']],
    $generatedOrder137,
);

$jsonSource137 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder(
    'json_tree',
    $current137,
    $current137,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'key', 'operator' => '=', 'value' => 0],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path']],
    [['name' => 'enabled', 'source' => 'json', 'path' => '$.enabled']],
);

$unrunnable137 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder(
    'json_tree',
    $current137,
    array_replace($next137, ['option_value' => null]),
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
    [['column' => 'path']],
    $generatedOrder137,
);

$tests = [
    'records dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-generated-order-current-source-next137', $plan137()['dependencies'], true)),
    'keeps path order dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-orderby-cost-current-source-next131', $plan137()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-path-generated-order-source-until-cursor-reset', $plan137()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-path-generated-order-source-plan', $plan137()['nextReaderPolicy']),
    'stable single reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-path-generated-order-source-plan', $single137()['nextReaderPolicy']),
    'stable single does not require replan' => static fn (TestRunner $t) => $t->same(false, $single137()['replanRequired']),
    'generated terms normalized' => static fn (TestRunner $t) => $t->same([
        ['name' => 'priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'ASC'],
        ['name' => 'slug', 'source' => 'value', 'path' => '$.slug', 'direction' => 'ASC'],
    ], $plan137()['currentPathGeneratedOrder']['generatedOrderBy']),
    'selected path signature carried forward' => static fn (TestRunner $t) => $t->same('2:path:LIKE:"$.rules%"', $plan137()['currentPathGeneratedOrder']['selectedPathSignature']),
    'path scan strategy carried forward' => static fn (TestRunner $t) => $t->same('path-constraint-pushdown', $plan137()['currentPathGeneratedOrder']['pathScanStrategy']),
    'path order cost class carried forward' => static fn (TestRunner $t) => $t->same('json-table-path-order-block-sort', $plan137()['currentPathGeneratedOrder']['pathOrderCostClass']),
    'current row count is path-filtered objects' => static fn (TestRunner $t) => $t->same(3, $plan137()['currentPathGeneratedOrder']['rowCount']),
    'next row count includes inserted rule' => static fn (TestRunner $t) => $t->same(4, $plan137()['nextPathGeneratedOrder']['rowCount']),
    'current generated sorter required' => static fn (TestRunner $t) => $t->same(true, $plan137()['currentPathGeneratedOrder']['requiresGeneratedSorter']),
    'next generated sorter required' => static fn (TestRunner $t) => $t->same(true, $plan137()['nextPathGeneratedOrder']['requiresGeneratedSorter']),
    'single row avoids generated sorter' => static fn (TestRunner $t) => $t->same(false, $single137()['currentPathGeneratedOrder']['requiresGeneratedSorter']),
    'current generated keys sort priority then slug' => static fn (TestRunner $t) => $t->same([[2, 'seo'], [4, 'forms'], [7, 'cache']], $plan137()['currentPathGeneratedOrder']['rowGeneratedKeys']),
    'next generated keys sort changed priorities' => static fn (TestRunner $t) => $t->same([[1, 'cache'], [4, 'forms'], [5, 'shop'], [6, 'seo']], $plan137()['nextPathGeneratedOrder']['rowGeneratedKeys']),
    'current rowids follow generated order' => static fn (TestRunner $t) => $t->same([1, 7, 4], $plan137()['currentPathGeneratedOrder']['orderedRowids']),
    'next rowids follow generated order' => static fn (TestRunner $t) => $t->same([4, 7, 10, 1], $plan137()['nextPathGeneratedOrder']['orderedRowids']),
    'current first generated key' => static fn (TestRunner $t) => $t->same([2, 'seo'], $plan137()['currentPathGeneratedOrder']['firstGeneratedKey']),
    'next first generated key' => static fn (TestRunner $t) => $t->same([1, 'cache'], $plan137()['nextPathGeneratedOrder']['firstGeneratedKey']),
    'current last generated key' => static fn (TestRunner $t) => $t->same([7, 'cache'], $plan137()['currentPathGeneratedOrder']['lastGeneratedKey']),
    'next last generated key' => static fn (TestRunner $t) => $t->same([6, 'seo'], $plan137()['nextPathGeneratedOrder']['lastGeneratedKey']),
    'current output tape preserves fullkeys' => static fn (TestRunner $t) => $t->same(['$.rules[0]', '$.rules[2]', '$.rules[1]'], array_column($plan137()['currentPathGeneratedOrder']['generatedOutputTape'], 'fullkey')),
    'next output tape preserves inserted fullkey' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[2]', '$.rules[3]', '$.rules[0]'], array_column($plan137()['nextPathGeneratedOrder']['generatedOutputTape'], 'fullkey')),
    'current output tape path stays base root' => static fn (TestRunner $t) => $t->same(['$.rules', '$.rules', '$.rules'], array_column($plan137()['currentPathGeneratedOrder']['generatedOutputTape'], 'path')),
    'sort penalty reflects three rows two terms' => static fn (TestRunner $t) => $t->same(12, $plan137()['currentPathGeneratedOrder']['generatedSortPenalty']),
    'next sort penalty reflects four rows two terms' => static fn (TestRunner $t) => $t->same(16, $plan137()['nextPathGeneratedOrder']['generatedSortPenalty']),
    'current path effective cost carried' => static fn (TestRunner $t) => $t->same(7, $plan137()['currentPathGeneratedOrder']['pathOrderEffectiveCost']),
    'next path effective cost carried' => static fn (TestRunner $t) => $t->same(9, $plan137()['nextPathGeneratedOrder']['pathOrderEffectiveCost']),
    'current effective cost adds generated sort' => static fn (TestRunner $t) => $t->same(19, $plan137()['currentPathGeneratedOrder']['effectiveEstimatedCost']),
    'next effective cost adds generated sort' => static fn (TestRunner $t) => $t->same(25, $plan137()['nextPathGeneratedOrder']['effectiveEstimatedCost']),
    'cost class is path generated block sort' => static fn (TestRunner $t) => $t->same('json-table-path-generated-order-block-sort', $plan137()['currentPathGeneratedOrder']['costClass']),
    'descending priority current rowids reverse priority' => static fn (TestRunner $t) => $t->same([4, 7, 1], $desc137()['currentPathGeneratedOrder']['orderedRowids']),
    'descending priority next rowids reverse priority' => static fn (TestRunner $t) => $t->same([1, 10, 7, 4], $desc137()['nextPathGeneratedOrder']['orderedRowids']),
    'json source term reads full document flag' => static fn (TestRunner $t) => $t->same([[1]], $jsonSource137()['currentPathGeneratedOrder']['rowGeneratedKeys']),
    'single row cost class is narrow' => static fn (TestRunner $t) => $t->same('json-table-path-generated-order-narrow', $single137()['currentPathGeneratedOrder']['costClass']),
    'transition count records path generated state' => static fn (TestRunner $t) => $t->same(7, count($plan137()['pathGeneratedOrderTransitions'])),
    'generated order transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan137()['pathGeneratedOrderTransitions'][0]['changed']),
    'selected path transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan137()['pathGeneratedOrderTransitions'][1]['changed']),
    'generated keys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan137()['pathGeneratedOrderTransitions'][2]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan137()['pathGeneratedOrderTransitions'][3]['changed']),
    'sorter transition remains stable' => static fn (TestRunner $t) => $t->same(false, $plan137()['pathGeneratedOrderTransitions'][4]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan137()['pathGeneratedOrderTransitions'][5]['changed']),
    'cost class transition stable' => static fn (TestRunner $t) => $t->same(false, $plan137()['pathGeneratedOrderTransitions'][6]['changed']),
    'reasons include generated keys' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-generated-keys-changed', $plan137()['next137ReplanReasons'], true)),
    'reasons include generated order output' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-generated-output-order-changed', $plan137()['next137ReplanReasons'], true)),
    'reasons include generated cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-generated-cost-changed', $plan137()['next137ReplanReasons'], true)),
    'reasons include source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan137()['next137ReplanReasons'], true)),
    'unrunnable next cost class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable137()['nextPathGeneratedOrder']['costClass']),
    'unrunnable next effective cost is sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable137()['nextPathGeneratedOrder']['effectiveEstimatedCost']),
    'unrunnable next output tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable137()['nextPathGeneratedOrder']['generatedOutputTape']),
    'missing generated order rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder('json_tree', $current137, $next137, 'option_value', [], 'scan_root', [], [])),
    'bad generated source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder('json_tree', $current137, $next137, 'option_value', [], 'scan_root', [], [['name' => 'bad', 'source' => 'missing', 'path' => '$.x']])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder('json_tree', $current137, $next137, 'option_value', [], 'scan_root', [], [['name' => 'bad', 'path' => '$[']])),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathGeneratedOrder('json_bad', $current137, $next137, 'option_value', [], 'scan_root', [], $generatedOrder137)),
];

foreach ($tests as $name => $case) {
    $tests['json table path generated order current source next137 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
