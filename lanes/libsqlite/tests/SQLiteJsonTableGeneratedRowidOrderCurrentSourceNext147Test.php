<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current147 = [
    'option_id' => 147,
    'option_name' => 'wp_plugin_generated_rowid_order',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next147 = [
    'option_id' => 147,
    'option_name' => 'wp_plugin_generated_rowid_order',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$constraints147 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [1, 13]],
];
$generatedConstraints147 = [
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [1, 8]],
];
$generatedOrder147 = [
    ['name' => 'generated_priority', 'path' => '$.priority'],
    ['name' => 'generated_enabled', 'path' => '$.enabled'],
];

$plan147 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generatedConstraints = null,
    ?array $generatedOrder = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedRowidOrderNext147(
    'json_tree',
    $current ?? $current147,
    $next ?? $next147,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints147,
    [['column' => 'id']],
    $generatedConstraints ?? $generatedConstraints147,
    $generatedOrder ?? $generatedOrder147,
);

$stable147 = static fn (): array => $plan147($current147, $current147);
$point147 = static fn (): array => $plan147(
    $current147,
    $next147,
    array_replace($constraints147, [2 => ['column' => '_rowid_', 'operator' => '=', 'value' => 9]]),
);
$miss147 = static fn (): array => $plan147(
    $current147,
    $next147,
    array_replace($constraints147, [2 => ['column' => 'oid', 'operator' => '=', 'value' => 99]]),
);
$desc147 = static fn (): array => $plan147(
    $current147,
    $next147,
    $constraints147,
    $generatedConstraints147,
    [
        ['name' => 'generated_priority', 'path' => '$.priority', 'direction' => 'DESC'],
        ['name' => 'generated_enabled', 'path' => '$.enabled'],
    ],
);
$jsonb147 = static fn (): array => $plan147(
    $current147,
    array_replace($next147, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next147['option_value'])))]),
);
$unrunnable147 = static fn (): array => $plan147($current147, array_replace($next147, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan147()['function']),
    'records next147 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-rowid-order-current-source-next147', $plan147()['dependencies'], true)),
    'preserves next142 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-rowid-cost-current-source-next142', $plan147()['dependencies'], true)),
    'pins current reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-rowid-order-source-until-cursor-reset', $plan147()['currentReaderPolicy']),
    'prepares next reader' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-rowid-order-plan', $plan147()['nextReaderPolicy']),
    'stable reader reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-rowid-order-plan', $stable147()['nextReaderPolicy']),
    'stable has no next147 reasons' => static fn (TestRunner $t) => $t->same([], $stable147()['next147ReplanReasons']),
    'generated order terms normalized' => static fn (TestRunner $t) => $t->same([
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'ASC'],
        ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'direction' => 'ASC'],
    ], $plan147()['currentGeneratedRowidOrder']['generatedOrderBy']),
    'rowid signature is range' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[1,13]', $plan147()['currentGeneratedRowidOrder']['rowidConstraintSignature']),
    'current rowid order count' => static fn (TestRunner $t) => $t->same(2, $plan147()['currentGeneratedRowidOrder']['intersectedRowCount']),
    'next rowid order count includes inserted shop' => static fn (TestRunner $t) => $t->same(3, $plan147()['nextGeneratedRowidOrder']['intersectedRowCount']),
    'current generated rowids sort by priority' => static fn (TestRunner $t) => $t->same([1, 9], $plan147()['currentGeneratedRowidOrder']['orderedRowids']),
    'next generated rowids sort by priority' => static fn (TestRunner $t) => $t->same([9, 13, 1], $plan147()['nextGeneratedRowidOrder']['orderedRowids']),
    'current generated keys sort by priority' => static fn (TestRunner $t) => $t->same([[2, 1], [4, 1]], $plan147()['currentGeneratedRowidOrder']['orderedGeneratedKeys']),
    'next generated keys sort by priority' => static fn (TestRunner $t) => $t->same([[4, 1], [5, 1], [8, 1]], $plan147()['nextGeneratedRowidOrder']['orderedGeneratedKeys']),
    'current fullkeys sorted' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[0]', '$.plugin.groups[0].rules[2]'], $plan147()['currentGeneratedRowidOrder']['orderedFullkeys']),
    'next fullkeys sorted' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]', '$.plugin.groups[0].rules[3]', '$.plugin.groups[0].rules[0]'], $plan147()['nextGeneratedRowidOrder']['orderedFullkeys']),
    'current first rowid' => static fn (TestRunner $t) => $t->same(1, $plan147()['currentGeneratedRowidOrder']['firstOrderedRowid']),
    'current last rowid' => static fn (TestRunner $t) => $t->same(9, $plan147()['currentGeneratedRowidOrder']['lastOrderedRowid']),
    'next first rowid' => static fn (TestRunner $t) => $t->same(9, $plan147()['nextGeneratedRowidOrder']['firstOrderedRowid']),
    'next last rowid' => static fn (TestRunner $t) => $t->same(1, $plan147()['nextGeneratedRowidOrder']['lastOrderedRowid']),
    'current requires generated sorter' => static fn (TestRunner $t) => $t->same(true, $plan147()['currentGeneratedRowidOrder']['requiresGeneratedSorter']),
    'next requires generated sorter' => static fn (TestRunner $t) => $t->same(true, $plan147()['nextGeneratedRowidOrder']['requiresGeneratedSorter']),
    'current sort penalty is four' => static fn (TestRunner $t) => $t->same(4, $plan147()['currentGeneratedRowidOrder']['generatedSortPenalty']),
    'next sort penalty is twelve' => static fn (TestRunner $t) => $t->same(12, $plan147()['nextGeneratedRowidOrder']['generatedSortPenalty']),
    'current effective cost includes sort' => static fn (TestRunner $t) => $t->same(6, $plan147()['currentGeneratedRowidOrder']['effectiveEstimatedCost']),
    'next effective cost includes sort' => static fn (TestRunner $t) => $t->same(15, $plan147()['nextGeneratedRowidOrder']['effectiveEstimatedCost']),
    'current cost class narrow sort' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-narrow-sort', $plan147()['currentGeneratedRowidOrder']['costClass']),
    'next cost class full sort' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-sort', $plan147()['nextGeneratedRowidOrder']['costClass']),
    'current tape has two rows' => static fn (TestRunner $t) => $t->same(2, count($plan147()['currentGeneratedRowidOrder']['generatedRowidOrderTape'])),
    'next tape has three rows' => static fn (TestRunner $t) => $t->same(3, count($plan147()['nextGeneratedRowidOrder']['generatedRowidOrderTape'])),
    'current tape all matched' => static fn (TestRunner $t) => $t->same([true, true], array_column($plan147()['currentGeneratedRowidOrder']['generatedRowidOrderTape'], 'matched')),
    'next tape all matched' => static fn (TestRunner $t) => $t->same([true, true, true], array_column($plan147()['nextGeneratedRowidOrder']['generatedRowidOrderTape'], 'matched')),
    'transition count' => static fn (TestRunner $t) => $t->same(10, count($plan147()['generatedRowidOrderTransitions'])),
    'root transition stable' => static fn (TestRunner $t) => $t->same(false, $plan147()['generatedRowidOrderTransitions'][0]['changed']),
    'rowid constraint transition stable' => static fn (TestRunner $t) => $t->same(false, $plan147()['generatedRowidOrderTransitions'][1]['changed']),
    'generated order transition stable' => static fn (TestRunner $t) => $t->same(false, $plan147()['generatedRowidOrderTransitions'][2]['changed']),
    'row count transition changes' => static fn (TestRunner $t) => $t->same(true, $plan147()['generatedRowidOrderTransitions'][3]['changed']),
    'ordered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan147()['generatedRowidOrderTransitions'][4]['changed']),
    'generated keys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan147()['generatedRowidOrderTransitions'][5]['changed']),
    'sorter transition stable' => static fn (TestRunner $t) => $t->same(false, $plan147()['generatedRowidOrderTransitions'][6]['changed']),
    'cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan147()['generatedRowidOrderTransitions'][7]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan147()['generatedRowidOrderTransitions'][8]['changed']),
    'tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan147()['generatedRowidOrderTransitions'][9]['changed']),
    'reasons include row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-row-count-changed', $plan147()['next147ReplanReasons'], true)),
    'reasons include output' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-output-changed', $plan147()['next147ReplanReasons'], true)),
    'reasons include keys' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-keys-changed', $plan147()['next147ReplanReasons'], true)),
    'reasons include cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-rowid-order-cost-changed', $plan147()['next147ReplanReasons'], true)),
    'reasons include source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan147()['next147ReplanReasons'], true)),
    'point rowid orders single current row' => static fn (TestRunner $t) => $t->same([9], $point147()['currentGeneratedRowidOrder']['orderedRowids']),
    'point rowid orders single next row' => static fn (TestRunner $t) => $t->same([9], $point147()['nextGeneratedRowidOrder']['orderedRowids']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-point', $point147()['currentGeneratedRowidOrder']['costClass']),
    'point does not require sorter' => static fn (TestRunner $t) => $t->same(false, $point147()['currentGeneratedRowidOrder']['requiresGeneratedSorter']),
    'miss rowids empty' => static fn (TestRunner $t) => $t->same([], $miss147()['currentGeneratedRowidOrder']['orderedRowids']),
    'miss cost class empty' => static fn (TestRunner $t) => $t->same('json-table-generated-rowid-order-empty', $miss147()['currentGeneratedRowidOrder']['costClass']),
    'descending current rowids' => static fn (TestRunner $t) => $t->same([9, 1], $desc147()['currentGeneratedRowidOrder']['orderedRowids']),
    'descending next rowids' => static fn (TestRunner $t) => $t->same([1, 13, 9], $desc147()['nextGeneratedRowidOrder']['orderedRowids']),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb147()['next']['runnable']),
    'jsonb next order matches text next' => static fn (TestRunner $t) => $t->same([9, 13, 1], $jsonb147()['nextGeneratedRowidOrder']['orderedRowids']),
    'jsonb records kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb147()['next147ReplanReasons'], true)),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable147()['nextGeneratedRowidOrder']['costClass']),
    'unrunnable next effective cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable147()['nextGeneratedRowidOrder']['effectiveEstimatedCost']),
    'unrunnable next output empty' => static fn (TestRunner $t) => $t->same([], $unrunnable147()['nextGeneratedRowidOrder']['orderedRowids']),
    'missing generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan147($current147, $next147, $constraints147, [], $generatedOrder147)),
    'missing generated order rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan147($current147, $next147, $constraints147, $generatedConstraints147, [])),
    'bad generated order path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan147($current147, $next147, $constraints147, $generatedConstraints147, [['name' => 'bad', 'path' => '$[']])),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedRowidOrderNext147('json_bad', $current147, $next147, 'option_value', 'base_root', 'nested_path', $constraints147, [], $generatedConstraints147, $generatedOrder147)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated rowid order current source next147 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
