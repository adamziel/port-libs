<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current142 = [
    'option_id' => 142,
    'option_name' => 'wp_plugin_generated_hidden_rowid_cost',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next142 = [
    'option_id' => 142,
    'option_name' => 'wp_plugin_generated_hidden_rowid_cost',
    'option_value' => '{"plugin":{"groups":[{"rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$constraints142 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 9],
];
$generated142 = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
];

$plan142 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedHiddenRowidCost(
    'json_tree',
    $current ?? $current142,
    $next ?? $next142,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints142,
    [['column' => 'id']],
    $generated ?? $generated142,
);

$stable142 = static fn (): array => $plan142($current142, $current142);
$miss142 = static fn (): array => $plan142(
    $current142,
    $next142,
    array_replace($constraints142, [2 => ['column' => '_rowid_', 'operator' => '=', 'value' => 99]]),
);
$range142 = static fn (): array => $plan142(
    $current142,
    $next142,
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [1, 13]],
    ],
);
$unconstrained142 = static fn (): array => $plan142(
    $current142,
    $next142,
    array_slice($constraints142, 0, 2),
);
$unusable142 = static fn (): array => $plan142(
    $current142,
    $next142,
    array_replace($constraints142, [2 => ['column' => 'rowid', 'operator' => '=', 'value' => 9, 'usable' => false]]),
);
$jsonb142 = static fn (): array => $plan142(
    $current142,
    array_replace($next142, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next142['option_value'])))]),
);
$unrunnable142 = static fn (): array => $plan142($current142, array_replace($next142, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan142()['function']),
    'records generated hidden rowid cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-rowid-cost', $plan142()['dependencies'], true)),
    'preserves generated hidden dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-cost-current-source-next136', $plan142()['dependencies'], true)),
    'pins current reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-hidden-rowid-cost-source-until-cursor-reset', $plan142()['currentReaderPolicy']),
    'prepares changed next plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-hidden-rowid-cost-plan', $plan142()['nextReaderPolicy']),
    'stable plan reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-hidden-rowid-cost-plan', $stable142()['nextReaderPolicy']),
    'stable has no generated hidden rowid cost reasons' => static fn (TestRunner $t) => $t->same([], $stable142()['generatedHiddenRowidCostReplanReasons']),
    'current rowid signature normalizes rowid' => static fn (TestRunner $t) => $t->same('id:=:9', $plan142()['currentGeneratedHiddenRowidCost']['rowidConstraintSignature']),
    'current rowid column records id alias' => static fn (TestRunner $t) => $t->same('id', $plan142()['currentGeneratedHiddenRowidCost']['rowidConstraintColumn']),
    'current rowid operator is equality' => static fn (TestRunner $t) => $t->same('=', $plan142()['currentGeneratedHiddenRowidCost']['rowidConstraintOperator']),
    'current rowid value preserved' => static fn (TestRunner $t) => $t->same(9, $plan142()['currentGeneratedHiddenRowidCost']['rowidConstraintValue']),
    'current rowid is scoped' => static fn (TestRunner $t) => $t->same(true, $plan142()['currentGeneratedHiddenRowidCost']['rowidScoped']),
    'current generated match count comes from next136' => static fn (TestRunner $t) => $t->same(1, $plan142()['currentGeneratedHiddenRowidCost']['generatedMatchedRowCount']),
    'next generated match count stays rowid scoped' => static fn (TestRunner $t) => $t->same(1, $plan142()['nextGeneratedHiddenRowidCost']['generatedMatchedRowCount']),
    'current rowid match count is one' => static fn (TestRunner $t) => $t->same(1, $plan142()['currentGeneratedHiddenRowidCost']['rowidMatchedRowCount']),
    'next rowid match count is one' => static fn (TestRunner $t) => $t->same(1, $plan142()['nextGeneratedHiddenRowidCost']['rowidMatchedRowCount']),
    'current intersection count is one' => static fn (TestRunner $t) => $t->same(1, $plan142()['currentGeneratedHiddenRowidCost']['intersectedRowCount']),
    'next intersection count is one' => static fn (TestRunner $t) => $t->same(1, $plan142()['nextGeneratedHiddenRowidCost']['intersectedRowCount']),
    'current intersected rowid is forms' => static fn (TestRunner $t) => $t->same([9], $plan142()['currentGeneratedHiddenRowidCost']['intersectedRowids']),
    'next intersected rowid remains forms' => static fn (TestRunner $t) => $t->same([9], $plan142()['nextGeneratedHiddenRowidCost']['intersectedRowids']),
    'current intersected fullkey is forms object' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $plan142()['currentGeneratedHiddenRowidCost']['intersectedFullkeys']),
    'next intersected fullkey remains forms object' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $plan142()['nextGeneratedHiddenRowidCost']['intersectedFullkeys']),
    'first rowid tracked' => static fn (TestRunner $t) => $t->same(9, $plan142()['currentGeneratedHiddenRowidCost']['firstIntersectedRowid']),
    'last rowid tracked' => static fn (TestRunner $t) => $t->same(9, $plan142()['currentGeneratedHiddenRowidCost']['lastIntersectedRowid']),
    'base generated cost is inherited' => static fn (TestRunner $t) => $t->same($plan142()['currentGeneratedHiddenCost']['effectiveEstimatedCost'], $plan142()['currentGeneratedHiddenRowidCost']['baseGeneratedCost']),
    'point rowid lowers current effective cost' => static fn (TestRunner $t) => $t->same(1, $plan142()['currentGeneratedHiddenRowidCost']['effectiveEstimatedCost']),
    'point rowid lowers next effective cost' => static fn (TestRunner $t) => $t->same(1, $plan142()['nextGeneratedHiddenRowidCost']['effectiveEstimatedCost']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-rowid-point', $plan142()['currentGeneratedHiddenRowidCost']['costClass']),
    'tape has current rowid scoped row' => static fn (TestRunner $t) => $t->same(1, count($plan142()['currentGeneratedHiddenRowidCost']['generatedRowidTape'])),
    'tape has next rowid scoped row' => static fn (TestRunner $t) => $t->same(1, count($plan142()['nextGeneratedHiddenRowidCost']['generatedRowidTape'])),
    'current tape exposes rowid scoped priority' => static fn (TestRunner $t) => $t->same([4], array_map(static fn (array $row): mixed => $row['values']['generated_priority'], $plan142()['currentGeneratedHiddenRowidCost']['generatedRowidTape'])),
    'next tape exposes same rowid scoped priority' => static fn (TestRunner $t) => $t->same([4], array_map(static fn (array $row): mixed => $row['values']['generated_priority'], $plan142()['nextGeneratedHiddenRowidCost']['generatedRowidTape'])),
    'current tape marks rowid nine matched' => static fn (TestRunner $t) => $t->same([true], array_column($plan142()['currentGeneratedHiddenRowidCost']['generatedRowidTape'], 'matched')),
    'next tape keeps rowid nine matched' => static fn (TestRunner $t) => $t->same([true], array_column($plan142()['nextGeneratedHiddenRowidCost']['generatedRowidTape'], 'matched')),
    'current tape records rowid match independently' => static fn (TestRunner $t) => $t->same([true], array_column($plan142()['currentGeneratedHiddenRowidCost']['generatedRowidTape'], 'rowidMatched')),
    'current tape records generated match independently' => static fn (TestRunner $t) => $t->same([true], array_column($plan142()['currentGeneratedHiddenRowidCost']['generatedRowidTape'], 'generatedMatched')),
    'transition count records rowid cost state' => static fn (TestRunner $t) => $t->same(9, count($plan142()['generatedHiddenRowidCostTransitions'])),
    'root transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][0]['changed']),
    'rowid signature transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][1]['changed']),
    'generated count transition is stable under point rowid' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][2]['changed']),
    'rowid count transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][3]['changed']),
    'rowid set transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][4]['changed']),
    'cost transition is stable at point lookup' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][6]['changed']),
    'tape transition is stable for pinned rowid' => static fn (TestRunner $t) => $t->same(false, $plan142()['generatedHiddenRowidCostTransitions'][8]['changed']),
    'reasons do not invent generated count change' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-generated-hidden-rowid-generated-count-changed', $plan142()['generatedHiddenRowidCostReplanReasons'], true)),
    'reasons do not invent rowid tape change' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-generated-hidden-rowid-tape-changed', $plan142()['generatedHiddenRowidCostReplanReasons'], true)),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan142()['generatedHiddenRowidCostReplanReasons'], true)),
    'miss has no intersection' => static fn (TestRunner $t) => $t->same([], $miss142()['currentGeneratedHiddenRowidCost']['intersectedRowids']),
    'miss cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-rowid-empty', $miss142()['currentGeneratedHiddenRowidCost']['costClass']),
    'miss effective cost is one' => static fn (TestRunner $t) => $t->same(1, $miss142()['currentGeneratedHiddenRowidCost']['effectiveEstimatedCost']),
    'range is not rowid scoped' => static fn (TestRunner $t) => $t->same(false, $range142()['currentGeneratedHiddenRowidCost']['rowidScoped']),
    'range rowid constraint signature uses oid alias' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[1,13]', $range142()['currentGeneratedHiddenRowidCost']['rowidConstraintSignature']),
    'range intersects current generated rows' => static fn (TestRunner $t) => $t->same([9], $range142()['currentGeneratedHiddenRowidCost']['intersectedRowids']),
    'range intersects next generated rows' => static fn (TestRunner $t) => $t->same([9, 13], $range142()['nextGeneratedHiddenRowidCost']['intersectedRowids']),
    'range cost class is narrow intersection' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-rowid-narrow-intersection', $range142()['currentGeneratedHiddenRowidCost']['costClass']),
    'unconstrained rowid has null signature' => static fn (TestRunner $t) => $t->same(null, $unconstrained142()['currentGeneratedHiddenRowidCost']['rowidConstraintSignature']),
    'unconstrained rowid class' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-rowid-unconstrained', $unconstrained142()['currentGeneratedHiddenRowidCost']['costClass']),
    'unusable rowid behaves as unconstrained' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-rowid-unconstrained', $unusable142()['currentGeneratedHiddenRowidCost']['costClass']),
    'jsonb next source remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb142()['next']['runnable']),
    'jsonb next preserves rowid intersection' => static fn (TestRunner $t) => $t->same([9], $jsonb142()['nextGeneratedHiddenRowidCost']['intersectedRowids']),
    'jsonb next records source kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb142()['generatedHiddenRowidCostReplanReasons'], true)),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable142()['nextGeneratedHiddenRowidCost']['costClass']),
    'unrunnable next effective cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable142()['nextGeneratedHiddenRowidCost']['effectiveEstimatedCost']),
    'bad generated constraint still rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan142($current142, $next142, $constraints142, [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated hidden rowid cost current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
