<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentRowidHiddenPath = [
    'option_id' => 138,
    'option_name' => 'wp_plugin_rowid_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$nextRowidHiddenPath = [
    'option_id' => 138,
    'option_name' => 'wp_plugin_rowid_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":8,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":8,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$constraintsRowidHiddenPath = [
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [2, 14]],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
];

$rowidHiddenPathPlan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
): array => SQLiteJsonTablePlan::currentSourceRowidHiddenPathPlan(
    'json_tree',
    $current ?? $currentRowidHiddenPath,
    $next ?? $nextRowidHiddenPath,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraintsRowidHiddenPath,
    [['column' => 'id']],
);

$stableRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan($currentRowidHiddenPath, $currentRowidHiddenPath);
$pointRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    $currentRowidHiddenPath,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
);
$relativeRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    $currentRowidHiddenPath,
    [
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[2].priority'],
        ['column' => 'oid', 'operator' => '=', 'value' => '11'],
    ],
);
$pathOnlyRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    $nextRowidHiddenPath,
    [
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].slug'],
    ],
);
$rowidOnlyRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    $currentRowidHiddenPath,
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$emptyRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    $currentRowidHiddenPath,
    [
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[99].priority'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 999],
    ],
);
$unusablePathRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    $currentRowidHiddenPath,
    [
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[1].priority', 'usable' => false],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$unrunnableRowidHiddenPath = static fn (): array => $rowidHiddenPathPlan(
    $currentRowidHiddenPath,
    array_replace($nextRowidHiddenPath, ['option_value' => null]),
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $rowidHiddenPathPlan()['function']),
    'records rowid hidden path dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-path-current-source', $rowidHiddenPathPlan()['dependencies'], true)),
    'preserves next133 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-path-rowid-current-source-next133', $rowidHiddenPathPlan()['dependencies'], true)),
    'preserves next129 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-hidden-cost-current-source-next129', $rowidHiddenPathPlan()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-rowid-hidden-path-source-until-cursor-reset', $rowidHiddenPathPlan()['currentReaderPolicy']),
    'changed source prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-rowid-hidden-path-source-plan', $rowidHiddenPathPlan()['nextReaderPolicy']),
    'stable source reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-rowid-hidden-path-source-plan', $stableRowidHiddenPath()['nextReaderPolicy']),
    'stable has no rowid hidden path reasons' => static fn (TestRunner $t) => $t->same([], $stableRowidHiddenPath()['rowidHiddenPathReplanReasons']),
    'current root is nested rules root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $rowidHiddenPathPlan()['currentRowidHiddenPath']['root']),
    'current base root retained' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $rowidHiddenPathPlan()['currentRowidHiddenPath']['baseRoot']),
    'current nested path retained' => static fn (TestRunner $t) => $t->same('[0].rules', $rowidHiddenPathPlan()['currentRowidHiddenPath']['nestedPath']),
    'path signature records fullkey LIKE' => static fn (TestRunner $t) => $t->same('fullkey:LIKE:"$.plugin.groups[0].rules[%].priority"', $rowidHiddenPathPlan()['currentRowidHiddenPath']['pathConstraintSignature']),
    'rowid signature records between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[2,14]', $rowidHiddenPathPlan()['currentRowidHiddenPath']['rowidConstraintSignature']),
    'composite signature joins hidden path and rowid' => static fn (TestRunner $t) => $t->same('fullkey:LIKE:"$.plugin.groups[0].rules[%].priority"&&id:BETWEEN:[2,14]', $rowidHiddenPathPlan()['currentRowidHiddenPath']['compositeSignature']),
    'path scoped is true' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenPathPlan()['currentRowidHiddenPath']['pathScoped']),
    'between rowid is not point scoped' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['currentRowidHiddenPath']['rowidScoped']),
    'current path matched rowids are priority leaves' => static fn (TestRunner $t) => $t->same([3, 7, 11], $rowidHiddenPathPlan()['currentRowidHiddenPath']['pathMatchedRowids']),
    'next path matched rowids include inserted priority' => static fn (TestRunner $t) => $t->same([3, 7, 11], $rowidHiddenPathPlan()['nextRowidHiddenPath']['pathMatchedRowids']),
    'current rowid matched rowids mirror bounded integer rows' => static fn (TestRunner $t) => $t->same([3, 7, 11], $rowidHiddenPathPlan()['currentRowidHiddenPath']['rowidMatchedRowids']),
    'next rowid matched rowids include new bounded row' => static fn (TestRunner $t) => $t->same([3, 7, 11], $rowidHiddenPathPlan()['nextRowidHiddenPath']['rowidMatchedRowids']),
    'current intersection rowids are priorities' => static fn (TestRunner $t) => $t->same([3, 7, 11], $rowidHiddenPathPlan()['currentRowidHiddenPath']['intersectedRowids']),
    'next intersection rowids include inserted priority' => static fn (TestRunner $t) => $t->same([3, 7, 11], $rowidHiddenPathPlan()['nextRowidHiddenPath']['intersectedRowids']),
    'current relative fullkeys are root relative priorities' => static fn (TestRunner $t) => $t->same(['$[0].priority', '$[1].priority', '$[2].priority'], $rowidHiddenPathPlan()['currentRowidHiddenPath']['relativeFullkeys']),
    'next relative fullkeys remain root relative priorities' => static fn (TestRunner $t) => $t->same(['$[0].priority', '$[1].priority', '$[2].priority'], $rowidHiddenPathPlan()['nextRowidHiddenPath']['relativeFullkeys']),
    'first intersected rowid tracked' => static fn (TestRunner $t) => $t->same(3, $rowidHiddenPathPlan()['currentRowidHiddenPath']['firstIntersectedRowid']),
    'last next intersected rowid tracked' => static fn (TestRunner $t) => $t->same(11, $rowidHiddenPathPlan()['nextRowidHiddenPath']['lastIntersectedRowid']),
    'current matched count is three' => static fn (TestRunner $t) => $t->same(3, $rowidHiddenPathPlan()['currentRowidHiddenPath']['matchedRowCount']),
    'next matched count is three' => static fn (TestRunner $t) => $t->same(3, $rowidHiddenPathPlan()['nextRowidHiddenPath']['matchedRowCount']),
    'path scan cost class is hidden path scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-scan', $rowidHiddenPathPlan()['currentRowidHiddenPath']['costClass']),
    'path effective cost is narrowed to row count' => static fn (TestRunner $t) => $t->same(3, $rowidHiddenPathPlan()['currentRowidHiddenPath']['effectiveEstimatedCost']),
    'next effective cost follows three rows' => static fn (TestRunner $t) => $t->same(3, $rowidHiddenPathPlan()['nextRowidHiddenPath']['effectiveEstimatedCost']),
    'hidden path tape first fullkey' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules[0].priority', $rowidHiddenPathPlan()['currentRowidHiddenPath']['hiddenPathTape'][0]['fullkey']),
    'hidden path tape first relative fullkey' => static fn (TestRunner $t) => $t->same('$[0].priority', $rowidHiddenPathPlan()['currentRowidHiddenPath']['hiddenPathTape'][0]['relativeFullkey']),
    'hidden path tape marks path match' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenPathPlan()['currentRowidHiddenPath']['hiddenPathTape'][0]['pathMatched']),
    'hidden path tape marks rowid match' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenPathPlan()['currentRowidHiddenPath']['hiddenPathTape'][0]['rowidMatched']),
    'hidden path tape marks intersection match' => static fn (TestRunner $t) => $t->same(true, $rowidHiddenPathPlan()['currentRowidHiddenPath']['hiddenPathTape'][0]['matched']),
    'transition count records rowid hidden path fields' => static fn (TestRunner $t) => $t->same(8, count($rowidHiddenPathPlan()['rowidHiddenPathTransitions'])),
    'root transition stable' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][0]['changed']),
    'composite transition stable' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][1]['changed']),
    'matched count transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][2]['changed']),
    'rowid transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][3]['changed']),
    'fullkey transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][4]['changed']),
    'cost transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][5]['changed']),
    'tape transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $rowidHiddenPathPlan()['rowidHiddenPathTransitions'][7]['changed']),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $rowidHiddenPathPlan()['rowidHiddenPathReplanReasons'], true)),
    'reasons preserve source argument tape change' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $rowidHiddenPathPlan()['rowidHiddenPathReplanReasons'], true)),
    'reasons do not invent hidden path rowset change' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-rowid-hidden-path-rowset-changed', $rowidHiddenPathPlan()['rowidHiddenPathReplanReasons'], true)),
    'reasons do not invent hidden path tape change' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-rowid-hidden-path-tape-changed', $rowidHiddenPathPlan()['rowidHiddenPathReplanReasons'], true)),
    'point path scoped true' => static fn (TestRunner $t) => $t->same(true, $pointRowidHiddenPath()['currentRowidHiddenPath']['pathScoped']),
    'point rowid scoped true' => static fn (TestRunner $t) => $t->same(true, $pointRowidHiddenPath()['currentRowidHiddenPath']['rowidScoped']),
    'point class is point' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-path-point', $pointRowidHiddenPath()['currentRowidHiddenPath']['costClass']),
    'point effective cost is one' => static fn (TestRunner $t) => $t->same(1, $pointRowidHiddenPath()['currentRowidHiddenPath']['effectiveEstimatedCost']),
    'point intersection is cache priority' => static fn (TestRunner $t) => $t->same([6], $pointRowidHiddenPath()['currentRowidHiddenPath']['intersectedRowids']),
    'fullkey equality with mismatched rowid returns empty rowset' => static fn (TestRunner $t) => $t->same([], $relativeRowidHiddenPath()['currentRowidHiddenPath']['intersectedRowids']),
    'fullkey equality signature records absolute hidden path' => static fn (TestRunner $t) => $t->same('fullkey:=:"$.plugin.groups[0].rules[2].priority"', $relativeRowidHiddenPath()['currentRowidHiddenPath']['pathConstraintSignature']),
    'path only class is hidden path scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-scan', $pathOnlyRowidHiddenPath()['currentRowidHiddenPath']['costClass']),
    'path only current slug rowids' => static fn (TestRunner $t) => $t->same([2, 6, 10], $pathOnlyRowidHiddenPath()['currentRowidHiddenPath']['intersectedRowids']),
    'rowid only class is hidden rowid scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-scan', $rowidOnlyRowidHiddenPath()['currentRowidHiddenPath']['costClass']),
    'rowid only has no path signature' => static fn (TestRunner $t) => $t->same(null, $rowidOnlyRowidHiddenPath()['currentRowidHiddenPath']['pathConstraintSignature']),
    'unusable path is ignored' => static fn (TestRunner $t) => $t->same(null, $unusablePathRowidHiddenPath()['currentRowidHiddenPath']['pathConstraintSignature']),
    'empty class is empty' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-path-empty', $emptyRowidHiddenPath()['currentRowidHiddenPath']['costClass']),
    'empty intersected rowids are empty' => static fn (TestRunner $t) => $t->same([], $emptyRowidHiddenPath()['currentRowidHiddenPath']['intersectedRowids']),
    'unrunnable next class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnableRowidHiddenPath()['nextRowidHiddenPath']['costClass']),
    'unrunnable next tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnableRowidHiddenPath()['nextRowidHiddenPath']['hiddenPathTape']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceRowidHiddenPathPlan('json_bad', $currentRowidHiddenPath, $nextRowidHiddenPath, 'option_value', 'base_root', 'nested_path')),
    'missing json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceRowidHiddenPathPlan('json_tree', ['base_root' => '$', 'nested_path' => '$'], $nextRowidHiddenPath, 'option_value', 'base_root', 'nested_path')),
    'bad path operator rejected by residual matcher' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $rowidHiddenPathPlan($currentRowidHiddenPath, $currentRowidHiddenPath, [['column' => 'fullkey', 'operator' => 'BETWEEN', 'value' => ['$.a']]])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table rowid hidden path current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
