<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerSavepointCurrentNextPlan;

$savepointRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'preflight_marker', 'level' => 0, 'autoload' => 'no'],
];
$currentRows = [
    ['option_id' => 10, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes'],
];
$nextRows = [
    ['option_id' => 20, 'option_name' => 'plugin_retry', 'level' => 1, 'autoload' => 'yes'],
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
$conflictingTrigger = $recursiveTrigger;
$conflictingTrigger[0]['when']['value'] = 3;
$conflictingTrigger[0]['insert_row']['option_name'] = 'preflight_marker';
$conflictingTrigger[0]['conflict_action'] = 'rollback';
$returning = [
    'option_id',
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    static fn (array $row, int $ordinal, array $effect): string => $ordinal . ':' . $effect['result'] . ':' . $row['option_name'],
];

$run = static fn (
    array $current = null,
    array $next = null,
    array $currentTriggerSet = null,
    array $nextTriggerSet = null,
    string $currentConflict = 'rollback',
    string $nextConflict = 'abort',
    array $currentOptions = [],
    array $nextOptions = []
): array => SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback(
    'wp_import_sp',
    $savepointRows,
    $current ?? $currentRows,
    $currentTriggerSet ?? $conflictingTrigger,
    $next ?? $nextRows,
    $nextTriggerSet ?? $recursiveTrigger,
    ['option_name'],
    $returning,
    $currentConflict,
    $nextConflict,
    $currentOptions,
    $nextOptions,
);

$plan = static fn (): array => $run();
$noRollback = static fn (): array => $run(null, null, $recursiveTrigger, $recursiveTrigger, 'abort');
$nextRollback = static function () use ($run, $conflictingTrigger): array {
    $nextConflict = $conflictingTrigger;
    $nextConflict[0]['insert_row']['option_name'] = 'siteurl';

    return $run(null, null, null, $nextConflict);
};
$disabledNext = static fn (): array => $run(null, null, null, $recursiveTrigger, 'rollback', 'abort', [], ['recursive_triggers' => false]);

$cases = [
    'status rolls back then applies next' => [static fn (): mixed => $plan()['status'], 'rolled-back-then-next-applied'],
    'savepoint name retained' => [static fn (): mixed => $plan()['savepoint'], 'wp_import_sp'],
    'current rolled back' => [static fn (): mixed => $plan()['current_rolled_back'], true],
    'next not rolled back' => [static fn (): mixed => $plan()['next_rolled_back'], false],
    'current returning suppressed' => [static fn (): mixed => $plan()['current_returning_rows'], []],
    'current attempted yield names' => [static fn (): mixed => array_column(array_filter($plan()['current_attempted_yields'], static fn (array $yield): bool => $yield['returning'] !== null), 'current_rowid'), [10]],
    'current failed yield is constraint' => [static fn (): mixed => $plan()['current_attempted_yields'][1]['status'], 'constraint-error'],
    'current failed yield row suppressed' => [static fn (): mixed => $plan()['current_attempted_yields'][1]['row'], null],
    'current conflict action rollback' => [static fn (): mixed => $plan()['current_attempted_yields'][1]['conflict_action'], 'rollback'],
    'current rollback reason' => [static fn (): mixed => $plan()['current_rollback_reason'], 'unique-conflict-rollback'],
    'savepoint preserved after current' => [static fn (): mixed => $plan()['savepoint_preserved_after_current'], true],
    'discarded current row count' => [static fn (): mixed => count($plan()['discarded_current_rows']), 1],
    'discarded current row name' => [static fn (): mixed => $plan()['discarded_current_rows'][0]['option_name'], 'plugin_seed'],
    'current changes reset' => [static fn (): mixed => $plan()['current_changes'], 0],
    'current next rowid restored to savepoint' => [static fn (): mixed => $plan()['next_rowid_after_current'], 3],
    'next starts from savepoint rows' => [static fn (): mixed => $plan()['next_started_from_savepoint'], true],
    'next rows include retry descendants only' => [static fn (): mixed => array_column($plan()['next_rows'], 'option_name'), ['siteurl', 'preflight_marker', 'plugin_retry', 'plugin_retry:child', 'plugin_retry:child:child', 'plugin_retry:child:child:child']],
    'next rows exclude discarded current seed' => [static fn (): mixed => in_array('plugin_seed', array_column($plan()['next_rows'], 'option_name'), true), false],
    'next returning names' => [static fn (): mixed => array_column($plan()['next_returning_rows'], 'name'), ['plugin_retry', 'plugin_retry:child', 'plugin_retry:child:child', 'plugin_retry:child:child:child']],
    'next returning depths' => [static fn (): mixed => array_column($plan()['next_returning_rows'], 'trigger_depth'), [0, 1, 2, 3]],
    'next callable labels' => [static fn (): mixed => array_column($plan()['next_returning_rows'], 'expr3'), ['0:inserted:plugin_retry', '1:inserted:plugin_retry:child', '2:inserted:plugin_retry:child:child', '3:inserted:plugin_retry:child:child:child']],
    'next attempted yield statuses changed' => [static fn (): mixed => array_column($plan()['next_attempted_yields'], 'status'), ['changed', 'changed', 'changed', 'changed']],
    'next attempted yield depths' => [static fn (): mixed => array_column($plan()['next_attempted_yields'], 'depth'), [0, 1, 2, 3]],
    'next changes count retry recursion' => [static fn (): mixed => $plan()['next_changes'], 4],
    'total changes excludes rolled back current' => [static fn (): mixed => $plan()['total_changes'], 4],
    'next rowid after retry' => [static fn (): mixed => $plan()['next_rowid_after_next'], 24],
    'current effects include savepoint rollback marker' => [static fn (): mixed => $plan()['current_effects'][array_key_last($plan()['current_effects'])]['result'], 'rollback-to-current-savepoint'],
    'current rollback effect names savepoint' => [static fn (): mixed => $plan()['current_effects'][array_key_last($plan()['current_effects'])]['savepoint'], 'wp_import_sp'],
    'next effects start with retry insert' => [static fn (): mixed => $plan()['next_effects'][0]['row']['option_name'], 'plugin_retry'],
    'dependencies include current next71' => [static fn (): mixed => in_array('sqlite-recursive-trigger-savepoint-current-next71', $plan()['dependencies'], true), true],
    'dependencies include recursive conflict' => [static fn (): mixed => in_array('sqlite-recursive-trigger-conflict', $plan()['dependencies'], true), true],
    'dependencies include returning yield' => [static fn (): mixed => in_array('sqlite-returning-recursive-yield-current-next50', $plan()['dependencies'], true), true],

    'no rollback status applied' => [static fn (): mixed => $noRollback()['status'], 'current-and-next-applied'],
    'no rollback current returning names' => [static fn (): mixed => array_column($noRollback()['current_returning_rows'], 'name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'no rollback next starts after current rows' => [static fn (): mixed => array_slice(array_column($noRollback()['next_rows'], 'option_name'), 0, 6), ['siteurl', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'no rollback total changes includes both statements' => [static fn (): mixed => $noRollback()['total_changes'], 8],
    'no rollback savepoint not preserved' => [static fn (): mixed => $noRollback()['savepoint_preserved_after_current'], false],

    'next rollback status' => [static fn (): mixed => $nextRollback()['status'], 'next-rolled-back'],
    'next rollback restores savepoint rows' => [static fn (): mixed => array_column($nextRollback()['next_rows'], 'option_name'), ['siteurl', 'preflight_marker']],
    'next rollback suppresses returning' => [static fn (): mixed => $nextRollback()['next_returning_rows'], []],
    'next rollback reason retained' => [static fn (): mixed => $nextRollback()['next_rollback_reason'], 'unique-conflict-rollback'],
    'next rollback total changes zero' => [static fn (): mixed => $nextRollback()['total_changes'], 0],

    'disabled next recursion inserts one child' => [static fn (): mixed => array_column($disabledNext()['next_returning_rows'], 'name'), ['plugin_retry', 'plugin_retry:child']],
    'disabled next rowid advances through child' => [static fn (): mixed => $disabledNext()['next_rowid_after_next'], 22],

    'bad savepoint throws' => [static fn (): mixed => SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback('bad-name', $savepointRows, $currentRows, $conflictingTrigger, $nextRows, $recursiveTrigger, ['option_name'], $returning), InvalidArgumentException::class],
    'empty current rows throws' => [static fn (): mixed => SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback('ok_name', $savepointRows, [], $conflictingTrigger, $nextRows, $recursiveTrigger, ['option_name'], $returning), InvalidArgumentException::class],
    'empty next rows throws' => [static fn (): mixed => SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback('ok_name', $savepointRows, $currentRows, $conflictingTrigger, [], $recursiveTrigger, ['option_name'], $returning), InvalidArgumentException::class],
    'bad unique column throws through recursive plan' => [static fn (): mixed => SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback('ok_name', $savepointRows, $currentRows, $conflictingTrigger, $nextRows, $recursiveTrigger, ['bad-column'], $returning), InvalidArgumentException::class],
    'bad trigger operator throws through recursive plan' => [static function () use ($savepointRows, $currentRows, $nextRows, $recursiveTrigger, $returning, $conflictingTrigger): mixed {
        $bad = $conflictingTrigger;
        $bad[0]['when']['operator'] = 'like';
        return SQLiteRecursiveTriggerSavepointCurrentNextPlan::retryAfterRollback('ok_name', $savepointRows, $currentRows, $bad, $nextRows, $recursiveTrigger, ['option_name'], $returning);
    }, InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger recursive savepoint current next71 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
