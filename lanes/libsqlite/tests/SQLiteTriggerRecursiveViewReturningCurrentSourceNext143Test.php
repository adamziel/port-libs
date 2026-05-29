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
        'option_id' => 'new_increment.option_id',
        'option_name' => 'concat:new.option_name::child',
        'depth' => 'new_increment.depth',
        'autoload' => 'new.autoload',
    ],
]];
$initial143 = [['option_id' => 10, 'option_name' => 'siteurl', 'depth' => 0, 'autoload' => 'yes']];
$current143 = [['option_id' => 20, 'option_name' => 'plugin_current', 'depth' => 1, 'autoload' => 'yes']];
$next143 = [['option_id' => 30, 'option_name' => 'plugin_next', 'depth' => 1, 'autoload' => 'no']];
$returning143 = [
    'new.option_id',
    ['expr' => 'option_name', 'as' => 'name'],
    'depth',
    'autoload',
    static fn (array $row, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $row['option_name'],
];
$plan143 = static fn (array $options = [], array $currentRows = null, array $nextRows = null, array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSourcesNext143(
    $initial143,
    $currentRows ?? $current143,
    $nextRows ?? $next143,
    $trigger143,
    ['option_name'],
    $returning ?? $returning143,
    array_merge([
        'view' => 'wp_option_import_view',
        'savepoint' => 'wp_import_next143',
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
    'view name retained' => [static fn (): mixed => $rolled143()['view'], 'wp_option_import_view'],
    'savepoint retained' => [static fn (): mixed => $rolled143()['savepoint'], 'wp_import_next143'],
    'default status admits next after current rollback' => [static fn (): mixed => $rolled143()['status'], 'current-recursive-view-rollback-next-source-admitted'],
    'current source token retained' => [static fn (): mixed => $rolled143()['current_source'], 'main@trigger143-current'],
    'next source token retained' => [static fn (): mixed => $rolled143()['next_source'], 'main@trigger143-next'],
    'source transition uses saved current image' => [static fn (): mixed => $rolled143()['source_transition']['next_input'], 'saved-current-source'],
    'visible source is next on default path' => [static fn (): mixed => $rolled143()['source_transition']['visible_source'], 'main@trigger143-next'],
    'current source not admitted by default' => [static fn (): mixed => $rolled143()['current_source_admitted'], false],
    'next source admitted by default' => [static fn (): mixed => $rolled143()['next_source_admitted'], true],
    'before rows preserve siteurl' => [static fn (): mixed => array_column($rolled143()['before'], 'option_name'), ['siteurl']],
    'current statement recursively inserts current rows' => [static fn (): mixed => array_column($rolled143()['current']['after_statement'], 'option_name'), ['siteurl', 'plugin_current', 'plugin_current:child', 'plugin_current:child:child']],
    'current savepoint restores baseline' => [static fn (): mixed => array_column($rolled143()['current']['after_savepoint'], 'option_name'), ['siteurl']],
    'next statement starts from saved source' => [static fn (): mixed => array_column($rolled143()['next']['before'], 'option_name'), ['siteurl']],
    'next statement recursively inserts next rows' => [static fn (): mixed => array_column($rolled143()['next']['after_statement'], 'option_name'), ['siteurl', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'final rows contain baseline and next recursive rows' => [static fn (): mixed => array_column($rolled143()['final_rows'], 'option_name'), ['siteurl', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'returning rows expose admitted next names only' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'name'), ['plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'returning rows expose admitted next ids' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'option_id'), [30, 31, 32]],
    'returning rows expose admitted next depths' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'depth'), [1, 2, 3]],
    'returning callable keeps admitted next statement depth' => [static fn (): mixed => array_column($rolled143()['returning_rows'], 'expr4'), ['0:0:plugin_next', '1:1:plugin_next:child', '2:2:plugin_next:child:child']],
    'suppressed rows expose current recursive names' => [static fn (): mixed => array_column($rolled143()['suppressed_returning_rows'], 'name'), ['plugin_current', 'plugin_current:child', 'plugin_current:child:child']],
    'current stream phases are current' => [static fn (): mixed => array_column($rolled143()['current_source_stream'], 'phase'), ['current', 'current', 'current']],
    'next stream phases are next' => [static fn (): mixed => array_column($rolled143()['next_source_stream'], 'phase'), ['next', 'next', 'next']],
    'current stream view names retained' => [static fn (): mixed => array_column($rolled143()['current_source_stream'], 'view'), ['wp_option_import_view', 'wp_option_import_view', 'wp_option_import_view']],
    'next stream source retained' => [static fn (): mixed => array_column($rolled143()['next_source_stream'], 'source'), ['main@trigger143-next', 'main@trigger143-next', 'main@trigger143-next']],
    'attempted stream phases combine current then next' => [static fn (): mixed => array_column($rolled143()['attempted_source_stream'], 'phase'), ['current', 'current', 'current', 'next', 'next', 'next']],
    'attempted stream admission flags suppress current' => [static fn (): mixed => array_column($rolled143()['attempted_source_stream'], 'admitted'), [false, false, false, true, true, true]],
    'attempted stream ordinals reset per source' => [static fn (): mixed => array_column($rolled143()['attempted_source_stream'], 'source_ordinal'), [0, 1, 2, 0, 1, 2]],
    'discarded returning count includes current rollback only' => [static fn (): mixed => $rolled143()['discarded_returning_count'], 3],
    'current yielded rows are rollback marked' => [static fn (): mixed => array_column($rolled143()['current_source_stream'], 'rolled_back_after_yield'), [true, true, true]],
    'next yielded rows are not rollback marked' => [static fn (): mixed => array_column($rolled143()['next_source_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'dependency closure marker' => [static fn (): mixed => $rolled143()['dependency_closure'], 'reuses-native-recursive-trigger-returning-savepoint-and-view-current-source-plans'],
    'dependencies include next136 view marker' => [static fn (): mixed => in_array('sqlite-trigger-returning-savepoint-view-current-source-next136', $rolled143()['dependencies'], true), true],
    'dependencies include next139 recursive marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-current-source-next139', $rolled143()['dependencies'], true), true],
    'dependencies include next143 marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next143', $rolled143()['dependencies'], true), true],

    'release status admits both phases' => [static fn (): mixed => $released143()['status'], 'current-next-recursive-view-returning-source-admitted'],
    'release transition uses current output' => [static fn (): mixed => $released143()['source_transition']['next_input'], 'current-phase-output'],
    'release current admitted true' => [static fn (): mixed => $released143()['current_source_admitted'], true],
    'release final rows include current and next chains' => [static fn (): mixed => array_column($released143()['final_rows'], 'option_name'), ['siteurl', 'plugin_current', 'plugin_current:child', 'plugin_current:child:child', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'release returning rows include current then next' => [static fn (): mixed => array_column($released143()['returning_rows'], 'name'), ['plugin_current', 'plugin_current:child', 'plugin_current:child:child', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'release has no suppressed rows' => [static fn (): mixed => $released143()['suppressed_returning_rows'], []],
    'release discarded returning count is zero' => [static fn (): mixed => $released143()['discarded_returning_count'], 0],

    'next rollback status keeps current only' => [static fn (): mixed => $nextRollback143()['status'], 'current-recursive-view-admitted-next-source-rolled-back'],
    'next rollback visible source marked rolled back' => [static fn (): mixed => $nextRollback143()['source_transition']['visible_source'], 'main@trigger143-next:rolled-back'],
    'next rollback returning names are current only' => [static fn (): mixed => array_column($nextRollback143()['returning_rows'], 'name'), ['plugin_current', 'plugin_current:child', 'plugin_current:child:child']],
    'next rollback suppressed names are next only' => [static fn (): mixed => array_column($nextRollback143()['suppressed_returning_rows'], 'name'), ['plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'next rollback final rows return to current output' => [static fn (): mixed => array_column($nextRollback143()['final_rows'], 'option_name'), ['siteurl', 'plugin_current', 'plugin_current:child', 'plugin_current:child:child']],
    'next rollback discarded returning count records next rows' => [static fn (): mixed => $nextRollback143()['discarded_returning_count'], 3],

    'both rollback status' => [static fn (): mixed => $bothRollback143()['status'], 'current-next-recursive-view-savepoints-rolled-back'],
    'both rollback returning rows empty' => [static fn (): mixed => $bothRollback143()['returning_rows'], []],
    'both rollback suppressed rows contain both phases' => [static fn (): mixed => array_column($bothRollback143()['suppressed_returning_rows'], 'name'), ['plugin_current', 'plugin_current:child', 'plugin_current:child:child', 'plugin_next', 'plugin_next:child', 'plugin_next:child:child']],
    'both rollback final rows restore baseline' => [static fn (): mixed => array_column($bothRollback143()['final_rows'], 'option_name'), ['siteurl']],
    'both rollback discarded returning count records both phases' => [static fn (): mixed => $bothRollback143()['discarded_returning_count'], 6],

    'non recursive returning admits seed and one child' => [static fn (): mixed => array_column($nonRecursive143()['returning_rows'], 'name'), ['plugin_next', 'plugin_next:child']],
    'non recursive current suppressed seed and one child' => [static fn (): mixed => array_column($nonRecursive143()['suppressed_returning_rows'], 'name'), ['plugin_current', 'plugin_current:child']],
    'non recursive final rows include one next child' => [static fn (): mixed => array_column($nonRecursive143()['final_rows'], 'option_name'), ['siteurl', 'plugin_next', 'plugin_next:child']],
    'non recursive flag propagates to next phase' => [static fn (): mixed => $nonRecursive143()['next']['recursive_triggers'], false],
    'custom max depth propagates' => [static fn (): mixed => $plan143(['max_depth' => 3])['next']['max_depth'], 3],
    'wildcard returning includes autoload' => [static fn (): mixed => array_column($plan143([], null, null, ['*'])['returning_rows'], 'autoload'), ['no', 'no', 'no']],
    'ignore conflict suppresses duplicate next child' => [static function () use ($trigger143, $initial143, $current143, $next143, $returning143): mixed {
        $trigger = $trigger143;
        $trigger[0]['insert_row']['option_name'] = 'plugin_next';
        return array_column(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSourcesNext143($initial143, $current143, $next143, $trigger, ['option_name'], $returning143, ['conflict_action' => 'ignore'])['returning_rows'], 'name');
    }, ['plugin_next']],
    'ignore conflict records ignored next row' => [static function () use ($trigger143, $initial143, $current143, $next143, $returning143): mixed {
        $trigger = $trigger143;
        $trigger[0]['insert_row']['option_name'] = 'plugin_next';
        return array_column(SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::insertThroughViewSourcesNext143($initial143, $current143, $next143, $trigger, ['option_name'], $returning143, ['conflict_action' => 'ignore'])['next']['ignored_before_rollback'], 'option_name');
    }, ['plugin_next']],
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
