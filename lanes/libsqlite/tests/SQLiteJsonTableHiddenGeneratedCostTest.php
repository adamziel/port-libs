<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current148 = [
    'option_id' => 148,
    'option_name' => 'wp_plugin_hidden_generated_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":true},{"slug":"forms","priority":4,"enabled":false}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next148 = [
    'option_id' => 148,
    'option_name' => 'wp_plugin_hidden_generated_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":8,"enabled":false},{"slug":"forms","priority":4,"enabled":false},{"slug":"shop","priority":8,"enabled":true}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];
$constraints148 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 5],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
];
$generated148 = [
    ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
    ['name' => 'priority', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
    ['name' => 'enabled', 'path' => '$.enabled', 'operator' => '=', 'value' => 1],
];

$plan148 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceHiddenGeneratedCost(
    'json_tree',
    $current ?? $current148,
    $next ?? $next148,
    'option_value',
    $constraints ?? $constraints148,
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
    $generated ?? $generated148,
);

$stable148 = static fn (): array => $plan148($current148, $current148);
$covering148 = static fn (): array => $plan148($current148, $current148, $constraints148, [
    ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
    ['name' => 'priority', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9]],
]);
$residual148 = static fn (): array => $plan148($current148, $current148, $constraints148, [
    ['name' => 'slug', 'path' => '$.slug', 'value' => 'cache'],
    ['name' => 'priority', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [5, 9], 'usable' => false],
]);
$miss148 = static fn (): array => $plan148(
    $current148,
    $next148,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[9]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 99],
    ],
);
$unusableHidden148 = static fn (): array => $plan148(
    $current148,
    $current148,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules', 'usable' => false],
        ['column' => 'rowid', 'operator' => '=', 'value' => 5],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
);
$jsonb148 = static fn (): array => $plan148(
    $current148,
    array_replace($current148, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($current148['option_value'])))]),
);
$unrunnable148 = static fn (): array => $plan148($current148, array_replace($next148, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan148()['function']),
    'records hidden generated cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-generated-cost', $plan148()['dependencies'], true)),
    'preserves hidden path generated dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-path-generated', $plan148()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-hidden-generated-cost-source-until-cursor-reset', $plan148()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-hidden-generated-cost-plan', $plan148()['nextReaderPolicy']),
    'stable reuses reader policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-hidden-generated-cost-plan', $stable148()['nextReaderPolicy']),
    'stable has no hidden generated cost replan reasons' => static fn (TestRunner $t) => $t->same([], $stable148()['hiddenGeneratedCostReplanReasons']),
    'current seek signature inherited' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules"&&3:id:=:5', $plan148()['currentHiddenGeneratedCost']['seekSignature']),
    'current source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan148()['currentHiddenGeneratedCost']['sourceKind']),
    'current row matched' => static fn (TestRunner $t) => $t->same(true, $plan148()['currentHiddenGeneratedCost']['matched']),
    'current generated matched' => static fn (TestRunner $t) => $t->same(true, $plan148()['currentHiddenGeneratedCost']['generatedMatched']),
    'next generated filtered' => static fn (TestRunner $t) => $t->same(false, $plan148()['nextHiddenGeneratedCost']['generatedMatched']),
    'current usable generated count' => static fn (TestRunner $t) => $t->same(3, $plan148()['currentHiddenGeneratedCost']['usableGeneratedConstraintCount']),
    'current residual generated count' => static fn (TestRunner $t) => $t->same(0, $plan148()['currentHiddenGeneratedCost']['residualGeneratedConstraintCount']),
    'residual generated count' => static fn (TestRunner $t) => $t->same(1, $residual148()['currentHiddenGeneratedCost']['residualGeneratedConstraintCount']),
    'hidden constraint columns normalized' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan148()['currentHiddenGeneratedCost']['hiddenConstraintColumns']),
    'omit columns include hidden point constraints' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan148()['currentHiddenGeneratedCost']['omitColumns']),
    'visible residual column retained' => static fn (TestRunner $t) => $t->same(['type'], $plan148()['currentHiddenGeneratedCost']['residualColumns']),
    'constraint usage count' => static fn (TestRunner $t) => $t->same(3, count($plan148()['currentHiddenGeneratedCost']['constraintUsage'])),
    'path constraint usage omits' => static fn (TestRunner $t) => $t->same(true, $plan148()['currentHiddenGeneratedCost']['constraintUsage'][0]['omit']),
    'rowid constraint usage omits' => static fn (TestRunner $t) => $t->same(true, $plan148()['currentHiddenGeneratedCost']['constraintUsage'][1]['omit']),
    'type constraint usage remains residual' => static fn (TestRunner $t) => $t->same(false, $plan148()['currentHiddenGeneratedCost']['constraintUsage'][2]['omit']),
    'argv binding indexes assigned' => static fn (TestRunner $t) => $t->same([1, 2, 3], array_column($plan148()['currentHiddenGeneratedCost']['argvBindings'], 'argvIndex')),
    'argv binding columns normalized' => static fn (TestRunner $t) => $t->same(['path', 'id', 'type'], array_column($plan148()['currentHiddenGeneratedCost']['argvBindings'], 'column')),
    'argv binding kinds recorded' => static fn (TestRunner $t) => $t->same(['hidden', 'hidden', 'visible'], array_column($plan148()['currentHiddenGeneratedCost']['argvBindings'], 'kind')),
    'argv binding omit flags recorded' => static fn (TestRunner $t) => $t->same([true, true, false], array_column($plan148()['currentHiddenGeneratedCost']['argvBindings'], 'omit')),
    'unusable hidden path is not bound' => static fn (TestRunner $t) => $t->same(['id', 'type'], array_column($unusableHidden148()['currentHiddenGeneratedCost']['argvBindings'], 'column')),
    'unusable hidden path is not omitted' => static fn (TestRunner $t) => $t->same(['id'], $unusableHidden148()['currentHiddenGeneratedCost']['omitColumns']),
    'current estimated rows covering point' => static fn (TestRunner $t) => $t->same(1, $plan148()['currentHiddenGeneratedCost']['estimatedRows']),
    'next estimated rows filtered to zero' => static fn (TestRunner $t) => $t->same(0, $plan148()['nextHiddenGeneratedCost']['estimatedRows']),
    'current estimated cost includes row estimate' => static fn (TestRunner $t) => $t->same(5, $plan148()['currentHiddenGeneratedCost']['estimatedCost']),
    'next estimated cost includes filtered penalty' => static fn (TestRunner $t) => $t->same(8, $plan148()['nextHiddenGeneratedCost']['estimatedCost']),
    'covering cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-cost-covering-point', $covering148()['currentHiddenGeneratedCost']['costClass']),
    'generated filter cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-cost-generated-filter', $residual148()['currentHiddenGeneratedCost']['costClass']),
    'filtered cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-cost-filtered', $plan148()['nextHiddenGeneratedCost']['costClass']),
    'miss cost class' => static fn (TestRunner $t) => $t->same('json-table-hidden-generated-cost-miss', $miss148()['currentHiddenGeneratedCost']['costClass']),
    'unrunnable cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable148()['nextHiddenGeneratedCost']['costClass']),
    'unrunnable estimated cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable148()['nextHiddenGeneratedCost']['estimatedCost']),
    'current matched rowid propagated' => static fn (TestRunner $t) => $t->same(5, $plan148()['currentHiddenGeneratedCost']['matchedRowid']),
    'current matched fullkey propagated' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan148()['currentHiddenGeneratedCost']['matchedFullkey']),
    'current generated values propagated' => static fn (TestRunner $t) => $t->same(['slug' => 'cache', 'priority' => 7, 'enabled' => 1], $plan148()['currentHiddenGeneratedCost']['generatedValues']),
    'next generated values propagated' => static fn (TestRunner $t) => $t->same(['slug' => 'cache', 'priority' => 8, 'enabled' => 0], $plan148()['nextHiddenGeneratedCost']['generatedValues']),
    'plan fingerprint is stable length' => static fn (TestRunner $t) => $t->same(64, strlen($plan148()['currentHiddenGeneratedCost']['planFingerprint'])),
    'plan fingerprint changes with next values' => static fn (TestRunner $t) => $t->same(true, $plan148()['hiddenGeneratedCostTransitions'][8]['changed']),
    'transition count records cost state' => static fn (TestRunner $t) => $t->same(9, count($plan148()['hiddenGeneratedCostTransitions'])),
    'seek transition stable' => static fn (TestRunner $t) => $t->same(false, $plan148()['hiddenGeneratedCostTransitions'][0]['changed']),
    'source kind transition stable' => static fn (TestRunner $t) => $t->same(false, $plan148()['hiddenGeneratedCostTransitions'][1]['changed']),
    'generated matched transition changes' => static fn (TestRunner $t) => $t->same(true, $plan148()['hiddenGeneratedCostTransitions'][2]['changed']),
    'generated values transition changes' => static fn (TestRunner $t) => $t->same(true, $plan148()['hiddenGeneratedCostTransitions'][3]['changed']),
    'argv transition stable' => static fn (TestRunner $t) => $t->same(false, $plan148()['hiddenGeneratedCostTransitions'][4]['changed']),
    'estimated rows transition changes' => static fn (TestRunner $t) => $t->same(true, $plan148()['hiddenGeneratedCostTransitions'][5]['changed']),
    'estimated cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan148()['hiddenGeneratedCostTransitions'][6]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan148()['hiddenGeneratedCostTransitions'][7]['changed']),
    'reasons include values change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-values-changed', $plan148()['hiddenGeneratedCostReplanReasons'], true)),
    'reasons include row estimate change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-row-estimate-changed', $plan148()['hiddenGeneratedCostReplanReasons'], true)),
    'reasons include estimate change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-estimate-changed', $plan148()['hiddenGeneratedCostReplanReasons'], true)),
    'reasons include fingerprint change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-fingerprint-changed', $plan148()['hiddenGeneratedCostReplanReasons'], true)),
    'reasons preserve hidden path generated value change' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-path-generated-value-changed', $plan148()['hiddenGeneratedCostReplanReasons'], true)),
    'jsonb next source kind changes' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb148()['nextHiddenGeneratedCost']['sourceKind']),
    'jsonb reason includes source kind' => static fn (TestRunner $t) => $t->true(in_array('json-table-hidden-generated-cost-source-kind-changed', $jsonb148()['hiddenGeneratedCostReplanReasons'], true)),
    'missing generated constraints rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenGeneratedCost('json_tree', $current148, $next148, 'option_value', $constraints148, 'scan_root', [], [])),
    'bad generated path rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan148($current148, $next148, $constraints148, [['name' => 'bad', 'path' => '$[', 'value' => 1]])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden generated cost current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
