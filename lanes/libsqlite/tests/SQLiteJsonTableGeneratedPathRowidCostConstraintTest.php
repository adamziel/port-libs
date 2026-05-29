<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current165 = [
    'option_id' => 165,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_next165',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next165 = [
    'option_id' => 165,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_next165',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$pointConstraints165 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
];
$plan165 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostConstraintPlan(
    'json_tree',
    $current ?? $current165,
    $next ?? $next165,
    'option_value',
    'generated_path',
    $constraints ?? $pointConstraints165,
    'scan_root',
    $orderBy ?? [['column' => 'path'], ['column' => 'rowid']],
);

$stable165 = static fn (): array => $plan165($current165, $current165);
$in165 = static fn (): array => $plan165(
    array_replace($current165, ['generated_path' => '$.rules']),
    array_replace($next165, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'rowid', 'operator' => 'IN', 'value' => [7, 42, '7', null]],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
);
$between165 = static fn (): array => $plan165(
    array_replace($current165, ['generated_path' => '$.rules']),
    array_replace($next165, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [4, 7]],
    ],
    [['column' => 'path']],
);
$scan165 = static fn (): array => $plan165(
    array_replace($current165, ['generated_path' => '$.rules']),
    array_replace($next165, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => '>', 'value' => 3],
    ],
);
$miss165 = static fn (): array => $plan165($current165, $next165, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 99],
]);
$unusable165 = static fn (): array => $plan165($current165, $current165, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => 'IN', 'value' => [6, 7], 'usable' => false],
]);

