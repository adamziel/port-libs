<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current134 = [
    'option_id' => 134,
    'option_name' => 'wp_plugin_generated_path',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next134 = [
    'option_id' => 134,
    'option_name' => 'wp_plugin_generated_path',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$point134 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    $current134,
    $next134,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$stable134 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    $current134,
    $current134,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$like134 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    array_replace($current134, ['generated_path' => '$.rules']),
    array_replace($next134, ['generated_path' => '$.rules']),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['text', 'integer']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$mismatch134 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    array_replace($current134, ['generated_path' => '$.rules[2]']),
    $next134,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);

$missing134 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    array_replace($current134, ['generated_path' => null]),
    array_replace($current134, ['generated_path' => null]),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);

$unrunnable134 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathCost(
    'json_tree',
    $current134,
    array_replace($next134, ['option_value' => null]),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $point134()['function']),
    'records next134 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-cost-current-source-next134', $point134()['dependencies'], true)),
    'preserves next131 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-orderby-cost-current-source-next131', $point134()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-cost-source-until-cursor-reset', $point134()['currentReaderPolicy']),
    'prepares next reader on generated path drift' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-cost-source-plan', $point134()['nextReaderPolicy']),
    'stable reuses current reader' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-cost-source-plan', $stable134()['nextReaderPolicy']),
    'stable does not require replan' => static fn (TestRunner $t) => $t->same(false, $stable134()['replanRequired']),
    'stable has no next134 reasons' => static fn (TestRunner $t) => $t->same([], $stable134()['next134ReplanReasons']),
    'point records generated column' => static fn (TestRunner $t) => $t->same('generated_path', $point134()['generatedPathColumn']),
    'point current generated path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point134()['currentGeneratedPathCost']['generatedPath']),
    'point next generated path shifts' => static fn (TestRunner $t) => $t->same('$.rules[2]', $point134()['nextGeneratedPathCost']['generatedPath']),
    'point selected path signature' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules[1]"', $point134()['currentGeneratedPathCost']['selectedPathSignature']),
    'point parsed constraint operator' => static fn (TestRunner $t) => $t->same('=', $point134()['currentGeneratedPathCost']['pathConstraint']['operator']),
    'point parsed constraint value' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point134()['currentGeneratedPathCost']['pathConstraint']['value']),
    'point current matches generated path' => static fn (TestRunner $t) => $t->same(true, $point134()['currentGeneratedPathCost']['generatedPathMatches']),
    'point next mismatches generated path' => static fn (TestRunner $t) => $t->same(false, $point134()['nextGeneratedPathCost']['generatedPathMatches']),
    'point current coverage is covered' => static fn (TestRunner $t) => $t->same('generated-path-covered', $point134()['currentGeneratedPathCost']['coverage']),
    'point next coverage is mismatch' => static fn (TestRunner $t) => $t->same('generated-path-mismatch', $point134()['nextGeneratedPathCost']['coverage']),
    'point current cost class is point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-point-cost', $point134()['currentGeneratedPathCost']['costClass']),
    'point next cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-empty-cost', $point134()['nextGeneratedPathCost']['costClass']),
    'point current estimated rows include child values' => static fn (TestRunner $t) => $t->same(2, $point134()['currentGeneratedPathCost']['generatedEstimatedRows']),
    'point next estimated rows empty on mismatch' => static fn (TestRunner $t) => $t->same(0, $point134()['nextGeneratedPathCost']['generatedEstimatedRows']),
    'point current estimated cost narrows' => static fn (TestRunner $t) => $t->same(1, $point134()['currentGeneratedPathCost']['generatedEstimatedCost']),
    'point next estimated cost penalizes mismatch' => static fn (TestRunner $t) => $t->same(51, $point134()['nextGeneratedPathCost']['generatedEstimatedCost']),
    'point current covered tape has slug and priority' => static fn (TestRunner $t) => $t->same([
        ['path' => '$.rules[1]', 'rowid' => 5],
        ['path' => '$.rules[1]', 'rowid' => 6],
    ], $point134()['currentGeneratedPathCost']['coveredPathTape']),
    'point next covered tape empty' => static fn (TestRunner $t) => $t->same([], $point134()['nextGeneratedPathCost']['coveredPathTape']),
    'point first covered path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point134()['currentGeneratedPathCost']['firstCoveredPath']),
    'point last covered path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point134()['currentGeneratedPathCost']['lastCoveredPath']),
    'point transition count' => static fn (TestRunner $t) => $t->same(7, count($point134()['generatedPathCostTransitions'])),
    'point generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $point134()['generatedPathCostTransitions'][0]['changed']),
    'point selected path transition stable' => static fn (TestRunner $t) => $t->same(false, $point134()['generatedPathCostTransitions'][1]['changed']),
    'point coverage transition changes' => static fn (TestRunner $t) => $t->same(true, $point134()['generatedPathCostTransitions'][2]['changed']),
    'point cost transition changes' => static fn (TestRunner $t) => $t->same(true, $point134()['generatedPathCostTransitions'][3]['changed']),
    'point rows transition changes' => static fn (TestRunner $t) => $t->same(true, $point134()['generatedPathCostTransitions'][4]['changed']),
    'point output transition changes' => static fn (TestRunner $t) => $t->same(true, $point134()['generatedPathCostTransitions'][6]['changed']),
    'point reasons include generated source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-source-changed', $point134()['next134ReplanReasons'], true)),
    'point reasons include coverage' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-coverage-changed', $point134()['next134ReplanReasons'], true)),
    'point reasons include output' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-output-changed', $point134()['next134ReplanReasons'], true)),
    'point reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $point134()['next134ReplanReasons'], true)),
    'like generated path matches prefix' => static fn (TestRunner $t) => $t->same(true, $like134()['currentGeneratedPathCost']['generatedPathMatches']),
    'like current coverage is covered' => static fn (TestRunner $t) => $t->same('generated-path-covered', $like134()['currentGeneratedPathCost']['coverage']),
    'like selected path signature' => static fn (TestRunner $t) => $t->same('2:path:LIKE:"$.rules%"', $like134()['currentGeneratedPathCost']['selectedPathSignature']),
    'like current covered row count' => static fn (TestRunner $t) => $t->same(6, count($like134()['currentGeneratedPathCost']['coveredPathTape'])),
    'like next covered row count grows' => static fn (TestRunner $t) => $t->same(8, count($like134()['nextGeneratedPathCost']['coveredPathTape'])),
    'like cost class is covered' => static fn (TestRunner $t) => $t->same('json-table-generated-path-covered-cost', $like134()['currentGeneratedPathCost']['costClass']),
    'like next output transition changes' => static fn (TestRunner $t) => $t->same(true, $like134()['generatedPathCostTransitions'][6]['changed']),
    'mismatch current does not match' => static fn (TestRunner $t) => $t->same(false, $mismatch134()['currentGeneratedPathCost']['generatedPathMatches']),
    'mismatch current has empty rows' => static fn (TestRunner $t) => $t->same(0, $mismatch134()['currentGeneratedPathCost']['generatedEstimatedRows']),
    'mismatch current class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-empty-cost', $mismatch134()['currentGeneratedPathCost']['costClass']),
    'missing generated path coverage' => static fn (TestRunner $t) => $t->same('missing-generated-path', $missing134()['currentGeneratedPathCost']['coverage']),
    'missing generated path class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-missing-cost', $missing134()['currentGeneratedPathCost']['costClass']),
    'missing generated path adds fallback penalty' => static fn (TestRunner $t) => $t->same(21, $missing134()['currentGeneratedPathCost']['generatedEstimatedCost']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable134()['nextGeneratedPathCost']['costClass']),
    'unrunnable next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable134()['nextGeneratedPathCost']['generatedEstimatedCost']),
    'unrunnable next rows zero' => static fn (TestRunner $t) => $t->same(0, $unrunnable134()['nextGeneratedPathCost']['generatedEstimatedRows']),
    'unrunnable preserves source plan reason' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable134()['next134ReplanReasons'], true)),
    'invalid generated path column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathCost('json_tree', $current134, $next134, 'option_value', '', [])),
    'missing generated path column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathCost('json_tree', $current134, $next134, 'option_value', 'missing_path', [])),
    'non text generated path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathCost('json_tree', array_replace($current134, ['generated_path' => 134]), $next134, 'option_value', 'generated_path', [])),
    'malformed generated path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathCost('json_tree', array_replace($current134, ['generated_path' => '$[']), $next134, 'option_value', 'generated_path', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path cost current source next134 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
