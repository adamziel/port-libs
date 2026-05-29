<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current159 = [
    'option_id' => 159,
    'option_name' => 'wp_plugin_generated_path_rowid_seek_cost',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[1]',
];
$next159 = [
    'option_id' => 159,
    'option_name' => 'wp_plugin_generated_path_rowid_seek_cost',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}],"meta":{"autoload":"yes"}}',
    'scan_root' => '$.rules',
    'generated_path' => '$.rules[2]',
];

$point159 = static fn (?array $current = null, ?array $next = null): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan(
    'json_tree',
    $current ?? $current159,
    $next ?? $next159,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);
$stable159 = static fn (): array => $point159($current159, $current159);
$in159 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan(
    'json_tree',
    array_replace($current159, ['generated_path' => '$.rules']),
    array_replace($next159, ['generated_path' => '$.rules']),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => '_rowid_', 'operator' => 'IN', 'value' => [5, 6, 42, '6', null]],
    ],
    'scan_root',
    [['column' => 'path'], ['column' => 'id']],
);
$between159 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan(
    'json_tree',
    array_replace($current159, ['generated_path' => '$.rules']),
    array_replace($next159, ['generated_path' => '$.rules']),
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'oid', 'operator' => 'BETWEEN', 'value' => [4, 7]],
    ],
    'scan_root',
);
$wide159 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan(
    'json_tree',
    array_replace($current159, ['generated_path' => '$.rules']),
    $next159,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => 'LIKE', 'value' => '$.rules%'],
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [1, 100]],
    ],
    'scan_root',
);
$unconstrained159 = static fn (): array => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan(
    'json_tree',
    $current159,
    $next159,
    'option_value',
    'generated_path',
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ],
    'scan_root',
);
$unrunnable159 = static fn (): array => $point159($current159, array_replace($next159, ['option_value' => null]));

