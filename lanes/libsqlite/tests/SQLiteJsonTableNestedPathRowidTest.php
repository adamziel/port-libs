<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current133 = [
    'option_id' => 133,
    'option_name' => 'wp_plugin_nested_path_rowid',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[0].rules',
];
$next133 = [
    'option_id' => 133,
    'option_name' => 'wp_plugin_nested_path_rowid',
    'option_value' => '{"plugin":{"groups":[{"name":"core","rules":[{"slug":"seo","priority":3,"enabled":true},{"slug":"cache","priority":8,"enabled":false}]},{"name":"forms","rules":[{"slug":"forms","priority":4,"enabled":true},{"slug":"lead","priority":6,"enabled":true},{"slug":"spam","priority":1,"enabled":false}]}]}}',
    'base_root' => '$.plugin.groups',
    'nested_path' => '[1].rules',
];

$point133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current133,
    $next133,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 2],
    ],
    [['column' => 'id']],
);

$stable133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current133,
    $current133,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 2],
    ],
    [['column' => 'id']],
);

$miss133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current133,
    $next133,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'oid', 'operator' => '=', 'value' => 99],
    ],
    [['column' => 'id']],
);

$range133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current133,
    $next133,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => 'BETWEEN', 'value' => [2, 5]],
        ['column' => 'type', 'operator' => '=', 'value' => 'text'],
    ],
    [['column' => 'id']],
);

$absolute133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    array_replace($current133, ['nested_path' => '$.plugin.groups[1].rules']),
    array_replace($current133, ['nested_path' => '$.plugin.groups[1].rules']),
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => '_rowid_', 'operator' => '=', 'value' => '2'],
    ],
    [['column' => 'id']],
);

$unusable133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current133,
    $next133,
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 2, 'usable' => false],
        ['column' => 'type', 'operator' => '=', 'value' => 'text'],
    ],
    [['column' => 'id']],
);

