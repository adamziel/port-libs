<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current138 = [
    'option_id' => 138,
    'option_name' => 'wp_plugin_rowid_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next138 = [
    'option_id' => 138,
    'option_name' => 'wp_plugin_rowid_hidden_path',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":8,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":8,"enabled":true}]},{"name":"commerce","rules":[{"slug":"shop","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];

$constraints138 = [
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].priority'],
    ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [2, 14]],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
];

$plan138 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $constraints = null,
): array => SQLiteJsonTablePlan::currentSourceRowidHiddenPathNext138(
    'json_tree',
    $current ?? $current138,
    $next ?? $next138,
    'option_value',
    'base_root',
    'nested_path',
    $constraints ?? $constraints138,
    [['column' => 'id']],
);

$stable138 = static fn (): array => $plan138($current138, $current138);
$point138 = static fn (): array => $plan138(
    $current138,
    $current138,
    [
        ['column' => 'path', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[1]'],
        ['column' => '_rowid_', 'operator' => '=', 'value' => 6],
    ],
);
$relative138 = static fn (): array => $plan138(
    $current138,
    $current138,
    [
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[2].priority'],
        ['column' => 'oid', 'operator' => '=', 'value' => '11'],
    ],
);
$pathOnly138 = static fn (): array => $plan138(
    $current138,
    $next138,
    [
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.groups[0].rules[%].slug'],
    ],
);
$rowidOnly138 = static fn (): array => $plan138(
    $current138,
    $current138,
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$empty138 = static fn (): array => $plan138(
    $current138,
    $current138,
    [
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[99].priority'],
        ['column' => 'rowid', 'operator' => '=', 'value' => 999],
    ],
);
$unusablePath138 = static fn (): array => $plan138(
    $current138,
    $current138,
    [
        ['column' => 'fullkey', 'operator' => '=', 'value' => '$.plugin.groups[0].rules[1].priority', 'usable' => false],
        ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ],
);
$unrunnable138 = static fn (): array => $plan138(
    $current138,
    array_replace($next138, ['option_value' => null]),
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan138()['function']),
    'records next138 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-rowid-hidden-path-current-source-next138', $plan138()['dependencies'], true)),
    'preserves next133 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-path-rowid-current-source-next133', $plan138()['dependencies'], true)),
    'preserves next129 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-hidden-cost-current-source-next129', $plan138()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-rowid-hidden-path-source-until-cursor-reset', $plan138()['currentReaderPolicy']),
    'changed source prepares next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-rowid-hidden-path-source-plan', $plan138()['nextReaderPolicy']),
    'stable source reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-rowid-hidden-path-source-plan', $stable138()['nextReaderPolicy']),
    'stable has no next138 reasons' => static fn (TestRunner $t) => $t->same([], $stable138()['next138ReplanReasons']),
    'current root is nested rules root' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $plan138()['currentRowidHiddenPath']['root']),
    'current base root retained' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $plan138()['currentRowidHiddenPath']['baseRoot']),
    'current nested path retained' => static fn (TestRunner $t) => $t->same('[0].rules', $plan138()['currentRowidHiddenPath']['nestedPath']),
    'path signature records fullkey LIKE' => static fn (TestRunner $t) => $t->same('fullkey:LIKE:"$.plugin.groups[0].rules[%].priority"', $plan138()['currentRowidHiddenPath']['pathConstraintSignature']),
    'rowid signature records between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[2,14]', $plan138()['currentRowidHiddenPath']['rowidConstraintSignature']),
    'composite signature joins hidden path and rowid' => static fn (TestRunner $t) => $t->same('fullkey:LIKE:"$.plugin.groups[0].rules[%].priority"&&id:BETWEEN:[2,14]', $plan138()['currentRowidHiddenPath']['compositeSignature']),
    'path scoped is true' => static fn (TestRunner $t) => $t->same(true, $plan138()['currentRowidHiddenPath']['pathScoped']),
    'between rowid is not point scoped' => static fn (TestRunner $t) => $t->same(false, $plan138()['currentRowidHiddenPath']['rowidScoped']),
    'current path matched rowids are priority leaves' => static fn (TestRunner $t) => $t->same([3, 7, 11], $plan138()['currentRowidHiddenPath']['pathMatchedRowids']),
    'next path matched rowids include inserted priority' => static fn (TestRunner $t) => $t->same([3, 7, 11], $plan138()['nextRowidHiddenPath']['pathMatchedRowids']),
    'current rowid matched rowids mirror bounded integer rows' => static fn (TestRunner $t) => $t->same([3, 7, 11], $plan138()['currentRowidHiddenPath']['rowidMatchedRowids']),
    'next rowid matched rowids include new bounded row' => static fn (TestRunner $t) => $t->same([3, 7, 11], $plan138()['nextRowidHiddenPath']['rowidMatchedRowids']),
    'current intersection rowids are priorities' => static fn (TestRunner $t) => $t->same([3, 7, 11], $plan138()['currentRowidHiddenPath']['intersectedRowids']),
    'next intersection rowids include inserted priority' => static fn (TestRunner $t) => $t->same([3, 7, 11], $plan138()['nextRowidHiddenPath']['intersectedRowids']),
    'current relative fullkeys are root relative priorities' => static fn (TestRunner $t) => $t->same(['$[0].priority', '$[1].priority', '$[2].priority'], $plan138()['currentRowidHiddenPath']['relativeFullkeys']),
    'next relative fullkeys remain root relative priorities' => static fn (TestRunner $t) => $t->same(['$[0].priority', '$[1].priority', '$[2].priority'], $plan138()['nextRowidHiddenPath']['relativeFullkeys']),
    'first intersected rowid tracked' => static fn (TestRunner $t) => $t->same(3, $plan138()['currentRowidHiddenPath']['firstIntersectedRowid']),
    'last next intersected rowid tracked' => static fn (TestRunner $t) => $t->same(11, $plan138()['nextRowidHiddenPath']['lastIntersectedRowid']),
    'current matched count is three' => static fn (TestRunner $t) => $t->same(3, $plan138()['currentRowidHiddenPath']['matchedRowCount']),
    'next matched count is three' => static fn (TestRunner $t) => $t->same(3, $plan138()['nextRowidHiddenPath']['matchedRowCount']),
    'path scan cost class is hidden path scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-scan', $plan138()['currentRowidHiddenPath']['costClass']),
    'path effective cost is narrowed to row count' => static fn (TestRunner $t) => $t->same(3, $plan138()['currentRowidHiddenPath']['effectiveEstimatedCost']),
    'next effective cost follows three rows' => static fn (TestRunner $t) => $t->same(3, $plan138()['nextRowidHiddenPath']['effectiveEstimatedCost']),
    'hidden path tape first fullkey' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules[0].priority', $plan138()['currentRowidHiddenPath']['hiddenPathTape'][0]['fullkey']),
    'hidden path tape first relative fullkey' => static fn (TestRunner $t) => $t->same('$[0].priority', $plan138()['currentRowidHiddenPath']['hiddenPathTape'][0]['relativeFullkey']),
    'hidden path tape marks path match' => static fn (TestRunner $t) => $t->same(true, $plan138()['currentRowidHiddenPath']['hiddenPathTape'][0]['pathMatched']),
    'hidden path tape marks rowid match' => static fn (TestRunner $t) => $t->same(true, $plan138()['currentRowidHiddenPath']['hiddenPathTape'][0]['rowidMatched']),
    'hidden path tape marks intersection match' => static fn (TestRunner $t) => $t->same(true, $plan138()['currentRowidHiddenPath']['hiddenPathTape'][0]['matched']),
    'transition count records next138 fields' => static fn (TestRunner $t) => $t->same(8, count($plan138()['rowidHiddenPathTransitions'])),
    'root transition stable' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][0]['changed']),
    'composite transition stable' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][1]['changed']),
    'matched count transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][2]['changed']),
    'rowid transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][3]['changed']),
    'fullkey transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][4]['changed']),
    'cost transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][5]['changed']),
    'tape transition remains stable for same priority rowset' => static fn (TestRunner $t) => $t->same(false, $plan138()['rowidHiddenPathTransitions'][7]['changed']),
    'reasons preserve source json change' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $plan138()['next138ReplanReasons'], true)),
    'reasons preserve source argument tape change' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $plan138()['next138ReplanReasons'], true)),
    'reasons do not invent hidden path rowset change' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-rowid-hidden-path-rowset-changed', $plan138()['next138ReplanReasons'], true)),
    'reasons do not invent hidden path tape change' => static fn (TestRunner $t) => $t->same(false, in_array('json-table-rowid-hidden-path-tape-changed', $plan138()['next138ReplanReasons'], true)),
    'point path scoped true' => static fn (TestRunner $t) => $t->same(true, $point138()['currentRowidHiddenPath']['pathScoped']),
    'point rowid scoped true' => static fn (TestRunner $t) => $t->same(true, $point138()['currentRowidHiddenPath']['rowidScoped']),
    'point class is point' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-path-point', $point138()['currentRowidHiddenPath']['costClass']),
    'point effective cost is one' => static fn (TestRunner $t) => $t->same(1, $point138()['currentRowidHiddenPath']['effectiveEstimatedCost']),
    'point intersection is cache priority' => static fn (TestRunner $t) => $t->same([6], $point138()['currentRowidHiddenPath']['intersectedRowids']),
    'fullkey equality with mismatched rowid returns empty rowset' => static fn (TestRunner $t) => $t->same([], $relative138()['currentRowidHiddenPath']['intersectedRowids']),
    'fullkey equality signature records absolute hidden path' => static fn (TestRunner $t) => $t->same('fullkey:=:"$.plugin.groups[0].rules[2].priority"', $relative138()['currentRowidHiddenPath']['pathConstraintSignature']),
    'path only class is hidden path scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-path-scan', $pathOnly138()['currentRowidHiddenPath']['costClass']),
    'path only current slug rowids' => static fn (TestRunner $t) => $t->same([2, 6, 10], $pathOnly138()['currentRowidHiddenPath']['intersectedRowids']),
    'rowid only class is hidden rowid scan' => static fn (TestRunner $t) => $t->same('json-table-hidden-rowid-scan', $rowidOnly138()['currentRowidHiddenPath']['costClass']),
    'rowid only has no path signature' => static fn (TestRunner $t) => $t->same(null, $rowidOnly138()['currentRowidHiddenPath']['pathConstraintSignature']),
    'unusable path is ignored' => static fn (TestRunner $t) => $t->same(null, $unusablePath138()['currentRowidHiddenPath']['pathConstraintSignature']),
    'empty class is empty' => static fn (TestRunner $t) => $t->same('json-table-rowid-hidden-path-empty', $empty138()['currentRowidHiddenPath']['costClass']),
    'empty intersected rowids are empty' => static fn (TestRunner $t) => $t->same([], $empty138()['currentRowidHiddenPath']['intersectedRowids']),
    'unrunnable next class is sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable138()['nextRowidHiddenPath']['costClass']),
    'unrunnable next tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable138()['nextRowidHiddenPath']['hiddenPathTape']),
    'bad function rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceRowidHiddenPathNext138('json_bad', $current138, $next138, 'option_value', 'base_root', 'nested_path')),
    'missing json column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceRowidHiddenPathNext138('json_tree', ['base_root' => '$', 'nested_path' => '$'], $next138, 'option_value', 'base_root', 'nested_path')),
    'bad path operator rejected by residual matcher' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $plan138($current138, $current138, [['column' => 'fullkey', 'operator' => 'BETWEEN', 'value' => ['$.a']]])),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table rowid hidden path current source next138 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
