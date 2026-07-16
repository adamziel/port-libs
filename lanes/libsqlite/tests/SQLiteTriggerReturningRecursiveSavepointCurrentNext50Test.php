<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveTriggerReturningSavepointPlan;

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
$returning = [
    'option_id',
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    static fn (array $row, int $ordinal, array $effect): string => $ordinal . ':' . $effect['result'] . ':' . $row['option_name'],
];

$run = static fn (array $rows = null, array $triggers = null, string $conflictAction = 'rollback', ?array $projection = null, array $options = []): array => SQLiteRecursiveTriggerReturningSavepointPlan::insertRows(
    'wp-current',
    $savepointRows,
    $rows ?? $inputRows,
    $triggers ?? $trigger,
    ['option_name'],
    $conflictAction,
    $projection ?? $returning,
    $options
);

$conflictingTrigger = $trigger;
$conflictingTrigger[0]['when']['value'] = 3;
$conflictingTrigger[0]['insert_row']['option_name'] = 'preflight_marker';
$conflictingTrigger[0]['conflict_action'] = 'rollback';

$successful = static fn (): array => $run();
$rolledBack = static fn (): array => $run(null, $conflictingTrigger);
$ignored = static function () use ($run, $conflictingTrigger): array {
    $conflictingTrigger[0]['conflict_action'] = 'ignore';
    return $run(null, $conflictingTrigger, 'abort');
};
$replaced = static function () use ($run, $conflictingTrigger): array {
    $conflictingTrigger[0]['conflict_action'] = 'replace';
    return $run(null, $conflictingTrigger, 'abort');
};
$disabled = static fn (): array => $run(null, $trigger, 'abort', null, ['recursive_triggers' => false]);

