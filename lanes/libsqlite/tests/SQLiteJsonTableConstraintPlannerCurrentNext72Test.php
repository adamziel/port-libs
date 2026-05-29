<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$currentSettings72 = '{"plugins":{"seo":{"enabled":true,"priority":2},"cache":{"enabled":false,"priority":7},"forms":{"enabled":true,"priority":4}}}';
$nextSettings72 = '{"plugins":{"seo":{"enabled":true,"priority":3},"cache":{"enabled":true,"priority":6},"forms":{"enabled":true,"priority":4},"shop":{"enabled":true,"priority":5}}}';

$baseCurrent72 = [
    ['column' => 'json', 'operator' => '=', 'value' => $currentSettings72],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => 'IN', 'value' => ['seo', 'cache', 'forms']],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'limit', 'operator' => '=', 'value' => 6],
];
$baseNext72 = [
    ['column' => 'json', 'operator' => '=', 'value' => $nextSettings72],
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugins'],
    ['column' => 'key', 'operator' => 'IN', 'value' => ['seo', 'cache', 'forms', 'shop']],
    ['column' => 'type', 'operator' => '=', 'value' => 'object'],
    ['column' => 'atom', 'operator' => 'IS NULL', 'value' => null],
    ['column' => 'limit', 'operator' => '=', 'value' => 8],
];

$plan72 = static fn (): array => SQLiteJsonTablePlan::constraintPlannerComparison(
    'json_tree',
    $baseCurrent72,
    $baseNext72,
    [['column' => 'id']],
);

$stable72 = static fn (): array => SQLiteJsonTablePlan::constraintPlannerComparison(
    'json_each',
    [
        ['column' => 'json', 'operator' => '=', 'value' => '{"a":1,"b":2}'],
        ['column' => 'key', 'operator' => '=', 'value' => 'a'],
    ],
    [
        ['column' => 'json', 'operator' => '=', 'value' => '{"a":1,"b":2}'],
        ['column' => 'key', 'operator' => '=', 'value' => 'a'],
    ],
);

$becomesRunnable72 = static fn (): array => SQLiteJsonTablePlan::constraintPlannerComparison(
    'json_each',
    [
        ['column' => 'key', 'operator' => '=', 'value' => 'plugin'],
    ],
    [
        ['column' => 'json', 'operator' => '=', 'value' => '{"plugin":true}'],
        ['column' => 'key', 'operator' => '=', 'value' => 'plugin'],
    ],
);

$becomesUnrunnable72 = static fn (): array => SQLiteJsonTablePlan::constraintPlannerComparison(
    'json_each',
    [
        ['column' => 'json', 'operator' => '=', 'value' => '{"plugin":true}'],
        ['column' => 'key', 'operator' => '=', 'value' => 'plugin'],
    ],
    [
        ['column' => 'json', 'operator' => '=', 'value' => '{"plugin":true}', 'usable' => false],
        ['column' => 'key', 'operator' => '=', 'value' => 'plugin'],
    ],
);

$operatorChanged72 = static fn (): array => SQLiteJsonTablePlan::constraintPlannerComparison(
    'json_tree',
    [
        ['column' => 'json', 'operator' => '=', 'value' => $currentSettings72],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.plugins.%'],
    ],
    [
        ['column' => 'json', 'operator' => '=', 'value' => $currentSettings72],
        ['column' => 'fullkey', 'operator' => 'GLOB', 'value' => '$.plugins.*'],
    ],
);

