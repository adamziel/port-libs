<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current144 = [
    'option_id' => 144,
    'option_name' => 'wp_plugin_generated_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":5,"enabled":true},{"slug":"cart","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'active_path' => '[0].rules',
];
$next144 = [
    'option_id' => 144,
    'option_name' => 'wp_plugin_generated_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":5,"enabled":true},{"slug":"cart","priority":6,"enabled":true},{"slug":"coupons","priority":9,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'active_path' => '[1].rules',
];
$constraints144 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[%].rules[%]'],
];
$generated144 = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop', 'cart'], 'usable' => false],
];

$plan144 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedHiddenPathNext144(
    'json_tree',
    $current ?? $current144,
    $next ?? $next144,
    'option_value',
    'base_root',
    'active_path',
    $constraints144,
    [['column' => 'id']],
    $generated ?? $generated144,
);
$stable144 = static fn (): array => $plan144($current144, $current144);
$jsonb144 = static fn (): array => $plan144(
    $current144,
    array_replace($next144, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next144['option_value'])))]),
);
$unrunnable144 = static fn (): array => $plan144($current144, array_replace($next144, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan144()['function']),
    'records next144 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-path-current-source-next144', $plan144()['dependencies'], true)),
    'preserves next141 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-residual-cost-current-source-next141', $plan144()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-hidden-path-source-until-cursor-reset', $plan144()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-hidden-path-plan', $plan144()['nextReaderPolicy']),
    'stable reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-hidden-path-plan', $stable144()['nextReaderPolicy']),
    'stable has no next144 reasons' => static fn (TestRunner $t) => $t->same([], $stable144()['next144ReplanReasons']),
    'current base root recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $plan144()['currentGeneratedHiddenPath']['baseRoot']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('[0].rules', $plan144()['currentGeneratedHiddenPath']['generatedPath']),
    'next generated path recorded' => static fn (TestRunner $t) => $t->same('[1].rules', $plan144()['nextGeneratedHiddenPath']['generatedPath']),
    'current composed root recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan144()['currentGeneratedHiddenPath']['composedRoot']),
    'next composed root recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan144()['nextGeneratedHiddenPath']['composedRoot']),
    'current mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan144()['currentGeneratedHiddenPath']['mode']),
    'next mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan144()['nextGeneratedHiddenPath']['mode']),
    'current source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan144()['currentGeneratedHiddenPath']['jsonSourceKind']),
    'next source kind is text' => static fn (TestRunner $t) => $t->same('text', $plan144()['nextGeneratedHiddenPath']['jsonSourceKind']),
    'current row count scoped to first group' => static fn (TestRunner $t) => $t->same(3, $plan144()['currentGeneratedHiddenPath']['rowCount']),
    'next row count scoped to generated path' => static fn (TestRunner $t) => $t->same(3, $plan144()['nextGeneratedHiddenPath']['rowCount']),
    'current matched row count' => static fn (TestRunner $t) => $t->same(1, $plan144()['currentGeneratedHiddenPath']['matchedRowCount']),
    'next matched row count' => static fn (TestRunner $t) => $t->same(2, $plan144()['nextGeneratedHiddenPath']['matchedRowCount']),
    'current matched fullkey' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $plan144()['currentGeneratedHiddenPath']['matchedFullkeys']),
    'next matched fullkeys' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[1].rules[0]', '$.plugin.groups[1].rules[1]'], $plan144()['nextGeneratedHiddenPath']['matchedFullkeys']),
    'current residual columns' => static fn (TestRunner $t) => $t->same(['generated_slug'], $plan144()['currentGeneratedHiddenPath']['residualColumns']),
    'current residual slug tape' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $plan144()['currentGeneratedHiddenPath']['residualValueTape'])),
    'next residual slug tape' => static fn (TestRunner $t) => $t->same(['shop', 'cart', 'coupons'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $plan144()['nextGeneratedHiddenPath']['residualValueTape'])),
    'current matched tape' => static fn (TestRunner $t) => $t->same([false, false, true], array_column($plan144()['currentGeneratedHiddenPath']['residualValueTape'], 'matched')),
    'next matched tape' => static fn (TestRunner $t) => $t->same([true, true, false], array_column($plan144()['nextGeneratedHiddenPath']['residualValueTape'], 'matched')),
    'current cost class is path point' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-path-point', $plan144()['currentGeneratedHiddenPath']['costClass']),
    'next cost class is path subtree' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-path-subtree', $plan144()['nextGeneratedHiddenPath']['costClass']),
    'current effective cost includes residual' => static fn (TestRunner $t) => $t->same(6, $plan144()['currentGeneratedHiddenPath']['effectiveEstimatedCost']),
    'next effective cost includes residual' => static fn (TestRunner $t) => $t->same(8, $plan144()['nextGeneratedHiddenPath']['effectiveEstimatedCost']),
    'path stable key changes' => static fn (TestRunner $t) => $t->same(true, $plan144()['generatedHiddenPathTransitions'][0]['changed']),
    'source kind transition stable' => static fn (TestRunner $t) => $t->same(false, $plan144()['generatedHiddenPathTransitions'][1]['changed']),
    'source fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $plan144()['generatedHiddenPathTransitions'][2]['changed']),
    'matched fullkeys transition changes' => static fn (TestRunner $t) => $t->same(true, $plan144()['generatedHiddenPathTransitions'][3]['changed']),
    'residual tape transition changes' => static fn (TestRunner $t) => $t->same(true, $plan144()['generatedHiddenPathTransitions'][4]['changed']),
    'cost transition changes' => static fn (TestRunner $t) => $t->same(true, $plan144()['generatedHiddenPathTransitions'][5]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan144()['generatedHiddenPathTransitions'][6]['changed']),
    'reasons include root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-root-changed', $plan144()['next144ReplanReasons'], true)),
    'reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-source-changed', $plan144()['next144ReplanReasons'], true)),
    'reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-rowset-changed', $plan144()['next144ReplanReasons'], true)),
    'reasons include values change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-values-changed', $plan144()['next144ReplanReasons'], true)),
    'reasons include residual cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-cost-changed', $plan144()['next144ReplanReasons'], true)),
    'jsonb next source kind changes' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb144()['nextGeneratedHiddenPath']['jsonSourceKind']),
    'jsonb reason includes source kind' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-source-kind-changed', $jsonb144()['next144ReplanReasons'], true)),
    'unrunnable next source kind sql null' => static fn (TestRunner $t) => $t->same('sql-null', $unrunnable144()['nextGeneratedHiddenPath']['jsonSourceKind']),
    'unrunnable next cost class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable144()['nextGeneratedHiddenPath']['costClass']),
    'unrunnable next matched count zero' => static fn (TestRunner $t) => $t->same(0, $unrunnable144()['nextGeneratedHiddenPath']['matchedRowCount']),
    'missing generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan144($current144, array_diff_key($next144, ['active_path' => true]))),
    'empty generated path column name rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedHiddenPathNext144('json_tree', $current144, $next144, 'option_value', 'base_root', '', $constraints144, [], $generated144)),
    'bad json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedHiddenPathNext144('json_tree', $current144, $next144, '', 'base_root', 'active_path', $constraints144, [], $generated144)),
    'bad generated path value rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan144($current144, array_replace($next144, ['active_path' => '[#-]']))),
    'bad generated constraint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan144($current144, $next144, [['name' => 'bad', 'source' => 'value', 'path' => '$.slug', 'operator' => 'LIKE']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated hidden path current source next144 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
