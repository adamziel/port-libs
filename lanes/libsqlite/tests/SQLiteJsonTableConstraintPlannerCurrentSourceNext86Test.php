<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSource86 = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true,"priority":2},{"slug":"cache","enabled":false,"priority":7},{"slug":"forms","enabled":true,"priority":4}],"meta":{"site":"main"}}',
    'json_root' => '$.plugins',
];
$nextSource86 = [
    'option_id' => 11,
    'option_name' => 'wp_plugin_settings',
    'option_value' => '{"plugins":[{"slug":"seo","enabled":true,"priority":3},{"slug":"cache","enabled":true,"priority":6},{"slug":"forms","enabled":true,"priority":4},{"slug":"shop","enabled":true,"priority":5}],"meta":{"site":"main"}}',
    'json_root' => '$.plugins',
];
$constraints86 = [
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'key', 'operator' => 'IN', 'value' => [0, 1, 2, 3]],
    ['column' => 'limit', 'operator' => '=', 'value' => 4],
];
$plan86 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_tree',
    $currentSource86,
    $nextSource86,
    'option_value',
    $constraints86,
    'json_root',
    [['column' => 'id']],
);
$stable86 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_each',
    $currentSource86,
    $currentSource86,
    'option_value',
    [['column' => 'key', 'operator' => '=', 'value' => 0]],
    'json_root',
);
$rootChange86 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_tree',
    $currentSource86,
    array_replace($currentSource86, ['json_root' => '$.meta']),
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'text']],
    'json_root',
);
$malformedNext86 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_tree',
    $currentSource86,
    array_replace($currentSource86, ['option_value' => new SQLiteBlobValue("\x1c\x00")]),
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'object']],
    'json_root',
);
$jsonbNext86 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_each',
    $currentSource86,
    array_replace($currentSource86, ['option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(json_decode($nextSource86['option_value'])))]),
    'option_value',
    [['column' => 'key', 'operator' => '=', 'value' => 0]],
    'json_root',
);
$nullNext86 = static fn (): array => SQLiteJsonTablePlan::currentSourceConstraintPlanner(
    'json_each',
    $currentSource86,
    array_replace($currentSource86, ['option_value' => null]),
    'option_value',
    [['column' => 'key', 'operator' => '=', 'value' => 0]],
    'json_root',
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan86()['function']),
    'marks source json change for replan' => static fn (TestRunner $t) => $t->same(true, $plan86()['replanRequired']),
    'reports source json changed first' => static fn (TestRunner $t) => $t->same('source-json-changed', $plan86()['replanReasons'][0]),
    'reports argument tape changed' => static fn (TestRunner $t) => $t->true(in_array('source-argument-tape-changed', $plan86()['replanReasons'], true)),
    'keeps estimates stable for same root and limit' => static fn (TestRunner $t) => $t->same(false, in_array('source-estimate-changed', $plan86()['replanReasons'], true)),
    'current reader policy pins active source' => static fn (TestRunner $t) => $t->same('pin-current-json-table-source-until-cursor-reset', $plan86()['currentReaderPolicy']),
    'next reader policy prepares next source plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-source-plan', $plan86()['nextReaderPolicy']),
    'dependency marker names current source next86' => static fn (TestRunner $t) => $t->same('sqlite-json-table-current-source-constraint-planner', $plan86()['dependencies'][0]),
    'current source row is preserved' => static fn (TestRunner $t) => $t->same('wp_plugin_settings', $plan86()['current']['source']['option_name']),
    'next source row is preserved' => static fn (TestRunner $t) => $t->same('wp_plugin_settings', $plan86()['next']['source']['option_name']),
    'current json column is recorded' => static fn (TestRunner $t) => $t->same('option_value', $plan86()['current']['jsonColumn']),
    'next json column is recorded' => static fn (TestRunner $t) => $t->same('option_value', $plan86()['next']['jsonColumn']),
    'current root column is recorded' => static fn (TestRunner $t) => $t->same('json_root', $plan86()['current']['rootColumn']),
    'next root column is recorded' => static fn (TestRunner $t) => $t->same('json_root', $plan86()['next']['rootColumn']),
    'current root value comes from current source' => static fn (TestRunner $t) => $t->same('$.plugins', $plan86()['current']['rootValue']),
    'next root value comes from next source' => static fn (TestRunner $t) => $t->same('$.plugins', $plan86()['next']['rootValue']),
    'source transition count includes json root kind validity' => static fn (TestRunner $t) => $t->same(4, count($plan86()['sourceTransitions'])),
    'json source transition changes' => static fn (TestRunner $t) => $t->same(true, $plan86()['sourceTransitions'][0]['changed']),
    'root source transition stays stable' => static fn (TestRunner $t) => $t->same(false, $plan86()['sourceTransitions'][1]['changed']),
    'json kind source transition stays text' => static fn (TestRunner $t) => $t->same(false, $plan86()['sourceTransitions'][2]['changed']),
    'json validity source transition stays valid' => static fn (TestRunner $t) => $t->same(false, $plan86()['sourceTransitions'][3]['changed']),
    'current plan is runnable' => static fn (TestRunner $t) => $t->same(true, $plan86()['current']['runnable']),
    'next plan is runnable' => static fn (TestRunner $t) => $t->same(true, $plan86()['next']['runnable']),
    'current input kind is text' => static fn (TestRunner $t) => $t->same('text', $plan86()['current']['jsonInputKind']),
    'next input kind is text' => static fn (TestRunner $t) => $t->same('text', $plan86()['next']['jsonInputKind']),
    'current input is valid' => static fn (TestRunner $t) => $t->same(true, $plan86()['current']['jsonValid']),
    'next input is valid' => static fn (TestRunner $t) => $t->same(true, $plan86()['next']['jsonValid']),
    'current idx num includes json root visible limit' => static fn (TestRunner $t) => $t->same(15, $plan86()['current']['idxNum']),
    'next idx num includes json root visible limit' => static fn (TestRunner $t) => $t->same(15, $plan86()['next']['idxNum']),
    'current idx string starts with hidden json' => static fn (TestRunner $t) => $t->same('hidden:json:=', explode('|', $plan86()['current']['idxStr'])[0]),
    'current idx string includes hidden root' => static fn (TestRunner $t) => $t->same('hidden:root:=', explode('|', $plan86()['current']['idxStr'])[1]),
    'current idx string includes visible type' => static fn (TestRunner $t) => $t->same('visible:type:=', explode('|', $plan86()['current']['idxStr'])[2]),
    'current idx string includes visible key in' => static fn (TestRunner $t) => $t->same('visible:key:IN', explode('|', $plan86()['current']['idxStr'])[3]),
    'current idx string includes limit metadata' => static fn (TestRunner $t) => $t->same('hidden:limit:=', explode('|', $plan86()['current']['idxStr'])[4]),
    'current argument zero is current json' => static fn (TestRunner $t) => $t->same($currentSource86['option_value'], $plan86()['argumentTransitions'][0]['current']),
    'argument zero changes to next json' => static fn (TestRunner $t) => $t->same($nextSource86['option_value'], $plan86()['argumentTransitions'][0]['next']),
    'argument zero changed flag is true' => static fn (TestRunner $t) => $t->same(true, $plan86()['argumentTransitions'][0]['changed']),
    'argument one root remains stable' => static fn (TestRunner $t) => $t->same(false, $plan86()['argumentTransitions'][1]['changed']),
    'argument two type remains object' => static fn (TestRunner $t) => $t->same('object', $plan86()['argumentTransitions'][2]['current']),
    'argument three key list remains stable' => static fn (TestRunner $t) => $t->same(false, $plan86()['argumentTransitions'][3]['changed']),
    'usage transition count tracks five constraints' => static fn (TestRunner $t) => $t->same(5, count($plan86()['usageTransitions'])),
    'usage json column is stable' => static fn (TestRunner $t) => $t->same('json', $plan86()['usageTransitions'][0]['current']['column']),
    'usage root column is stable' => static fn (TestRunner $t) => $t->same('root', $plan86()['usageTransitions'][1]['current']['column']),
    'usage type column is visible' => static fn (TestRunner $t) => $t->same('visible', $plan86()['usageTransitions'][2]['current']['kind']),
    'usage key operator is IN' => static fn (TestRunner $t) => $t->same('IN', $plan86()['usageTransitions'][3]['current']['operator']),
    'usage limit metadata has no argv' => static fn (TestRunner $t) => $t->same(0, $plan86()['usageTransitions'][4]['current']['argvIndex']),
    'filter current next starts with json to root' => static fn (TestRunner $t) => $t->same('root', $plan86()['current']['filterCurrentNext'][0]['next']['column']),
    'filter current next terminal is key' => static fn (TestRunner $t) => $t->same('key', $plan86()['current']['filterCurrentNext'][3]['current']['column']),
    'current order by id is consumed' => static fn (TestRunner $t) => $t->same(true, $plan86()['current']['orderByConsumed']),
    'next order by id is consumed' => static fn (TestRunner $t) => $t->same(true, $plan86()['next']['orderByConsumed']),
    'current rows keep original source count' => static fn (TestRunner $t) => $t->same(3, count($plan86()['currentRows'])),
    'next rows keep next source count' => static fn (TestRunner $t) => $t->same(4, count($plan86()['nextRows'])),
    'current first row is seo object' => static fn (TestRunner $t) => $t->same(0, $plan86()['currentRows'][0]['key']),
    'next final row is shop object' => static fn (TestRunner $t) => $t->same(3, $plan86()['nextRows'][3]['key']),
    'current row json value stays pinned' => static fn (TestRunner $t) => $t->same($currentSource86['option_value'], $plan86()['currentRows'][0]['json']),
    'next row json value uses next source' => static fn (TestRunner $t) => $t->same($nextSource86['option_value'], $plan86()['nextRows'][0]['json']),
    'stable source needs no replan' => static fn (TestRunner $t) => $t->same(false, $stable86()['replanRequired']),
    'stable source reuses next policy' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-source-plan', $stable86()['nextReaderPolicy']),
    'stable source has no replan reasons' => static fn (TestRunner $t) => $t->same([], $stable86()['replanReasons']),
    'stable current rows equal next rows' => static fn (TestRunner $t) => $t->same($stable86()['currentRows'], $stable86()['nextRows']),
    'root change reports source root changed' => static fn (TestRunner $t) => $t->true(in_array('source-root-changed', $rootChange86()['replanReasons'], true)),
    'root change current root is plugins' => static fn (TestRunner $t) => $t->same('$.plugins', $rootChange86()['sourceTransitions'][1]['current']),
    'root change next root is meta' => static fn (TestRunner $t) => $t->same('$.meta', $rootChange86()['sourceTransitions'][1]['next']),
    'malformed next reports kind changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $malformedNext86()['replanReasons'], true)),
    'malformed next reports validity changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-validity-changed', $malformedNext86()['replanReasons'], true)),
    'malformed next becomes unrunnable' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $malformedNext86()['replanReasons'], true)),
    'malformed next rows are empty' => static fn (TestRunner $t) => $t->same([], $malformedNext86()['nextRows']),
    'malformed next error is jsonb' => static fn (TestRunner $t) => $t->same('malformed JSONB', $malformedNext86()['next']['jsonError']),
    'jsonb next reports kind changed' => static fn (TestRunner $t) => $t->true(in_array('source-json-kind-changed', $jsonbNext86()['replanReasons'], true)),
    'jsonb next remains valid' => static fn (TestRunner $t) => $t->same(true, $jsonbNext86()['next']['jsonValid']),
    'jsonb next rows are runnable' => static fn (TestRunner $t) => $t->same(true, $jsonbNext86()['next']['runnable']),
    'sql null next becomes unrunnable' => static fn (TestRunner $t) => $t->true(in_array('next-source-plan-becomes-unrunnable', $nullNext86()['replanReasons'], true)),
    'sql null next rows are empty' => static fn (TestRunner $t) => $t->same([], $nullNext86()['nextRows']),
    'missing json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintPlanner('json_each', [], $nextSource86, 'option_value')),
    'missing root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintPlanner('json_each', $currentSource86, $nextSource86, 'option_value', [], 'missing_root')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintPlanner('json_each', $currentSource86, $nextSource86, '')),
    'empty root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintPlanner('json_each', $currentSource86, $nextSource86, 'option_value', [], '')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::currentSourceConstraintPlanner('json_bad', $currentSource86, $nextSource86, 'option_value')),
];

foreach ($tests as $name => $case) {
    $tests['json table constraint planner current source next86 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
