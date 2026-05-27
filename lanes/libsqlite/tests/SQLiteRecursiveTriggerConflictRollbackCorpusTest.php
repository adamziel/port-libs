<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerConflictRollbackPlan;

$committedRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
];
$transactionRows = [
    ...$committedRows,
    ['option_id' => 2, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$seedRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$recursiveTrigger = [[
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

$run = static function (array $trigger = null, string $conflictAction = 'rollback', array $input = null, array $options = []) use ($transactionRows, $seedRows, $recursiveTrigger): array {
    return SQLiteRecursiveTriggerConflictRollbackPlan::insertRows(
        $transactionRows,
        $input ?? $seedRows,
        $trigger ?? $recursiveTrigger,
        ['option_name'],
        $conflictAction,
        $options + ['rollback_rows' => [['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes']]]
    );
};

$conflictingRecursiveTrigger = static function (string $conflictAction = 'rollback') use ($recursiveTrigger): array {
    $trigger = $recursiveTrigger;
    $trigger[0]['when']['value'] = 3;
    $trigger[0]['insert_row']['option_name'] = 'preflight_marker';
    $trigger[0]['conflict_action'] = $conflictAction;

    return $trigger;
};

$tests = [
    'recursive trigger rollback corpus successful recursion keeps transaction rows' => static function (TestRunner $t) use ($run): void {
        $t->same(['siteurl', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child'], array_column($run(null, 'abort')['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus successful recursion counts inserted rows' => static function (TestRunner $t) use ($run): void {
        $t->same(4, $run(null, 'abort')['changes']);
    },
    'recursive trigger rollback corpus successful recursion is not aborted' => static function (TestRunner $t) use ($run): void {
        $t->same(false, $run(null, 'abort')['aborted']);
    },
    'recursive trigger rollback corpus successful recursion records no rollback scope' => static function (TestRunner $t) use ($run): void {
        $t->same('none', $run(null, 'abort')['rollback_scope']);
    },
    'recursive trigger rollback corpus successful recursion records trigger depths' => static function (TestRunner $t) use ($run): void {
        $effects = array_values(array_filter($run(null, 'abort')['effects'], static fn (array $effect): bool => $effect['action'] === 'trigger' && $effect['result'] === 'fired'));
        $t->same([0, 1, 2], array_column($effects, 'depth'));
    },
    'recursive trigger rollback corpus rollback restores committed transaction image' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(['siteurl'], array_column($run($conflictingRecursiveTrigger())['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus rollback removes prestatement transaction row' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(false, in_array('preflight_marker', array_column($run($conflictingRecursiveTrigger())['rows'], 'option_name'), true));
    },
    'recursive trigger rollback corpus rollback removes outer seed insert' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(false, in_array('plugin_seed', array_column($run($conflictingRecursiveTrigger())['rows'], 'option_name'), true));
    },
    'recursive trigger rollback corpus rollback removes recursive child insert' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(false, in_array('plugin_seed:child', array_column($run($conflictingRecursiveTrigger())['rows'], 'option_name'), true));
    },
    'recursive trigger rollback corpus rollback marks transaction scope' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('transaction', $run($conflictingRecursiveTrigger())['rollback_scope']);
    },
    'recursive trigger rollback corpus rollback reports conflict reason' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('unique-conflict-rollback', $run($conflictingRecursiveTrigger())['rollback_reason']);
    },
    'recursive trigger rollback corpus rollback clears counted changes' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(0, $run($conflictingRecursiveTrigger())['changes']);
    },
    'recursive trigger rollback corpus rollback clears inserted diagnostics' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same([], $run($conflictingRecursiveTrigger())['inserted']);
    },
    'recursive trigger rollback corpus rollback records rolled back effect' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('rolled-back-conflict', array_column($run($conflictingRecursiveTrigger())['effects'], 'result')[2]);
    },
    'recursive trigger rollback corpus rollback effect keeps recursive depth' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(1, $run($conflictingRecursiveTrigger())['effects'][2]['depth']);
    },
    'recursive trigger rollback corpus rollback effect uses trigger policy' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('rollback', $run($conflictingRecursiveTrigger())['effects'][2]['effective_conflict_action']);
    },
    'recursive trigger rollback corpus statement abort restores statement start only' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(['siteurl', 'preflight_marker'], array_column($run($conflictingRecursiveTrigger('abort'), 'abort')['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus statement abort preserves preflight transaction row' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('preflight_marker', $run($conflictingRecursiveTrigger('abort'), 'abort')['rows'][1]['option_name']);
    },
    'recursive trigger rollback corpus statement abort marks statement scope' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('statement', $run($conflictingRecursiveTrigger('abort'), 'abort')['rollback_scope']);
    },
    'recursive trigger rollback corpus statement abort reports abort reason' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same('unique-conflict-abort', $run($conflictingRecursiveTrigger('abort'), 'abort')['rollback_reason']);
    },
    'recursive trigger rollback corpus fail preserves earlier statement inserts' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(['siteurl', 'preflight_marker', 'plugin_seed'], array_column($run($conflictingRecursiveTrigger('fail'), 'abort')['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus fail counts earlier statement inserts' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(1, $run($conflictingRecursiveTrigger('fail'), 'abort')['changes']);
    },
    'recursive trigger rollback corpus fail records ignored conflict row' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(['preflight_marker'], array_column($run($conflictingRecursiveTrigger('fail'), 'abort')['ignored'], 'option_name'));
    },
    'recursive trigger rollback corpus fail does not mark aborted transaction' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $result = $run($conflictingRecursiveTrigger('fail'), 'abort');
        $t->same(false, $result['rolled_back']);
        $t->same(false, $result['aborted']);
    },
    'recursive trigger rollback corpus ignore skips conflict and keeps earlier rows' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(['siteurl', 'preflight_marker', 'plugin_seed'], array_column($run($conflictingRecursiveTrigger('ignore'), 'abort')['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus ignore keeps ignored diagnostic' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(['preflight_marker'], array_column($run($conflictingRecursiveTrigger('ignore'), 'abort')['ignored'], 'option_name'));
    },
    'recursive trigger rollback corpus replace updates conflicting transaction row' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $result = $run($conflictingRecursiveTrigger('replace'), 'abort');
        $t->same(12, $result['rows'][1]['option_id']);
    },
    'recursive trigger rollback corpus replace counts replacement as change' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(3, $run($conflictingRecursiveTrigger('replace'), 'abort')['changes']);
    },
    'recursive trigger rollback corpus recursive trigger off suppresses grandchild conflict' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $result = $run(null, 'abort', null, ['recursive_triggers' => false]);
        $t->same(['siteurl', 'preflight_marker', 'plugin_seed', 'plugin_seed:child'], array_column($result['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus recursive trigger off reports disabled' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $t->same(false, $run($conflictingRecursiveTrigger(), 'abort', null, ['recursive_triggers' => false])['recursive_triggers']);
    },
    'recursive trigger rollback corpus max depth blocks runaway recursion' => static function (TestRunner $t) use ($run): void {
        $t->throws(RuntimeException::class, static fn () => $run(null, 'abort', null, ['max_depth' => 1]));
    },
    'recursive trigger rollback corpus max depth metadata is reported' => static function (TestRunner $t) use ($run): void {
        $t->same(6, $run(null, 'abort', null, ['max_depth' => 6])['max_depth']);
    },
    'recursive trigger rollback corpus custom rollback image is honored' => static function (TestRunner $t) use ($run, $conflictingRecursiveTrigger): void {
        $result = $run($conflictingRecursiveTrigger(), 'rollback', null, ['rollback_rows' => [['option_id' => 7, 'option_name' => 'committed_snapshot', 'level' => 0, 'autoload' => 'yes']]]);
        $t->same(['committed_snapshot'], array_column($result['rows'], 'option_name'));
    },
    'recursive trigger rollback corpus malformed conflict action rejected' => static function (TestRunner $t) use ($run): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run(null, 'sideways'));
    },
    'recursive trigger rollback corpus malformed trigger timing rejected' => static function (TestRunner $t) use ($run, $recursiveTrigger): void {
        $trigger = $recursiveTrigger;
        $trigger[0]['timing'] = 'before';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, 'abort'));
    },
    'recursive trigger rollback corpus malformed trigger target rejected' => static function (TestRunner $t) use ($run, $recursiveTrigger): void {
        $trigger = $recursiveTrigger;
        $trigger[0]['table'] = 'side';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, 'abort'));
    },
    'recursive trigger rollback corpus missing insert row rejected' => static function (TestRunner $t) use ($run, $recursiveTrigger): void {
        $trigger = $recursiveTrigger;
        unset($trigger[0]['insert_row']);
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, 'abort'));
    },
    'recursive trigger rollback corpus missing new column rejected' => static function (TestRunner $t) use ($run, $recursiveTrigger): void {
        $trigger = $recursiveTrigger;
        $trigger[0]['insert_row']['autoload'] = 'new.missing';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, 'abort'));
    },
    'recursive trigger rollback corpus unsupported when operator rejected' => static function (TestRunner $t) use ($run, $recursiveTrigger): void {
        $trigger = $recursiveTrigger;
        $trigger[0]['when']['operator'] = 'in';
        $t->throws(InvalidArgumentException::class, static fn () => $run($trigger, 'abort'));
    },
    'recursive trigger rollback corpus empty unique columns rejected' => static function (TestRunner $t) use ($transactionRows, $seedRows, $recursiveTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerConflictRollbackPlan::insertRows($transactionRows, $seedRows, $recursiveTrigger, []));
    },
    'recursive trigger rollback corpus malformed unique column rejected' => static function (TestRunner $t) use ($transactionRows, $seedRows, $recursiveTrigger): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteRecursiveTriggerConflictRollbackPlan::insertRows($transactionRows, $seedRows, $recursiveTrigger, ['1bad']));
    },
];

return $tests;
