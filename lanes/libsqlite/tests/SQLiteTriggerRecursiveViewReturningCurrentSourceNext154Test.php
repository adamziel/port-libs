<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$trigger154 = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'depth', 'operator' => '<', 'value' => 2],
    'insert_row' => [
        'setting_id' => 'new_increment.setting_id',
        'key_name' => 'concat:new.key_name::child',
        'depth' => 'new_increment.depth',
        'load_policy' => 'new.load_policy',
    ],
]];
$initial154 = [['setting_id' => 1, 'key_name' => 'base_url', 'depth' => 0, 'load_policy' => 'yes']];
$current154 = [['setting_id' => 10, 'key_name' => 'current_module', 'depth' => 0, 'load_policy' => 'yes']];
$next154 = [['setting_id' => 20, 'key_name' => 'next_module', 'depth' => 0, 'load_policy' => 'no']];
$returning154 = [
    'new.setting_id',
    ['expr' => 'key_name', 'as' => 'name'],
    'depth',
    static fn (array $row, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $row['key_name'],
];

$plan154 = static fn (array $options = []): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceDrainBeforeNextYield(
    $initial154,
    $current154,
    $next154,
    $trigger154,
    ['key_name'],
    $returning154,
    $options + [
        'view' => 'app_recursive_setting_view',
        'savepoint' => 'app_recursive_view_next154',
        'current_source' => 'main@trigger154-current',
        'next_source' => 'main@trigger154-next',
        'current_cursor' => 'app_current_returning_cursor_154',
        'next_cursor' => 'app_next_returning_cursor_154',
    ],
);
$blocked154 = static fn (): array => $plan154(['acknowledged_current_rows' => 1]);
$released154 = static fn (): array => $plan154(['acknowledged_current_rows' => 3]);
$overAck154 = static fn (): array => $plan154(['acknowledged_current_rows' => 99]);

$cases154 = [
    'blocked status' => [static fn (): mixed => $blocked154()['status'], 'trigger-recursive-view-returning-current-source-drain-before-next-yield'],
    'blocked view retained' => [static fn (): mixed => $blocked154()['view'], 'app_recursive_setting_view'],
    'blocked savepoint retained' => [static fn (): mixed => $blocked154()['savepoint'], 'app_recursive_view_next154'],
    'blocked current source retained' => [static fn (): mixed => $blocked154()['current_source'], 'main@trigger154-current'],
    'blocked next source retained' => [static fn (): mixed => $blocked154()['next_source'], 'main@trigger154-next'],
    'blocked current cursor retained' => [static fn (): mixed => $blocked154()['current_cursor'], 'app_current_returning_cursor_154'],
    'blocked next cursor retained' => [static fn (): mixed => $blocked154()['next_cursor'], 'app_next_returning_cursor_154'],
    'blocked current required count' => [static fn (): mixed => $blocked154()['current_returning_required'], 3],
    'blocked current acknowledged count' => [static fn (): mixed => $blocked154()['current_returning_acknowledged'], 1],
    'blocked current remaining count' => [static fn (): mixed => $blocked154()['current_returning_remaining'], 2],
    'blocked current not done' => [static fn (): mixed => $blocked154()['current_source_done'], false],
    'blocked next yield flag' => [static fn (): mixed => $blocked154()['next_yield_blocked'], true],
    'blocked next yield blocker' => [static fn (): mixed => $blocked154()['next_yield_blocker'], 'current-returning-source-not-drained'],
    'blocked yield boundary' => [static fn (): mixed => $blocked154()['yield_boundary'], 'current-recursive-view-returning-must-drain-before-next-yield'],
    'blocked current returning names' => [static fn (): mixed => array_column($blocked154()['current_returning_rows'], 'name'), ['current_module', 'current_module:child', 'current_module:child:child']],
    'blocked next returning rows still staged' => [static fn (): mixed => array_column($blocked154()['blocked_next_returning_rows'], 'name'), ['next_module', 'next_module:child', 'next_module:child:child']],
    'blocked visible next empty' => [static fn (): mixed => $blocked154()['visible_next_returning_rows'], []],
    'blocked admitted rows still preserve current then next' => [static fn (): mixed => array_column($blocked154()['returning_rows'], 'name'), ['current_module', 'current_module:child', 'current_module:child:child', 'next_module', 'next_module:child', 'next_module:child:child']],
    'blocked final rows include both written phases' => [static fn (): mixed => array_column($blocked154()['final_rows'], 'key_name'), ['base_url', 'current_module', 'current_module:child', 'current_module:child:child', 'next_module', 'next_module:child', 'next_module:child:child']],
    'blocked current stream admitted' => [static fn (): mixed => array_column($blocked154()['current_source_stream'], 'admitted'), [true, true, true]],
    'blocked next stream admitted by storage plan' => [static fn (): mixed => array_column($blocked154()['next_source_stream'], 'admitted'), [true, true, true]],
    'blocked cursor handoff from current' => [static fn (): mixed => $blocked154()['cursor_handoff']['from'], 'app_current_returning_cursor_154'],
    'blocked cursor handoff to next' => [static fn (): mixed => $blocked154()['cursor_handoff']['to'], 'app_next_returning_cursor_154'],
    'blocked cursor required rows' => [static fn (): mixed => $blocked154()['cursor_handoff']['required_current_rows'], 3],
    'blocked cursor acknowledged rows' => [static fn (): mixed => $blocked154()['cursor_handoff']['acknowledged_current_rows'], 1],
    'blocked cursor blocked next rows' => [static fn (): mixed => $blocked154()['cursor_handoff']['blocked_next_rows'], 3],
    'blocked cursor visible next rows' => [static fn (): mixed => $blocked154()['cursor_handoff']['visible_next_rows'], 0],
    'blocked dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next154', $blocked154()['dependencies'], true), true],
    'blocked drain dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-drain-before-next-yield', $blocked154()['dependencies'], true), true],
    'blocked dependency closure' => [static fn (): mixed => $blocked154()['dependency_closure'], 'reuses-native-recursive-trigger-returning-current-source-drain-and-cursor-handoff-model'],

    'released status' => [static fn (): mixed => $released154()['status'], 'trigger-recursive-view-returning-current-source-drained-next-yield-visible'],
    'released current acknowledged' => [static fn (): mixed => $released154()['current_returning_acknowledged'], 3],
    'released current remaining zero' => [static fn (): mixed => $released154()['current_returning_remaining'], 0],
    'released current done' => [static fn (): mixed => $released154()['current_source_done'], true],
    'released next yield flag' => [static fn (): mixed => $released154()['next_yield_blocked'], false],
    'released blocker null' => [static fn (): mixed => $released154()['next_yield_blocker'], null],
    'released boundary' => [static fn (): mixed => $released154()['yield_boundary'], 'current-recursive-view-returning-drained-before-next-yield-visible'],
    'released blocked next empty' => [static fn (): mixed => $released154()['blocked_next_returning_rows'], []],
    'released visible next names' => [static fn (): mixed => array_column($released154()['visible_next_returning_rows'], 'name'), ['next_module', 'next_module:child', 'next_module:child:child']],
    'released visible next ids' => [static fn (): mixed => array_column($released154()['visible_next_returning_rows'], 'setting_id'), [20, 21, 22]],
    'released visible next depths' => [static fn (): mixed => array_column($released154()['visible_next_returning_rows'], 'depth'), [0, 1, 2]],
    'released callable trace' => [static fn (): mixed => array_column($released154()['visible_next_returning_rows'], 'expr3'), ['0:0:next_module', '1:1:next_module:child', '2:2:next_module:child:child']],
    'released cursor blocked next zero' => [static fn (): mixed => $released154()['cursor_handoff']['blocked_next_rows'], 0],
    'released cursor visible next count' => [static fn (): mixed => $released154()['cursor_handoff']['visible_next_rows'], 3],
    'released source transition current output' => [static fn (): mixed => $released154()['source_transition']['next_input'], 'current-phase-output'],
    'released visible source next' => [static fn (): mixed => $released154()['source_transition']['visible_source'], 'main@trigger154-next'],

    'over ack clamps acknowledged count' => [static fn (): mixed => $overAck154()['current_returning_acknowledged'], 3],
    'over ack keeps done true' => [static fn (): mixed => $overAck154()['current_source_done'], true],
    'zero ack blocks all next rows' => [static fn (): mixed => $plan154(['acknowledged_current_rows' => 0])['cursor_handoff']['blocked_next_rows'], 3],
    'negative ack normalizes to zero' => [static fn (): mixed => $plan154(['acknowledged_current_rows' => -5])['current_returning_acknowledged'], 0],
    'custom cursors accepted' => [static fn (): mixed => $plan154(['current_cursor' => 'custom_current_cursor', 'next_cursor' => 'custom_next_cursor'])['cursor_handoff']['to'], 'custom_next_cursor'],
    'bad current cursor rejected' => [static fn (): mixed => $plan154(['current_cursor' => 'bad cursor']), InvalidArgumentException::class],
    'bad next cursor rejected' => [static fn (): mixed => $plan154(['next_cursor' => 'bad cursor']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases154 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next154 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
