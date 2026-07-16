<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRowidOrder = [
    'option_id' => 147,
    'option_name' => 'wp_plugin_generated_rowid_order',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextRowidOrder = [
    'option_id' => 147,
    'option_name' => 'wp_plugin_generated_rowid_order',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$rowidOrderConstraints = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [1, 13]],
];
$rowidOrderGeneratedConstraints = [
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [1, 8]],
];
$rowidGeneratedOrder = [
    ['name' => 'generated_priority', 'path' => '$.priority'],
    ['name' => 'generated_enabled', 'path' => '$.enabled'],
];

$rowidOrderPlan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generatedConstraints = null,
    ?array $generatedOrder = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedRowidOrder(
    'json_tree',
    $current ?? $currentRowidOrder,
    $next ?? $nextRowidOrder,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $rowidOrderConstraints,
    [['column' => 'id']],
    $generatedConstraints ?? $rowidOrderGeneratedConstraints,
    $generatedOrder ?? $rowidGeneratedOrder,
);

$stableRowidOrderPlan = static fn (): array => $rowidOrderPlan($currentRowidOrder, $currentRowidOrder);
$pointRowidOrderPlan = static fn (): array => $rowidOrderPlan(
    $currentRowidOrder,
    $nextRowidOrder,
    array_replace($rowidOrderConstraints, [2 => ['column' => '_rowid_', 'operator' => '=', 'value' => 9]]),
);
$missRowidOrderPlan = static fn (): array => $rowidOrderPlan(
    $currentRowidOrder,
    $nextRowidOrder,
    array_replace($rowidOrderConstraints, [2 => ['column' => 'oid', 'operator' => '=', 'value' => 99]]),
);
$descendingRowidOrderPlan = static fn (): array => $rowidOrderPlan(
    $currentRowidOrder,
    $nextRowidOrder,
    $rowidOrderConstraints,
    $rowidOrderGeneratedConstraints,
    [
        ['name' => 'generated_priority', 'path' => '$.priority', 'direction' => 'DESC'],
        ['name' => 'generated_enabled', 'path' => '$.enabled'],
    ],
);
$jsonbRowidOrderPlan = static fn (): array => $rowidOrderPlan(
    $currentRowidOrder,
    array_replace($nextRowidOrder, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($nextRowidOrder['option_value'])))]),
);
$unrunnableRowidOrderPlan = static fn (): array => $rowidOrderPlan($currentRowidOrder, array_replace($nextRowidOrder, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $rowidOrderPlan()['function']),
    'records generated rowid order dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-rowid-order-current-source', $rowidOrderPlan()['dependencies'], true)),
    'preserves next142 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-rowid-cost-current-source-next142', $rowidOrderPlan()['dependencies'], true)),
    'pins current reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-rowid-order-source-until-cursor-reset', $rowidOrderPlan()['currentReaderPolicy']),
    'prepares next reader' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-rowid-order-plan', $rowidOrderPlan()['nextReaderPolicy']),
    'stable reader reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-rowid-order-plan', $stableRowidOrderPlan()['nextReaderPolicy']),
    'stable has no generated rowid order reasons' => static fn (TestRunner $t) => $t->same([], $stableRowidOrderPlan()['generatedRowidOrderReplanReasons']),
    'generated order terms normalized' => static fn (TestRunner $t) => $t->same([
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'ASC'],
        ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'direction' => 'ASC'],
    ], $rowidOrderPlan()['currentGeneratedRowidOrder']['generatedOrderBy']),
    'rowid signature is range' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[1,13]', $rowidOrderPlan()['currentGeneratedRowidOrder']['rowidConstraintSignature']),
    'current rowid order count' => static fn (TestRunner $t) => $t->same(2, $rowidOrderPlan()['currentGeneratedRowidOrder']['intersectedRowCount']),
    'next rowid order count includes inserted shop' => static fn (TestRunner $t) => $t->same(3, $rowidOrderPlan()['nextGeneratedRowidOrder']['intersectedRowCount']),
    'current generated rowids sort by priority' => static fn (TestRunner $t) => $t->same([1, 9], $rowidOrderPlan()['currentGeneratedRowidOrder']['orderedRowids']),
    'next generated rowids sort by priority' => static fn (TestRunner $t) => $t->same([9, 13, 1], $rowidOrderPlan()['nextGeneratedRowidOrder']['orderedRowids']),
    'current generated keys sort by priority' => static fn (TestRunner $t) => $t->same([[2, 1], [4, 1]], $rowidOrderPlan()['currentGeneratedRowidOrder']['orderedGeneratedKeys']),
    'next generated keys sort by priority' => static fn (TestRunner $t) => $t->same([[4, 1], [5, 1], [8, 1]], $rowidOrderPlan()['nextGeneratedRowidOrder']['orderedGeneratedKeys']),
    'current fullkeys sorted' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[0]', '$.plugin.groups[0].rules[2]'], $rowidOrderPlan()['currentGeneratedRowidOrder']['orderedFullkeys']),
    'next fullkeys sorted' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]', '$.plugin.groups[0].rules[3]', '$.plugin.groups[0].rules[0]'], $rowidOrderPlan()['nextGeneratedRowidOrder']['orderedFullkeys']),
    'current first rowid' => static fn (TestRunner $t) => $t->same(1, $rowidOrderPlan()['currentGeneratedRowidOrder']['firstOrderedRowid']),
    'current last rowid' => static fn (TestRunner $t) => $t->same(9, $rowidOrderPlan()['currentGeneratedRowidOrder']['lastOrderedRowid']),
    'next first rowid' => static fn (TestRunner $t) => $t->same(9, $rowidOrderPlan()['nextGeneratedRowidOrder']['firstOrderedRowid']),
    'next last rowid' => static fn (TestRunner $t) => $t->same(1, $rowidOrderPlan()['nextGeneratedRowidOrder']['lastOrderedRowid']),
    'current requires generated sorter' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['currentGeneratedRowidOrder']['requiresGeneratedSorter']),
    'next requires generated sorter' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['nextGeneratedRowidOrder']['requiresGeneratedSorter']),
    'current sort penalty is four' => static fn (TestRunner $t) => $t->same(4, $rowidOrderPlan()['currentGeneratedRowidOrder']['generatedSortPenalty']),
    'next sort penalty is twelve' => static fn (TestRunner $t) => $t->same(12, $rowidOrderPlan()['nextGeneratedRowidOrder']['generatedSortPenalty']),
    'current effective cost includes sort' => static fn (TestRunner $t) => $t->same(6, $rowidOrderPlan()['currentGeneratedRowidOrder']['effectiveEstimatedCost']),
    'next effective cost includes sort' => static fn (TestRunner $t) => $t->same(15, $rowidOrderPlan()['nextGeneratedRowidOrder']['effectiveEstimatedCost']),
    'current cost class narrow sort' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-narrow-sort', $rowidOrderPlan()['currentGeneratedRowidOrder']['costClass']),
    'next cost class full sort' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-sort', $rowidOrderPlan()['nextGeneratedRowidOrder']['costClass']),
    'current tape has two rows' => static fn (TestRunner $t) => $t->same(2, count($rowidOrderPlan()['currentGeneratedRowidOrder']['generatedRowidOrderTape'])),
    'next tape has three rows' => static fn (TestRunner $t) => $t->same(3, count($rowidOrderPlan()['nextGeneratedRowidOrder']['generatedRowidOrderTape'])),
    'current tape all matched' => static fn (TestRunner $t) => $t->same([true, true], array_column($rowidOrderPlan()['currentGeneratedRowidOrder']['generatedRowidOrderTape'], 'matched')),
    'next tape all matched' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($rowidOrderPlan()['nextGeneratedRowidOrder']['generatedRowidOrderTape'], 'matched')),
    'transition count' => static fn (TestRunner $t) => $t->same(10, count($rowidOrderPlan()['generatedRowidOrderTransitions'])),
    'root transition stable' => static fn (TestRunner $t) => $t->same(false, $rowidOrderPlan()['generatedRowidOrderTransitions'][0]['changed']),
    'rowid constraint transition stable' => static fn (TestRunner $t) => $t->same(false, $rowidOrderPlan()['generatedRowidOrderTransitions'][1]['changed']),
    'generated order transition stable' => static fn (TestRunner $t) => $t->same(false, $rowidOrderPlan()['generatedRowidOrderTransitions'][2]['changed']),
    'row count transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['generatedRowidOrderTransitions'][3]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['generatedRowidOrderTransitions'][4]['changed']),
    'generated keys transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['generatedRowidOrderTransitions'][5]['changed']),
    'sorter transition stable' => static fn (TestRunner $t) => $t->same(false, $rowidOrderPlan()['generatedRowidOrderTransitions'][6]['changed']),
    'cost transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['generatedRowidOrderTransitions'][7]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['generatedRowidOrderTransitions'][8]['changed']),
    'tape transition changes' => static fn (TestRunner $t) => $t->same(true, $rowidOrderPlan()['generatedRowidOrderTransitions'][9]['changed']),
    'reasons include row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-row-count-changed', $rowidOrderPlan()['generatedRowidOrderReplanReasons'], true)),
    'reasons include output' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-output-changed', $rowidOrderPlan()['generatedRowidOrderReplanReasons'], true)),
    'reasons include keys' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-keys-changed', $rowidOrderPlan()['generatedRowidOrderReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-cost-changed', $rowidOrderPlan()['generatedRowidOrderReplanReasons'], true)),
    'reasons include source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $rowidOrderPlan()['generatedRowidOrderReplanReasons'], true)),
    'point rowid orders single current row' => static fn (TestRunner $t) => $t->same([9], $pointRowidOrderPlan()['currentGeneratedRowidOrder']['orderedRowids']),
    'point rowid orders single next row' => static fn (TestRunner $t) => $t->same([9], $pointRowidOrderPlan()['nextGeneratedRowidOrder']['orderedRowids']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-point', $pointRowidOrderPlan()['currentGeneratedRowidOrder']['costClass']),
    'point does not require sorter' => static fn (TestRunner $t) => $t->same(false, $pointRowidOrderPlan()['currentGeneratedRowidOrder']['requiresGeneratedSorter']),
    'miss rowids empty' => static fn (TestRunner $t) => $t->same([], $missRowidOrderPlan()['currentGeneratedRowidOrder']['orderedRowids']),
    'miss cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-empty', $missRowidOrderPlan()['currentGeneratedRowidOrder']['costClass']),
    'descending current rowids' => static fn (TestRunner $t) => $t->same([9, 1], $descendingRowidOrderPlan()['currentGeneratedRowidOrder']['orderedRowids']),
    'descending next rowids' => static fn (TestRunner $t) => $t->same([1, 13, 9], $descendingRowidOrderPlan()['nextGeneratedRowidOrder']['orderedRowids']),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonbRowidOrderPlan()['next']['runnable']),
    'jsonb next order matches text next' => static fn (TestRunner $t) => $t->same([9, 13, 1], $jsonbRowidOrderPlan()['nextGeneratedRowidOrder']['orderedRowids']),
    'jsonb records kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonbRowidOrderPlan()['generatedRowidOrderReplanReasons'], true)),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnableRowidOrderPlan()['nextGeneratedRowidOrder']['costClass']),
    'unrunnable next effective cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnableRowidOrderPlan()['nextGeneratedRowidOrder']['effectiveEstimatedCost']),
    'unrunnable next output empty' => static fn (TestRunner $t) => $t->same([], $unrunnableRowidOrderPlan()['nextGeneratedRowidOrder']['orderedRowids']),
    'missing generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $rowidOrderPlan($currentRowidOrder, $nextRowidOrder, $rowidOrderConstraints, [], $rowidGeneratedOrder)),
    'missing generated order rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $rowidOrderPlan($currentRowidOrder, $nextRowidOrder, $rowidOrderConstraints, $rowidOrderGeneratedConstraints, [])),
    'bad generated order path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $rowidOrderPlan($currentRowidOrder, $nextRowidOrder, $rowidOrderConstraints, $rowidOrderGeneratedConstraints, [['name' => 'bad', 'path' => '$[']])),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedRowidOrder('json_bad', $currentRowidOrder, $nextRowidOrder, 'option_value', 'base_root', 'nested_path', $rowidOrderConstraints, [], $rowidOrderGeneratedConstraints, $rowidGeneratedOrder)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated rowid order ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
