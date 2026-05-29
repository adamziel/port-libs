<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan;

$triggerSource = [[
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
$returningSource = [
    'new.option_id',
    ['expr' => 'option_name', 'as' => 'name'],
    'level',
    static fn (array $row, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $row['option_name'],
];
$initialSourceRows = [['option_id' => 40, 'option_name' => 'existing', 'level' => 0, 'autoload' => 'no']];
$inputSourceRows = [['option_id' => 1, 'option_name' => 'plugin_seed', 'level' => 1, 'autoload' => 'yes']];
$sourcePlan = static fn (array $options = []): array => SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint(
    $initialSourceRows,
    $inputSourceRows,
    $triggerSource,
    ['option_name'],
    $returningSource,
    array_merge([
        'savepoint' => 'wp_recursive_import',
        'current_source' => 'current-trigger-recursive-returning',
        'next_source' => 'next-after-rollback-to-recursive-trigger',
    ], $options),
);
$rolledSourcePlan = static fn (): array => $sourcePlan();
$releasedSourcePlan = static fn (): array => $sourcePlan(['rollback_to' => false]);
$nonRecursiveSourcePlan = static fn (): array => $sourcePlan(['recursive_triggers' => false]);

$sourceCases = [
    'status savepoint name' => [static fn (): mixed => $rolledSourcePlan()['savepoint'], 'wp_recursive_import'],
    'rolled back flag default' => [static fn (): mixed => $rolledSourcePlan()['rolled_back'], true],
    'current source marker' => [static fn (): mixed => $rolledSourcePlan()['current_source'], 'current-trigger-recursive-returning'],
    'next source marker' => [static fn (): mixed => $rolledSourcePlan()['next_source'], 'next-after-rollback-to-recursive-trigger'],
    'before rows preserve imported baseline' => [static fn (): mixed => array_column($rolledSourcePlan()['before'], 'option_name'), ['existing']],
    'after statement includes recursive seed and children' => [static fn (): mixed => array_column($rolledSourcePlan()['after_statement'], 'option_name'), ['existing', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'after statement levels include recursive levels' => [static fn (): mixed => array_column($rolledSourcePlan()['after_statement'], 'level'), [0, 1, 2, 3, 4]],
    'after savepoint restores baseline rows' => [static fn (): mixed => array_column($rolledSourcePlan()['after_savepoint'], 'option_name'), ['existing']],
    'after savepoint restores baseline autoload' => [static fn (): mixed => array_column($rolledSourcePlan()['after_savepoint'], 'autoload'), ['no']],
    'returning captures seed and recursive children' => [static fn (): mixed => array_column($rolledSourcePlan()['returning_rows'], 'name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'returning captures option ids' => [static fn (): mixed => array_column($rolledSourcePlan()['returning_rows'], 'option_id'), [1, 2, 3, 4]],
    'returning captures levels' => [static fn (): mixed => array_column($rolledSourcePlan()['returning_rows'], 'level'), [1, 2, 3, 4]],
    'returning callable captures statement and depth' => [static fn (): mixed => array_column($rolledSourcePlan()['returning_rows'], 'expr3'), ['0:0:plugin_seed', '1:1:plugin_seed:child', '2:2:plugin_seed:child:child', '3:3:plugin_seed:child:child:child']],
    'yielded rows preserve statement order' => [static fn (): mixed => array_column($rolledSourcePlan()['yielded'], 'statement'), [0, 1, 2, 3]],
    'yielded rows carry savepoint' => [static fn (): mixed => array_column($rolledSourcePlan()['yielded'], 'savepoint'), ['wp_recursive_import', 'wp_recursive_import', 'wp_recursive_import', 'wp_recursive_import']],
    'yielded rows are marked rolled back' => [static fn (): mixed => array_column($rolledSourcePlan()['yielded'], 'rolled_back_after_yield'), [true, true, true, true]],
    'yielded row payload includes first returning name' => [static fn (): mixed => $rolledSourcePlan()['yielded'][0]['row']['name'], 'plugin_seed'],
    'inserted rows retained as before rollback evidence' => [static fn (): mixed => array_column($rolledSourcePlan()['inserted_before_rollback'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'changes before rollback count recursive inserts' => [static fn (): mixed => $rolledSourcePlan()['changes_before_rollback'], 4],
    'discarded returning count equals yielded rows' => [static fn (): mixed => $rolledSourcePlan()['discarded_returning_count'], 4],
    'restored unique keys contain only baseline' => [static fn (): mixed => $rolledSourcePlan()['restored_unique_keys'], ['existing']],
    'statement unique keys contain recursive rows' => [static fn (): mixed => $rolledSourcePlan()['statement_unique_keys'], ['existing', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'recursive triggers flag true' => [static fn (): mixed => $rolledSourcePlan()['recursive_triggers'], true],
    'default max depth preserved' => [static fn (): mixed => $rolledSourcePlan()['max_depth'], 1000],
    'trigger effects include four inserted rows' => [static fn (): mixed => count(array_filter($rolledSourcePlan()['trigger_effects_before_rollback'], static fn (array $effect): bool => ($effect['action'] ?? null) === 'insert')), 4],
    'trigger fire depths include recursive chain' => [static function () use ($rolledSourcePlan): mixed {
        $effects = array_values(array_filter($rolledSourcePlan()['trigger_effects_before_rollback'], static fn (array $effect): bool => ($effect['action'] ?? null) === 'trigger' && ($effect['result'] ?? null) === 'fired'));
        return array_column($effects, 'depth');
    }, [0, 1, 2]],
    'trigger final when skip recorded' => [static fn (): mixed => end($rolledSourcePlan()['trigger_effects_before_rollback'])['result'], 'when-skipped'],
    'dependencies include recursion corpus' => [static fn (): mixed => in_array('sqlite-dml-trigger-recursion-corpus', $rolledSourcePlan()['dependencies'], true), true],
    'dependencies include stable source marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-returning-savepoint-current-source', $rolledSourcePlan()['dependencies'], true), true],
    'dependencies include returning rollback marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-rollback-to-savepoint', $rolledSourcePlan()['dependencies'], true), true],
    'release path not rolled back' => [static fn (): mixed => $releasedSourcePlan()['rolled_back'], false],
    'release path keeps recursive rows' => [static fn (): mixed => array_column($releasedSourcePlan()['after_savepoint'], 'option_name'), ['existing', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'release path does not discard returning rows' => [static fn (): mixed => $releasedSourcePlan()['discarded_returning_count'], 0],
    'release path yielded rows not rollback marked' => [static fn (): mixed => array_column($releasedSourcePlan()['yielded'], 'rolled_back_after_yield'), [false, false, false, false]],
    'release path restored keys are statement keys' => [static fn (): mixed => $releasedSourcePlan()['restored_unique_keys'], ['existing', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'non recursive yields seed and first child' => [static fn (): mixed => array_column($nonRecursiveSourcePlan()['returning_rows'], 'name'), ['plugin_seed', 'plugin_seed:child']],
    'non recursive statement rows include one child' => [static fn (): mixed => array_column($nonRecursiveSourcePlan()['after_statement'], 'option_name'), ['existing', 'plugin_seed', 'plugin_seed:child']],
    'non recursive rollback restores baseline' => [static fn (): mixed => array_column($nonRecursiveSourcePlan()['after_savepoint'], 'option_name'), ['existing']],
    'non recursive discarded returning count' => [static fn (): mixed => $nonRecursiveSourcePlan()['discarded_returning_count'], 2],
    'non recursive reports disabled flag' => [static fn (): mixed => $nonRecursiveSourcePlan()['recursive_triggers'], false],
    'custom max depth passes through' => [static fn (): mixed => $sourcePlan(['max_depth' => 4])['max_depth'], 4],
    'wildcard returning includes option names' => [static fn (): mixed => array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $triggerSource, ['option_name'], ['*'])['returning_rows'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'wildcard returning includes autoload values' => [static fn (): mixed => array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $triggerSource, ['option_name'], ['*'])['returning_rows'], 'autoload'), ['yes', 'yes', 'yes', 'yes']],
    'wildcard returning rollback still restores baseline' => [static fn (): mixed => array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $triggerSource, ['option_name'], ['*'])['after_savepoint'], 'option_name'), ['existing']],
    'ignore conflict suppresses duplicate recursive child returning' => [static function () use ($initialSourceRows, $inputSourceRows, $triggerSource, $returningSource): mixed {
        $trigger = $triggerSource;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $trigger, ['option_name'], $returningSource, ['conflict_action' => 'ignore'])['returning_rows'], 'name');
    }, ['plugin_seed']],
    'ignore conflict records ignored before rollback' => [static function () use ($initialSourceRows, $inputSourceRows, $triggerSource, $returningSource): mixed {
        $trigger = $triggerSource;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $trigger, ['option_name'], $returningSource, ['conflict_action' => 'ignore'])['ignored_before_rollback'], 'option_name');
    }, ['plugin_seed']],
    'ignore conflict keeps one discarded returning row' => [static function () use ($initialSourceRows, $inputSourceRows, $triggerSource, $returningSource): mixed {
        $trigger = $triggerSource;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        return SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $trigger, ['option_name'], $returningSource, ['conflict_action' => 'ignore'])['discarded_returning_count'];
    }, 1],
    'replace conflict returns replacement row' => [static function () use ($initialSourceRows, $inputSourceRows, $triggerSource, $returningSource): mixed {
        $trigger = $triggerSource;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        return array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $trigger, ['option_name'], $returningSource, ['conflict_action' => 'replace'])['returning_rows'], 'level');
    }, [1, 7]],
    'replace conflict rollback restores baseline only' => [static function () use ($initialSourceRows, $inputSourceRows, $triggerSource, $returningSource): mixed {
        $trigger = $triggerSource;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        return SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $trigger, ['option_name'], $returningSource, ['conflict_action' => 'replace'])['restored_unique_keys'];
    }, ['existing']],
    'replace conflict statement keys show replacement' => [static function () use ($initialSourceRows, $inputSourceRows, $triggerSource, $returningSource): mixed {
        $trigger = $triggerSource;
        $trigger[0]['when']['value'] = 2;
        $trigger[0]['insert_row']['option_name'] = 'plugin_seed';
        $trigger[0]['insert_row']['level'] = 7;
        return SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $trigger, ['option_name'], $returningSource, ['conflict_action' => 'replace'])['statement_unique_keys'];
    }, ['existing', 'plugin_seed']],
    'release with wildcard keeps rows after savepoint' => [static fn (): mixed => array_column(SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $triggerSource, ['option_name'], ['*'], ['rollback_to' => false])['after_savepoint'], 'option_name'), ['existing', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'plugin_seed:child:child:child']],
    'bad savepoint throws' => [static fn (): mixed => $sourcePlan(['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'bad returning column throws' => [static fn (): mixed => SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $triggerSource, ['option_name'], ['new.missing']), InvalidArgumentException::class],
    'missing unique column throws' => [static fn (): mixed => SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint([['option_id' => 1]], $inputSourceRows, $triggerSource, ['option_name'], $returningSource), InvalidArgumentException::class],
    'bad unique column throws' => [static fn (): mixed => SQLiteTriggerRecursiveReturningSavepointCurrentSourceNextPlan::insertRowsWithinSavepoint($initialSourceRows, $inputSourceRows, $triggerSource, ['bad-name'], $returningSource), InvalidArgumentException::class],
    'negative max depth throws' => [static fn (): mixed => $sourcePlan(['max_depth' => -1]), InvalidArgumentException::class],
];

foreach ($sourceCases as $name => [$callback, $expected]) {
    $tests['trigger recursive returning savepoint current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
