<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentHosts75 = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}],"version":3}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 11,
        'option_name' => 'plugin_beta_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'forms', 'priority' => 4],
                ['name' => 'media', 'priority' => 1],
            ],
            'version' => 9,
        ])),
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 12,
        'option_name' => 'plugin_orphan_settings',
        'option_value' => null,
        'scan_root' => '$.rules',
    ],
];

$nextHosts75 = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":[{"name":"seo","priority":3},{"name":"cache","priority":6},{"name":"shop","priority":5}],"version":4}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 11,
        'option_name' => 'plugin_beta_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'rules' => [
                ['name' => 'forms', 'priority' => 4],
                ['name' => 'media', 'priority' => 8],
            ],
            'version' => 10,
        ])),
        'scan_root' => '$.rules[1]',
    ],
    [
        'option_id' => 13,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":[{"name":"gallery","priority":5}],"version":1}',
        'scan_root' => '$.rules',
    ],
];

$constraints75 = [
    ['column' => 'key', 'operator' => 'IN', 'value' => ['name', 'priority']],
    ['column' => 'atom', 'operator' => 'IS NOT NULL', 'value' => null],
    ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules%'],
    ['column' => 'limit', 'operator' => '=', 'value' => 8],
];

$plan75 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintPlannerComparison(
    $currentHosts75,
    $nextHosts75,
    'option_value',
    'json_tree',
    $constraints75,
    'scan_root',
    [['column' => 'id']],
);

$stable75 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintPlannerComparison(
    [$currentHosts75[0]],
    [$currentHosts75[0]],
    'option_value',
    'json_tree',
    $constraints75,
    'scan_root',
    [['column' => 'id']],
);

$becomesRunnable75 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintPlannerComparison(
    [$currentHosts75[2]],
    [$nextHosts75[2]],
    'option_value',
    'json_tree',
    $constraints75,
    'scan_root',
    [['column' => 'id']],
);

$becomesUnrunnable75 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintPlannerComparison(
    [$nextHosts75[2]],
    [[
        'option_id' => 13,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => new SQLiteBlobValue(hex2bin('1c00')),
        'scan_root' => '$.rules',
    ]],
    'option_value',
    'json_tree',
    $constraints75,
    'scan_root',
    [['column' => 'id']],
);

$withoutRoot75 = static fn (): array => SQLiteJsonTablePlan::lateralConstraintPlannerComparison(
    [$currentHosts75[0]],
    [$currentHosts75[0]],
    'option_value',
    'json_each',
    [['column' => 'key', 'operator' => '=', 'value' => 'rules']],
);

