<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan;

$trigger147 = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'depth', 'operator' => '<', 'value' => 3],
    'insert_row' => [
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'depth' => 'new_increment.depth',
        'autoload' => 'new.autoload',
    ],
]];
$savepoint147 = [['option_id' => 1, 'option_name' => 'siteurl', 'depth' => 0, 'autoload' => 'yes']];
$current147 = [['option_id' => 10, 'option_name' => 'current_plugin', 'depth' => 1, 'autoload' => 'yes']];
$next147 = [['option_id' => 20, 'option_name' => 'next_plugin', 'depth' => 1, 'autoload' => 'no']];
$returning147 = [
    'new.option_id',
    ['expr' => 'option_name', 'as' => 'name'],
    'depth',
    'autoload',
    static fn (array $row, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $row['option_name'],
];
$plan147 = static fn (array $options = [], ?array $currentRows = null, ?array $nextRows = null, ?array $returning = null): array => SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeNext147(
    $savepoint147,
    $currentRows ?? $current147,
    $nextRows ?? $next147,
    $trigger147,
    ['option_name'],
    $returning ?? $returning147,
    array_merge([
        'savepoint' => 'wp_recursive_options_import',
        'current_source' => 'main@trigger147-current',
        'next_source' => 'main@trigger147-next',
    ], $options),
);
$default147 = static fn (): array => $plan147();
$released147 = static fn (): array => $plan147(['rollback_current' => false]);
$nextRollback147 = static fn (): array => $plan147(['rollback_current' => false, 'rollback_next' => true]);
$bothRollback147 = static fn (): array => $plan147(['rollback_next' => true]);
$nonRecursive147 = static fn (): array => $plan147(['recursive_triggers' => false]);

$cases147 = [
    'status rolls current then admits next' => [static fn (): mixed => $default147()['status'], 'trigger-recursive-returning-savepoint-current-source-next147-current-rolled-back-next-admitted'],
    'savepoint retained' => [static fn (): mixed => $default147()['savepoint'], 'wp_recursive_options_import'],
    'current source retained' => [static fn (): mixed => $default147()['current_source'], 'main@trigger147-current'],
    'next source retained' => [static fn (): mixed => $default147()['next_source'], 'main@trigger147-next'],
    'current rollback flag default true' => [static fn (): mixed => $default147()['rollback_current'], true],
    'next rollback flag default false' => [static fn (): mixed => $default147()['rollback_next'], false],
    'savepoint image preserved' => [static fn (): mixed => array_column($default147()['savepoint_rows'], 'option_name'), ['siteurl']],
    'current statement recursively inserts current rows' => [static fn (): mixed => array_column($default147()['current']['after_statement'], 'option_name'), ['siteurl', 'current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'current savepoint image restores baseline' => [static fn (): mixed => array_column($default147()['current']['after_savepoint'], 'option_name'), ['siteurl']],
    'next starts from savepoint image after current rollback' => [static fn (): mixed => array_column($default147()['next_base_rows'], 'option_name'), ['siteurl']],
    'next statement recursively inserts next rows' => [static fn (): mixed => array_column($default147()['next']['after_statement'], 'option_name'), ['siteurl', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'final rows admit next recursive chain only' => [static fn (): mixed => array_column($default147()['final_rows'], 'option_name'), ['siteurl', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'returning rows expose next names' => [static fn (): mixed => array_column($default147()['returning_rows'], 'name'), ['next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'returning rows expose next ids' => [static fn (): mixed => array_column($default147()['returning_rows'], 'option_id'), [20, 21, 22]],
    'returning rows expose next depths' => [static fn (): mixed => array_column($default147()['returning_rows'], 'depth'), [1, 2, 3]],
    'returning rows expose next autoload' => [static fn (): mixed => array_column($default147()['returning_rows'], 'autoload'), ['no', 'no', 'no']],
    'returning callable carries next statement depths' => [static fn (): mixed => array_column($default147()['returning_rows'], 'expr4'), ['0:0:next_plugin', '1:1:next_plugin:child', '2:2:next_plugin:child:child']],
    'suppressed rows expose current names' => [static fn (): mixed => array_column($default147()['suppressed_returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'attempted stream combines current then next' => [static fn (): mixed => array_column($default147()['attempted_returning_stream'], 'phase'), ['current', 'current', 'current', 'next', 'next', 'next']],
    'attempted stream admission suppresses current' => [static fn (): mixed => array_column($default147()['attempted_returning_stream'], 'admitted'), [false, false, false, true, true, true]],
    'attempted stream ordinals reset per source' => [static fn (): mixed => array_column($default147()['attempted_returning_stream'], 'source_ordinal'), [0, 1, 2, 0, 1, 2]],
    'current stream source retained' => [static fn (): mixed => array_column($default147()['current_stream'], 'source'), ['main@trigger147-current', 'main@trigger147-current', 'main@trigger147-current']],
    'next stream source retained' => [static fn (): mixed => array_column($default147()['next_stream'], 'source'), ['main@trigger147-next', 'main@trigger147-next', 'main@trigger147-next']],
    'current stream rows marked rolled back' => [static fn (): mixed => array_column($default147()['current_stream'], 'rolled_back_after_yield'), [true, true, true]],
    'next stream rows not rolled back' => [static fn (): mixed => array_column($default147()['next_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'source transition starts next from savepoint' => [static fn (): mixed => $default147()['source_transition']['next_started_from'], 'savepoint-current-source'],
    'source transition suppresses current returning' => [static fn (): mixed => $default147()['source_transition']['current_returning_visibility'], 'suppressed-after-rollback-to'],
    'source transition admits next returning' => [static fn (): mixed => $default147()['source_transition']['next_returning_visibility'], 'admitted'],
    'visible source is next' => [static fn (): mixed => $default147()['source_transition']['visible_source'], 'main@trigger147-next'],
    'changes track attempted current' => [static fn (): mixed => $default147()['changes_before_rollback']['current'], 3],
    'changes track attempted next' => [static fn (): mixed => $default147()['changes_before_rollback']['next'], 3],
    'changes track admitted next only' => [static fn (): mixed => $default147()['changes_before_rollback']['admitted'], 3],
    'discarded count suppresses current rows' => [static fn (): mixed => $default147()['discarded_returning_count'], 3],
    'dependency closure marker' => [static fn (): mixed => $default147()['dependency_closure'], 'no-new-support-component-reuses-native-recursive-trigger-returning-savepoint-current-source'],
    'dependencies include next139' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-current-source-next139', $default147()['dependencies'], true), true],
    'dependencies include next147' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-current-source-next147', $default147()['dependencies'], true), true],

    'release status admits both sources' => [static fn (): mixed => $released147()['status'], 'trigger-recursive-returning-savepoint-current-source-next147-both-admitted'],
    'release next starts from current output' => [static fn (): mixed => $released147()['source_transition']['next_started_from'], 'current-statement-output'],
    'release final rows include current and next chains' => [static fn (): mixed => array_column($released147()['final_rows'], 'option_name'), ['siteurl', 'current_plugin', 'current_plugin:child', 'current_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'release returning rows include both chains' => [static fn (): mixed => array_column($released147()['returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'release suppressed rows empty' => [static fn (): mixed => $released147()['suppressed_returning_rows'], []],
    'release discarded count zero' => [static fn (): mixed => $released147()['discarded_returning_count'], 0],
    'release admitted changes include both sources' => [static fn (): mixed => $released147()['changes_before_rollback']['admitted'], 6],

    'next rollback status admits current only' => [static fn (): mixed => $nextRollback147()['status'], 'trigger-recursive-returning-savepoint-current-source-next147-current-admitted-next-rolled-back'],
    'next rollback visible source marked rolled back' => [static fn (): mixed => $nextRollback147()['source_transition']['visible_source'], 'main@trigger147-next:rolled-back'],
    'next rollback returning rows are current' => [static fn (): mixed => array_column($nextRollback147()['returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'next rollback suppressed rows are next' => [static fn (): mixed => array_column($nextRollback147()['suppressed_returning_rows'], 'name'), ['next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'next rollback final rows keep current chain' => [static fn (): mixed => array_column($nextRollback147()['final_rows'], 'option_name'), ['siteurl', 'current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'next rollback discarded count is next rows' => [static fn (): mixed => $nextRollback147()['discarded_returning_count'], 3],

    'both rollback status suppresses both sources' => [static fn (): mixed => $bothRollback147()['status'], 'trigger-recursive-returning-savepoint-current-source-next147-both-rolled-back'],
    'both rollback returning rows empty' => [static fn (): mixed => $bothRollback147()['returning_rows'], []],
    'both rollback suppressed rows contain both chains' => [static fn (): mixed => array_column($bothRollback147()['suppressed_returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'both rollback final rows restore savepoint image' => [static fn (): mixed => array_column($bothRollback147()['final_rows'], 'option_name'), ['siteurl']],
    'both rollback discarded count includes both sources' => [static fn (): mixed => $bothRollback147()['discarded_returning_count'], 6],

    'non recursive returning admits seed and one child' => [static fn (): mixed => array_column($nonRecursive147()['returning_rows'], 'name'), ['next_plugin', 'next_plugin:child']],
    'non recursive suppressed current seed and child' => [static fn (): mixed => array_column($nonRecursive147()['suppressed_returning_rows'], 'name'), ['current_plugin', 'current_plugin:child']],
    'non recursive final rows include one child' => [static fn (): mixed => array_column($nonRecursive147()['final_rows'], 'option_name'), ['siteurl', 'next_plugin', 'next_plugin:child']],
    'custom max depth propagates to next statement' => [static fn (): mixed => $plan147(['max_depth' => 2])['next']['max_depth'], 2],
    'wildcard returning admits next autoload' => [static fn (): mixed => array_column($plan147([], null, null, ['*'])['returning_rows'], 'autoload'), ['no', 'no', 'no']],
    'ignore conflict suppresses duplicate recursive row returning' => [static function () use ($trigger147, $savepoint147, $current147, $next147, $returning147): mixed {
        $trigger = $trigger147;
        $trigger[0]['insert_row']['option_name'] = 'next_plugin';
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeNext147($savepoint147, $current147, $next147, $trigger, ['option_name'], $returning147, ['conflict_action' => 'ignore'])['returning_rows'], 'name');
    }, ['next_plugin']],
    'ignore conflict records ignored next row' => [static function () use ($trigger147, $savepoint147, $current147, $next147, $returning147): mixed {
        $trigger = $trigger147;
        $trigger[0]['insert_row']['option_name'] = 'next_plugin';
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeNext147($savepoint147, $current147, $next147, $trigger, ['option_name'], $returning147, ['conflict_action' => 'ignore'])['next']['ignored_before_rollback'], 'option_name');
    }, ['next_plugin']],
    'bad savepoint rejected' => [static fn (): mixed => $plan147(['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'bad current source rejected' => [static fn (): mixed => $plan147(['current_source' => 'bad source']), InvalidArgumentException::class],
    'bad next source rejected' => [static fn (): mixed => $plan147(['next_source' => 'bad source']), InvalidArgumentException::class],
    'empty current rows rejected' => [static fn (): mixed => $plan147([], []), InvalidArgumentException::class],
    'empty next rows rejected' => [static fn (): mixed => $plan147([], null, []), InvalidArgumentException::class],
    'empty returning rejected' => [static fn (): mixed => $plan147([], null, null, []), InvalidArgumentException::class],
    'bad returning column rejected' => [static fn (): mixed => $plan147([], null, null, ['missing']), InvalidArgumentException::class],
];

foreach ($cases147 as $name => [$callback, $expected]) {
    $tests['trigger recursive returning savepoint current source next147 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
