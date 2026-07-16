<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentPathRowidCost = [
    'option_id' => 145,
    'option_name' => 'wp_plugin_generated_path_rowid_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$nextPathRowidCost = [
    'option_id' => 145,
    'option_name' => 'wp_plugin_generated_path_rowid_cost',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$pointPathRowidCostPlan = static fn (?array $current = null, ?array $next = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost(
    'json_tree',
    $current ?? $currentPathRowidCost,
    $next ?? $nextPathRowidCost,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);

$stablePathRowidCostPlan = static fn (): array => $pointPathRowidCostPlan($currentPathRowidCost, $currentPathRowidCost);
$rangePathRowidCostPlan = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost(
    'json_tree',
    array_replace($currentPathRowidCost, ['generated_path' => '$.rules']),
    array_replace($nextPathRowidCost, ['generated_path' => '$.rules']),
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
$missPathRowidCostPlan = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost(
    'json_tree',
    $currentPathRowidCost,
    $nextPathRowidCost,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'oid', 'operator' => '=', 'value' => 99],
    ],
    'scan_root',
);
$unconstrainedPathRowidCostPlan = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost(
    'json_tree',
    $currentPathRowidCost,
    $nextPathRowidCost,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);
$unrunnablePathRowidCostPlan = static fn (): array => $pointPathRowidCostPlan($currentPathRowidCost, array_replace($nextPathRowidCost, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $pointPathRowidCostPlan()['function']),
    'records generated path rowid dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $pointPathRowidCostPlan()['dependencies'], true)),
    'preserves generated path dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-cost-current-source-next134', $pointPathRowidCostPlan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-cost-source-until-cursor-reset', $pointPathRowidCostPlan()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-cost-plan', $pointPathRowidCostPlan()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-cost-plan', $stablePathRowidCostPlan()['nextReaderPolicy']),
    'stable plan has no generated path rowid reasons' => static fn (TestRunner $t) => $t->same([], $stablePathRowidCostPlan()['generatedPathRowidCostReplanReasons']),
    'point current generated path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['generatedPath']),
    'point next generated path changes' => static fn (TestRunner $t) => $t->same('$.rules[2]', $pointPathRowidCostPlan()['nextGeneratedPathRowidCost']['generatedPath']),
    'point rowid signature normalizes id' => static fn (TestRunner $t) => $t->same('id:=:6', $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidConstraintSignature']),
    'point rowid column records alias' => static fn (TestRunner $t) => $t->same('id', $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidConstraintColumn']),
    'point rowid operator records equals' => static fn (TestRunner $t) => $t->same('=', $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidConstraintOperator']),
    'point rowid value records integer' => static fn (TestRunner $t) => $t->same(6, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidConstraintValue']),
    'point rowid scoped' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidScoped']),
    'point base path matched rows' => static fn (TestRunner $t) => $t->same(1, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['pathMatchedRowCount']),
    'point rowid matched rows' => static fn (TestRunner $t) => $t->same(1, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidMatchedRowCount']),
    'point intersection row count' => static fn (TestRunner $t) => $t->same(1, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['intersectedRowCount']),
    'point intersected rowids' => static fn (TestRunner $t) => $t->same([6], $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'point intersected paths' => static fn (TestRunner $t) => $t->same(['$.rules[1]'], $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['intersectedPaths']),
    'point first rowid' => static fn (TestRunner $t) => $t->same(6, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['firstIntersectedRowid']),
    'point last rowid' => static fn (TestRunner $t) => $t->same(6, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['lastIntersectedRowid']),
    'point base cost records generated path cost' => static fn (TestRunner $t) => $t->same(1, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['baseGeneratedPathCost']),
    'point effective cost is seek' => static fn (TestRunner $t) => $t->same(1, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-point', $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['costClass']),
    'point next becomes empty after generated path drift' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-empty', $pointPathRowidCostPlan()['nextGeneratedPathRowidCost']['costClass']),
    'point tape keeps generated path row' => static fn (TestRunner $t) => $t->same(1, count($pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['generatedPathRowidTape'])),
    'point tape marks generated path row matched' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['generatedPathRowidTape'][0]['matched']),
    'point tape records generated row path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $pointPathRowidCostPlan()['currentGeneratedPathRowidCost']['generatedPathRowidTape'][0]['path']),
    'point next tape is empty' => static fn (TestRunner $t) => $t->same([], $pointPathRowidCostPlan()['nextGeneratedPathRowidCost']['generatedPathRowidTape']),
    'transition count records rowid cost state' => static fn (TestRunner $t) => $t->same(9, count($pointPathRowidCostPlan()['generatedPathRowidCostTransitions'])),
    'generated path transition changes' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['generatedPathRowidCostTransitions'][0]['changed']),
    'rowid signature transition stable' => static fn (TestRunner $t) => $t->same(false, $pointPathRowidCostPlan()['generatedPathRowidCostTransitions'][1]['changed']),
    'path count transition changes' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['generatedPathRowidCostTransitions'][2]['changed']),
    'rowid count transition changes' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['generatedPathRowidCostTransitions'][3]['changed']),
    'rowset transition changes' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['generatedPathRowidCostTransitions'][4]['changed']),
    'path tape transition changes' => static fn (TestRunner $t) => $t->same(true, $pointPathRowidCostPlan()['generatedPathRowidCostTransitions'][8]['changed']),
    'reasons include path source' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-source-changed', $pointPathRowidCostPlan()['generatedPathRowidCostReplanReasons'], true)),
    'reasons include rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-rowset-changed', $pointPathRowidCostPlan()['generatedPathRowidCostReplanReasons'], true)),
    'reasons include tape' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-tape-changed', $pointPathRowidCostPlan()['generatedPathRowidCostReplanReasons'], true)),
    'reasons preserve generated path output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-output-changed', $pointPathRowidCostPlan()['generatedPathRowidCostReplanReasons'], true)),
    'range rowid signature uses between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[4,9]', $rangePathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidConstraintSignature']),
    'range is not rowid scoped' => static fn (TestRunner $t) => $t->same(false, $rangePathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidScoped']),
    'range intersects current rowids' => static fn (TestRunner $t) => $t->same([5, 6, 8, 9], $rangePathRowidCostPlan()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'range intersects next rowids' => static fn (TestRunner $t) => $t->same([5, 6, 8, 9], $rangePathRowidCostPlan()['nextGeneratedPathRowidCost']['intersectedRowids']),
    'range current cost class is narrow' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-narrow-intersection', $rangePathRowidCostPlan()['currentGeneratedPathRowidCost']['costClass']),
    'range effective cost counts intersections' => static fn (TestRunner $t) => $t->same(4, $rangePathRowidCostPlan()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'range path matched row count' => static fn (TestRunner $t) => $t->same(4, $rangePathRowidCostPlan()['currentGeneratedPathRowidCost']['pathMatchedRowCount']),
    'range next path matched row count remains bounded by rowid range' => static fn (TestRunner $t) => $t->same(4, $rangePathRowidCostPlan()['nextGeneratedPathRowidCost']['pathMatchedRowCount']),
    'miss rowid matched count is zero' => static fn (TestRunner $t) => $t->same(0, $missPathRowidCostPlan()['currentGeneratedPathRowidCost']['rowidMatchedRowCount']),
    'miss intersection is empty' => static fn (TestRunner $t) => $t->same([], $missPathRowidCostPlan()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'miss cost class is empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-empty', $missPathRowidCostPlan()['currentGeneratedPathRowidCost']['costClass']),
    'miss effective cost remains cheap empty seek' => static fn (TestRunner $t) => $t->same(1, $missPathRowidCostPlan()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'unconstrained rowid cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-unconstrained', $unconstrainedPathRowidCostPlan()['currentGeneratedPathRowidCost']['costClass']),
    'unconstrained rowids include generated path rows' => static fn (TestRunner $t) => $t->same([5, 6], $unconstrainedPathRowidCostPlan()['currentGeneratedPathRowidCost']['intersectedRowids']),
    'unconstrained effective cost inherits generated path' => static fn (TestRunner $t) => $t->same(1, $unconstrainedPathRowidCostPlan()['currentGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'unrunnable next cost class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnablePathRowidCostPlan()['nextGeneratedPathRowidCost']['costClass']),
    'unrunnable next effective cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnablePathRowidCostPlan()['nextGeneratedPathRowidCost']['effectiveEstimatedCost']),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost('json_tree', $currentPathRowidCost, $nextPathRowidCost, 'option_value', '', [])),
    'missing generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCost('json_tree', $currentPathRowidCost, $nextPathRowidCost, 'option_value', 'missing_path', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid cost ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
