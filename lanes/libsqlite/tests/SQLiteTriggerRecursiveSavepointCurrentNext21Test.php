<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerSavepointPlan;

$savepointRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$inputRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$trigger = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'level', 'operator' => '<', 'value' => 4],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'level' => 'new_increment.level',
        'autoload' => 'new.autoload',
    ],
]];

$conflictingTrigger = $trigger;
$conflictingTrigger[0]['when']['value'] = 3;
$conflictingTrigger[0]['insert_row']['option_name'] = 'preflight_marker';
$conflictingTrigger[0]['conflict_action'] = 'rollback';

$run = static fn (array $triggers = null, string $conflictAction = 'rollback', array $options = []) => SQLiteRecursiveTriggerSavepointPlan::insertRows(
    'plugin-option-import',
    $savepointRows,
    $inputRows,
    $triggers ?? $trigger,
    ['option_name'],
    $conflictAction,
    $options
);

$tests = [
    'recursive trigger savepoint current next21 successful recursion appends descendants' => static fn (TestRunner $t) => $t->same(['siteurl', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child'], array_column($run($trigger, 'abort')['rows'], 'option_name')),
    'recursive trigger savepoint current next21 successful recursion counts changes' => static fn (TestRunner $t) => $t->same(4, $run($trigger, 'abort')['changes']),
    'recursive trigger savepoint current next21 successful recursion keeps scope none' => static fn (TestRunner $t) => $t->same('none', $run($trigger, 'abort')['rollback_scope']),
    'recursive trigger savepoint current next21 successful recursion is not rolled back' => static fn (TestRunner $t) => $t->same(false, $run($trigger, 'abort')['rolled_back']),
    'recursive trigger savepoint current next21 successful recursion reports savepoint name' => static fn (TestRunner $t) => $t->same('plugin-option-import', $run($trigger, 'abort')['savepoint']),
    'recursive trigger savepoint current next21 successful recursion current rows equal rows' => static fn (TestRunner $t) => $t->same($run($trigger, 'abort')['rows'], $run($trigger, 'abort')['current_rows']),
    'recursive trigger savepoint current next21 successful recursion records recursive option' => static fn (TestRunner $t) => $t->same(true, $run($trigger, 'abort')['recursive_triggers']),
    'recursive trigger savepoint current next21 successful recursion reports max depth' => static fn (TestRunner $t) => $t->same(1000, $run($trigger, 'abort')['max_depth']),
    'recursive trigger savepoint current next21 successful recursion has no discarded rows' => static fn (TestRunner $t) => $t->same([], $run($trigger, 'abort')['discarded']),
    'recursive trigger savepoint current next21 successful recursion dependencies named' => static fn (TestRunner $t) => $t->same(['sqlite-recursive-trigger-conflict', 'sqlite-savepoint-current-rollback'], $run($trigger, 'abort')['dependencies']),
    'recursive trigger savepoint current next21 rollback restores savepoint rows' => static fn (TestRunner $t) => $t->same(['siteurl', 'preflight_marker'], array_column($run($conflictingTrigger)['rows'], 'option_name')),
    'recursive trigger savepoint current next21 rollback current rows match savepoint' => static fn (TestRunner $t) => $t->same(['siteurl', 'preflight_marker'], array_column($run($conflictingTrigger)['current_rows'], 'option_name')),
    'recursive trigger savepoint current next21 rollback does not remove outer current marker' => static fn (TestRunner $t) => $t->same('preflight_marker', $run($conflictingTrigger)['rows'][1]['option_name']),
    'recursive trigger savepoint current next21 rollback removes seed' => static fn (TestRunner $t) => $t->same(false, in_array('plugin_seed', array_column($run($conflictingTrigger)['rows'], 'option_name'), true)),
    'recursive trigger savepoint current next21 rollback removes recursive child' => static fn (TestRunner $t) => $t->same(false, in_array('plugin_seed:child', array_column($run($conflictingTrigger)['rows'], 'option_name'), true)),
    'recursive trigger savepoint current next21 rollback marks savepoint scope' => static fn (TestRunner $t) => $t->same('savepoint', $run($conflictingTrigger)['rollback_scope']),
    'recursive trigger savepoint current next21 rollback reports unique conflict reason' => static fn (TestRunner $t) => $t->same('unique-conflict-rollback', $run($conflictingTrigger)['rollback_reason']),
    'recursive trigger savepoint current next21 rollback clears changes' => static fn (TestRunner $t) => $t->same(0, $run($conflictingTrigger)['changes']),
    'recursive trigger savepoint current next21 rollback clears inserted rows' => static fn (TestRunner $t) => $t->same([], $run($conflictingTrigger)['inserted']),
    'recursive trigger savepoint current next21 rollback clears ignored rows' => static fn (TestRunner $t) => $t->same([], $run($conflictingTrigger)['ignored']),
    'recursive trigger savepoint current next21 rollback preserves savepoint flag' => static fn (TestRunner $t) => $t->same(true, $run($conflictingTrigger)['savepoint_preserved']),
    'recursive trigger savepoint current next21 rollback records discarded seed' => static fn (TestRunner $t) => $t->same(['plugin_seed'], array_column($run($conflictingTrigger)['discarded'], 'option_name')),
    'recursive trigger savepoint current next21 rollback records savepoint effect' => static fn (TestRunner $t) => $t->same('rollback-to-current-savepoint', array_column($run($conflictingTrigger)['effects'], 'result')[3]),
    'recursive trigger savepoint current next21 rollback effect names savepoint' => static fn (TestRunner $t) => $t->same('plugin-option-import', $run($conflictingTrigger)['effects'][3]['savepoint']),
    'recursive trigger savepoint current next21 rollback effect counts discarded rows' => static fn (TestRunner $t) => $t->same(1, $run($conflictingTrigger)['effects'][3]['discarded_count']),
    'recursive trigger savepoint current next21 rollback attempted rows are savepoint image' => static fn (TestRunner $t) => $t->same(['siteurl', 'preflight_marker'], array_column($run($conflictingTrigger)['attempted_rows'], 'option_name')),
    'recursive trigger savepoint current next21 fail preserves prior statement insert' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'fail';
        $t->same(['siteurl', 'preflight_marker', 'plugin_seed'], array_column($run($conflictingTrigger, 'abort')['rows'], 'option_name'));
    },
    'recursive trigger savepoint current next21 fail marks no rollback' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'fail';
        $t->same(false, $run($conflictingTrigger, 'abort')['rolled_back']);
    },
    'recursive trigger savepoint current next21 fail keeps change count' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'fail';
        $t->same(1, $run($conflictingTrigger, 'abort')['changes']);
    },
    'recursive trigger savepoint current next21 ignore keeps seed row' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'ignore';
        $t->same(['siteurl', 'preflight_marker', 'plugin_seed'], array_column($run($conflictingTrigger, 'abort')['rows'], 'option_name'));
    },
    'recursive trigger savepoint current next21 ignore records ignored conflict' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'ignore';
        $t->same(['preflight_marker'], array_column($run($conflictingTrigger, 'abort')['ignored'], 'option_name'));
    },
    'recursive trigger savepoint current next21 replace rewrites current conflict row' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'replace';
        $t->same(12, $run($conflictingTrigger, 'abort')['rows'][1]['option_id']);
    },
    'recursive trigger savepoint current next21 replace counts seed and replacement' => static function (TestRunner $t) use ($run, $conflictingTrigger): void {
        $conflictingTrigger[0]['conflict_action'] = 'replace';
        $t->same(3, $run($conflictingTrigger, 'abort')['changes']);
    },
    'recursive trigger savepoint current next21 recursive triggers off still rolls back top level conflict' => static fn (TestRunner $t) => $t->same(['siteurl', 'preflight_marker'], array_column($run($conflictingTrigger, 'abort', ['recursive_triggers' => false])['rows'], 'option_name')),
    'recursive trigger savepoint current next21 recursive triggers off reports disabled' => static fn (TestRunner $t) => $t->same(false, $run($conflictingTrigger, 'abort', ['recursive_triggers' => false])['recursive_triggers']),
    'recursive trigger savepoint current next21 custom max depth reported' => static fn (TestRunner $t) => $t->same(6, $run($trigger, 'abort', ['max_depth' => 6])['max_depth']),
    'recursive trigger savepoint current next21 max depth blocks runaway' => static fn (TestRunner $t) => $t->throws(RuntimeException::class, static fn () => $run($trigger, 'abort', ['max_depth' => 1])),
    'recursive trigger savepoint current next21 empty savepoint name rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerSavepointPlan::insertRows('', $savepointRows, $inputRows, $trigger, ['option_name'])),
    'recursive trigger savepoint current next21 malformed conflict action rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, 'sideways')),
    'recursive trigger savepoint current next21 malformed trigger timing rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['timing'] = 'before';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'recursive trigger savepoint current next21 malformed unique column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerSavepointPlan::insertRows('sp', $savepointRows, $inputRows, $trigger, ['1bad'])),
    'recursive trigger savepoint current next21 empty unique column list rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerSavepointPlan::insertRows('sp', $savepointRows, $inputRows, $trigger, [])),
    'recursive trigger savepoint current next21 missing new column rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['insert_row']['autoload'] = 'new.missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
    'recursive trigger savepoint current next21 unsupported when operator rejected' => static function (TestRunner $t) use ($run, $trigger): void {
        $trigger[0]['when']['operator'] = 'like';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger));
    },
];

return $tests;