$cases = [
    'successful rows include recursive descendants' => [static fn (): mixed => array_column($successful()['rows'], 'option_name'), ['siteurl', 'preflight_marker', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'successful changes count recursive inserts' => [static fn (): mixed => $successful()['changes'], 4],
    'successful rollback scope none' => [static fn (): mixed => $successful()['rollback_scope'], 'none'],
    'successful returning row count' => [static fn (): mixed => count($successful()['returning_rows']), 4],
    'successful returning ids are current rowids' => [static fn (): mixed => array_column($successful()['returning_rows'], 'option_id'), [10, 11, 12, 13]],
    'successful returning names are current rows' => [static fn (): mixed => array_column($successful()['returning_rows'], 'name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'successful returning depths follow recursive trigger depth' => [static fn (): mixed => array_column($successful()['returning_rows'], 'trigger_depth'), [0, 1, 2, 3]],
    'successful callable returning labels each yield' => [static fn (): mixed => array_column($successful()['returning_rows'], 'expr3'), ['0:inserted:plugin_seed', '1:inserted:plugin_seed:child', '2:inserted:plugin_seed:child:child', '3:inserted:plugin_seed:child:child:child']],
    'successful attempted yield statuses changed' => [static fn (): mixed => array_column($successful()['attempted_yields'], 'status'), ['changed', 'changed', 'changed', 'changed']],
    'successful attempted yield events insert' => [static fn (): mixed => array_column($successful()['attempted_yields'], 'event'), ['insert', 'insert', 'insert', 'insert']],
    'successful attempted yield depths' => [static fn (): mixed => array_column($successful()['attempted_yields'], 'depth'), [0, 1, 2, 3]],
    'successful attempted yield current rowids' => [static fn (): mixed => array_column($successful()['attempted_yields'], 'current_rowid'), [10, 11, 12, 13]],
    'successful attempted yield next rowids advance' => [static fn (): mixed => array_column($successful()['attempted_yields'], 'next_rowid'), [3, 11, 12, 13]],
    'successful final next rowid' => [static fn (): mixed => $successful()['next_rowid'], 14],
    'successful savepoint preserved false after inserts' => [static fn (): mixed => $successful()['savepoint_preserved'], false],
    'successful discarded empty' => [static fn (): mixed => $successful()['discarded'], []],
    'successful dependency names include returning yield' => [static fn (): mixed => in_array('sqlite-returning-recursive-yield-current-next50', $successful()['dependencies'], true), true],
    'successful current rows mirror rows' => [static fn (): mixed => $successful()['current_rows'], $successful()['rows']],
    'successful inserted rows match returning count' => [static fn (): mixed => count($successful()['inserted']), 4],
    'successful first yield returning name' => [static fn (): mixed => $successful()['attempted_yields'][0]['returning']['name'], 'plugin_seed'],
    'successful third yield row level' => [static fn (): mixed => $successful()['attempted_yields'][2]['row']['level'], 3],
    'successful trigger fired effects interleave after first insert' => [static fn (): mixed => $successful()['effects'][1]['result'], 'fired'],

    'rollback restores savepoint rows' => [static fn (): mixed => array_column($rolledBack()['rows'], 'option_name'), ['siteurl', 'preflight_marker']],
    'rollback suppresses committed returning rows' => [static fn (): mixed => $rolledBack()['returning_rows'], []],
    'rollback still exposes first attempted returning diagnostic' => [static fn (): mixed => $rolledBack()['attempted_yields'][0]['returning']['name'], 'plugin_seed'],
    'rollback records failing attempted yield as constraint error' => [static fn (): mixed => $rolledBack()['attempted_yields'][1]['status'], 'constraint-error'],
    'rollback failing yield has null row' => [static fn (): mixed => $rolledBack()['attempted_yields'][1]['row'], null],
    'rollback failing result names rolled back conflict' => [static fn (): mixed => $rolledBack()['attempted_yields'][1]['result'], 'rolled-back-conflict'],
    'rollback failing conflict action is rollback' => [static fn (): mixed => $rolledBack()['attempted_yields'][1]['conflict_action'], 'rollback'],
    'rollback savepoint scope' => [static fn (): mixed => $rolledBack()['rollback_scope'], 'savepoint'],
    'rollback reason is unique conflict rollback' => [static fn (): mixed => $rolledBack()['rollback_reason'], 'unique-conflict-rollback'],
    'rollback changes reset' => [static fn (): mixed => $rolledBack()['changes'], 0],
    'rollback inserted rows cleared' => [static fn (): mixed => $rolledBack()['inserted'], []],
    'rollback current rows equal savepoint' => [static fn (): mixed => $rolledBack()['current_rows'], $savepointRows],
    'rollback savepoint preserved true' => [static fn (): mixed => $rolledBack()['savepoint_preserved'], true],
    'rollback discarded first inserted row' => [static fn (): mixed => array_column($rolledBack()['discarded'], 'option_name'), ['plugin_seed']],
    'rollback final next rowid returns savepoint next' => [static fn (): mixed => $rolledBack()['next_rowid'], 3],
    'rollback attempted rowids include failed candidate' => [static fn (): mixed => array_column($rolledBack()['attempted_yields'], 'current_rowid'), [10, 11]],

    'ignore keeps seed only' => [static fn (): mixed => array_column($ignored()['rows'], 'option_name'), ['siteurl', 'preflight_marker', 'plugin_seed']],
    'ignore returning rows only changed seed' => [static fn (): mixed => array_column($ignored()['returning_rows'], 'name'), ['plugin_seed']],
    'ignore records ignored attempted yield' => [static fn (): mixed => $ignored()['attempted_yields'][1]['status'], 'ignored'],
    'ignore ignored returning is null' => [static fn (): mixed => $ignored()['attempted_yields'][1]['returning'], null],
    'ignore changes count one inserted row' => [static fn (): mixed => $ignored()['changes'], 1],
    'ignore final next rowid advances past seed' => [static fn (): mixed => $ignored()['next_rowid'], 11],

    'replace rewrites conflicting savepoint row through recursive replacement' => [static fn (): mixed => $replaced()['rows'][1]['option_id'], 12],
    'replace records replace event' => [static fn (): mixed => $replaced()['attempted_yields'][1]['event'], 'replace'],
    'replace returning includes repeated recursive replacement names' => [static fn (): mixed => array_column($replaced()['returning_rows'], 'name'), ['plugin_seed', 'preflight_marker', 'preflight_marker']],
    'replace changes count seed and recursive replacements' => [static fn (): mixed => $replaced()['changes'], 3],
    'replace savepoint no longer preserved' => [static fn (): mixed => $replaced()['savepoint_preserved'], false],

    'recursive triggers disabled permits first trigger child only' => [static fn (): mixed => array_column($disabled()['returning_rows'], 'name'), ['plugin_seed', 'plugin_seed:child']],
    'recursive triggers disabled records option' => [static fn (): mixed => $disabled()['recursive_triggers'], false],
    'recursive triggers disabled next rowid' => [static fn (): mixed => $disabled()['next_rowid'], 12],

    'star projection returns complete row' => [static fn (): mixed => $run(null, null, 'abort', ['*'])['returning_rows'][0]['*']['option_name'], 'plugin_seed'],
    'new column projection without alias works' => [static fn (): mixed => $run(null, null, 'abort', [['expr' => 'new.autoload', 'as' => 'autoload_value']])['returning_rows'][0]['autoload_value'], 'yes'],
    'missing returning column throws' => [static fn (): mixed => $run(null, null, 'abort', ['missing']), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => $run(null, null, 'abort', [['expr' => 'option_id', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'bad savepoint throws through recursive savepoint plan' => [static fn (): mixed => SQLiteRecursiveTriggerReturningSavepointPlan::insertRows('', $savepointRows, $inputRows, $trigger, ['option_name']), InvalidArgumentException::class],
    'bad trigger operator throws through recursive trigger plan' => [static function () use ($run, $trigger): mixed {
        $trigger[0]['when']['operator'] = 'like';
        return $run(null, $trigger);
    }, InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning recursive savepoint current next50 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
