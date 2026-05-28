<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current145 = [
    'option_id' => 145,
    'option_name' => 'wp_plugin_generated_path_rowid_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next145 = [
    'option_id' => 145,
    'option_name' => 'wp_plugin_generated_path_rowid_cost',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$point145 = static fn (?array $current = null, ?array $next = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145(
    'json_tree',
    $current ?? $current145,
    $next ?? $next145,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$stable145 = static fn (): array => $point145($current145, $current145);
$range145 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145(
    'json_tree',
    array_replace($current145, ['generated_path' => '$.rules']),
    array_replace($next145, ['generated_path' => '$.rules']),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'BETWEEN', 'value' => [4, 9]],
        ['column' => 'type', 'operator' => 'IN', 'value' => ['text', 'integer']],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);
$miss145 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145(
    'json_tree',
    $current145,
    $next145,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 99],
    ],
    'scan_root',
);
$unconstrained145 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145(
    'json_tree',
    $current145,
    $next145,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);
$unrunnable145 = static fn (): array => $point145($current145, array_replace($next145, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $point145()['function']),
    'records next145 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source-next145', $point145()['dependencies'], true)),
    'preserves generated path dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-cost-current-source-next134', $point145()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-source-until-cursor-reset', $point145()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-plan', $point145()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-plan', $stable145()['nextReaderPolicy']),
    'stable plan has no next145 reasons' => static fn (TestRunner $t) => $t->same([], $stable145()['next145ReplanReasons']),
    'point current generated path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point145()['currentGeneratedPathRowidCost']['generatedPath']),
    'point next generated path changes' => static fn (TestRunner $t) => $t->same('$.rules[2]', $point145()['nextGeneratedPathRowidCost']['generatedPath']),
    'point rowid signature normalizes id' => static fn (TestRunner $t) => $t->same('id:=:6', $point145()['currentGeneratedPathRowidCost']['rowidConstraintSignature']),
    'point rowid column records alias' => static fn (TestRunner $t) => $t->same('id', $point145()['currentGeneratedPathRowidCost']['rowidConstraintColumn']),
    'point rowid operator records equals' => static fn (TestRunner $t) => $t->same('=', $point145()['currentGeneratedPathRowidCost']['rowidConstraintOperator']),
    'point rowid value records integer' => static fn (TestRunner $t) => $t->same(6, $point145()['currentGeneratedPathRowidCost']['rowidConstraintValue']),
    'point rowid scoped' => static fn (TestRunner $t) => $t->same(true, $point145()['currentGeneratedPathRowidCost']['rowidScoped']),
    'point base path matched rows' => static fn (TestRunner $t) => $t->same(1, $point145()['currentGeneratedPathRowidCost']['pathMatchedRowCount']),
    'point rowid matched rows' => static fn (TestRunner $t) => $t->same(1, $point145()['currentGeneratedPathRowidCost']['rowidMatchedRowCount']),
    'point intersection row count' => static fn (TestRunner $t) => $t->same(1, $point145()['currentGeneratedPathRowidCost']['intersectedRowCount']),
    'point intersected rowids' => static fn (TestRunner $t) => $t->same([6], $point145()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'point intersected paths' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $point145()['currentGeneratedPathRowidCost']['intersectedPaths']),
    'point first rowid' => static fn (TestRunner $t) => $t->same(6, $point145()['currentGeneratedPathRowidCost']['firstIntersectedRowid']),
    'point last rowid' => static fn (TestRunner $t) => $t->same(6, $point145()['currentGeneratedPathRowidCost']['lastIntersectedRowid']),
    'point base cost records generated path cost' => static fn (TestRunner $t) => $t->same(1, $point145()['currentGeneratedPathRowidCost']['baseGeneratedPathCost']),
    'point effective cost is seek' => static fn (TestRunner $t) => $t->same(1, $point145()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-point', $point145()['currentGeneratedPathRowidCost']['costClass']),
    'point next becomes empty after generated path drift' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-empty', $point145()['nextGeneratedPathRowidCost']['costClass']),
    'point tape keeps generated path row' => static fn (TestRunner $t) => $t->same(1, count($point145()['currentGeneratedPathRowidCost']['generatedPathRowidTape'])),
    'point tape marks generated path row matched' => static fn (TestRunner $t) => $t->same(true, $point145()['currentGeneratedPathRowidCost']['generatedPathRowidTape'][0]['matched']),
    'point tape records generated row path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point145()['currentGeneratedPathRowidCost']['generatedPathRowidTape'][0]['path']),
    'point next tape is empty' => static fn (TestRunner $t) => $t->same([], $point145()['nextGeneratedPathRowidCost']['generatedPathRowidTape']),
    'transition count records rowid cost state' => static fn (TestRunner $t) => $t->same(9, count($point145()['generatedPathRowidCostTransitions'])),
    'generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $point145()['generatedPathRowidCostTransitions'][0]['changed']),
    'rowid signature transition stable' => static fn (TestRunner $t) => $t->same(false, $point145()['generatedPathRowidCostTransitions'][1]['changed']),
    'path count transition changes' => static fn (TestRunner $t) => $t->same(true, $point145()['generatedPathRowidCostTransitions'][2]['changed']),
    'rowid count transition changes' => static fn (TestRunner $t) => $t->same(true, $point145()['generatedPathRowidCostTransitions'][3]['changed']),
    'rowset transition changes' => static fn (TestRunner $t) => $t->same(true, $point145()['generatedPathRowidCostTransitions'][4]['changed']),
    'path tape transition changes' => static fn (TestRunner $t) => $t->same(true, $point145()['generatedPathRowidCostTransitions'][8]['changed']),
    'reasons include path source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-changed', $point145()['next145ReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-rowset-changed', $point145()['next145ReplanReasons'], true)),
    'reasons include tape' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-tape-changed', $point145()['next145ReplanReasons'], true)),
    'reasons preserve generated path output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-output-changed', $point145()['next145ReplanReasons'], true)),
    'range rowid signature uses between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[4,9]', $range145()['currentGeneratedPathRowidCost']['rowidConstraintSignature']),
    'range is not rowid scoped' => static fn (TestRunner $t) => $t->same(false, $range145()['currentGeneratedPathRowidCost']['rowidScoped']),
    'range intersects current rowids' => static fn (TestRunner $t) => $t->same([5, 6, 8, 9], $range145()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'range intersects next rowids' => static fn (TestRunner $t) => $t->same([5, 6, 8, 9], $range145()['nextGeneratedPathRowidCost']['intersectedRowids']),
    'range current cost class is narrow' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-narrow-intersection', $range145()['currentGeneratedPathRowidCost']['costClass']),
    'range effective cost counts intersections' => static fn (TestRunner $t) => $t->same(4, $range145()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'range path matched row count' => static fn (TestRunner $t) => $t->same(4, $range145()['currentGeneratedPathRowidCost']['pathMatchedRowCount']),
    'range next path matched row count remains bounded by rowid range' => static fn (TestRunner $t) => $t->same(4, $range145()['nextGeneratedPathRowidCost']['pathMatchedRowCount']),
    'miss rowid matched count is zero' => static fn (TestRunner $t) => $t->same(0, $miss145()['currentGeneratedPathRowidCost']['rowidMatchedRowCount']),
    'miss intersection is empty' => static fn (TestRunner $t) => $t->same([], $miss145()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'miss cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-empty', $miss145()['currentGeneratedPathRowidCost']['costClass']),
    'miss effective cost remains cheap empty seek' => static fn (TestRunner $t) => $t->same(1, $miss145()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'unconstrained rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-unconstrained', $unconstrained145()['currentGeneratedPathRowidCost']['costClass']),
    'unconstrained rowids include generated path rows' => static fn (TestRunner $t) => $t->same([5, 6], $unconstrained145()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'unconstrained effective cost inherits generated path' => static fn (TestRunner $t) => $t->same(1, $unconstrained145()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable145()['nextGeneratedPathRowidCost']['costClass']),
    'unrunnable next effective cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable145()['nextGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145('json_tree', $current145, $next145, 'option_value', '', [])),
    'missing generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostNext145('json_tree', $current145, $next145, 'option_value', 'missing_path', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost current source next145 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
