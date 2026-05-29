<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current88 = [
    'option_id' => 21,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":false}],"fallback":[{"name":"safe"}]}',
    'json_root' => '$.rules',
];
$next88 = [
    'option_id' => 21,
    'option_name' => 'wp_plugin_rules',
    'option_value' => '{"rules":[{"name":"seo","enabled":true},{"name":"cache","enabled":true},{"name":"forms","enabled":true}],"fallback":[{"name":"safe"}]}',
    'json_root' => '$.rules',
];

$sameRoot88 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $current88,
    $next88,
    'option_value',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'json_root',
    [['column' => 'id']],
);
$conflictingRoot88 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $current88,
    $next88,
    'option_value',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.fallback'],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'json_root',
);
$conflictingJson88 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $current88,
    $next88,
    'option_value',
    [
        ['column' => 'json', 'operator' => '=', 'value' => $current88['option_value']],
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ],
    'json_root',
);
$stable88 = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $current88,
    $current88,
    'option_value',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
        ['column' => 'json', 'operator' => '=', 'value' => $current88['option_value']],
    ],
    'json_root',
);
$jsonbNextSource = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $current88,
    array_replace($next88, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($next88['option_value'])))]),
    'option_value',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
    ],
    'json_root',
);
$nullNextSource = static fn (): array => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner(
    'json_each',
    $current88,
    array_replace($next88, ['option_value' => null]),
    'option_value',
    [
        ['column' => 'root', 'operator' => '=', 'value' => '$.rules'],
    ],
    'json_root',
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_each', $sameRoot88()['function']),
    'adds hidden constraint planner dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-hidden-constraint-planner', $sameRoot88()['dependencies'], true)),
    'preserves next86 dependency marker' => static fn (TestRunner $t) => $t->true(in_array('sqlite-json-table-current-source-constraint-planner', $sameRoot88()['dependencies'], true)),
    'pins current reader policy' => static fn (TestRunner $t) => $t->same('pin-current-json-table-source-until-cursor-reset', $sameRoot88()['currentReaderPolicy']),
    'prepares next source when source changes' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-source-plan', $sameRoot88()['nextReaderPolicy']),
    'source json change remains reported' => static fn (TestRunner $t) => $t->true(in_array('source-json-changed', $sameRoot88()['hiddenConstraintReplanReasons'], true)),
    'argument tape change remains reported' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $sameRoot88()['hiddenConstraintReplanReasons'], true)),
    'hidden residual is reported for duplicate root' => static fn (TestRunner $t) => $t->same('root', $sameRoot88()['currentHiddenResiduals'][0]['column']),
    'duplicate root residual keeps operator' => static fn (TestRunner $t) => $t->same('=', $sameRoot88()['currentHiddenResiduals'][0]['operator']),
    'duplicate root residual is usable' => static fn (TestRunner $t) => $t->same(true, $sameRoot88()['currentHiddenResiduals'][0]['usable']),
    'duplicate root residual has source constraint index offset' => static fn (TestRunner $t) => $t->same(2, $sameRoot88()['currentHiddenResiduals'][0]['constraintIndex']),
    'next hidden residual mirrors current root duplicate' => static fn (TestRunner $t) => $t->same($sameRoot88()['currentHiddenResiduals'], $sameRoot88()['nextHiddenResiduals']),
    'hidden residual presence is a hidden constraint reason' => static fn (TestRunner $t) => $t->true(in_array('hidden-residual-constraint-present', $sameRoot88()['hiddenConstraintReplanReasons'], true)),
    'same root duplicate keeps current rows' => static fn (TestRunner $t) => $t->same(2, $sameRoot88()['rowCountTransition']['current']),
    'same root duplicate sees next row growth' => static fn (TestRunner $t) => $t->same(3, $sameRoot88()['rowCountTransition']['next']),
    'row count transition changes for next source' => static fn (TestRunner $t) => $t->same(true, $sameRoot88()['rowCountTransition']['changed']),
    'rowset change is a hidden constraint reason' => static fn (TestRunner $t) => $t->true(in_array('hidden-residual-rowset-changed', $sameRoot88()['hiddenConstraintReplanReasons'], true)),
    'same root current first row is seo' => static fn (TestRunner $t) => $t->same(0, $sameRoot88()['currentRows'][0]['key']),
    'same root next final row is forms' => static fn (TestRunner $t) => $t->same(2, $sameRoot88()['nextRows'][2]['key']),
    'same root current row retains current json' => static fn (TestRunner $t) => $t->same($current88['option_value'], $sameRoot88()['currentRows'][0]['json']),
    'same root next row retains next json' => static fn (TestRunner $t) => $t->same($next88['option_value'], $sameRoot88()['nextRows'][0]['json']),
    'same root keeps hidden root out of idx string duplicates' => static fn (TestRunner $t) => $t->same('hidden:json:=|hidden:root:=|visible:type:=', $sameRoot88()['current']['idxStr']),
    'same root usage tracks duplicate as residual' => static fn (TestRunner $t) => $t->same('residual', $sameRoot88()['current']['constraintUsage'][2]['kind']),
    'same root visible type still pushes down' => static fn (TestRunner $t) => $t->same('visible', $sameRoot88()['current']['constraintUsage'][3]['kind']),
    'same root order by id consumed' => static fn (TestRunner $t) => $t->same(true, $sameRoot88()['current']['orderByConsumed']),
    'conflicting root current rows are empty' => static fn (TestRunner $t) => $t->same([], $conflictingRoot88()['currentRows']),
    'conflicting root next rows are empty' => static fn (TestRunner $t) => $t->same([], $conflictingRoot88()['nextRows']),
    'conflicting root still records residual' => static fn (TestRunner $t) => $t->same('root', $conflictingRoot88()['currentHiddenResiduals'][0]['column']),
    'conflicting root row count remains stable zero' => static fn (TestRunner $t) => $t->same(false, $conflictingRoot88()['rowCountTransition']['changed']),
    'conflicting root keeps plan runnable for cursor setup' => static fn (TestRunner $t) => $t->same(true, $conflictingRoot88()['current']['runnable']),
    'conflicting json records hidden json residual' => static fn (TestRunner $t) => $t->same('json', $conflictingJson88()['currentHiddenResiduals'][0]['column']),
    'conflicting json keeps current rows' => static fn (TestRunner $t) => $t->same(2, count($conflictingJson88()['currentRows'])),
    'conflicting json filters next rows to empty' => static fn (TestRunner $t) => $t->same([], $conflictingJson88()['nextRows']),
    'conflicting json rowset change is reported' => static fn (TestRunner $t) => $t->true(in_array('hidden-residual-rowset-changed', $conflictingJson88()['hiddenConstraintReplanReasons'], true)),
    'stable duplicate hidden constraints need no source replan' => static fn (TestRunner $t) => $t->same(false, $stable88()['replanRequired']),
    'stable duplicate hidden constraints reuse next source plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-source-plan', $stable88()['nextReaderPolicy']),
    'stable duplicate hidden constraints still report residual presence' => static fn (TestRunner $t) => $t->same(['hidden-residual-constraint-present'], $stable88()['hiddenConstraintReplanReasons']),
    'stable duplicate hidden constraints include root and json residuals' => static fn (TestRunner $t) => $t->same(['root', 'json'], array_column($stable88()['currentHiddenResiduals'], 'column')),
    'stable duplicate hidden constraints keep row counts equal' => static fn (TestRunner $t) => $t->same(false, $stable88()['rowCountTransition']['changed']),
    'jsonb next reports kind changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonbNextSource()['hiddenConstraintReplanReasons'], true)),
    'jsonb next remains valid' => static fn (TestRunner $t) => $t->same(true, $jsonbNextSource()['next']['jsonValid']),
    'jsonb duplicate root residual survives' => static fn (TestRunner $t) => $t->same('root', $jsonbNextSource()['nextHiddenResiduals'][0]['column']),
    'sql null next becomes unrunnable' => static fn (TestRunner $t) => $t->same(false, $nullNextSource()['next']['runnable']),
    'sql null next has empty rows' => static fn (TestRunner $t) => $t->same([], $nullNextSource()['nextRows']),
    'sql null next reports unrunnable' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $nullNextSource()['hiddenConstraintReplanReasons'], true)),
    'missing json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner('json_each', [], $next88, 'option_value')),
    'missing root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner('json_each', $current88, $next88, 'option_value', [], 'missing_root')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner('json_each', $current88, $next88, '')),
    'empty root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner('json_each', $current88, $next88, 'option_value', [], '')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceHiddenConstraintPlanner('json_bad', $current88, $next88, 'option_value')),
];

foreach ($tests as $name => $case) {
    $tests['json table hidden constraint planner current source ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
