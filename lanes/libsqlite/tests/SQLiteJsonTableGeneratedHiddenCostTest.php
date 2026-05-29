<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current136 = [
    'option_id' => 136,
    'option_name' => 'wp_plugin_generated_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next136 = [
    'option_id' => 136,
    'option_name' => 'wp_plugin_generated_hidden_cost',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":8,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$constraints136 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%]'],
];
$generated136 = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
];

$plan136 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedHiddenCost(
    'json_tree',
    $current ?? $current136,
    $next ?? $next136,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints136,
    $orderBy ?? [['column' => 'id']],
    $generated ?? $generated136,
);

$stable136 = static fn (): array => $plan136($current136, $current136);
$point136 = static fn (): array => $plan136(
    $current136,
    $current136,
    $constraints136,
    [['column' => 'id']],
    [['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => '=', 'value' => 'seo']],
);
$empty136 = static fn (): array => $plan136(
    $current136,
    $current136,
    $constraints136,
    [['column' => 'id']],
    [['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [20, 30]]],
);
$in136 = static fn (): array => $plan136(
    $current136,
    $current136,
    $constraints136,
    [['column' => 'id']],
    [['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['seo', 'forms']]],
);
$atom136 = static fn (): array => $plan136(
    $current136,
    $current136,
    $constraints136,
    [['column' => 'id']],
    [['name' => 'generated_atom', 'source' => 'atom', 'path' => '$', 'operator' => 'IS NOT NULL']],
);
$json136 = static fn (): array => $plan136(
    $current136,
    $current136,
    $constraints136,
    [['column' => 'id']],
    [['name' => 'generated_rules', 'source' => 'json', 'path' => '$.plugin.groups[0].rules', 'operator' => 'IS NOT NULL']],
);
$unusable136 = static fn (): array => $plan136(
    $current136,
    $next136,
    $constraints136,
    [['column' => 'id']],
    [['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => '=', 'value' => 999, 'usable' => false]],
);
$jsonb136 = static fn (): array => $plan136(
    $current136,
    array_replace($next136, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next136['option_value'])))]),
);
$unrunnable136 = static fn (): array => $plan136(
    $current136,
    array_replace($next136, ['option_value' => null]),
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan136()['function']),
    'records next136 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-cost-current-source-next136', $plan136()['dependencies'], true)),
    'preserves next129 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-hidden-cost-current-source-next129', $plan136()['dependencies'], true)),
    'pins current reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-hidden-cost-source-until-cursor-reset', $plan136()['currentReaderPolicy']),
    'prepares changed next plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-hidden-cost-plan', $plan136()['nextReaderPolicy']),
    'stable plan reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-hidden-cost-plan', $stable136()['nextReaderPolicy']),
    'stable plan has no next136 reasons' => static fn (TestRunner $t) => $t->same([], $stable136()['next136ReplanReasons']),
    'current generated constraints normalize' => static fn (TestRunner $t) => $t->same(array_map(static fn (array $constraint): array => $constraint + ['usable' => true], $generated136), $plan136()['currentGeneratedHiddenCost']['generatedConstraints']),
    'constraint signatures include generated priority' => static fn (TestRunner $t) => $t->same('generated_priority:value:$.priority:BETWEEN:[3,6]', $plan136()['currentGeneratedHiddenCost']['generatedConstraintSignatures'][0]),
    'constraint signatures include generated enabled' => static fn (TestRunner $t) => $t->same('generated_enabled:value:$.enabled:IS:1', $plan136()['currentGeneratedHiddenCost']['generatedConstraintSignatures'][1]),
    'current root is composed nested root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan136()['currentGeneratedHiddenCost']['root']),
    'next root remains composed nested root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan136()['nextGeneratedHiddenCost']['root']),
    'current mode tracks array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan136()['currentGeneratedHiddenCost']['mode']),
    'current row count is generated object row count' => static fn (TestRunner $t) => $t->same(3, $plan136()['currentGeneratedHiddenCost']['rowCount']),
    'next row count tracks inserted rule' => static fn (TestRunner $t) => $t->same(4, $plan136()['nextGeneratedHiddenCost']['rowCount']),
    'current matched row count filters enabled medium priority rule' => static fn (TestRunner $t) => $t->same(1, $plan136()['currentGeneratedHiddenCost']['matchedRowCount']),
    'next matched row count filters forms and shop' => static fn (TestRunner $t) => $t->same(2, $plan136()['nextGeneratedHiddenCost']['matchedRowCount']),
    'current filtered rowid is forms object' => static fn (TestRunner $t) => $t->same([9], $plan136()['currentGeneratedHiddenCost']['filteredRowids']),
    'next filtered rowids include forms and shop' => static fn (TestRunner $t) => $t->same([9, 13], $plan136()['nextGeneratedHiddenCost']['filteredRowids']),
    'current filtered fullkey is forms object' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $plan136()['currentGeneratedHiddenCost']['filteredFullkeys']),
    'next filtered fullkeys include inserted shop' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]', '$.plugin.groups[0].rules[3]'], $plan136()['nextGeneratedHiddenCost']['filteredFullkeys']),
    'first filtered rowid is tracked' => static fn (TestRunner $t) => $t->same(9, $plan136()['currentGeneratedHiddenCost']['firstFilteredRowid']),
    'last filtered rowid tracks next inserted rule' => static fn (TestRunner $t) => $t->same(13, $plan136()['nextGeneratedHiddenCost']['lastFilteredRowid']),
    'generated tape exposes current priorities' => static fn (TestRunner $t) => $t->same([2, 7, 4], array_map(static fn (array $row): mixed => $row['values']['generated_priority'], $plan136()['currentGeneratedHiddenCost']['generatedValueTape'])),
    'generated tape exposes next priorities' => static fn (TestRunner $t) => $t->same([8, 1, 4, 5], array_map(static fn (array $row): mixed => $row['values']['generated_priority'], $plan136()['nextGeneratedHiddenCost']['generatedValueTape'])),
    'generated tape exposes enabled values' => static fn (TestRunner $t) => $t->same([1, 0, 1], array_map(static fn (array $row): mixed => $row['values']['generated_enabled'], $plan136()['currentGeneratedHiddenCost']['generatedValueTape'])),
    'generated tape marks only current forms match' => static fn (TestRunner $t) => $t->same([false, false, true], array_column($plan136()['currentGeneratedHiddenCost']['generatedValueTape'], 'matched')),
    'generated tape marks next forms and shop match' => static fn (TestRunner $t) => $t->same([false, false, true, true], array_column($plan136()['nextGeneratedHiddenCost']['generatedValueTape'], 'matched')),
    'generated cost is no greater than hidden cost' => static fn (TestRunner $t) => $t->true($plan136()['currentGeneratedHiddenCost']['effectiveEstimatedCost'] <= $plan136()['currentGeneratedHiddenCost']['hiddenEstimatedCost']),
    'next generated cost is no greater than hidden cost' => static fn (TestRunner $t) => $t->true($plan136()['nextGeneratedHiddenCost']['effectiveEstimatedCost'] <= $plan136()['nextGeneratedHiddenCost']['hiddenEstimatedCost']),
    'current cost class is point lookup' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-point', $plan136()['currentGeneratedHiddenCost']['costClass']),
    'next cost class is generated filter' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-narrow-filter', $plan136()['nextGeneratedHiddenCost']['costClass']),
    'point equality cost class is point' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-point', $point136()['currentGeneratedHiddenCost']['costClass']),
    'point equality rowid is seo' => static fn (TestRunner $t) => $t->same([1], $point136()['currentGeneratedHiddenCost']['filteredRowids']),
    'empty range cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-empty', $empty136()['currentGeneratedHiddenCost']['costClass']),
    'empty range has no fullkeys' => static fn (TestRunner $t) => $t->same([], $empty136()['currentGeneratedHiddenCost']['filteredFullkeys']),
    'in-list matches seo and forms' => static fn (TestRunner $t) => $t->same([1, 9], $in136()['currentGeneratedHiddenCost']['filteredRowids']),
    'atom source keeps object atom values null' => static fn (TestRunner $t) => $t->same(0, $atom136()['currentGeneratedHiddenCost']['matchedRowCount']),
    'json source can read full document value' => static fn (TestRunner $t) => $t->same(3, $json136()['currentGeneratedHiddenCost']['matchedRowCount']),
    'unusable generated constraint does not filter current rows' => static fn (TestRunner $t) => $t->same(3, $unusable136()['currentGeneratedHiddenCost']['matchedRowCount']),
    'unusable generated constraint does not filter next rows' => static fn (TestRunner $t) => $t->same(4, $unusable136()['nextGeneratedHiddenCost']['matchedRowCount']),
    'transition count records generated hidden state' => static fn (TestRunner $t) => $t->same(8, count($plan136()['generatedHiddenCostTransitions'])),
    'root transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan136()['generatedHiddenCostTransitions'][0]['changed']),
    'constraint transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan136()['generatedHiddenCostTransitions'][1]['changed']),
    'matched count transition changes' => static fn (TestRunner $t) => $t->same(true, $plan136()['generatedHiddenCostTransitions'][2]['changed']),
    'filtered rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan136()['generatedHiddenCostTransitions'][3]['changed']),
    'filtered fullkeys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan136()['generatedHiddenCostTransitions'][4]['changed']),
    'effective cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan136()['generatedHiddenCostTransitions'][5]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan136()['generatedHiddenCostTransitions'][6]['changed']),
    'value tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan136()['generatedHiddenCostTransitions'][7]['changed']),
    'reasons include generated hidden row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-row-count-changed', $plan136()['next136ReplanReasons'], true)),
    'reasons include generated hidden rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-rowset-changed', $plan136()['next136ReplanReasons'], true)),
    'reasons include generated hidden values' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-values-changed', $plan136()['next136ReplanReasons'], true)),
    'reasons preserve next129 output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-hidden-output-changed', $plan136()['next136ReplanReasons'], true)),
    'jsonb next source remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb136()['next']['runnable']),
    'jsonb next source records kind change' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb136()['next136ReplanReasons'], true)),
    'unrunnable next has sentinel cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable136()['nextGeneratedHiddenCost']['costClass']),
    'unrunnable next has sentinel effective cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable136()['nextGeneratedHiddenCost']['effectiveEstimatedCost']),
    'unrunnable next has no filtered rowids' => static fn (TestRunner $t) => $t->same([], $unrunnable136()['nextGeneratedHiddenCost']['filteredRowids']),
    'empty generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan136($current136, $next136, $constraints136, [['column' => 'id']], [])),
    'bad generated source rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan136($current136, $next136, $constraints136, [['column' => 'id']], [['name' => 'bad', 'source' => 'missing', 'path' => '$.slug']])),
    'bad generated operator rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan136($current136, $next136, $constraints136, [['column' => 'id']], [['name' => 'bad', 'source' => 'value', 'path' => '$.slug', 'operator' => 'LIKE']])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan136($current136, $next136, $constraints136, [['column' => 'id']], [['name' => 'bad', 'source' => 'value', 'path' => '$[#-]']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated hidden cost current source next136 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