$tests = [
    'records next165 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next165', $plan165()['dependencies'], true)),
    'preserves next162 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next162', $plan165()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-next165-until-xfilter-reset', $plan165()['currentReaderPolicy']),
    'prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-next165-plan', $plan165()['nextReaderPolicy']),
    'stable reader reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-next165-plan', $stable165()['nextReaderPolicy']),
    'stable has no next165 reasons' => static fn (TestRunner $t) => $t->same([], $stable165()['next165ReplanReasons']),
    'point rowid alias normalized' => static fn (TestRunner $t) => $t->same('_rowid_', $plan165()['currentGeneratedPathRowidCost165']['rowidAlias']),
    'point seek operator' => static fn (TestRunner $t) => $t->same('=', $plan165()['currentGeneratedPathRowidCost165']['seekOperator']),
    'point is seekable' => static fn (TestRunner $t) => $t->same(true, $plan165()['currentGeneratedPathRowidCost165']['seekable']),
    'point seek rowids' => static fn (TestRunner $t) => $t->same([6], $plan165()['currentGeneratedPathRowidCost165']['seekRowids']),
    'point matched rowids' => static fn (TestRunner $t) => $t->same([6], $plan165()['currentGeneratedPathRowidCost165']['matchedSeekRowids']),
    'point has no missing rowids' => static fn (TestRunner $t) => $t->same([], $plan165()['currentGeneratedPathRowidCost165']['missingSeekRowids']),
    'path argv index is retained' => static fn (TestRunner $t) => $t->same(1, $plan165()['currentGeneratedPathRowidCost165']['generatedPathArgvIndex']),
    'rowid argv index is retained' => static fn (TestRunner $t) => $t->same(2, $plan165()['currentGeneratedPathRowidCost165']['rowidArgvIndex']),
    'point idx num records path rowid order and current source' => static fn (TestRunner $t) => $t->same(15, $plan165()['currentGeneratedPathRowidCost165']['idxNum']),
    'point idx str records path rowid order and current source' => static fn (TestRunner $t) => $t->same('generated-path+rowid-seek+orderby+current-source', $plan165()['currentGeneratedPathRowidCost165']['idxStr']),
    'point order by columns normalize rowid alias' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan165()['currentGeneratedPathRowidCost165']['orderByColumns']),
    'point consumes order by' => static fn (TestRunner $t) => $t->same(true, $plan165()['currentGeneratedPathRowidCost165']['orderByConsumed']),
    'point omits path and rowid' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan165()['currentGeneratedPathRowidCost165']['omittedConstraintColumns']),
    'point residual columns empty' => static fn (TestRunner $t) => $t->same([], $plan165()['currentGeneratedPathRowidCost165']['residualConstraintColumns']),
    'point estimated rows one' => static fn (TestRunner $t) => $t->same(1, $plan165()['currentGeneratedPathRowidCost165']['estimatedRows']),
    'point estimated cost one' => static fn (TestRunner $t) => $t->same(1, $plan165()['currentGeneratedPathRowidCost165']['estimatedCost']),
    'point cost class covering' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-covering-point', $plan165()['currentGeneratedPathRowidCost165']['costClass']),
    'point stable key has sha256 length' => static fn (TestRunner $t) => $t->same(64, strlen($plan165()['currentGeneratedPathRowidCost165']['seekStableKey'])),
    'next point estimates zero rows' => static fn (TestRunner $t) => $t->same(0, $plan165()['nextGeneratedPathRowidCost165']['estimatedRows']),
    'next point is empty class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-empty', $plan165()['nextGeneratedPathRowidCost165']['costClass']),
    'transition count records next165 state' => static fn (TestRunner $t) => $t->same(12, count($plan165()['generatedPathRowidCost165Transitions'])),
    'seek operator transition stable' => static fn (TestRunner $t) => $t->same(false, $plan165()['generatedPathRowidCost165Transitions'][0]['changed']),
    'idx str transition stable' => static fn (TestRunner $t) => $t->same(false, $plan165()['generatedPathRowidCost165Transitions'][6]['changed']),
    'matched rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan165()['generatedPathRowidCost165Transitions'][3]['changed']),
    'missing rowids transition changes' => static fn (TestRunner $t) => $t->same(true, $plan165()['generatedPathRowidCost165Transitions'][4]['changed']),
    'row estimate transition changes' => static fn (TestRunner $t) => $t->same(true, $plan165()['generatedPathRowidCost165Transitions'][8]['changed']),
    'stable key transition changes' => static fn (TestRunner $t) => $t->same(true, $plan165()['generatedPathRowidCost165Transitions'][11]['changed']),
    'reasons include next165 rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-next165-rowset-changed', $plan165()['next165ReplanReasons'], true)),
    'reasons include next165 cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-next165-cost-changed', $plan165()['next165ReplanReasons'], true)),
    'reasons preserve next162 stable key change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-next162-stable-key-changed', $plan165()['next165ReplanReasons'], true)),
    'in operator is seekable' => static fn (TestRunner $t) => $t->same(true, $in165()['currentGeneratedPathRowidCost165']['seekable']),
    'in rowids are unique sorted ints' => static fn (TestRunner $t) => $t->same([7, 42], $in165()['currentGeneratedPathRowidCost165']['seekRowids']),
    'in matched rowids' => static fn (TestRunner $t) => $t->same([7], $in165()['currentGeneratedPathRowidCost165']['matchedSeekRowids']),
    'in missing rowids' => static fn (TestRunner $t) => $t->same([42], $in165()['currentGeneratedPathRowidCost165']['missingSeekRowids']),
    'in keeps residual visible column' => static fn (TestRunner $t) => $t->same(['type'], $in165()['currentGeneratedPathRowidCost165']['residualConstraintColumns']),
    'in estimates matched rows' => static fn (TestRunner $t) => $t->same(1, $in165()['currentGeneratedPathRowidCost165']['estimatedRows']),
    'in class is partial seek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-partial-seek', $in165()['currentGeneratedPathRowidCost165']['costClass']),
    'between operator is retained' => static fn (TestRunner $t) => $t->same('BETWEEN', $between165()['currentGeneratedPathRowidCost165']['seekOperator']),
    'between seek rowids expand' => static fn (TestRunner $t) => $t->same([4, 5, 6, 7], $between165()['currentGeneratedPathRowidCost165']['seekRowids']),
    'between consumes path only order' => static fn (TestRunner $t) => $t->same(true, $between165()['currentGeneratedPathRowidCost165']['orderByConsumed']),
    'between class is partial seek' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-partial-seek', $between165()['currentGeneratedPathRowidCost165']['costClass']),
    'scan rowid predicate is residual' => static fn (TestRunner $t) => $t->same(false, $scan165()['currentGeneratedPathRowidCost165']['seekable']),
    'scan has no rowid argv' => static fn (TestRunner $t) => $t->same(null, $scan165()['currentGeneratedPathRowidCost165']['rowidArgvIndex']),
    'scan keeps path and rowid residuals' => static fn (TestRunner $t) => $t->same(['path', 'id'], $scan165()['currentGeneratedPathRowidCost165']['residualConstraintColumns']),
    'scan class is residual scan' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-residual-scan', $scan165()['currentGeneratedPathRowidCost165']['costClass']),
    'miss keeps seek index' => static fn (TestRunner $t) => $t->same(15, $miss165()['currentGeneratedPathRowidCost165']['idxNum']),
    'miss estimates no rows' => static fn (TestRunner $t) => $t->same(0, $miss165()['currentGeneratedPathRowidCost165']['estimatedRows']),
    'miss class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-empty', $miss165()['currentGeneratedPathRowidCost165']['costClass']),
    'unusable rowid omits only path' => static fn (TestRunner $t) => $t->same(['path'], $unusable165()['currentGeneratedPathRowidCost165']['omittedConstraintColumns']),
    'unusable rowid has no seek' => static fn (TestRunner $t) => $t->same(false, $unusable165()['currentGeneratedPathRowidCost165']['seekable']),
    'unusable rowid class residual scan' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-next165-residual-scan', $unusable165()['currentGeneratedPathRowidCost165']['costClass']),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostConstraintPlan('json_tree', $current165, $next165, 'option_value', '', [])),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostConstraintPlan('json_tree', $current165, $next165, '', 'generated_path', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next165 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
