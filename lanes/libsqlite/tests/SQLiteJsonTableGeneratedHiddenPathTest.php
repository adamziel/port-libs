<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentHiddenPath = [
    'option_id' => 144,
    'option_name' => 'wp_plugin_generated_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":5,"enabled":true},{"slug":"cart","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'active_path' => '[0].rules',
];
$nextHiddenPath = [
    'option_id' => 144,
    'option_name' => 'wp_plugin_generated_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":5,"enabled":true},{"slug":"cart","priority":6,"enabled":true},{"slug":"coupons","priority":9,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'active_path' => '[1].rules',
];
$hiddenPathConstraints = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[%].rules[%]'],
];
$hiddenPathGenerated = [
    ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'operator' => 'BETWEEN', 'value' => [3, 6]],
    ['name' => 'generated_enabled', 'source' => 'value', 'path' => '$.enabled', 'operator' => 'IS', 'value' => 1],
    ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'operator' => 'IN', 'value' => ['forms', 'shop', 'cart'], 'usable' => false],
];

$hiddenPathPlan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $generated = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedHiddenPath(
    'json_tree',
    $current ?? $currentHiddenPath,
    $next ?? $nextHiddenPath,
    'option_value',
    'base_root',
    'active_path',
    $hiddenPathConstraints,
    [['column' => 'id']],
    $generated ?? $hiddenPathGenerated,
);
$stableHiddenPathPlan = static fn (): array => $hiddenPathPlan($currentHiddenPath, $currentHiddenPath);
$jsonbHiddenPathPlan = static fn (): array => $hiddenPathPlan(
    $currentHiddenPath,
    array_replace($nextHiddenPath, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($nextHiddenPath['option_value'])))]),
);
$unrunnableHiddenPathPlan = static fn (): array => $hiddenPathPlan($currentHiddenPath, array_replace($nextHiddenPath, ['option_value' => null]));

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $hiddenPathPlan()['function']),
    'records generated hidden path dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-path-current-source', $hiddenPathPlan()['dependencies'], true)),
    'preserves generated hidden residual dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-hidden-residual-cost-current-source', $hiddenPathPlan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-hidden-path-source-until-cursor-reset', $hiddenPathPlan()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-hidden-path-plan', $hiddenPathPlan()['nextReaderPolicy']),
    'stable reader policy reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-hidden-path-plan', $stableHiddenPathPlan()['nextReaderPolicy']),
    'stable has no generated hidden path reasons' => static fn (TestRunner $t) => $t->same([], $stableHiddenPathPlan()['generatedHiddenPathReplanReasons']),
    'current base root recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $hiddenPathPlan()['currentGeneratedHiddenPath']['baseRoot']),
    'current generated path recorded' => static fn (TestRunner $t) => $t->same('[0].rules', $hiddenPathPlan()['currentGeneratedHiddenPath']['generatedPath']),
    'next generated path recorded' => static fn (TestRunner $t) => $t->same('[1].rules', $hiddenPathPlan()['nextGeneratedHiddenPath']['generatedPath']),
    'current composed root recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $hiddenPathPlan()['currentGeneratedHiddenPath']['composedRoot']),
    'next composed root recorded' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $hiddenPathPlan()['nextGeneratedHiddenPath']['composedRoot']),
    'current mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $hiddenPathPlan()['currentGeneratedHiddenPath']['mode']),
    'next mode is array fragment' => static fn (TestRunner $t) => $t->same('array-fragment', $hiddenPathPlan()['nextGeneratedHiddenPath']['mode']),
    'current source kind is text' => static fn (TestRunner $t) => $t->same('text', $hiddenPathPlan()['currentGeneratedHiddenPath']['jsonSourceKind']),
    'next source kind is text' => static fn (TestRunner $t) => $t->same('text', $hiddenPathPlan()['nextGeneratedHiddenPath']['jsonSourceKind']),
    'current row count scoped to first group' => static fn (TestRunner $t) => $t->same(3, $hiddenPathPlan()['currentGeneratedHiddenPath']['rowCount']),
    'next row count scoped to generated path' => static fn (TestRunner $t) => $t->same(3, $hiddenPathPlan()['nextGeneratedHiddenPath']['rowCount']),
    'current matched row count' => static fn (TestRunner $t) => $t->same(1, $hiddenPathPlan()['currentGeneratedHiddenPath']['matchedRowCount']),
    'next matched row count' => static fn (TestRunner $t) => $t->same(2, $hiddenPathPlan()['nextGeneratedHiddenPath']['matchedRowCount']),
    'current matched fullkey' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[0].rules[2]'], $hiddenPathPlan()['currentGeneratedHiddenPath']['matchedFullkeys']),
    'next matched fullkeys' => static fn (TestRunner $t) => $t->same(['$.plugin.groups[1].rules[0]', '$.plugin.groups[1].rules[1]'], $hiddenPathPlan()['nextGeneratedHiddenPath']['matchedFullkeys']),
    'current residual columns' => static fn (TestRunner $t) => $t->same(['generated_slug'], $hiddenPathPlan()['currentGeneratedHiddenPath']['residualColumns']),
    'current residual slug tape' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $hiddenPathPlan()['currentGeneratedHiddenPath']['residualValueTape'])),
    'next residual slug tape' => static fn (TestRunner $t) => $t->same(['shop', 'cart', 'coupons'], array_map(static fn (array $entry): mixed => $entry['residualValues']['generated_slug'], $hiddenPathPlan()['nextGeneratedHiddenPath']['residualValueTape'])),
    'current matched tape' => static fn (TestRunner $t) => $t->same([false, false, true], array_column($hiddenPathPlan()['currentGeneratedHiddenPath']['residualValueTape'], 'matched')),
    'next matched tape' => static fn (TestRunner $t) => $t->same([true, true, false], array_column($hiddenPathPlan()['nextGeneratedHiddenPath']['residualValueTape'], 'matched')),
    'current cost class is path point' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-path-point', $hiddenPathPlan()['currentGeneratedHiddenPath']['costClass']),
    'next cost class is path subtree' => static fn (TestRunner $t) => $t->same('json-table-generated-hidden-path-subtree', $hiddenPathPlan()['nextGeneratedHiddenPath']['costClass']),
    'current effective cost includes residual' => static fn (TestRunner $t) => $t->same(6, $hiddenPathPlan()['currentGeneratedHiddenPath']['effectiveEstimatedCost']),
    'next effective cost includes residual' => static fn (TestRunner $t) => $t->same(8, $hiddenPathPlan()['nextGeneratedHiddenPath']['effectiveEstimatedCost']),
    'path stable key changes' => static fn (TestRunner $t) => $t->same(true, $hiddenPathPlan()['generatedHiddenPathTransitions'][0]['changed']),
    'source kind transition stable' => static fn (TestRunner $t) => $t->same(false, $hiddenPathPlan()['generatedHiddenPathTransitions'][1]['changed']),
    'source fingerprint transition changes' => static fn (TestRunner $t) => $t->same(true, $hiddenPathPlan()['generatedHiddenPathTransitions'][2]['changed']),
    'matched fullkeys transition changes' => static fn (TestRunner $t) => $t->same(true, $hiddenPathPlan()['generatedHiddenPathTransitions'][3]['changed']),
    'residual tape transition changes' => static fn (TestRunner $t) => $t->same(true, $hiddenPathPlan()['generatedHiddenPathTransitions'][4]['changed']),
    'cost transition changes' => static fn (TestRunner $t) => $t->same(true, $hiddenPathPlan()['generatedHiddenPathTransitions'][5]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $hiddenPathPlan()['generatedHiddenPathTransitions'][6]['changed']),
    'reasons include root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-root-changed', $hiddenPathPlan()['generatedHiddenPathReplanReasons'], true)),
    'reasons include source change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-source-changed', $hiddenPathPlan()['generatedHiddenPathReplanReasons'], true)),
    'reasons include rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-rowset-changed', $hiddenPathPlan()['generatedHiddenPathReplanReasons'], true)),
    'reasons include values change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-values-changed', $hiddenPathPlan()['generatedHiddenPathReplanReasons'], true)),
    'reasons include residual cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-residual-cost-changed', $hiddenPathPlan()['generatedHiddenPathReplanReasons'], true)),
    'jsonb next source kind changes' => static fn (TestRunner $t) => $t->same('jsonb', $jsonbHiddenPathPlan()['nextGeneratedHiddenPath']['jsonSourceKind']),
    'jsonb reason includes source kind' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-hidden-path-source-kind-changed', $jsonbHiddenPathPlan()['generatedHiddenPathReplanReasons'], true)),
    'unrunnable next source kind sql null' => static fn (TestRunner $t) => $t->same('sql-null', $unrunnableHiddenPathPlan()['nextGeneratedHiddenPath']['jsonSourceKind']),
    'unrunnable next cost class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnableHiddenPathPlan()['nextGeneratedHiddenPath']['costClass']),
    'unrunnable next matched count zero' => static fn (TestRunner $t) => $t->same(0, $unrunnableHiddenPathPlan()['nextGeneratedHiddenPath']['matchedRowCount']),
    'missing generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $hiddenPathPlan($currentHiddenPath, array_diff_key($nextHiddenPath, ['active_path' => true]))),
    'empty generated path column name rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedHiddenPath('json_tree', $currentHiddenPath, $nextHiddenPath, 'option_value', 'base_root', '', $hiddenPathConstraints, [], $hiddenPathGenerated)),
    'bad json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedHiddenPath('json_tree', $currentHiddenPath, $nextHiddenPath, '', 'base_root', 'active_path', $hiddenPathConstraints, [], $hiddenPathGenerated)),
    'bad generated path value rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $hiddenPathPlan($currentHiddenPath, array_replace($nextHiddenPath, ['active_path' => '[#-]']))),
    'bad generated constraint rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $hiddenPathPlan($currentHiddenPath, $nextHiddenPath, [['name' => 'bad', 'source' => 'value', 'path' => '$.slug', 'operator' => 'LIKE']])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated hidden path ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