$unrunnable133 = static fn (): array => SQLiteJsonTablePlan::currentSourceNestedPathRowid(
    'json_tree',
    $current133,
    array_replace($next133, ['option_value' => null]),
    'option_value',
    'base_root',
    'nested_path',
    [
        ['column' => 'rowid', 'operator' => '=', 'value' => 2],
    ],
    [['column' => 'id']],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $point133()['function']),
    'records next133 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-path-rowid-current-source-next133', $point133()['dependencies'], true)),
    'preserves next129 dependency' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-nested-hidden-cost-current-source-next129', $point133()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-nested-path-rowid-source-until-cursor-reset', $point133()['currentReaderPolicy']),
    'prepares changed next reader policy' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-nested-path-rowid-source-plan', $point133()['nextReaderPolicy']),
    'stable plan reuses reader' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-nested-path-rowid-source-plan', $stable133()['nextReaderPolicy']),
    'stable plan has no next133 reasons' => static fn (TestRunner $t) => $t->same([], $stable133()['next133ReplanReasons']),
    'current root is scoped to first group rules' => static fn (TestRunner $t) => $t->same('$.plugin.groups[0].rules', $point133()['currentNestedPathRowid']['root']),
    'next root is scoped to second group rules' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $point133()['nextNestedPathRowid']['root']),
    'current base root retained' => static fn (TestRunner $t) => $t->same('$.plugin.groups', $point133()['currentNestedPathRowid']['baseRoot']),
    'current nested path retained' => static fn (TestRunner $t) => $t->same('[0].rules', $point133()['currentNestedPathRowid']['nestedPath']),
    'next nested path retained' => static fn (TestRunner $t) => $t->same('[1].rules', $point133()['nextNestedPathRowid']['nestedPath']),
    'rowid constraint column normalizes alias' => static fn (TestRunner $t) => $t->same('id', $point133()['currentNestedPathRowid']['rowidConstraintColumn']),
    'rowid constraint operator is equality' => static fn (TestRunner $t) => $t->same('=', $point133()['currentNestedPathRowid']['rowidConstraintOperator']),
    'rowid constraint value is recorded' => static fn (TestRunner $t) => $t->same(2, $point133()['currentNestedPathRowid']['rowidConstraintValue']),
    'rowid constraint signature records normalized id' => static fn (TestRunner $t) => $t->same('id:=:2', $point133()['currentNestedPathRowid']['rowidConstraintSignature']),
    'rowid constraint is usable' => static fn (TestRunner $t) => $t->same(true, $point133()['currentNestedPathRowid']['rowidConstraintUsable']),
    'rowid is scoped' => static fn (TestRunner $t) => $t->same(true, $point133()['currentNestedPathRowid']['rowidScoped']),
    'current scoped rowid keeps local id two' => static fn (TestRunner $t) => $t->same([2], $point133()['currentNestedPathRowid']['scopedRowids']),
    'next scoped rowid keeps local id two under changed root' => static fn (TestRunner $t) => $t->same([2], $point133()['nextNestedPathRowid']['scopedRowids']),
    'current relative fullkey is root relative slug' => static fn (TestRunner $t) => $t->same(['$[0].slug'], $point133()['currentNestedPathRowid']['relativeFullkeys']),
    'next relative fullkey is root relative slug' => static fn (TestRunner $t) => $t->same(['$[0].slug'], $point133()['nextNestedPathRowid']['relativeFullkeys']),
    'first scoped rowid recorded' => static fn (TestRunner $t) => $t->same(2, $point133()['currentNestedPathRowid']['firstScopedRowid']),
    'last scoped rowid recorded' => static fn (TestRunner $t) => $t->same(2, $point133()['currentNestedPathRowid']['lastScopedRowid']),
    'current matched row count is one' => static fn (TestRunner $t) => $t->same(1, $point133()['currentNestedPathRowid']['matchedRowCount']),
    'next matched row count is one' => static fn (TestRunner $t) => $t->same(1, $point133()['nextNestedPathRowid']['matchedRowCount']),
    'point rowid is not missing' => static fn (TestRunner $t) => $t->same(false, $point133()['currentNestedPathRowid']['missingRowid']),
    'point cost class is rowid point' => static fn (TestRunner $t) => $t->same('json-table-nested-path-rowid-point', $point133()['currentNestedPathRowid']['costClass']),
    'point effective cost is bounded' => static fn (TestRunner $t) => $t->same(2, $point133()['currentNestedPathRowid']['effectiveEstimatedCost']),
    'root transition changes for moved nested path' => static fn (TestRunner $t) => $t->same(true, $point133()['nestedPathRowidTransitions'][0]['changed']),
    'rowid constraint transition is stable' => static fn (TestRunner $t) => $t->same(false, $point133()['nestedPathRowidTransitions'][1]['changed']),
    'scoped rowids transition is stable' => static fn (TestRunner $t) => $t->same(false, $point133()['nestedPathRowidTransitions'][2]['changed']),
    'relative fullkeys transition is stable' => static fn (TestRunner $t) => $t->same(false, $point133()['nestedPathRowidTransitions'][3]['changed']),
    'next133 reasons include root change' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-path-rowid-root-changed', $point133()['next133ReplanReasons'], true)),
    'next133 preserves nested hidden root reason' => static fn (TestRunner $t) => $t->true(in_array('json-table-nested-hidden-root-changed', $point133()['next133ReplanReasons'], true)),
    'miss scoped rowids empty' => static fn (TestRunner $t) => $t->same([], $miss133()['currentNestedPathRowid']['scopedRowids']),
    'miss marks current rowid missing' => static fn (TestRunner $t) => $t->same(true, $miss133()['currentNestedPathRowid']['missingRowid']),
    'miss cost class records miss' => static fn (TestRunner $t) => $t->same('json-table-nested-path-rowid-miss', $miss133()['currentNestedPathRowid']['costClass']),
    'range is not point scoped' => static fn (TestRunner $t) => $t->same(false, $range133()['currentNestedPathRowid']['rowidScoped']),
    'range signature records between' => static fn (TestRunner $t) => $t->same('id:BETWEEN:[2,5]', $range133()['currentNestedPathRowid']['rowidConstraintSignature']),
    'range scoped rowids follow residual between filter' => static fn (TestRunner $t) => $t->same([2], $range133()['currentNestedPathRowid']['scopedRowids']),
    'range relative fullkeys follow residual between filter' => static fn (TestRunner $t) => $t->same(['$[0].slug'], $range133()['currentNestedPathRowid']['relativeFullkeys']),
    'absolute root is retained' => static fn (TestRunner $t) => $t->same('$.plugin.groups[1].rules', $absolute133()['currentNestedPathRowid']['root']),
    'absolute numeric string rowid scopes' => static fn (TestRunner $t) => $t->same(true, $absolute133()['currentNestedPathRowid']['rowidScoped']),
    'absolute rowid alias normalizes' => static fn (TestRunner $t) => $t->same('id:=:"2"', $absolute133()['currentNestedPathRowid']['rowidConstraintSignature']),
    'unusable rowid is not scoped' => static fn (TestRunner $t) => $t->same(false, $unusable133()['currentNestedPathRowid']['rowidScoped']),
    'unusable rowid signature is null' => static fn (TestRunner $t) => $t->same(null, $unusable133()['currentNestedPathRowid']['rowidConstraintSignature']),
    'unrunnable next cost class sentinel' => static fn (TestRunner $t) => $t->same('unrunnable-json-table', $unrunnable133()['nextNestedPathRowid']['costClass']),
    'unrunnable next rowid tape empty' => static fn (TestRunner $t) => $t->same([], $unrunnable133()['nextNestedPathRowid']['scopedRowids']),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedPathRowid('json_bad', $current133, $next133, 'option_value', 'base_root', 'nested_path')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceNestedPathRowid('json_tree', $current133, $next133, '', 'base_root', 'nested_path')),
    'dependency scenario has no new support component' => static fn (TestRunner $t) => $t->same('no-new-support-component', 'no-new-support-component'),
];

foreach ($tests as $name => $case) {
    $tests['json table nested path rowid current source next133 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
