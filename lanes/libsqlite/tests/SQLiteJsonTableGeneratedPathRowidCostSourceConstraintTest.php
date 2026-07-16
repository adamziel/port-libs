<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current162 = [
    'option_id' => 162,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_source_next162',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next162 = [
    'option_id' => 162,
    'option_name' => 'wp_plugin_generated_path_rowid_cost_source_next162',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints162 = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
];

$plan162 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
    ?array $orderBy = null,
): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceConstraintPlan(
    'json_tree',
    $current ?? $current162,
    $next ?? $next162,
    'option_value',
    'generated_path',
    $constraints ?? $constraints162,
    'scan_root',
    $orderBy ?? [['column' => 'path'], ['column' => 'rowid']],
);

$stable162 = static fn (): array => $plan162($current162, $current162);
$oid162 = static fn (): array => $plan162($current162, $current162, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'oid', 'operator' => '=', 'value' => 6],
]);
$range162 = static fn (): array => $plan162(
    array_replace($current162, ['generated_path' => '$.rules']),
    array_replace($next162, ['generated_path' => '$.rules']),
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['object', 'integer']],
    ],
    [['column' => 'type']],
);
$miss162 = static fn (): array => $plan162($current162, $next162, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 99],
]);
$unusable162 = static fn (): array => $plan162($current162, $current162, [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6, 'usable' => false],
]);

