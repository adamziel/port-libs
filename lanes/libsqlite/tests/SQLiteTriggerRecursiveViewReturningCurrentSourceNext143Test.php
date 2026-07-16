<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$trigger143 = [[
    'timing' => 'after',
    'event' => 'insert',
    'table' => 'target',
    'action' => 'insert',
    'when' => ['column' => 'depth', 'operator' => '<', 'value' => 3],
    'insert_row' => [
        'setting_id' => 'new_increment.setting_id',
        'key_name' => 'concat:new.key_name::child',
        'depth' => 'new_increment.depth',
        'load_policy' => 'new.load_policy',
    ],
]];
$initial143 = [['setting_id' => 10, 'key_name' => 'base_url', 'depth' => 0, 'load_policy' => 'yes']];
$current143 = [['setting_id' => 20, 'key_name' => 'module_current', 'depth' => 1, 'load_policy' => 'yes']];
$next143 = [['setting_id' => 30, 'key_name' => 'module_next', 'depth' => 1, 'load_policy' => 'no']];
$returning143 = [
    'new.setting_id',
    ['expr' => 'key_name', 'as' => 'name'],
    'depth',
    'load_policy',
    static fn (array $row, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $row['key_name'],
];
$plan143 = static fn (array $options = [], array $currentRows = null, array $nextRows = null, array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSources(
    $initial143,
    $currentRows ?? $current143,
    $nextRows ?? $next143,
    $trigger143,
    ['key_name'],
    $returning ?? $returning143,
    array_merge([
        'view' => 'app_setting_import_view',
        'savepoint' => 'app_import_next143',
        'current_source' => 'main@trigger143-current',
        'next_source' => 'main@trigger143-next',
    ], $options),
);
$rolled143 = static fn (): array => $plan143();
$released143 = static fn (): array => $plan143(['current_rollback_to' => false]);
$nextRollback143 = static fn (): array => $plan143(['current_rollback_to' => false, 'next_rollback_to' => true]);
$bothRollback143 = static fn (): array => $plan143(['next_rollback_to' => true]);
$nonRecursive143 = static fn (): array => $plan143(['recursive_triggers' => false]);

$cases143 = [
    'view name retained' => [static fn (): mixed => $rolled143()['view'], 'app_setting_import_view'],
    'savepoint retained' => [static fn (): mixed => $rolled143()['savepoint'], 'app_import_next143'],
    'default status admits next after current rollback' => [static fn (): mixed => $rolled143()['status'], 'current-recursive-view-rollback-next-source-admitted'],
    'current source token retained' => [static fn (): mixed => $rolled143()['current_source'], 'main@trigger143-current'],
    'next source token retained' => [static fn (): mixed => $rolled143()['next_source'], 'main@trigger143-next'],
    'source transition uses saved current image' => [static fn (): mixed => $rolled143()['source_transition']['next_input'], 'saved-current-source'],
    'visible source is next on default path' => [static fn (): mixed => $rolled143()['source_transition']['visible_source'], 'main@trigger143-next'],
    'current source not admitted by default' => [static fn (): mixed => $rolled143()['current_source_admitted'], false],
    'next source admitted by default' => [static fn (): mixed => $rolled143()['next_source_admitted'], true],
    'before rows preserve base_url' => [static fn (): mixed => array_column($rolled143()['before'], 'key_name'), ['base_url']],
    'current statement recursively inserts current rows' => [static fn (): mixed => array_column($rolled143()['current']['after_statement'], 'key_name'), ['base_url', 'module_current', 'module_current:child', 'module_current:child:child']],
    'current savepoint restores baseline' => [static fn (): mixed => array_column($rolled143()['current']['after_savepoint'], 'key_name'), ['base_url']],
    'next statement starts from saved source' => [static fn (): mixed => array_column($rolled143()['next']['before'], 'key_name'), ['base_url']],
    'next statement recursively inserts next rows' => [static fn (): mixed => array_column($rolled143()['next']['after_statement'], 'key_name'), ['base_url', 'module_next', 'module_next:child', 'module_next:child:child']],
    'final rows contain baseline and next recursive rows' => [static fn (): mixed => array_column($rolled143()['final_rows'], 'key_name'), ['base_url', 'module_next', 'module_next:child', 'module_next:child:child']],
    'returning rows expose admitted next names only' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'name'), ['module_next', 'module_next:child', 'module_next:child:child']],
    'returning rows expose admitted next ids' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'setting_id'), [30, 31, 32]],
    'returning rows expose admitted next depths' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'depth'), [1, 2, 3]],
    'returning callable keeps admitted next statement depth' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'expr4'), ['0:0:module_next', '1:1:module_next:child', '2:2:module_next:child:child']],
    'suppressed rows expose current recursive names' => [static fn (): mixed => array_column($rolled143()['suppressed_returning_rows'], 'name'), ['module_current', 'module_current:child', 'module_current:child:child']],
    'current stream phases are current' => [static fn (): mixed => array_column($rolled143()['current_source_stream'], 'phase'), ['current', 'current', 'current']],
    'next stream phases are next' => [static fn (): mixed => array_column($rolled143()['next_source_stream'], 'phase'), ['next', 'next', 'next']],
    'current stream view names retained' => [static fn (): mixed => array_column($rolled143()['current_source_stream'], 'view'), ['app_setting_import_view', 'app_setting_import_view', 'app_setting_import_view']],
    'next stream source retained' => [static fn (): mixed => array_column($rolled143()['next_source_stream'], 'source'), ['main@trigger143-next', 'main@trigger143-next', 'main@trigger143-next']],
    'attempted stream phases combine current then next' => [static fn (): mixed => array_column($rolled143()['attempted_source_stream'], 'phase'), ['current', 'current', 'current', 'next', 'next', 'next']],
    'attempted stream admission flags suppress current' => [static fn (): mixed => array_column($rolled143()['attempted_source_stream'], 'admitted'), [false, false, false, true, true, true]],
    'attempted stream ordinals reset per source' => [static fn (): mixed => array_column($rolled143()['attempted_source_stream'], 'source_ordinal'), [0, 1, 2, 0, 1, 2]],
    'discarded returning count includes current rollback only' => [static fn (): mixed => $rolled143()['discarded_returning_count'], 3],
    'current yielded rows are rollback marked' => [static fn (): mixed => array_column($rolled143()['current_source_stream'], 'rolled_back_after_yield'), [true, true, true]],
    'next yielded rows are not rollback marked' => [static fn (): mixed => array_column($rolled143()['next_source_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'dependency closure marker' => [static fn (): mixed => $rolled143()['dependency_closure'], 'reuses-native-recursive-trigger-returning-savepoint-and-view-current-source-plans'],
    'dependencies include next136 view marker' => [static fn (): mixed => in_array('sqlite-trigger-returning-savepoint-view-current-source-next136', $rolled143()['dependencies'], true), true],
    'dependencies include recursive marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-current-source', $rolled143()['dependencies'], true), true],
    'dependencies include next143 marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next143', $rolled143()['dependencies'], true), true],

    'release status admits both phases' => [static fn (): mixed => $released143()['status'], 'current-next-recursive-view-returning-source-admitted'],
    'release transition uses current output' => [static fn (): mixed => $released143()['source_transition']['next_input'], 'current-phase-output'],
    'release current admitted true' => [static fn (): mixed => $released143()['current_source_admitted'], true],
    'release final rows include current and next chains' => [static fn (): mixed => array_column($released143()['final_rows'], 'key_name'), ['base_url', 'module_current', 'module_current:child', 'module_current:child:child', 'module_next', 'module_next:child', 'module_next:child:child']],
    'release returning rows include current then next' => [static fn (): mixed => array_column($released143()['returning_rows'], 'name'), ['module_current', 'module_current:child', 'module_current:child:child', 'module_next', 'module_next:child', 'module_next:child:child']],
    'release has no suppressed rows' => [static fn (): mixed => $released143()['suppressed_returning_rows'], []],
    'release discarded returning count is zero' => [static fn (): mixed => $released143()['discarded_returning_count'], 0],

    'next rollback status keeps current only' => [static fn (): mixed => $nextRollback143()['status'], 'current-recursive-view-admitted-next-source-rolled-back'],
    'next rollback visible source marked rolled back' => [static fn (): mixed => $nextRollback143()['source_transition']['visible_source'], 'main@trigger143-next:rolled-back'],
    'next rollback returning names are current only' => [static fn (): mixed => array_column($nextRollback143()['returning_rows'], 'name'), ['module_current', 'module_current:child', 'module_current:child:child']],
    'next rollback suppressed names are next only' => [static fn (): mixed => array_column($nextRollback143()['suppressed_returning_rows'], 'name'), ['module_next', 'module_next:child', 'module_next:child:child']],
    'next rollback final rows return to current output' => [static fn (): mixed => array_column($nextRollback143()['final_rows'], 'key_name'), ['base_url', 'module_current', 'module_current:child', 'module_current:child:child']],
    'next rollback discarded returning count records next rows' => [static fn (): mixed => $nextRollback143()['discarded_returning_count'], 3],

    'both rollback status' => [static fn (): mixed => $bothRollback143()['status'], 'current-next-recursive-view-savepoints-rolled-back'],
    'both rollback returning rows empty' => [static fn (): mixed => $bothRollback143()['returning_rows'], []],
    'both rollback suppressed rows contain both phases' => [static fn (): mixed => array_column($bothRollback143()['suppressed_returning_rows'], 'name'), ['module_current', 'module_current:child', 'module_current:child:child', 'module_next', 'module_next:child', 'module_next:child:child']],
    'both rollback final rows restore baseline' => [static fn (): mixed => array_column($bothRollback143()['final_rows'], 'key_name'), ['base_url']],
    'both rollback discarded returning count records both phases' => [static fn (): mixed => $bothRollback143()['discarded_returning_count'], 6],

    'non recursive returning admits seed and one child' => [static fn (): mixed => array_column($nonRecursive143()['returning_rows'], 'name'), ['module_next', 'module_next:child']],
    'non recursive current suppressed seed and one child' => [static fn (): mixed => array_column($nonRecursive143()['suppressed_returning_rows'], 'name'), ['module_current', 'module_current:child']],
    'non recursive final rows include one next child' => [static fn (): mixed => array_column($nonRecursive143()['final_rows'], 'key_name'), ['base_url', 'module_next', 'module_next:child']],
    'non recursive flag propagates to next phase' => [static fn (): mixed => $nonRecursive143()['next']['recursive_triggers'], false],
    'custom max depth propagates' => [static fn (): mixed => $plan143(['max_depth' => 3])['next']['max_depth'], 3],
    'wildcard returning includes load_policy' => [static fn (): mixed => array_column($plan143([], null, null, ['*'])['returning_rows'], 'load_policy'), ['no', 'no', 'no']],
    'ignore conflict suppresses duplicate next child' => [static function () use ($trigger143, $initial143, $current143, $next143, $returning143): mixed {
        $trigger = $trigger143;
        $trigger[0]['insert_row']['key_name'] = 'module_next';
        return array_column(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSources($initial143, $current143, $next143, $trigger, ['key_name'], $returning143, ['conflict_action' => 'ignore'])['returning_rows'], 'name');
    }, ['module_next']],
    'ignore conflict records ignored next row' => [static function () use ($trigger143, $initial143, $current143, $next143, $returning143): mixed {
        $trigger = $trigger143;
        $trigger[0]['insert_row']['key_name'] = 'module_next';
        return array_column(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSources($initial143, $current143, $next143, $trigger, ['key_name'], $returning143, ['conflict_action' => 'ignore'])['next']['ignored_before_rollback'], 'key_name');
    }, ['module_next']],
    'bad view rejected' => [static fn (): mixed => $plan143(['view' => 'bad-view']), InvalidArgumentException::class],
    'bad savepoint rejected' => [static fn (): mixed => $plan143(['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'bad current source rejected' => [static fn (): mixed => $plan143(['current_source' => 'bad token']), InvalidArgumentException::class],
    'bad next source rejected' => [static fn (): mixed => $plan143(['next_source' => 'bad token']), InvalidArgumentException::class],
    'empty current rows rejected' => [static fn (): mixed => $plan143([], []), InvalidArgumentException::class],
    'empty next rows rejected' => [static fn (): mixed => $plan143([], null, []), InvalidArgumentException::class],
    'missing returning column rejected' => [static fn (): mixed => $plan143([], null, null, ['missing']), InvalidArgumentException::class],
];

foreach ($cases143 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next143 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