$tests = [
    'normalizes function' => static fn (TestRunner $t) => $t->same('json_tree', $point159()['function']),
    'records next159 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-seek-cost-current-source-next159', $point159()['dependencies'], true)),
    'preserves generated path rowid cost dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-generated-path-rowid-cost-current-source', $point159()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-generated-path-rowid-seek-cost-source-until-cursor-reset', $point159()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-generated-path-rowid-seek-cost-plan', $point159()['nextReaderPolicy']),
    'stable reader policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-generated-path-rowid-seek-cost-plan', $stable159()['nextReaderPolicy']),
    'stable has no next159 reasons' => static fn (TestRunner $t) => $t->same([], $stable159()['next159ReplanReasons']),
    'point signature inherited' => static fn (TestRunner $t) => $t->same('id:=:6', $point159()['currentGeneratedPathRowidSeekCost']['rowidConstraintSignature']),
    'point operator recorded' => static fn (TestRunner $t) => $t->same('=', $point159()['currentGeneratedPathRowidSeekCost']['seekOperator']),
    'point is seekable' => static fn (TestRunner $t) => $t->same(true, $point159()['currentGeneratedPathRowidSeekCost']['seekable']),
    'point seek rowids' => static fn (TestRunner $t) => $t->same([6], $point159()['currentGeneratedPathRowidSeekCost']['seekRowids']),
    'point matched seek rowids' => static fn (TestRunner $t) => $t->same([6], $point159()['currentGeneratedPathRowidSeekCost']['matchedSeekRowids']),
    'point has no missing seek rowids' => static fn (TestRunner $t) => $t->same([], $point159()['currentGeneratedPathRowidSeekCost']['missingSeekRowids']),
    'point hit tape path' => static fn (TestRunner $t) => $t->same('$.rules[1]', $point159()['currentGeneratedPathRowidSeekCost']['seekHitTape'][0]['path']),
    'point estimated seek rows' => static fn (TestRunner $t) => $t->same(1, $point159()['currentGeneratedPathRowidSeekCost']['estimatedSeekRows']),
    'point effective seek cost' => static fn (TestRunner $t) => $t->same(1, $point159()['currentGeneratedPathRowidSeekCost']['effectiveEstimatedCost']),
    'point cost class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-seek-point', $point159()['currentGeneratedPathRowidSeekCost']['costClass']),
    'point next seek becomes empty' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-seek-empty', $point159()['nextGeneratedPathRowidSeekCost']['costClass']),
    'point next missing rowid' => static fn (TestRunner $t) => $t->same([6], $point159()['nextGeneratedPathRowidSeekCost']['missingSeekRowids']),
    'transition count records seek cost state' => static fn (TestRunner $t) => $t->same(8, count($point159()['generatedPathRowidSeekCostTransitions'])),
    'seekable transition stable' => static fn (TestRunner $t) => $t->same(false, $point159()['generatedPathRowidSeekCostTransitions'][1]['changed']),
    'matched seek transition changes' => static fn (TestRunner $t) => $t->same(true, $point159()['generatedPathRowidSeekCostTransitions'][3]['changed']),
    'missing seek transition changes' => static fn (TestRunner $t) => $t->same(true, $point159()['generatedPathRowidSeekCostTransitions'][4]['changed']),
    'seek tape transition changes' => static fn (TestRunner $t) => $t->same(true, $point159()['generatedPathRowidSeekCostTransitions'][5]['changed']),
    'reasons include seek rowset' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-seek-rowset-changed', $point159()['next159ReplanReasons'], true)),
    'reasons include seek cost' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-seek-cost-changed', $point159()['next159ReplanReasons'], true)),
    'reasons preserve next145 output change' => static fn (TestRunner $t) => $t->true(in_array('json-table-generated-path-rowid-tape-changed', $point159()['next159ReplanReasons'], true)),
    'in operator recorded' => static fn (TestRunner $t) => $t->same('IN', $in159()['currentGeneratedPathRowidSeekCost']['seekOperator']),
    'in rowids are unique sorted ints' => static fn (TestRunner $t) => $t->same([5, 6, 42], $in159()['currentGeneratedPathRowidSeekCost']['seekRowids']),
    'in matched rowids' => static fn (TestRunner $t) => $t->same([5, 6], $in159()['currentGeneratedPathRowidSeekCost']['matchedSeekRowids']),
    'in missing rowids' => static fn (TestRunner $t) => $t->same([42], $in159()['currentGeneratedPathRowidSeekCost']['missingSeekRowids']),
    'in hit tape records miss' => static fn (TestRunner $t) => $t->same(false, $in159()['currentGeneratedPathRowidSeekCost']['seekHitTape'][2]['matched']),
    'in cost class is partial' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-seek-partial', $in159()['currentGeneratedPathRowidSeekCost']['costClass']),
    'in effective cost bounded by matching seek rows' => static fn (TestRunner $t) => $t->same(2, $in159()['currentGeneratedPathRowidSeekCost']['effectiveEstimatedCost']),
    'between operator recorded' => static fn (TestRunner $t) => $t->same('BETWEEN', $between159()['currentGeneratedPathRowidSeekCost']['seekOperator']),
    'between seek rowids expanded' => static fn (TestRunner $t) => $t->same([4, 5, 6, 7], $between159()['currentGeneratedPathRowidSeekCost']['seekRowids']),
    'between matched rowids' => static fn (TestRunner $t) => $t->same([4, 5, 6, 7], $between159()['currentGeneratedPathRowidSeekCost']['matchedSeekRowids']),
    'between missing rowids' => static fn (TestRunner $t) => $t->same([], $between159()['currentGeneratedPathRowidSeekCost']['missingSeekRowids']),
    'between cost class narrow' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-seek-narrow', $between159()['currentGeneratedPathRowidSeekCost']['costClass']),
    'wide between is residual' => static fn (TestRunner $t) => $t->same(false, $wide159()['currentGeneratedPathRowidSeekCost']['seekable']),
    'wide between has no seek rowids' => static fn (TestRunner $t) => $t->same([], $wide159()['currentGeneratedPathRowidSeekCost']['seekRowids']),
    'wide between cost class residual' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-seek-residual', $wide159()['currentGeneratedPathRowidSeekCost']['costClass']),
    'wide between adds residual penalty' => static fn (TestRunner $t) => $t->same(7, $wide159()['currentGeneratedPathRowidSeekCost']['effectiveEstimatedCost']),
    'unconstrained seek class' => static fn (TestRunner $t) => $t->same('json-table-generated-path-rowid-seek-unconstrained', $unconstrained159()['currentGeneratedPathRowidSeekCost']['costClass']),
    'unconstrained estimated rows inherit intersections' => static fn (TestRunner $t) => $t->same(2, $unconstrained159()['currentGeneratedPathRowidSeekCost']['estimatedSeekRows']),
    'unconstrained seek rowids empty' => static fn (TestRunner $t) => $t->same([], $unconstrained159()['currentGeneratedPathRowidSeekCost']['seekRowids']),
    'unrunnable next class' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable159()['nextGeneratedPathRowidSeekCost']['costClass']),
    'unrunnable next cost sentinel' => static fn (TestRunner $t) => $t->same(1000000, $unrunnable159()['nextGeneratedPathRowidSeekCost']['effectiveEstimatedCost']),
    'bad generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan('json_tree', $current159, $next159, 'option_value', '', [])),
    'missing generated path column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSeekCostPlan('json_tree', $current159, $next159, 'option_value', 'missing_path', [])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table generated path rowid seek cost current source next159 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