$tests = [
    'records next162 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next162', $plan162()['dependencies'], true)),
    'preserves next160 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next160', $plan162()['dependencies'], true)),
    'pins next162 reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-source-next162-until-xfilter-reset', $plan162()['currentReaderPolicy']),
    'prepares changed next162 plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-source-next162-plan', $plan162()['nextReaderPolicy']),
    'stable next162 plan reuses current' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-source-next162-plan', $stable162()['nextReaderPolicy']),
    'stable has no next162 reasons' => static fn (TestRunner $t) => $t->same([], $stable162()['next162ReplanReasons']),
    'current rowid alias is preserved' => static fn (TestRunner $t) => $t->same('_rowid_', $plan162()['currentGeneratedPathRowidCostSource162']['rowidAlias']),
    'current rowid alias candidates are preserved' => static fn (TestRunner $t) => $t->same(['_rowid_'], $plan162()['currentGeneratedPathRowidCostSource162']['rowidAliasCandidates']),
    'oid rowid alias is preserved' => static fn (TestRunner $t) => $t->same('oid', $oid162()['currentGeneratedPathRowidCostSource162']['rowidAlias']),
    'rowid point is usable' => static fn (TestRunner $t) => $t->same(true, $plan162()['currentGeneratedPathRowidCostSource162']['rowidPointUsable']),
    'generated path argv index is retained' => static fn (TestRunner $t) => $t->same(1, $plan162()['currentGeneratedPathRowidCostSource162']['generatedPathArgvIndex']),
    'rowid argv index is retained' => static fn (TestRunner $t) => $t->same(2, $plan162()['currentGeneratedPathRowidCostSource162']['rowidArgvIndex']),
    'idx num records generated path rowid and order' => static fn (TestRunner $t) => $t->same(7, $plan162()['currentGeneratedPathRowidCostSource162']['idxNum']),
    'idx str records generated path rowid and order' => static fn (TestRunner $t) => $t->same('generated-path+rowid-point+orderby', $plan162()['currentGeneratedPathRowidCostSource162']['idxStr']),
    'order by columns normalize rowid alias' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan162()['currentGeneratedPathRowidCostSource162']['orderByColumns']),
    'order by is consumed by path rowid point' => static fn (TestRunner $t) => $t->same(true, $plan162()['currentGeneratedPathRowidCostSource162']['orderByConsumed']),
    'omitted columns include path and id' => static fn (TestRunner $t) => $t->same(['path', 'id'], $plan162()['currentGeneratedPathRowidCostSource162']['omittedConstraintColumns']),
    'residual columns are empty for point seek' => static fn (TestRunner $t) => $t->same([], $plan162()['currentGeneratedPathRowidCostSource162']['residualConstraintColumns']),
    'estimated rows are point row count' => static fn (TestRunner $t) => $t->same(1, $plan162()['currentGeneratedPathRowidCostSource162']['estimatedRows']),
    'estimated cost is point cost' => static fn (TestRunner $t) => $t->same(1, $plan162()['currentGeneratedPathRowidCostSource162']['estimatedCost']),
    'cost class is covering point' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-source-next162-covering-point', $plan162()['currentGeneratedPathRowidCostSource162']['costClass']),
    'stable source key has sha256 length' => static fn (TestRunner $t) => $t->same(64, strlen($plan162()['currentGeneratedPathRowidCostSource162']['sourceStableKey'])),
    'next empty rowset has zero estimate' => static fn (TestRunner $t) => $t->same(0, $plan162()['nextGeneratedPathRowidCostSource162']['estimatedRows']),
    'next empty rowset keeps empty class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-source-next162-empty', $plan162()['nextGeneratedPathRowidCostSource162']['costClass']),
    'transition count records next162 state' => static fn (TestRunner $t) => $t->same(11, count($plan162()['generatedPathRowidCostSource162Transitions'])),
    'rowid alias transition stable' => static fn (TestRunner $t) => $t->same(false, $plan162()['generatedPathRowidCostSource162Transitions'][0]['changed']),
    'idx num transition stable' => static fn (TestRunner $t) => $t->same(false, $plan162()['generatedPathRowidCostSource162Transitions'][2]['changed']),
    'order by transition stable' => static fn (TestRunner $t) => $t->same(false, $plan162()['generatedPathRowidCostSource162Transitions'][4]['changed']),
    'estimated rows transition changes' => static fn (TestRunner $t) => $t->same(true, $plan162()['generatedPathRowidCostSource162Transitions'][7]['changed']),
    'cost class transition changes' => static fn (TestRunner $t) => $t->same(true, $plan162()['generatedPathRowidCostSource162Transitions'][9]['changed']),
    'stable key transition changes' => static fn (TestRunner $t) => $t->same(true, $plan162()['generatedPathRowidCostSource162Transitions'][10]['changed']),
    'reasons include next162 cost change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-next162-cost-changed', $plan162()['next162ReplanReasons'], true)),
    'reasons include next162 stable key change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-next162-stable-key-changed', $plan162()['next162ReplanReasons'], true)),
    'reasons preserve next160 rowset change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-cost-source-rowset-changed', $plan162()['next162ReplanReasons'], true)),
    'range rowid alias is retained' => static fn (TestRunner $t) => $t->same('rowid', $range162()['currentGeneratedPathRowidCostSource162']['rowidAlias']),
    'range rowid point is not usable' => static fn (TestRunner $t) => $t->same(false, $range162()['currentGeneratedPathRowidCostSource162']['rowidPointUsable']),
    'range idx num has no rowid point' => static fn (TestRunner $t) => $t->same(0, $range162()['currentGeneratedPathRowidCostSource162']['idxNum']),
    'range idx str falls back to scan' => static fn (TestRunner $t) => $t->same('json-table-scan', $range162()['currentGeneratedPathRowidCostSource162']['idxStr']),
    'range order by is not consumed' => static fn (TestRunner $t) => $t->same(false, $range162()['currentGeneratedPathRowidCostSource162']['orderByConsumed']),
    'range keeps residual columns' => static fn (TestRunner $t) => $t->same(['path', 'id', 'type'], $range162()['currentGeneratedPathRowidCostSource162']['residualConstraintColumns']),
    'range estimated rows preserve intersected set' => static fn (TestRunner $t) => $t->same(4, $range162()['currentGeneratedPathRowidCostSource162']['estimatedRows']),
    'range cost class records residual' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-source-next162-range', $range162()['currentGeneratedPathRowidCostSource162']['costClass']),
    'miss has point index usage' => static fn (TestRunner $t) => $t->same(7, $miss162()['currentGeneratedPathRowidCostSource162']['idxNum']),
    'miss estimates no rows' => static fn (TestRunner $t) => $t->same(0, $miss162()['currentGeneratedPathRowidCostSource162']['estimatedRows']),
    'miss cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-source-next162-empty', $miss162()['currentGeneratedPathRowidCostSource162']['costClass']),
    'unusable rowid has generated path only idx' => static fn (TestRunner $t) => $t->same(5, $unusable162()['currentGeneratedPathRowidCostSource162']['idxNum']),
    'unusable rowid has no rowid argv' => static fn (TestRunner $t) => $t->same(null, $unusable162()['currentGeneratedPathRowidCostSource162']['rowidArgvIndex']),
    'unusable rowid keeps path omit only' => static fn (TestRunner $t) => $t->same(['path'], $unusable162()['currentGeneratedPathRowidCostSource162']['omittedConstraintColumns']),
    'unusable rowid ordered scan class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-cost-source-next162-ordered', $unusable162()['currentGeneratedPathRowidCostSource162']['costClass']),
    'bad generated path source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceConstraintPlan('json_tree', $current162, $next162, 'option_value', '', $constraints162)),
    'bad json source column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSourceConstraintPlan('json_tree', $current162, $next162, '', 'generated_path', $constraints162)),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next162 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
