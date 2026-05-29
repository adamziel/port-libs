<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan;

$triggerSourceComparison = [[
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
$savepointSourceComparison = [['option_id' => 1, 'option_name' => 'siteurl', 'depth' => 0, 'autoload' => 'yes']];
$currentSourceRows = [['option_id' => 10, 'option_name' => 'current_plugin', 'depth' => 1, 'autoload' => 'yes']];
$nextSourceRows = [['option_id' => 20, 'option_name' => 'next_plugin', 'depth' => 1, 'autoload' => 'no']];
$returningSourceComparison = [
    'new.option_id',
    ['expr' => 'option_name', 'as' => 'name'],
    'depth',
    'autoload',
    static fn (array $row, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $row['option_name'],
];
$sourceComparisonPlan = static fn (array $options = [], ?array $currentRows = null, ?array $nextRows = null, ?array $returning = null): array => SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeSourceComparison(
    $savepointSourceComparison,
    $currentRows ?? $currentSourceRows,
    $nextRows ?? $nextSourceRows,
    $triggerSourceComparison,
    ['option_name'],
    $returning ?? $returningSourceComparison,
    array_merge([
        'savepoint' => 'wp_recursive_options_import',
        'current_source' => 'main@trigger-source-comparison-current',
        'next_source' => 'main@trigger-source-comparison-next',
    ], $options),
);
$defaultSourceComparison = static fn (): array => $sourceComparisonPlan();
$releasedSourceComparison = static fn (): array => $sourceComparisonPlan(['rollback_current' => false]);
$nextRollbackSourceComparison = static fn (): array => $sourceComparisonPlan(['rollback_current' => false, 'rollback_next' => true]);
$bothRollbackSourceComparison = static fn (): array => $sourceComparisonPlan(['rollback_next' => true]);
$nonRecursiveSourceComparison = static fn (): array => $sourceComparisonPlan(['recursive_triggers' => false]);

$sourceComparisonCases = [
    'status rolls current then admits next' => [static fn (): mixed => $defaultSourceComparison()['status'], 'trigger-recursive-returning-savepoint-current-source-current-rolled-back-next-admitted'],
    'savepoint retained' => [static fn (): mixed => $defaultSourceComparison()['savepoint'], 'wp_recursive_options_import'],
    'current source retained' => [static fn (): mixed => $defaultSourceComparison()['current_source'], 'main@trigger-source-comparison-current'],
    'next source retained' => [static fn (): mixed => $defaultSourceComparison()['next_source'], 'main@trigger-source-comparison-next'],
    'current rollback flag default true' => [static fn (): mixed => $defaultSourceComparison()['rollback_current'], true],
    'next rollback flag default false' => [static fn (): mixed => $defaultSourceComparison()['rollback_next'], false],
    'savepoint image preserved' => [static fn (): mixed => array_column($defaultSourceComparison()['savepoint_rows'], 'option_name'), ['siteurl']],
    'current statement recursively inserts current rows' => [static fn (): mixed => array_column($defaultSourceComparison()['current']['after_statement'], 'option_name'), ['siteurl', 'current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'current savepoint image restores baseline' => [static fn (): mixed => array_column($defaultSourceComparison()['current']['after_savepoint'], 'option_name'), ['siteurl']],
    'next starts from savepoint image after current rollback' => [static fn (): mixed => array_column($defaultSourceComparison()['next_base_rows'], 'option_name'), ['siteurl']],
    'next statement recursively inserts next rows' => [static fn (): mixed => array_column($defaultSourceComparison()['next']['after_statement'], 'option_name'), ['siteurl', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'final rows admit next recursive chain only' => [static fn (): mixed => array_column($defaultSourceComparison()['final_rows'], 'option_name'), ['siteurl', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'returning rows expose next names' => [static fn (): mixed => array_column($defaultSourceComparison()['returning_rows'], 'name'), ['next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'returning rows expose next ids' => [static fn (): mixed => array_column($defaultSourceComparison()['returning_rows'], 'option_id'), [20, 21, 22]],
    'returning rows expose next depths' => [static fn (): mixed => array_column($defaultSourceComparison()['returning_rows'], 'depth'), [1, 2, 3]],
    'returning rows expose next autoload' => [static fn (): mixed => array_column($defaultSourceComparison()['returning_rows'], 'autoload'), ['no', 'no', 'no']],
    'returning callable carries next statement depths' => [static fn (): mixed => array_column($defaultSourceComparison()['returning_rows'], 'expr4'), ['0:0:next_plugin', '1:1:next_plugin:child', '2:2:next_plugin:child:child']],
    'suppressed rows expose current names' => [static fn (): mixed => array_column($defaultSourceComparison()['suppressed_returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'attempted stream combines current then next' => [static fn (): mixed => array_column($defaultSourceComparison()['attempted_returning_stream'], 'phase'), ['current', 'current', 'current', 'next', 'next', 'next']],
    'attempted stream admission suppresses current' => [static fn (): mixed => array_column($defaultSourceComparison()['attempted_returning_stream'], 'admitted'), [false, false, false, true, true, true]],
    'attempted stream ordinals reset per source' => [static fn (): mixed => array_column($defaultSourceComparison()['attempted_returning_stream'], 'source_ordinal'), [0, 1, 2, 0, 1, 2]],
    'current stream source retained' => [static fn (): mixed => array_column($defaultSourceComparison()['current_stream'], 'source'), ['main@trigger-source-comparison-current', 'main@trigger-source-comparison-current', 'main@trigger-source-comparison-current']],
    'next stream source retained' => [static fn (): mixed => array_column($defaultSourceComparison()['next_stream'], 'source'), ['main@trigger-source-comparison-next', 'main@trigger-source-comparison-next', 'main@trigger-source-comparison-next']],
    'current stream rows marked rolled back' => [static fn (): mixed => array_column($defaultSourceComparison()['current_stream'], 'rolled_back_after_yield'), [true, true, true]],
    'next stream rows not rolled back' => [static fn (): mixed => array_column($defaultSourceComparison()['next_stream'], 'rolled_back_after_yield'), [false, false, false]],
    'source transition starts next from savepoint' => [static fn (): mixed => $defaultSourceComparison()['source_transition']['next_started_from'], 'savepoint-current-source'],
    'source transition suppresses current returning' => [static fn (): mixed => $defaultSourceComparison()['source_transition']['current_returning_visibility'], 'suppressed-after-rollback-to'],
    'source transition admits next returning' => [static fn (): mixed => $defaultSourceComparison()['source_transition']['next_returning_visibility'], 'admitted'],
    'visible source is next' => [static fn (): mixed => $defaultSourceComparison()['source_transition']['visible_source'], 'main@trigger-source-comparison-next'],
    'changes track attempted current' => [static fn (): mixed => $defaultSourceComparison()['changes_before_rollback']['current'], 3],
    'changes track attempted next' => [static fn (): mixed => $defaultSourceComparison()['changes_before_rollback']['next'], 3],
    'changes track admitted next only' => [static fn (): mixed => $defaultSourceComparison()['changes_before_rollback']['admitted'], 3],
    'discarded count suppresses current rows' => [static fn (): mixed => $defaultSourceComparison()['discarded_returning_count'], 3],
    'dependency closure marker' => [static fn (): mixed => $defaultSourceComparison()['dependency_closure'], 'no-new-support-component-reuses-native-recursive-trigger-returning-savepoint-current-source'],
    'dependencies include stable source marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-current-source', $defaultSourceComparison()['dependencies'], true), true],
    'dependencies include source comparison marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-source-comparison', $defaultSourceComparison()['dependencies'], true), true],

    'release status admits both sources' => [static fn (): mixed => $releasedSourceComparison()['status'], 'trigger-recursive-returning-savepoint-current-source-both-admitted'],
    'release next starts from current output' => [static fn (): mixed => $releasedSourceComparison()['source_transition']['next_started_from'], 'current-statement-output'],
    'release final rows include current and next chains' => [static fn (): mixed => array_column($releasedSourceComparison()['final_rows'], 'option_name'), ['siteurl', 'current_plugin', 'current_plugin:child', 'current_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'release returning rows include both chains' => [static fn (): mixed => array_column($releasedSourceComparison()['returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'release suppressed rows empty' => [static fn (): mixed => $releasedSourceComparison()['suppressed_returning_rows'], []],
    'release discarded count zero' => [static fn (): mixed => $releasedSourceComparison()['discarded_returning_count'], 0],
    'release admitted changes include both sources' => [static fn (): mixed => $releasedSourceComparison()['changes_before_rollback']['admitted'], 6],

    'next rollback status admits current only' => [static fn (): mixed => $nextRollbackSourceComparison()['status'], 'trigger-recursive-returning-savepoint-current-source-current-admitted-next-rolled-back'],
    'next rollback visible source marked rolled back' => [static fn (): mixed => $nextRollbackSourceComparison()['source_transition']['visible_source'], 'main@trigger-source-comparison-next:rolled-back'],
    'next rollback returning rows are current' => [static fn (): mixed => array_column($nextRollbackSourceComparison()['returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'next rollback suppressed rows are next' => [static fn (): mixed => array_column($nextRollbackSourceComparison()['suppressed_returning_rows'], 'name'), ['next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'next rollback final rows keep current chain' => [static fn (): mixed => array_column($nextRollbackSourceComparison()['final_rows'], 'option_name'), ['siteurl', 'current_plugin', 'current_plugin:child', 'current_plugin:child:child']],
    'next rollback discarded count is next rows' => [static fn (): mixed => $nextRollbackSourceComparison()['discarded_returning_count'], 3],

    'both rollback status suppresses both sources' => [static fn (): mixed => $bothRollbackSourceComparison()['status'], 'trigger-recursive-returning-savepoint-current-source-both-rolled-back'],
    'both rollback returning rows empty' => [static fn (): mixed => $bothRollbackSourceComparison()['returning_rows'], []],
    'both rollback suppressed rows contain both chains' => [static fn (): mixed => array_column($bothRollbackSourceComparison()['suppressed_returning_rows'], 'name'), ['current_plugin', 'current_plugin:child', 'current_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'both rollback final rows restore savepoint image' => [static fn (): mixed => array_column($bothRollbackSourceComparison()['final_rows'], 'option_name'), ['siteurl']],
    'both rollback discarded count includes both sources' => [static fn (): mixed => $bothRollbackSourceComparison()['discarded_returning_count'], 6],

    'non recursive returning admits seed and one child' => [static fn (): mixed => array_column($nonRecursiveSourceComparison()['returning_rows'], 'name'), ['next_plugin', 'next_plugin:child']],
    'non recursive suppressed current seed and child' => [static fn (): mixed => array_column($nonRecursiveSourceComparison()['suppressed_returning_rows'], 'name'), ['current_plugin', 'current_plugin:child']],
    'non recursive final rows include one child' => [static fn (): mixed => array_column($nonRecursiveSourceComparison()['final_rows'], 'option_name'), ['siteurl', 'next_plugin', 'next_plugin:child']],
    'custom max depth propagates to next statement' => [static fn (): mixed => $sourceComparisonPlan(['max_depth' => 2])['next']['max_depth'], 2],
    'wildcard returning admits next autoload' => [static fn (): mixed => array_column($sourceComparisonPlan([], null, null, ['*'])['returning_rows'], 'autoload'), ['no', 'no', 'no']],
    'ignore conflict suppresses duplicate recursive row returning' => [static function () use ($triggerSourceComparison, $savepointSourceComparison, $currentSourceRows, $nextSourceRows, $returningSourceComparison): mixed {
        $trigger = $triggerSourceComparison;
        $trigger[0]['insert_row']['option_name'] = 'next_plugin';
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeSourceComparison($savepointSourceComparison, $currentSourceRows, $nextSourceRows, $trigger, ['option_name'], $returningSourceComparison, ['conflict_action' => 'ignore'])['returning_rows'], 'name');
    }, ['next_plugin']],
    'ignore conflict records ignored next row' => [static function () use ($triggerSourceComparison, $savepointSourceComparison, $currentSourceRows, $nextSourceRows, $returningSourceComparison): mixed {
        $trigger = $triggerSourceComparison;
        $trigger[0]['insert_row']['option_name'] = 'next_plugin';
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::executeSourceComparison($savepointSourceComparison, $currentSourceRows, $nextSourceRows, $trigger, ['option_name'], $returningSourceComparison, ['conflict_action' => 'ignore'])['next']['ignored_before_rollback'], 'option_name');
    }, ['next_plugin']],
    'bad savepoint rejected' => [static fn (): mixed => $sourceComparisonPlan(['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'bad current source rejected' => [static fn (): mixed => $sourceComparisonPlan(['current_source' => 'bad source']), InvalidArgumentException::class],
    'bad next source rejected' => [static fn (): mixed => $sourceComparisonPlan(['next_source' => 'bad source']), InvalidArgumentException::class],
    'empty current rows rejected' => [static fn (): mixed => $sourceComparisonPlan([], []), InvalidArgumentException::class],
    'empty next rows rejected' => [static fn (): mixed => $sourceComparisonPlan([], null, []), InvalidArgumentException::class],
    'empty returning rejected' => [static fn (): mixed => $sourceComparisonPlan([], null, null, []), InvalidArgumentException::class],
    'bad returning column rejected' => [static fn (): mixed => $sourceComparisonPlan([], null, null, ['missing']), InvalidArgumentException::class],
];

foreach ($sourceComparisonCases as $name => [$callback, $expected]) {
    $tests['trigger recursive returning savepoint current source source comparison ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