$tests = [
    'reports json tree function' => static fn (TestRunner $t) => $t->same('json_tree', $plan75()['function']),
    'dependency marker names current next75' => static fn (TestRunner $t) => $t->same('sqlite-json-table-lateral-constraint-planner-comparison', $plan75()['dependencies'][0]),
    'current reader policy keeps active host cursor' => static fn (TestRunner $t) => $t->same('keep-current-lateral-json-table-plan-until-host-row-advances', $plan75()['currentReaderPolicy']),
    'next reader policy prepares changed host plan' => static fn (TestRunner $t) => $t->same('prepare-next-lateral-json-table-plan-for-host-row', $plan75()['nextReaderPolicy']),
    'changed host rows require replan' => static fn (TestRunner $t) => $t->same(true, $plan75()['replanRequired']),
    'changed host rows record filter argument reason' => static fn (TestRunner $t) => $t->same(['lateral-filter-argument-tape-changed', 'next-lateral-plan-becomes-runnable'], $plan75()['replanReasons']),
    'current host plan count follows current rows' => static fn (TestRunner $t) => $t->same(3, count($plan75()['current'])),
    'next host plan count follows next rows' => static fn (TestRunner $t) => $t->same(3, count($plan75()['next'])),
    'transition count spans both host streams' => static fn (TestRunner $t) => $t->same(3, count($plan75()['transitions'])),
    'first transition changes json tape only' => static fn (TestRunner $t) => $t->same('lateral-filter-argument-tape-changed', $plan75()['transitions'][0]['reason']),
    'second transition changes jsonb and root tape' => static fn (TestRunner $t) => $t->same('lateral-filter-argument-tape-changed', $plan75()['transitions'][1]['reason']),
    'third transition becomes runnable from sql null current' => static fn (TestRunner $t) => $t->same('next-lateral-plan-becomes-runnable', $plan75()['transitions'][2]['reason']),
    'first current host index is zero' => static fn (TestRunner $t) => $t->same(0, $plan75()['current'][0]['hostIndex']),
    'second next host index is one' => static fn (TestRunner $t) => $t->same(1, $plan75()['next'][1]['hostIndex']),
    'first current host row is preserved' => static fn (TestRunner $t) => $t->same('plugin_alpha_settings', $plan75()['current'][0]['hostRow']['option_name']),
    'second next host row is preserved' => static fn (TestRunner $t) => $t->same('plugin_beta_settings', $plan75()['next'][1]['hostRow']['option_name']),
    'current json argument comes from current host row' => static fn (TestRunner $t) => $t->same($currentHosts75[0]['option_value'], $plan75()['current'][0]['filterArguments'][0]),
    'next json argument comes from next host row' => static fn (TestRunner $t) => $t->same($nextHosts75[0]['option_value'], $plan75()['next'][0]['filterArguments'][0]),
    'current root argument comes from host root column' => static fn (TestRunner $t) => $t->same('$.rules', $plan75()['current'][0]['filterArguments'][1]),
    'next root argument can change per host row' => static fn (TestRunner $t) => $t->same('$.rules[1]', $plan75()['next'][1]['filterArguments'][1]),
    'visible key argument follows hidden arguments' => static fn (TestRunner $t) => $t->same(['name', 'priority'], $plan75()['current'][0]['filterArguments'][2]),
    'visible atom argument follows key' => static fn (TestRunner $t) => $t->same(null, $plan75()['current'][0]['filterArguments'][3]),
    'visible fullkey argument follows atom' => static fn (TestRunner $t) => $t->same('$.rules%', $plan75()['current'][0]['filterArguments'][4]),
    'limit metadata is not part of filter tape' => static fn (TestRunner $t) => $t->same(5, count($plan75()['current'][0]['filterArguments'])),
    'current idx string includes lateral hidden json' => static fn (TestRunner $t) => $t->same(true, str_starts_with($plan75()['current'][0]['idxStr'], 'hidden:json:=')),
    'current idx string includes lateral hidden root' => static fn (TestRunner $t) => $t->same(true, str_contains($plan75()['current'][0]['idxStr'], 'hidden:root:=')),
    'current idx string includes visible key in' => static fn (TestRunner $t) => $t->same(true, str_contains($plan75()['current'][0]['idxStr'], 'visible:key:IN')),
    'current idx num records json root visible limit' => static fn (TestRunner $t) => $t->same(15, $plan75()['current'][0]['idxNum']),
    'current order by id is consumed' => static fn (TestRunner $t) => $t->same(true, $plan75()['current'][0]['orderByConsumed']),
    'current json input kind is text' => static fn (TestRunner $t) => $t->same('text', $plan75()['current'][0]['jsonInputKind']),
    'second current json input kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $plan75()['current'][1]['jsonInputKind']),
    'third current json input kind is sql null' => static fn (TestRunner $t) => $t->same('sql-null', $plan75()['current'][2]['jsonInputKind']),
    'third current plan is not runnable' => static fn (TestRunner $t) => $t->same(false, $plan75()['current'][2]['runnable']),
    'third next plan is runnable' => static fn (TestRunner $t) => $t->same(true, $plan75()['next'][2]['runnable']),
    'third current cost is disabled' => static fn (TestRunner $t) => $t->same(1000000, $plan75()['current'][2]['estimatedCost']),
    'third next cost is bounded' => static fn (TestRunner $t) => $t->true($plan75()['next'][2]['estimatedCost'] < 1000000),
    'filter current-next starts at hidden json' => static fn (TestRunner $t) => $t->same('json', $plan75()['current'][0]['filterCurrentNext'][0]['current']['column']),
    'filter current-next json points to root' => static fn (TestRunner $t) => $t->same('root', $plan75()['current'][0]['filterCurrentNext'][0]['next']['column']),
    'filter current-next root points to key' => static fn (TestRunner $t) => $t->same('key', $plan75()['current'][0]['filterCurrentNext'][1]['next']['column']),
    'first transition argument count is five' => static fn (TestRunner $t) => $t->same(5, count($plan75()['transitions'][0]['argumentTransitions'])),
    'first transition json argument changes' => static fn (TestRunner $t) => $t->same(true, $plan75()['transitions'][0]['argumentTransitions'][0]['changed']),
    'first transition root argument is stable' => static fn (TestRunner $t) => $t->same(false, $plan75()['transitions'][0]['argumentTransitions'][1]['changed']),
    'second transition root argument changes' => static fn (TestRunner $t) => $t->same(true, $plan75()['transitions'][1]['argumentTransitions'][1]['changed']),
    'third transition current argument tape includes sql null' => static fn (TestRunner $t) => $t->same(null, $plan75()['transitions'][2]['currentFilterArguments'][0]),
    'third transition next argument tape includes gamma json' => static fn (TestRunner $t) => $t->same($nextHosts75[2]['option_value'], $plan75()['transitions'][2]['nextFilterArguments'][0]),
    'stable host plan does not require replan' => static fn (TestRunner $t) => $t->same(false, $stable75()['replanRequired']),
    'stable host plan has empty replan reasons' => static fn (TestRunner $t) => $t->same([], $stable75()['replanReasons']),
    'stable next policy reuses current lateral plan' => static fn (TestRunner $t) => $t->same('reuse-current-lateral-json-table-plan', $stable75()['nextReaderPolicy']),
    'stable transition reason is stable' => static fn (TestRunner $t) => $t->same('stable-lateral-json-plan', $stable75()['transitions'][0]['reason']),
    'stable transition changed flag is false' => static fn (TestRunner $t) => $t->same(false, $stable75()['transitions'][0]['changed']),
    'becomes runnable reason is explicit' => static fn (TestRunner $t) => $t->same('next-lateral-plan-becomes-runnable', $becomesRunnable75()['transitions'][0]['reason']),
    'becomes runnable records reason list' => static fn (TestRunner $t) => $t->same(['next-lateral-plan-becomes-runnable'], $becomesRunnable75()['replanReasons']),
    'becomes unrunnable reason is explicit' => static fn (TestRunner $t) => $t->same('next-lateral-plan-becomes-unrunnable', $becomesUnrunnable75()['transitions'][0]['reason']),
    'becomes unrunnable next json kind is jsonb' => static fn (TestRunner $t) => $t->same('jsonb', $becomesUnrunnable75()['next'][0]['jsonInputKind']),
    'becomes unrunnable next plan is false' => static fn (TestRunner $t) => $t->same(false, $becomesUnrunnable75()['next'][0]['runnable']),
    'without root uses default root value' => static fn (TestRunner $t) => $t->same('$', $withoutRoot75()['current'][0]['rootValue']),
    'without root uses only json and visible argument tape' => static fn (TestRunner $t) => $t->same([$currentHosts75[0]['option_value'], 'rules'], $withoutRoot75()['current'][0]['filterArguments']),
    'without root idx num records json plus visible only' => static fn (TestRunner $t) => $t->same(5, $withoutRoot75()['current'][0]['idxNum']),
    'added host row transition is reported' => static fn (TestRunner $t) => $t->same('next-host-row-added', SQLiteJsonTablePlan::lateralConstraintPlannerComparison([], [$nextHosts75[0]], 'option_value', 'json_tree', $constraints75, 'scan_root')['transitions'][0]['reason']),
    'removed host row transition is reported' => static fn (TestRunner $t) => $t->same('current-host-row-removed', SQLiteJsonTablePlan::lateralConstraintPlannerComparison([$currentHosts75[0]], [], 'option_value', 'json_tree', $constraints75, 'scan_root')['transitions'][0]['reason']),
    'missing json host column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintPlannerComparison([['option_name' => 'missing']], [], 'option_value', 'json_tree')),
    'missing root host column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintPlannerComparison([['option_value' => '{}']], [], 'option_value', 'json_tree', [], 'scan_root')),
    'empty json column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintPlannerComparison([], [], '', 'json_tree')),
    'empty root column is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintPlannerComparison([], [], 'option_value', 'json_tree', [], '')),
    'bad function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintPlannerComparison([], [], 'option_value', 'json_bad')),
    'bad dynamic root path is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::lateralConstraintPlannerComparison([['option_value' => '{}', 'scan_root' => '$[']], [], 'option_value', 'json_tree', [], 'scan_root')),
];

foreach ($tests as $name => $case) {
    $tests['json table lateral planner constraint current next75 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
