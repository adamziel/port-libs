<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current123 = [
    'option_id' => 23,
    'option_name' => 'wp_plugin_path_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next123 = [
    'option_id' => 23,
    'option_name' => 'wp_plugin_path_rules',
    'option_value' => '{"rules":[{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$pathEquals123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $current123,
    $next123,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['text', 'integer']],
    ],
    'scan_root',
    [['column' => 'id']],
);

$pathLike123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $current123,
    $next123,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$pathIn123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $current123,
    $next123,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'IN', 'value' => ['$.rules[0]', '$.rules[2]']],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['text', 'integer']],
    ],
    'scan_root',
);

$pathBetween123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $current123,
    $next123,
    'option_value',
    [
        ['column' => 'path', 'operator' => 'BETWEEN', 'value' => ['$.rules[0]', '$.rules[1]']],
        ['column' => 'key', 'operator' => 'IN', 'value' => ['slug', 'priority']],
    ],
    'scan_root',
);

$stable123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $current123,
    $current123,
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['text', 'integer']],
    ],
    'scan_root',
    [['column' => 'id']],
);

$unrunnable123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_tree',
    $current123,
    array_replace($current123, ['option_value' => null]),
    'option_value',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);

$nonPath123 = static fn (): array => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown(
    'json_each',
    $current123,
    $current123,
    'option_value',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'scan_root',
);

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $pathEquals123()['function']),
    'records next123 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-path-constraint-pushdown-current-source-next123', $pathEquals123()['dependencies'], true)),
    'preserves next119 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-indexed-constraint-cost-current-source-next119', $pathEquals123()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-path-constraint-source-until-cursor-reset', $pathEquals123()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-path-constraint-source-plan', $pathEquals123()['nextReaderPolicy']),
    'stable reader policy reuses plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-path-constraint-source-plan', $stable123()['nextReaderPolicy']),
    'stable does not require replan' => static fn (TestRunner $t) => $t->same(false, $stable123()['replanRequired']),
    'equals selected path column' => static fn (TestRunner $t) => $t->same('path', $pathEquals123()['currentPathConstraint']['selectedPath']['column']),
    'equals selected operator' => static fn (TestRunner $t) => $t->same('=', $pathEquals123()['currentPathConstraint']['selectedPath']['operator']),
    'equals selected signature' => static fn (TestRunner $t) => $t->same('2:path:=:"$.rules[1]"', $pathEquals123()['currentPathConstraint']['selectedPathSignature']),
    'equals next signature matches' => static fn (TestRunner $t) => $t->same($pathEquals123()['currentPathConstraint']['selectedPathSignature'], $pathEquals123()['nextPathConstraint']['selectedPathSignature']),
    'equals scan strategy is path pushdown' => static fn (TestRunner $t) => $t->same('path-constraint-pushdown', $pathEquals123()['currentPathConstraint']['pathScanStrategy']),
    'equals cost class is point lookup' => static fn (TestRunner $t) => $t->same('json-table-path-point-lookup', $pathEquals123()['currentPathConstraint']['costClass']),
    'equals current row count two' => static fn (TestRunner $t) => $t->same(2, $pathEquals123()['currentPathConstraint']['pathRowCount']),
    'equals next row count two' => static fn (TestRunner $t) => $t->same(2, $pathEquals123()['nextPathConstraint']['pathRowCount']),
    'equals current tape repeats path' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[1]'], $pathEquals123()['currentPathConstraint']['pathTape']),
    'equals next tape repeats path' => static fn (TestRunner $t) => $t->same(['$.rules[1]', '$.rules[1]'], $pathEquals123()['nextPathConstraint']['pathTape']),
    'equals first path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $pathEquals123()['currentPathConstraint']['firstPath']),
    'equals last path recorded' => static fn (TestRunner $t) => $t->same('$.rules[1]', $pathEquals123()['currentPathConstraint']['lastPath']),
    'equals current rows include cache slug' => static fn (TestRunner $t) => $t->same('cache', $pathEquals123()['currentRows'][0]['atom']),
    'equals next rows include cache priority' => static fn (TestRunner $t) => $t->same(6, $pathEquals123()['nextRows'][1]['atom']),
    'equals transition count' => static fn (TestRunner $t) => $t->same(6, count($pathEquals123()['pathConstraintTransitions'])),
    'equals selected transition stable' => static fn (TestRunner $t) => $t->same(false, $pathEquals123()['pathConstraintTransitions'][0]['changed']),
    'equals scan transition stable' => static fn (TestRunner $t) => $t->same(false, $pathEquals123()['pathConstraintTransitions'][1]['changed']),
    'equals tape transition stable' => static fn (TestRunner $t) => $t->same(false, $pathEquals123()['pathConstraintTransitions'][5]['changed']),
    'equals still detects source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $pathEquals123()['next123ReplanReasons'], true)),
    'like selected operator' => static fn (TestRunner $t) => $t->same('LIKE', $pathLike123()['currentPathConstraint']['selectedPath']['operator']),
    'like signature records prefix' => static fn (TestRunner $t) => $t->same('2:path:LIKE:"$.rules%"', $pathLike123()['currentPathConstraint']['selectedPathSignature']),
    'like cost class is range scan' => static fn (TestRunner $t) => $t->same('json-table-path-range-scan', $pathLike123()['currentPathConstraint']['costClass']),
    'like current scalar row count' => static fn (TestRunner $t) => $t->same(4, $pathLike123()['currentPathConstraint']['pathRowCount']),
    'like next scalar row count grows' => static fn (TestRunner $t) => $t->same(6, $pathLike123()['nextPathConstraint']['pathRowCount']),
    'like first current path' => static fn (TestRunner $t) => $t->same('$.rules[0]', $pathLike123()['currentPathConstraint']['firstPath']),
    'like next last path' => static fn (TestRunner $t) => $t->same('$.rules[2]', $pathLike123()['nextPathConstraint']['lastPath']),
    'like row count transition changes' => static fn (TestRunner $t) => $t->same(true, $pathLike123()['pathConstraintTransitions'][4]['changed']),
    'like tape transition changes' => static fn (TestRunner $t) => $t->same(true, $pathLike123()['pathConstraintTransitions'][5]['changed']),
    'like reasons include path row count' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-row-count-changed', $pathLike123()['next123ReplanReasons'], true)),
    'like reasons include path tape' => static fn (TestRunner $t) => $t->true(in_array('json-table-path-tape-changed', $pathLike123()['next123ReplanReasons'], true)),
    'in selected operator' => static fn (TestRunner $t) => $t->same('IN', $pathIn123()['currentPathConstraint']['selectedPath']['operator']),
    'in current row count only first rule' => static fn (TestRunner $t) => $t->same(2, $pathIn123()['currentPathConstraint']['pathRowCount']),
    'in next row count includes first and inserted third rule' => static fn (TestRunner $t) => $t->same(4, $pathIn123()['nextPathConstraint']['pathRowCount']),
    'in tape records inserted third path' => static fn (TestRunner $t) => $t->same('$.rules[2]', $pathIn123()['nextPathConstraint']['lastPath']),
    'between selected operator' => static fn (TestRunner $t) => $t->same('BETWEEN', $pathBetween123()['currentPathConstraint']['selectedPath']['operator']),
    'between cost class is range scan' => static fn (TestRunner $t) => $t->same('json-table-path-range-scan', $pathBetween123()['currentPathConstraint']['costClass']),
    'between current covers two rule paths' => static fn (TestRunner $t) => $t->same(['$.rules[0]', '$.rules[0]', '$.rules[1]', '$.rules[1]'], $pathBetween123()['currentPathConstraint']['pathTape']),
    'between next keeps two rule paths' => static fn (TestRunner $t) => $t->same(['$.rules[0]', '$.rules[0]', '$.rules[1]', '$.rules[1]'], $pathBetween123()['nextPathConstraint']['pathTape']),
    'non path has no selected path' => static fn (TestRunner $t) => $t->same(null, $nonPath123()['currentPathConstraint']['selectedPath']),
    'non path uses full scan' => static fn (TestRunner $t) => $t->same('full-json-table-scan', $nonPath123()['currentPathConstraint']['pathScanStrategy']),
    'non path class records full scan' => static fn (TestRunner $t) => $t->same('json-table-path-full-scan', $nonPath123()['currentPathConstraint']['costClass']),
    'unrunnable next path strategy' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable123()['nextPathConstraint']['pathScanStrategy']),
    'unrunnable next path cost' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable123()['nextPathConstraint']['pathEstimatedCost']),
    'unrunnable next path tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable123()['nextPathConstraint']['pathTape']),
    'unrunnable reasons include source plan' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $unrunnable123()['next123ReplanReasons'], true)),
    'bad json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown('json_tree', $current123, $next123, '', [])),
    'bad root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown('json_tree', $current123, $next123, 'option_value', [], '')),
    'missing json source is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown('json_tree', ['scan_root' => '$'], $next123, 'option_value', [])),
    'malformed root path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown('json_tree', $current123, array_replace($next123, ['scan_root' => '$[']), 'option_value', [], 'scan_root')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourcePathConstraintPushdown('json_bad', $current123, $next123, 'option_value', [])),
];

foreach ($tests as $name => $case) {
    $tests['json table path constraint pushdown current source next123 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
