<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7},{"name":"forms","priority":4}],"flags":["network","beta"]}}';
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.rules[%].priority'],
    ['column' => 'type', 'operator' => 'IN', 'value' => ['integer', 'real']],
    ['column' => 'path', 'operator' => 'GLOB', 'value' => '$.plugin.rules[*]'],
    ['column' => 'parent', 'operator' => 'IS NOT NULL', 'value' => null],
    ['column' => 'id', 'operator' => '>=', 'value' => 3],
    ['column' => 'key', 'operator' => 'NOT IN', 'value' => ['name']],
    ['column' => 'limit', 'operator' => '=', 'value' => 5],
    ['column' => 'offset', 'operator' => '=', 'value' => 1],
];

$plan = static fn (): array => SQLiteJsonTablePlan::xBestIndexPlan('json_tree', $constraints, [['column' => 'id']]);
$usage = static fn (): array => $plan()['constraintUsage'];
$filterPairs = static fn (): array => $plan()['filterCurrentNext'];
$arguments = static fn (): array => $plan()['filterArguments'];

$tests = [
    'filter argument tape includes hidden json first' => static fn (TestRunner $t) => $t->same($settings, $arguments()[0]),
    'filter argument tape includes hidden root second' => static fn (TestRunner $t) => $t->same('$.plugin.rules', $arguments()[1]),
    'filter argument tape includes visible equality third' => static fn (TestRunner $t) => $t->same('priority', $arguments()[2]),
    'filter argument tape includes visible between fourth' => static fn (TestRunner $t) => $t->same([3, 7], $arguments()[3]),
    'filter argument tape includes visible like fifth' => static fn (TestRunner $t) => $t->same('$.plugin.rules[%].priority', $arguments()[4]),
    'filter argument tape includes visible in sixth' => static fn (TestRunner $t) => $t->same(['integer', 'real'], $arguments()[5]),
    'filter argument tape includes visible glob seventh' => static fn (TestRunner $t) => $t->same('$.plugin.rules[*]', $arguments()[6]),
    'filter argument tape includes visible is not null eighth' => static fn (TestRunner $t) => $t->same(null, $arguments()[7]),
    'filter argument tape includes visible range ninth' => static fn (TestRunner $t) => $t->same(3, $arguments()[8]),
    'filter argument tape skips residual not in' => static fn (TestRunner $t) => $t->same(9, count($arguments())),
    'filter current next count follows argv count' => static fn (TestRunner $t) => $t->same(9, count($filterPairs())),
    'filter current starts at json hidden argv' => static fn (TestRunner $t) => $t->same('json', $filterPairs()[0]['current']['column']),
    'filter current json points to root' => static fn (TestRunner $t) => $t->same('root', $filterPairs()[0]['next']['column']),
    'filter current root points to key equality' => static fn (TestRunner $t) => $t->same('key', $filterPairs()[1]['next']['column']),
    'filter current key points to atom between' => static fn (TestRunner $t) => $t->same('atom', $filterPairs()[2]['next']['column']),
    'filter current atom points to fullkey like' => static fn (TestRunner $t) => $t->same('fullkey', $filterPairs()[3]['next']['column']),
    'filter current fullkey points to type in' => static fn (TestRunner $t) => $t->same('type', $filterPairs()[4]['next']['column']),
    'filter current type points to path glob' => static fn (TestRunner $t) => $t->same('path', $filterPairs()[5]['next']['column']),
    'filter current path points to parent is not null' => static fn (TestRunner $t) => $t->same('parent', $filterPairs()[6]['next']['column']),
    'filter current parent points to id range' => static fn (TestRunner $t) => $t->same('id', $filterPairs()[7]['next']['column']),
    'filter current terminal id has no next' => static fn (TestRunner $t) => $t->same(null, $filterPairs()[8]['next']),
    'filter current excludes residual not in' => static fn (TestRunner $t) => $t->same(false, in_array('NOT IN', array_column(array_column($filterPairs(), 'current'), 'operator'), true)),
    'constraint usage still includes residual not in' => static fn (TestRunner $t) => $t->same('residual', $usage()[9]['kind']),
    'constraint current next still includes residual not in' => static fn (TestRunner $t) => $t->same('key', $plan()['currentNext'][9]['current']['column']),
    'constraint current next residual points to limit metadata' => static fn (TestRunner $t) => $t->same('limit', $plan()['currentNext'][9]['next']['column']),
    'filter current next skips limit metadata' => static fn (TestRunner $t) => $t->same(false, in_array('limit', array_column(array_column($filterPairs(), 'current'), 'column'), true)),
    'filter current next skips offset metadata' => static fn (TestRunner $t) => $t->same(false, in_array('offset', array_column(array_column($filterPairs(), 'current'), 'column'), true)),
    'visible key argv index is sequential after hidden constraints' => static fn (TestRunner $t) => $t->same(3, $usage()[2]['argvIndex']),
    'visible atom argv index is sequential after key' => static fn (TestRunner $t) => $t->same(4, $usage()[3]['argvIndex']),
    'visible fullkey argv index is sequential after atom' => static fn (TestRunner $t) => $t->same(5, $usage()[4]['argvIndex']),
    'visible type argv index is sequential after fullkey' => static fn (TestRunner $t) => $t->same(6, $usage()[5]['argvIndex']),
    'visible path argv index is sequential after type' => static fn (TestRunner $t) => $t->same(7, $usage()[6]['argvIndex']),
    'visible parent argv index is sequential after path' => static fn (TestRunner $t) => $t->same(8, $usage()[7]['argvIndex']),
    'visible id argv index is sequential after parent' => static fn (TestRunner $t) => $t->same(9, $usage()[8]['argvIndex']),
    'limit metadata has no filter argument' => static fn (TestRunner $t) => $t->same(0, $usage()[10]['argvIndex']),
    'offset metadata has no filter argument' => static fn (TestRunner $t) => $t->same(0, $usage()[11]['argvIndex']),
    'limit metadata remains hidden usage' => static fn (TestRunner $t) => $t->same('hidden', $usage()[10]['kind']),
    'offset metadata remains hidden usage' => static fn (TestRunner $t) => $t->same('hidden', $usage()[11]['kind']),
    'idx string keeps visible argv columns' => static fn (TestRunner $t) => $t->same('visible:key:=', explode('|', $plan()['idxStr'])[2]),
    'idx string keeps visible between operator' => static fn (TestRunner $t) => $t->same('visible:atom:BETWEEN', explode('|', $plan()['idxStr'])[3]),
    'idx string keeps visible in operator' => static fn (TestRunner $t) => $t->same('visible:type:IN', explode('|', $plan()['idxStr'])[5]),
    'idx num records hidden and visible and paging constraints' => static fn (TestRunner $t) => $t->same(31, $plan()['idxNum']),
    'order by id remains consumed with filter argv tape' => static fn (TestRunner $t) => $t->same(true, $plan()['orderByConsumed']),
    'filter arguments empty when only residual unusable json exists' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings, 'usable' => false]])['filterArguments']),
    'filter current next empty when only residual unusable json exists' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings, 'usable' => false]])['filterCurrentNext']),
    'filter arguments include duplicate visible equality values' => static fn (TestRunner $t) => $t->same(['a', 'b'], array_slice(SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}'], ['column' => 'key', 'operator' => '=', 'value' => 'a'], ['column' => 'key', 'operator' => '=', 'value' => 'b']])['filterArguments'], 1)),
    'duplicate visible equality current next keeps first key' => static fn (TestRunner $t) => $t->same('key', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}'], ['column' => 'key', 'operator' => '=', 'value' => 'a'], ['column' => 'key', 'operator' => '=', 'value' => 'b']])['filterCurrentNext'][1]['current']['column']),
    'duplicate visible equality current next points to second key' => static fn (TestRunner $t) => $t->same('key', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}'], ['column' => 'key', 'operator' => '=', 'value' => 'a'], ['column' => 'key', 'operator' => '=', 'value' => 'b']])['filterCurrentNext'][1]['next']['column']),
    'root-only hidden plan includes two filter arguments' => static fn (TestRunner $t) => $t->same(['{"a":1}', '$.a'], SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'json', 'operator' => '=', 'value' => '{"a":1}'], ['column' => 'root', 'operator' => '=', 'value' => '$.a']])['filterArguments']),
    'limit-only unrunnable plan has empty filter arguments' => static fn (TestRunner $t) => $t->same([], SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'limit', 'operator' => '=', 'value' => 2]])['filterArguments']),
    'visible only without json still exposes filter argument' => static fn (TestRunner $t) => $t->same(['priority'], SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'key', 'operator' => '=', 'value' => 'priority']])['filterArguments']),
    'visible only without json remains unrunnable' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'key', 'operator' => '=', 'value' => 'priority']])['runnable']),
    'visible only current next has no next' => static fn (TestRunner $t) => $t->same(null, SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'key', 'operator' => '=', 'value' => 'priority']])['filterCurrentNext'][0]['next']),
    'not like residual does not enter filter arguments' => static fn (TestRunner $t) => $t->same([$settings], SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings], ['column' => 'key', 'operator' => 'NOT LIKE', 'value' => 'p%']])['filterArguments']),
    'not like residual stays in constraint current next' => static fn (TestRunner $t) => $t->same('NOT LIKE', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings], ['column' => 'key', 'operator' => 'NOT LIKE', 'value' => 'p%']])['currentNext'][1]['current']['operator']),
    'order by not consumed still preserves filter arguments' => static fn (TestRunner $t) => $t->same([$settings, 'priority'], SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'json', 'operator' => '=', 'value' => $settings], ['column' => 'key', 'operator' => '=', 'value' => 'priority']], [['column' => 'fullkey']])['filterArguments']),
    'visible null value survives filter argument tape' => static fn (TestRunner $t) => $t->same([null], SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'parent', 'operator' => 'IS NULL', 'value' => null]])['filterArguments']),
    'visible rowid alias normalizes to id argument usage' => static fn (TestRunner $t) => $t->same('id', SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [['column' => 'id', 'operator' => '>=', 'value' => 4]])['filterCurrentNext'][0]['current']['column']),
    'estimated rows continue to account for filter constraints' => static fn (TestRunner $t) => $t->same(0, $plan()['estimatedRows']),
];

foreach ($tests as $name => $case) {
    $tests['json table virtual planner constraints current next56 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
