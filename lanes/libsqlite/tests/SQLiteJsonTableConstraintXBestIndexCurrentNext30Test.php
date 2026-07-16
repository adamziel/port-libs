<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$settings = '{"plugin":{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7},{"name":"forms","priority":4}],"flags":{"network":true,"beta":false}}}';
$constraints = [
    ['column' => 'json', 'operator' => '=', 'value' => $settings],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'type', 'operator' => '=', 'value' => 'integer'],
    ['column' => 'atom', 'operator' => 'BETWEEN', 'value' => [3, 7]],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugin.rules[%].priority'],
    ['column' => 'key', 'operator' => 'NOT IN', 'value' => ['name']],
    ['column' => 'limit', 'operator' => '=', 'value' => 3],
    ['column' => 'offset', 'operator' => '=', 'value' => 1],
];

$plan = static fn (): array => SQLiteJsonTablePlan::xBestIndexPlan('json_tree', $constraints, [['column' => 'id']]);
$usage = static fn (): array => $plan()['constraintUsage'];
$pairs = static fn (): array => $plan()['currentNext'];

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan()['function']),
    'marks plan runnable with json hidden constraint' => static fn (TestRunner $t) => $t->same(true, $plan()['runnable']),
    'preserves json argument' => static fn (TestRunner $t) => $t->same($settings, $plan()['arguments'][0]),
    'preserves root argument' => static fn (TestRunner $t) => $t->same('$.plugin.rules', $plan()['arguments'][1]),
    'sets idx num for json root visible limit offset' => static fn (TestRunner $t) => $t->same(31, $plan()['idxNum']),
    'idx string records json hidden constraint first' => static fn (TestRunner $t) => $t->same('hidden:json:=', explode('|', $plan()['idxStr'])[0]),
    'idx string records root hidden constraint second' => static fn (TestRunner $t) => $t->same('hidden:root:=', explode('|', $plan()['idxStr'])[1]),
    'idx string records visible equality constraint' => static fn (TestRunner $t) => $t->same('visible:type:=', explode('|', $plan()['idxStr'])[2]),
    'idx string records visible between constraint' => static fn (TestRunner $t) => $t->same('visible:atom:BETWEEN', explode('|', $plan()['idxStr'])[3]),
    'idx string records visible like constraint' => static fn (TestRunner $t) => $t->same('visible:fullkey:LIKE', explode('|', $plan()['idxStr'])[4]),
    'idx string records limit constraint' => static fn (TestRunner $t) => $t->same('hidden:limit:=', explode('|', $plan()['idxStr'])[5]),
    'idx string records offset constraint' => static fn (TestRunner $t) => $t->same('hidden:offset:=', explode('|', $plan()['idxStr'])[6]),
    'records eight constraint usage entries' => static fn (TestRunner $t) => $t->same(8, count($usage())),
    'json constraint keeps original index zero' => static fn (TestRunner $t) => $t->same(0, $usage()[0]['constraintIndex']),
    'json constraint uses argv one' => static fn (TestRunner $t) => $t->same(1, $usage()[0]['argvIndex']),
    'json constraint is hidden kind' => static fn (TestRunner $t) => $t->same('hidden', $usage()[0]['kind']),
    'json constraint omits residual evaluation' => static fn (TestRunner $t) => $t->same(true, $usage()[0]['omit']),
    'root constraint keeps original index one' => static fn (TestRunner $t) => $t->same(1, $usage()[1]['constraintIndex']),
    'root constraint uses argv two' => static fn (TestRunner $t) => $t->same(2, $usage()[1]['argvIndex']),
    'root constraint omits residual evaluation' => static fn (TestRunner $t) => $t->same(true, $usage()[1]['omit']),
    'type visible constraint keeps original index two' => static fn (TestRunner $t) => $t->same(2, $usage()[2]['constraintIndex']),
    'type visible constraint remains non omit' => static fn (TestRunner $t) => $t->same(false, $usage()[2]['omit']),
    'type visible constraint is advertised' => static fn (TestRunner $t) => $t->same('visible', $usage()[2]['kind']),
    'between visible constraint keeps operator' => static fn (TestRunner $t) => $t->same('BETWEEN', $usage()[3]['operator']),
    'like visible constraint keeps column' => static fn (TestRunner $t) => $t->same('fullkey', $usage()[4]['column']),
    'not in constraint remains residual' => static fn (TestRunner $t) => $t->same('residual', $usage()[5]['kind']),
    'not in residual has no argv' => static fn (TestRunner $t) => $t->same(null, $usage()[5]['argvIndex']),
    'limit constraint keeps original index six' => static fn (TestRunner $t) => $t->same(6, $usage()[6]['constraintIndex']),
    'limit constraint is hidden kind' => static fn (TestRunner $t) => $t->same('hidden', $usage()[6]['kind']),
    'offset constraint keeps original index seven' => static fn (TestRunner $t) => $t->same(7, $usage()[7]['constraintIndex']),
    'offset constraint is hidden kind' => static fn (TestRunner $t) => $t->same('hidden', $usage()[7]['kind']),
    'usage current next count matches usage count' => static fn (TestRunner $t) => $t->same(count($usage()), count($pairs())),
    'first current next starts at json' => static fn (TestRunner $t) => $t->same('json', $pairs()[0]['current']['column']),
    'first current next points to root' => static fn (TestRunner $t) => $t->same('root', $pairs()[0]['next']['column']),
    'root current next points to type' => static fn (TestRunner $t) => $t->same('type', $pairs()[1]['next']['column']),
    'type current next points to atom' => static fn (TestRunner $t) => $t->same('atom', $pairs()[2]['next']['column']),
    'atom current next points to fullkey' => static fn (TestRunner $t) => $t->same('fullkey', $pairs()[3]['next']['column']),
    'fullkey current next points to residual key' => static fn (TestRunner $t) => $t->same('key', $pairs()[4]['next']['column']),
    'residual key current next points to limit' => static fn (TestRunner $t) => $t->same('limit', $pairs()[5]['next']['column']),
    'limit current next points to offset' => static fn (TestRunner $t) => $t->same('offset', $pairs()[6]['next']['column']),
    'terminal offset has no next constraint' => static fn (TestRunner $t) => $t->same(null, $pairs()[7]['next']),
    'id ascending order is consumed' => static fn (TestRunner $t) => $t->same(true, $plan()['orderByConsumed']),
    'rowid ascending order is consumed' => static fn (TestRunner $t) => $t->same(true, SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0]], [['column' => 'rowid']])['orderByConsumed']),
    'id descending order is not consumed' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0]], [['column' => 'id', 'direction' => 'DESC']])['orderByConsumed']),
    'fullkey order is not consumed' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0]], [['column' => 'fullkey']])['orderByConsumed']),
    'no order by is not consumed' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0]])['orderByConsumed']),
    'estimated rows reflect pushed visible constraints and paging' => static fn (TestRunner $t) => $t->same(0, $plan()['estimatedRows']),
    'estimated cost stays bounded for runnable plan' => static fn (TestRunner $t) => $t->true($plan()['estimatedCost'] < 1000000),
    'unusable json constraint makes plan unrunnable' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings, 'usable' => false]])['runnable']),
    'unusable json constraint remains residual usage' => static fn (TestRunner $t) => $t->same('residual', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'json', 'operator' => '=', 'value' => $settings, 'usable' => false]])['constraintUsage'][0]['kind']),
    'duplicate json constraint keeps first hidden' => static fn (TestRunner $t) => $t->same('hidden', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0], $constraints[0]])['constraintUsage'][0]['kind']),
    'duplicate json constraint leaves second residual' => static fn (TestRunner $t) => $t->same('residual', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0], $constraints[0]])['constraintUsage'][1]['kind']),
    'unsupported visible operator remains residual' => static fn (TestRunner $t) => $t->same('residual', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [$constraints[0], ['column' => 'key', 'operator' => 'NOT LIKE', 'value' => 'p%']])['constraintUsage'][1]['kind']),
    'visible in constraint is advertised' => static fn (TestRunner $t) => $t->same('visible', SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [$constraints[0], ['column' => 'key', 'operator' => 'IN', 'value' => ['priority']]])['constraintUsage'][1]['kind']),
    'visible glob constraint is advertised' => static fn (TestRunner $t) => $t->same('visible', SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [$constraints[0], ['column' => 'fullkey', 'operator' => 'GLOB', 'value' => '*.priority']])['constraintUsage'][1]['kind']),
    'visible greater than constraint is advertised' => static fn (TestRunner $t) => $t->same('visible', SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [$constraints[0], ['column' => 'id', 'operator' => '>', 'value' => 3]])['constraintUsage'][1]['kind']),
    'visible is null constraint is advertised' => static fn (TestRunner $t) => $t->same('visible', SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [$constraints[0], ['column' => 'parent', 'operator' => 'IS NULL', 'value' => null]])['constraintUsage'][1]['kind']),
    'visible value column is not advertised' => static fn (TestRunner $t) => $t->same('residual', SQLiteJsonTablePlan::xBestIndexPlan('json_tree', [$constraints[0], ['column' => 'value', 'operator' => '=', 'value' => 'seo']])['constraintUsage'][1]['kind']),
    'limit without json still records usage' => static fn (TestRunner $t) => $t->same('limit', SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'limit', 'operator' => '=', 'value' => 2]])['constraintUsage'][0]['column']),
    'limit without json remains unrunnable' => static fn (TestRunner $t) => $t->same(false, SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'limit', 'operator' => '=', 'value' => 2]])['runnable']),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::xBestIndexPlan('json_bad', [])),
    'bad limit operator is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'limit', 'operator' => '>', 'value' => 1]])),
    'bad offset value is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::xBestIndexPlan('json_each', [['column' => 'offset', 'operator' => '=', 'value' => -1]])),
];

foreach ($tests as $name => $case) {
    $tests['json table constraint xbestindex current next30 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