$tests = [
    'normalizes function name' => static fn (TestRunner $t) => $t->same('json_tree', $plan72()['function']),
    'marks changed plan for reprepare' => static fn (TestRunner $t) => $t->same(true, $plan72()['replanRequired']),
    'added visible constraint changes operator tape before argument changes' => static fn (TestRunner $t) => $t->same('constraint-operator-tape-changed', $plan72()['replanReason']),
    'current reader policy preserves active cursor' => static fn (TestRunner $t) => $t->same('keep-current-json-table-plan-until-statement-reset', $plan72()['currentReaderPolicy']),
    'next reader policy prepares changed plan' => static fn (TestRunner $t) => $t->same('prepare-next-json-table-xbestindex-plan', $plan72()['nextReaderPolicy']),
    'dependency marker names current next72' => static fn (TestRunner $t) => $t->same('sqlite-json-table-constraint-planner-comparison', $plan72()['dependencies'][0]),
    'current plan remains runnable' => static fn (TestRunner $t) => $t->same(true, $plan72()['current']['runnable']),
    'next plan remains runnable' => static fn (TestRunner $t) => $t->same(true, $plan72()['next']['runnable']),
    'current idx num records json root visible limit' => static fn (TestRunner $t) => $t->same(15, $plan72()['current']['idxNum']),
    'next idx num records added visible atom constraint' => static fn (TestRunner $t) => $t->same(15, $plan72()['next']['idxNum']),
    'current idx string omits next atom constraint' => static fn (TestRunner $t) => $t->same(false, str_contains($plan72()['current']['idxStr'], 'visible:atom:IS NULL')),
    'next idx string includes atom constraint' => static fn (TestRunner $t) => $t->same(true, str_contains($plan72()['next']['idxStr'], 'visible:atom:IS NULL')),
    'current argument tape starts with current json' => static fn (TestRunner $t) => $t->same($currentSettings72, $plan72()['currentArguments'][0]),
    'next argument tape starts with next json' => static fn (TestRunner $t) => $t->same($nextSettings72, $plan72()['nextArguments'][0]),
    'current root argument is stable' => static fn (TestRunner $t) => $t->same('$.plugins', $plan72()['currentArguments'][1]),
    'next root argument is stable' => static fn (TestRunner $t) => $t->same('$.plugins', $plan72()['nextArguments'][1]),
    'current visible key argument has three choices' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms'], $plan72()['currentArguments'][2]),
    'next visible key argument has four choices' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms', 'shop'], $plan72()['nextArguments'][2]),
    'current visible type argument is object' => static fn (TestRunner $t) => $t->same('object', $plan72()['currentArguments'][3]),
    'next visible type argument is object' => static fn (TestRunner $t) => $t->same('object', $plan72()['nextArguments'][3]),
    'next visible atom argument appends null' => static fn (TestRunner $t) => $t->same(null, $plan72()['nextArguments'][4]),
    'current argument tape has four entries' => static fn (TestRunner $t) => $t->same(4, count($plan72()['currentArguments'])),
    'next argument tape has five entries' => static fn (TestRunner $t) => $t->same(5, count($plan72()['nextArguments'])),
    'argument transition count spans longer tape' => static fn (TestRunner $t) => $t->same(5, count($plan72()['argumentTransitions'])),
    'argument transition zero changes json blob' => static fn (TestRunner $t) => $t->same(true, $plan72()['argumentTransitions'][0]['changed']),
    'argument transition one keeps root' => static fn (TestRunner $t) => $t->same(false, $plan72()['argumentTransitions'][1]['changed']),
    'argument transition two changes key in list' => static fn (TestRunner $t) => $t->same(true, $plan72()['argumentTransitions'][2]['changed']),
    'argument transition three keeps type' => static fn (TestRunner $t) => $t->same(false, $plan72()['argumentTransitions'][3]['changed']),
    'argument transition four records appended atom' => static fn (TestRunner $t) => $t->same(true, $plan72()['argumentTransitions'][4]['changed']),
    'argument transition four current is absent null' => static fn (TestRunner $t) => $t->same(null, $plan72()['argumentTransitions'][4]['current']),
    'argument transition four next is null atom value' => static fn (TestRunner $t) => $t->same(null, $plan72()['argumentTransitions'][4]['next']),
    'current usage count includes limit metadata' => static fn (TestRunner $t) => $t->same(5, count($plan72()['current']['constraintUsage'])),
    'next usage count includes atom and limit metadata' => static fn (TestRunner $t) => $t->same(6, count($plan72()['next']['constraintUsage'])),
    'usage transitions span next usage' => static fn (TestRunner $t) => $t->same(6, count($plan72()['usageTransitions'])),
    'usage transition starts with json column' => static fn (TestRunner $t) => $t->same('json', $plan72()['usageTransitions'][0]['current']['column']),
    'usage transition keeps root column' => static fn (TestRunner $t) => $t->same('root', $plan72()['usageTransitions'][1]['next']['column']),
    'usage transition keeps key shape while argument tape changes values' => static fn (TestRunner $t) => $t->same(false, $plan72()['usageTransitions'][2]['changed']),
    'usage transition keeps type constraint' => static fn (TestRunner $t) => $t->same(false, $plan72()['usageTransitions'][3]['changed']),
    'usage transition four changes limit into atom' => static fn (TestRunner $t) => $t->same('atom', $plan72()['usageTransitions'][4]['next']['column']),
    'usage transition five records appended limit' => static fn (TestRunner $t) => $t->same(null, $plan72()['usageTransitions'][5]['current']),
    'current filter current-next ends at type' => static fn (TestRunner $t) => $t->same('type', $plan72()['current']['filterCurrentNext'][3]['current']['column']),
    'next filter current-next ends at atom' => static fn (TestRunner $t) => $t->same('atom', $plan72()['next']['filterCurrentNext'][4]['current']['column']),
    'current order by id is consumed' => static fn (TestRunner $t) => $t->same(true, $plan72()['current']['orderByConsumed']),
    'next order by id is consumed' => static fn (TestRunner $t) => $t->same(true, $plan72()['next']['orderByConsumed']),
    'current estimate stays bounded' => static fn (TestRunner $t) => $t->true($plan72()['current']['estimatedCost'] < 1000000),
    'next estimate stays bounded' => static fn (TestRunner $t) => $t->true($plan72()['next']['estimatedCost'] < 1000000),
    'stable plan needs no reprepare' => static fn (TestRunner $t) => $t->same(false, $stable72()['replanRequired']),
    'stable plan reports stable reason' => static fn (TestRunner $t) => $t->same('stable-current-next-plan', $stable72()['replanReason']),
    'stable next policy reuses current plan' => static fn (TestRunner $t) => $t->same('reuse-current-json-table-plan', $stable72()['nextReaderPolicy']),
    'stable argument transitions are unchanged' => static fn (TestRunner $t) => $t->same(false, $stable72()['argumentTransitions'][0]['changed']),
    'stable usage transitions are unchanged' => static fn (TestRunner $t) => $t->same(false, $stable72()['usageTransitions'][0]['changed']),
    'becomes runnable requires replan' => static fn (TestRunner $t) => $t->same(true, $becomesRunnable72()['replanRequired']),
    'becomes runnable reason is explicit' => static fn (TestRunner $t) => $t->same('next-plan-becomes-runnable', $becomesRunnable72()['replanReason']),
    'becomes runnable current is false' => static fn (TestRunner $t) => $t->same(false, $becomesRunnable72()['current']['runnable']),
    'becomes runnable next is true' => static fn (TestRunner $t) => $t->same(true, $becomesRunnable72()['next']['runnable']),
    'becomes unrunnable requires replan' => static fn (TestRunner $t) => $t->same(true, $becomesUnrunnable72()['replanRequired']),
    'becomes unrunnable reason is explicit' => static fn (TestRunner $t) => $t->same('next-plan-becomes-unrunnable', $becomesUnrunnable72()['replanReason']),
    'becomes unrunnable current is true' => static fn (TestRunner $t) => $t->same(true, $becomesUnrunnable72()['current']['runnable']),
    'becomes unrunnable next is false' => static fn (TestRunner $t) => $t->same(false, $becomesUnrunnable72()['next']['runnable']),
    'operator change requires replan' => static fn (TestRunner $t) => $t->same(true, $operatorChanged72()['replanRequired']),
    'operator change reason is operator tape' => static fn (TestRunner $t) => $t->same('constraint-operator-tape-changed', $operatorChanged72()['replanReason']),
    'operator change keeps argument count' => static fn (TestRunner $t) => $t->same(count($operatorChanged72()['currentArguments']), count($operatorChanged72()['nextArguments'])),
    'operator change records usage transition' => static fn (TestRunner $t) => $t->same(true, $operatorChanged72()['usageTransitions'][1]['changed']),
    'operator change keeps current pattern' => static fn (TestRunner $t) => $t->same('$.plugins.%', $operatorChanged72()['argumentTransitions'][1]['current']),
    'operator change keeps next pattern' => static fn (TestRunner $t) => $t->same('$.plugins.*', $operatorChanged72()['argumentTransitions'][1]['next']),
    'invalid current function is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::constraintPlannerComparison('bad_json', [], [])),
    'invalid next root is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::constraintPlannerComparison('json_tree', [['column' => 'json', 'operator' => '=', 'value' => '{}']], [['column' => 'json', 'operator' => '=', 'value' => '{}'], ['column' => 'root', 'operator' => '=', 'value' => '$[']])),
    'invalid next limit is rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonTablePlan::constraintPlannerComparison('json_each', [['column' => 'json', 'operator' => '=', 'value' => '{}']], [['column' => 'json', 'operator' => '=', 'value' => '{}'], ['column' => 'limit', 'operator' => '=', 'value' => -1]])),
    'current plan filter arguments remain separate from next' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms'], $plan72()['current']['filterArguments'][2]),
    'next plan filter arguments remain separate from current' => static fn (TestRunner $t) => $t->same(['seo', 'cache', 'forms', 'shop'], $plan72()['next']['filterArguments'][2]),
    'current next wrapper exposes current plan idx string' => static fn (TestRunner $t) => $t->same($plan72()['current']['idxStr'], SQLiteJsonTablePlan::xBestIndexPlan('json_tree', $baseCurrent72, [['column' => 'id']])['idxStr']),
    'current next wrapper exposes next plan idx string' => static fn (TestRunner $t) => $t->same($plan72()['next']['idxStr'], SQLiteJsonTablePlan::xBestIndexPlan('json_tree', $baseNext72, [['column' => 'id']])['idxStr']),
];

foreach ($tests as $name => $case) {
    $tests['json table constraint planner current next72 ' . $name] = $case;
    unset($tests[$name]);
}

return $tests;
