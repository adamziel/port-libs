<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current121 = [
    'option_id' => 121,
    'option_name' => 'wp_plugin_nested_rules',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7}]},{"name":"forms","rules":[{"slug":"forms","priority":4}]}],"meta":{"version":1}}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next121 = [
    'option_id' => 121,
    'option_name' => 'wp_plugin_nested_rules',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":8},{"slug":"shop","priority":5}]},{"name":"forms","rules":[{"slug":"forms","priority":4},{"slug":"lead","priority":6}]}],"meta":{"version":2}}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];
$constraints121 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
];

$plan121 = static fn (array $current = null, array $next = null, array $constraints = null, array $orderBy = null): array => SQLiteJsonTablePlan::currentSourceNestedPathPlanner(
    'json_tree',
    $current ?? $current121,
    $next ?? $next121,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints121,
    $orderBy ?? [['column' => 'atom', 'direction' => 'DESC']],
);

$stable121 = static fn (): array => $plan121($current121, $current121);
$absolute121 = static fn (): array => $plan121(
    array_replace($current121, ['nested_path' => '$.plugin.meta']),
    array_replace($current121, ['nested_path' => '$.plugin.meta']),
    [['column' => 'key', 'operator' => '=', 'value' => 'version']],
    [['column' => 'id']],
);
$bare121 = static fn (): array => $plan121(
    array_replace($current121, ['base_root' => '$.plugin.groups[0]', 'nested_path' => 'rules']),
    array_replace($current121, ['base_root' => '$.plugin.groups[0]', 'nested_path' => '.rules']),
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    [['column' => 'id']],
);
$baseOnly121 = static fn (): array => $plan121(
    array_replace($current121, ['base_root' => '$.plugin.groups[0].rules[0]', 'nested_path' => '']),
    array_replace($current121, ['base_root' => '$.plugin.groups[0].rules[0]', 'nested_path' => '']),
    [['column' => 'key', 'operator' => '=', 'value' => 'slug']],
    [['column' => 'id']],
);
$jsonb121 = static fn (): array => $plan121(
    $current121,
    array_replace($next121, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next121['option_value'])))]),
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $plan121()['function']),
    'records dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-path-planner-current-source-next121', $plan121()['dependencies'], true)),
    'preserves cost order dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-cost-order', $plan121()['dependencies'], true)),
    'preserves current source dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-planner', $plan121()['dependencies'], true)),
    'pins current nested reader' => static fn (TestRunner $t) => $t->same('pin-current-json-table-nested-path-source-until-cursor-reset', $plan121()['currentReaderPolicy']),
    'prepares next nested reader' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-nested-path-source-plan', $plan121()['nextReaderPolicy']),
    'stable reader reuses nested path plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-nested-path-source-plan', $stable121()['nextReaderPolicy']),
    'changed nested path requires replan' => static fn (TestRunner $t) => $t->same(true, $plan121()['replanRequired']),
    'stable nested path has no next121 reasons' => static fn (TestRunner $t) => $t->same([], $stable121()['next121ReplanReasons']),
    'current base root is recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $plan121()['currentNestedPath']['baseRoot']),
    'next base root is recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $plan121()['nextNestedPath']['baseRoot']),
    'current nested path is recorded' => static fn (TestRunner $t) => $t->same('[0].rules', $plan121()['currentNestedPath']['nestedPath']),
    'next nested path is recorded' => static fn (TestRunner $t) => $t->same('[1].rules', $plan121()['nextNestedPath']['nestedPath']),
    'current composed root appends array fragment' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan121()['currentNestedPath']['root']),
    'next composed root appends array fragment' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan121()['nextNestedPath']['root']),
    'current mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan121()['currentNestedPath']['mode']),
    'next mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $plan121()['nextNestedPath']['mode']),
    'base root transition is stable' => static fn (TestRunner $t) => $t->same(false, $plan121()['nestedPathTransitions']['baseRoot']['changed']),
    'nested path transition changed' => static fn (TestRunner $t) => $t->same(true, $plan121()['nestedPathTransitions']['nestedPath']['changed']),
    'composed root transition changed' => static fn (TestRunner $t) => $t->same(true, $plan121()['nestedPathTransitions']['composedRoot']['changed']),
    'next121 reasons include nested path change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-path-changed', $plan121()['next121ReplanReasons'], true)),
    'next121 reasons include nested root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-root-changed', $plan121()['next121ReplanReasons'], true)),
    'next121 reasons include source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan121()['next121ReplanReasons'], true)),
    'current hidden root argument is composed' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan121()['current']['filterArguments'][1]),
    'next hidden root argument is composed' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $plan121()['next']['filterArguments'][1]),
    'current idx string includes hidden root and visible constraints' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:type:=|visible:key:=', $plan121()['current']['idxStr']),
    'current rows select core priorities' => static fn (TestRunner $t) => $t->same([7, 2], array_column($plan121()['currentRows'], 'atom')),
    'next rows select forms priorities' => static fn (TestRunner $t) => $t->same([6, 4], array_column($plan121()['nextRows'], 'atom')),
    'current fullkeys use composed core root' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[1].priority', '$.plugin.groups[0].rules[0].priority'], array_column($plan121()['currentRows'], 'fullkey')),
    'next fullkeys use composed forms root' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[1].rules[1].priority', '$.plugin.groups[1].rules[0].priority'], array_column($plan121()['nextRows'], 'fullkey')),
    'current cost order requires sorter' => static fn (TestRunner $t) => $t->same(true, $plan121()['currentCostOrder']['requiresSorter']),
    'next cost order requires sorter' => static fn (TestRunner $t) => $t->same(true, $plan121()['nextCostOrder']['requiresSorter']),
    'current first order key is highest priority' => static fn (TestRunner $t) => $t->same([7], $plan121()['currentCostOrder']['firstOrderKey']),
    'next first order key is highest forms priority' => static fn (TestRunner $t) => $t->same([6], $plan121()['nextCostOrder']['firstOrderKey']),
    'absolute nested path overrides base root' => static fn (TestRunner $t) => $t->same('$.plugin.meta', $absolute121()['currentNestedPath']['root']),
    'absolute nested path mode is recorded' => static fn (TestRunner $t) => $t->same('absolute-nested-root', $absolute121()['currentNestedPath']['mode']),
    'absolute nested path selects version' => static fn (TestRunner $t) => $t->same([1], array_column($absolute121()['currentRows'], 'atom')),
    'bare label composes through dot' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $bare121()['currentNestedPath']['root']),
    'bare label mode is recorded' => static fn (TestRunner $t) => $t->same('bare-label-fragment', $bare121()['currentNestedPath']['mode']),
    'dot label mode is recorded for next' => static fn (TestRunner $t) => $t->same('object-fragment', $bare121()['nextNestedPath']['mode']),
    'bare and dot label roots match' => static fn (TestRunner $t) => $t->same(false, $bare121()['nestedPathTransitions']['composedRoot']['changed']),
    'base-only path keeps base root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules[0]', $baseOnly121()['currentNestedPath']['root']),
    'base-only mode is recorded' => static fn (TestRunner $t) => $t->same('base-root', $baseOnly121()['currentNestedPath']['mode']),
    'base-only path selects slug leaf' => static fn (TestRunner $t) => $t->same(['seo'], array_column($baseOnly121()['currentRows'], 'atom')),
    'jsonb next changes input kind' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonb121()['next121ReplanReasons'], true)),
    'jsonb next remains runnable' => static fn (TestRunner $t) => $t->same(true, $jsonb121()['next']['runnable']),
    'jsonb next input kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $jsonb121()['next']['jsonInputKind']),
    'missing current base root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan121(['option_value' => '{}', 'nested_path' => 'rules'], $next121)),
    'missing next nested path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan121($current121, ['option_value' => '{}', 'base_root' => '$'])),
    'non-text base root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan121(array_replace($current121, ['base_root' => 7]), $next121)),
    'non-text nested path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan121(array_replace($current121, ['nested_path' => 7]), $next121)),
    'malformed base root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan121(array_replace($current121, ['base_root' => '$[#-]']), $next121)),
    'malformed composed root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan121(array_replace($current121, ['nested_path' => '[#-]']), $next121)),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedPathPlanner('json_tree', $current121, $next121, '', 'base_root', 'nested_path')),
    'empty base root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedPathPlanner('json_tree', $current121, $next121, 'option_value', '', 'nested_path')),
    'empty nested path column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedPathPlanner('json_tree', $current121, $next121, 'option_value', 'base_root', '')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedPathPlanner('json_bad', $current121, $next121, 'option_value', 'base_root', 'nested_path')),
];

foreach ($tests as $name => $case) {
    $tests['json table nested path planner current source next121 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
