<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerDepthPlan;

$initialRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
];
$inputRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$trigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 8],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
]];

$run = static fn (array $triggers = null, array $options = [], array $inputs = null, string $conflictAction = 'abort'): array => SQLiteRecursiveTriggerDepthPlan::insertRows(
    $initialRows,
    $inputs ?? $inputRows,
    $triggers ?? $trigger,
    ['option_name'],
    $conflictAction,
    $options
);

$tests = [
    'trigger recursive depth current next25 max depth admits exact next depth' => static fn (TestRunner $t) => $t->same(false, $run($trigger, ['max_depth' => 7])['limit_hit']),
    'trigger recursive depth current next25 exact max depth appends final descendant' => static fn (TestRunner $t) => $t->same('plugin_seed:child:child:child:child:child:child:child', $run($trigger, ['max_depth' => 7])['rows'][8]['option_name']),
    'trigger recursive depth current next25 exact max depth counts seed and descendants' => static fn (TestRunner $t) => $t->same(8, $run($trigger, ['max_depth' => 7])['changes']),
    'trigger recursive depth current next25 exact max depth records observed depth' => static fn (TestRunner $t) => $t->same(7, $run($trigger, ['max_depth' => 7])['max_observed_depth']),
    'trigger recursive depth current next25 exact max depth keeps rollback scope none' => static fn (TestRunner $t) => $t->same('none', $run($trigger, ['max_depth' => 7])['rollback_scope']),
    'trigger recursive depth current next25 exact max depth has no limit reason' => static fn (TestRunner $t) => $t->same(null, $run($trigger, ['max_depth' => 7])['limit_reason']),
    'trigger recursive depth current next25 exact max depth records allowed checks' => static fn (TestRunner $t) => $t->same([true, true, true, true, true, true, true], array_column($run($trigger, ['max_depth' => 7])['depth_checks'], 'allowed')),
    'trigger recursive depth current next25 exact max depth records current depths' => static fn (TestRunner $t) => $t->same([0, 1, 2, 3, 4, 5, 6], array_column($run($trigger, ['max_depth' => 7])['depth_checks'], 'current_depth')),
    'trigger recursive depth current next25 exact max depth records next depths' => static fn (TestRunner $t) => $t->same([1, 2, 3, 4, 5, 6, 7], array_column($run($trigger, ['max_depth' => 7])['depth_checks'], 'next_depth')),
    'trigger recursive depth current next25 exact max depth records max limit each check' => static fn (TestRunner $t) => $t->same([7, 7, 7, 7, 7, 7, 7], array_column($run($trigger, ['max_depth' => 7])['depth_checks'], 'max_depth')),
    'trigger recursive depth current next25 exact max depth fires seven trigger programs' => static fn (TestRunner $t) => $t->same(7, count(array_filter($run($trigger, ['max_depth' => 7])['effects'], static fn (array $effect): bool => $effect['result'] === 'fired'))),
    'trigger recursive depth current next25 exact max depth inserts eight target rows' => static fn (TestRunner $t) => $t->same(8, count(array_filter($run($trigger, ['max_depth' => 7])['effects'], static fn (array $effect): bool => $effect['result'] === 'inserted'))),
    'trigger recursive depth current next25 exact max depth preserves autoload propagation' => static fn (TestRunner $t) => $t->same(['yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes', 'yes'], array_column($run($trigger, ['max_depth' => 7])['inserted'], 'autoload')),
    'trigger recursive depth current next25 exact max depth preserves initial row first' => static fn (TestRunner $t) => $t->same('siteurl', $run($trigger, ['max_depth' => 7])['rows'][0]['option_name']),
    'trigger recursive depth current next25 exact max depth reports recursive triggers enabled' => static fn (TestRunner $t) => $t->same(true, $run($trigger, ['max_depth' => 7])['recursive_triggers']),

    'trigger recursive depth current next25 abort blocks before over limit child insert' => static fn (TestRunner $t) => $t->same(['siteurl'], array_column($run($trigger, ['max_depth' => 3])['rows'], 'option_name')),
    'trigger recursive depth current next25 abort clears inserted diagnostics' => static fn (TestRunner $t) => $t->same([], $run($trigger, ['max_depth' => 3])['inserted']),
    'trigger recursive depth current next25 abort clears ignored diagnostics' => static fn (TestRunner $t) => $t->same([], $run($trigger, ['max_depth' => 3])['ignored']),
    'trigger recursive depth current next25 abort clears change count' => static fn (TestRunner $t) => $t->same(0, $run($trigger, ['max_depth' => 3])['changes']),
    'trigger recursive depth current next25 abort marks limit hit' => static fn (TestRunner $t) => $t->same(true, $run($trigger, ['max_depth' => 3])['limit_hit']),
    'trigger recursive depth current next25 abort reports reason' => static fn (TestRunner $t) => $t->same('trigger-recursion-depth-exceeded', $run($trigger, ['max_depth' => 3])['limit_reason']),
    'trigger recursive depth current next25 abort marks statement rollback scope' => static fn (TestRunner $t) => $t->same('statement', $run($trigger, ['max_depth' => 3])['rollback_scope']),
    'trigger recursive depth current next25 abort does not mark transaction rollback' => static fn (TestRunner $t) => $t->same(false, $run($trigger, ['max_depth' => 3])['rolled_back']),
    'trigger recursive depth current next25 abort records last current depth at limit' => static fn (TestRunner $t) => $t->same(3, array_column($run($trigger, ['max_depth' => 3])['depth_checks'], 'current_depth')[3]),
    'trigger recursive depth current next25 abort records first disallowed next depth' => static fn (TestRunner $t) => $t->same(4, array_column($run($trigger, ['max_depth' => 3])['depth_checks'], 'next_depth')[3]),
    'trigger recursive depth current next25 abort records disallowed check' => static fn (TestRunner $t) => $t->same([true, true, true, false], array_column($run($trigger, ['max_depth' => 3])['depth_checks'], 'allowed')),
    'trigger recursive depth current next25 abort records blocked effect' => static fn (TestRunner $t) => $t->same(['depth-limit-blocked'], array_values(array_filter(array_column($run($trigger, ['max_depth' => 3])['effects'], 'result'), static fn (string $result): bool => $result === 'depth-limit-blocked'))),
    'trigger recursive depth current next25 abort blocked row is not materialized' => static fn (TestRunner $t) => $t->same(false, in_array('plugin_seed:child:child:child:child', array_column($run($trigger, ['max_depth' => 3])['rows'], 'option_name'), true)),
    'trigger recursive depth current next25 abort max observed depth stops at admitted frame' => static fn (TestRunner $t) => $t->same(3, $run($trigger, ['max_depth' => 3])['max_observed_depth']),
    'trigger recursive depth current next25 abort effect exposes current and next depth' => static function (TestRunner $t) use ($run, $trigger): void {
        $blocked = array_values(array_filter($run($trigger, ['max_depth' => 3])['effects'], static fn (array $effect): bool => $effect['result'] === 'depth-limit-blocked'))[0];
        $t->same([3, 4], [$blocked['current_depth'], $blocked['next_depth']]);
    },

    'trigger recursive depth current next25 rollback restores custom transaction image' => static fn (TestRunner $t) => $t->same(['committed_snapshot'], array_column($run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback', 'rollback_rows' => [['option_id' => 90, 'option_name' => 'committed_snapshot', 'level' => 0, 'autoload' => 'no']]])['rows'], 'option_name')),
    'trigger recursive depth current next25 rollback marks transaction scope' => static fn (TestRunner $t) => $t->same('transaction', $run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['rollback_scope']),
    'trigger recursive depth current next25 rollback marks rolled back' => static fn (TestRunner $t) => $t->same(true, $run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['rolled_back']),
    'trigger recursive depth current next25 rollback clears changes' => static fn (TestRunner $t) => $t->same(0, $run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['changes']),
    'trigger recursive depth current next25 rollback preserves limit diagnostics' => static fn (TestRunner $t) => $t->same('trigger-recursion-depth-exceeded', $run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['limit_reason']),
    'trigger recursive depth current next25 rollback keeps blocked effect for audit' => static fn (TestRunner $t) => $t->same(['depth-limit-blocked'], array_values(array_filter(array_column($run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['effects'], 'result'), static fn (string $result): bool => $result === 'depth-limit-blocked'))),
    'trigger recursive depth current next25 rollback records disallowed next depth three' => static fn (TestRunner $t) => $t->same(3, array_column($run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['depth_checks'], 'next_depth')[2]),
    'trigger recursive depth current next25 rollback max observed depth is last entered frame' => static fn (TestRunner $t) => $t->same(2, $run($trigger, ['max_depth' => 2, 'on_limit' => 'rollback'])['max_observed_depth']),

    'trigger recursive depth current next25 ignore keeps admitted rows' => static fn (TestRunner $t) => $t->same(['siteurl', 'plugin_seed', 'plugin_seed:child'], array_column($run($trigger, ['max_depth' => 1, 'on_limit' => 'ignore'])['rows'], 'option_name')),
    'trigger recursive depth current next25 ignore records blocked child as ignored' => static fn (TestRunner $t) => $t->same(['plugin_seed:child:child'], array_column($run($trigger, ['max_depth' => 1, 'on_limit' => 'ignore'])['ignored'], 'option_name')),
    'trigger recursive depth current next25 ignore keeps admitted change count' => static fn (TestRunner $t) => $t->same(2, $run($trigger, ['max_depth' => 1, 'on_limit' => 'ignore'])['changes']),
    'trigger recursive depth current next25 ignore does not rollback statement' => static fn (TestRunner $t) => $t->same('none', $run($trigger, ['max_depth' => 1, 'on_limit' => 'ignore'])['rollback_scope']),
    'trigger recursive depth current next25 ignore still marks limit hit' => static fn (TestRunner $t) => $t->same(true, $run($trigger, ['max_depth' => 1, 'on_limit' => 'ignore'])['limit_hit']),
    'trigger recursive depth current next25 ignore records false allowed check' => static fn (TestRunner $t) => $t->same([true, false], array_column($run($trigger, ['max_depth' => 1, 'on_limit' => 'ignore'])['depth_checks'], 'allowed')),

    'trigger recursive depth current next25 recursive triggers off suppresses depth one child trigger' => static fn (TestRunner $t) => $t->same(['siteurl', 'plugin_seed', 'plugin_seed:child'], array_column($run($trigger, ['max_depth' => 1, 'recursive_triggers' => false])['rows'], 'option_name')),
    'trigger recursive depth current next25 recursive triggers off avoids limit hit' => static fn (TestRunner $t) => $t->same(false, $run($trigger, ['max_depth' => 1, 'recursive_triggers' => false])['limit_hit']),
    'trigger recursive depth current next25 recursive triggers off records disabled option' => static fn (TestRunner $t) => $t->same(false, $run($trigger, ['max_depth' => 1, 'recursive_triggers' => false])['recursive_triggers']),
    'trigger recursive depth current next25 recursive triggers off records suppression' => static fn (TestRunner $t) => $t->same(['recursive-trigger-suppressed'], array_values(array_filter(array_column($run($trigger, ['max_depth' => 1, 'recursive_triggers' => false])['effects'], 'result'), static fn (string $result): bool => $result === 'recursive-trigger-suppressed'))),
    'trigger recursive depth current next25 recursive triggers off max observed depth is child frame' => static fn (TestRunner $t) => $t->same(1, $run($trigger, ['max_depth' => 1, 'recursive_triggers' => false])['max_observed_depth']),
    'trigger recursive depth current next25 recursive triggers off has two checks' => static fn (TestRunner $t) => $t->same(2, count($run($trigger, ['max_depth' => 1, 'recursive_triggers' => false])['depth_checks'])),

    'trigger recursive depth current next25 where guard below limit has no limit hit' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['when']['value'] = 3;
        $t->same(false, $run($trigger, ['max_depth' => 2])['limit_hit']);
    },
    'trigger recursive depth current next25 where guard below limit records final skip' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['when']['value'] = 3;
        $t->same(['when-skipped'], array_values(array_filter(array_column($run($trigger, ['max_depth' => 2])['effects'], 'result'), static fn (string $result): bool => $result === 'when-skipped')));
    },
    'trigger recursive depth current next25 unique ignore still records depth zero conflict' => static function (TestRunner $t) use ($run, $trigger, $inputRows): void {
        $inputRows[] = ['option_id' => 20, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'no'];
        $result = $run($trigger, ['max_depth' => 0, 'on_limit' => 'ignore'], $inputRows, 'ignore');
        $ignored = array_values(array_filter($result['effects'], static fn (array $effect): bool => $effect['result'] === 'ignored-conflict'));
        $t->same(0, $ignored[0]['current_depth']);
    },
    'trigger recursive depth current next25 unique ignore records duplicate row' => static function (TestRunner $t) use ($run, $trigger, $inputRows): void {
        $inputRows[] = ['option_id' => 20, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'no'];
        $t->same('plugin_seed', $run($trigger, ['max_depth' => 0, 'on_limit' => 'ignore'], $inputRows, 'ignore')['ignored'][1]['option_name']);
    },
    'trigger recursive depth current next25 unique replace rewrites top row' => static function (TestRunner $t) use ($run, $trigger, $inputRows): void {
        $inputRows[] = ['option_id' => 20, 'option_name' => 'plugin_seed', 'level' => 9, 'autoload' => 'no'];
        $t->same(9, $run($trigger, ['max_depth' => 0, 'on_limit' => 'ignore'], $inputRows, 'replace')['rows'][1]['level']);
    },
    'trigger recursive depth current next25 zero max depth blocks first trigger child' => static fn (TestRunner $t) => $t->same([false], array_column($run($trigger, ['max_depth' => 0])['depth_checks'], 'allowed')),
    'trigger recursive depth current next25 zero max depth abort keeps only initial row' => static fn (TestRunner $t) => $t->same(['siteurl'], array_column($run($trigger, ['max_depth' => 0])['rows'], 'option_name')),
    'trigger recursive depth current next25 default max depth reported' => static fn (TestRunner $t) => $t->same(1000, $run($trigger)['max_depth']),
    'trigger recursive depth current next25 negative max depth rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, ['max_depth' => -1])),
    'trigger recursive depth current next25 unsupported on limit rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, ['on_limit' => 'sideways'])),
    'trigger recursive depth current next25 malformed conflict action rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, [], null, 'rollback')),
    'trigger recursive depth current next25 malformed trigger timing rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['timing'] = 'instead';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'trigger recursive depth current next25 malformed trigger event rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['event'] = 'update';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'trigger recursive depth current next25 malformed trigger table rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['table'] = 'other';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'trigger recursive depth current next25 missing insert row rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        unset($trigger[0]['insert_row']);
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'trigger recursive depth current next25 missing new column rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['insert_row']['autoload'] = 'new.missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'trigger recursive depth current next25 unsupported when operator rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['when']['operator'] = 'like';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'trigger recursive depth current next25 malformed unique column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerDepthPlan::insertRows($initialRows, $inputRows, $trigger, ['1bad'])),
    'trigger recursive depth current next25 empty unique column list rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerDepthPlan::insertRows($initialRows, $inputRows, $trigger, [])),
];

return $tests;
