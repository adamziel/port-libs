<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;

$parents119 = [
    ['record_id' => 10, 'record_title' => 'Imported parent', 'slug' => 'parent'],
    ['record_id' => 20, 'record_title' => 'Imported child', 'slug' => 'child'],
    ['record_id' => 30, 'record_title' => 'Imported leaf', 'slug' => 'leaf'],
];
$children119 = [
    ['detail_id' => 1, 'record_id' => 10, 'detail_key' => '_source'],
    ['detail_id' => 2, 'record_id' => 20, 'detail_key' => '_source'],
    ['detail_id' => 3, 'record_id' => 30, 'detail_key' => '_source'],
];
$foreignKey119 = ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'cascade', 'deferred' => true];
$returning119 = [
    'old.record_id',
    ['expr' => 'new.record_id', 'as' => 'new_record_id'],
    ['expr' => 'new.record_title', 'as' => 'title'],
    static fn (array $new, array $old, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $old['record_id'] . '>' . $new['record_id'],
];
$triggers119 = [
    [
        'name' => 'app_items_au_enqueue_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.record_id', '=', 10],
        'match' => 20,
        'set' => ['record_id' => 120, 'record_title' => 'Recursive child'],
        'values' => ['old_id' => 'old.record_id', 'new_id' => 'new.record_id'],
    ],
    [
        'name' => 'app_items_au_enqueue_leaf',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.record_id', '=', 20],
        'match' => 30,
        'set' => ['record_id' => 130, 'record_title' => 'Recursive leaf'],
        'values' => ['old_id' => 'old.record_id', 'new_id' => 'new.record_id'],
    ],
    [
        'name' => 'app_items_au_orphan_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'when' => ['old.record_id', '=', 20],
        'row' => ['detail_id' => 99, 'record_id' => 20, 'detail_key' => '_old_child_audit'],
        'values' => ['audit_id' => 20],
    ],
];
$updates119 = [['match' => 10, 'set' => ['record_id' => 110, 'record_title' => 'Rekeyed parent']]];
$plan119 = static fn (array $options = []) => SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::updateParentsWithinSavepoint(
    $parents119,
    $children119,
    $updates119,
    $foreignKey119,
    $triggers119,
    $returning119,
    array_merge([
        'savepoint' => 'app_import_batch',
        'current_source' => 'current-trigger-savepoint',
        'next_source' => 'next-after-rollback-to',
    ], $options),
);
$rolled119 = static fn (): array => $plan119();
$released119 = static fn (): array => $plan119(['rollback_to' => false]);
$nonRecursive119 = static fn (): array => $plan119(['recursive_triggers' => false]);

$cases119 = [
    'savepoint name is preserved' => [static fn (): mixed => $rolled119()['savepoint'], 'app_import_batch'],
    'rolled back flag is true by default' => [static fn (): mixed => $rolled119()['rolled_back'], true],
    'current source is recorded' => [static fn (): mixed => $rolled119()['current_source'], 'current-trigger-savepoint'],
    'next source is recorded' => [static fn (): mixed => $rolled119()['next_source'], 'next-after-rollback-to'],
    'returning rows are yielded before rollback' => [static fn (): mixed => count($rolled119()['returning_rows']), 3],
    'returning old ids survive rollback evidence' => [static fn (): mixed => array_column($rolled119()['returning_rows'], 'record_id'), [10, 20, 30]],
    'returning new ids survive rollback evidence' => [static fn (): mixed => array_column($rolled119()['returning_rows'], 'new_record_id'), [110, 120, 130]],
    'returning titles survive rollback evidence' => [static fn (): mixed => array_column($rolled119()['returning_rows'], 'title'), ['Rekeyed parent', 'Recursive child', 'Recursive leaf']],
    'returning callable traces preserve statement order' => [static fn (): mixed => array_column($rolled119()['returning_rows'], 'expr3'), ['0:0:10>110', '1:1:20>120', '2:2:30>130']],
    'yielded rows keep statement indexes' => [static fn (): mixed => array_column($rolled119()['yielded'], 'statement'), [0, 1, 2]],
    'yielded rows keep recursive depths' => [static fn (): mixed => array_column($rolled119()['yielded'], 'depth'), [0, 1, 2]],
    'yielded rows are marked rolled back' => [static fn (): mixed => array_column($rolled119()['yielded'], 'rolled_back_after_yield'), [true, true, true]],
    'yielded rows carry savepoint name' => [static fn (): mixed => array_column($rolled119()['yielded'], 'savepoint'), ['app_import_batch', 'app_import_batch', 'app_import_batch']],
    'after statement parent ids reflect recursive updates' => [static fn (): mixed => array_column($rolled119()['after_statement']['parent'], 'record_id'), [110, 120, 130]],
    'after statement child ids reflect cascades and audit orphan' => [static fn (): mixed => array_column($rolled119()['after_statement']['child'], 'record_id'), [110, 120, 130, 20]],
    'after statement reports deferred violation' => [static fn (): mixed => count($rolled119()['after_statement']['deferred_violations']), 1],
    'after statement commit status is blocked' => [static fn (): mixed => $rolled119()['after_statement']['commit_status'], 'deferred-constraint-failed'],
    'after savepoint restores parent ids' => [static fn (): mixed => array_column($rolled119()['after_savepoint']['parent'], 'record_id'), [10, 20, 30]],
    'after savepoint restores parent titles' => [static fn (): mixed => array_column($rolled119()['after_savepoint']['parent'], 'record_title'), ['Imported parent', 'Imported child', 'Imported leaf']],
    'after savepoint restores child ids' => [static fn (): mixed => array_column($rolled119()['after_savepoint']['child'], 'record_id'), [10, 20, 30]],
    'after savepoint removes trigger inserted audit child' => [static fn (): mixed => array_column($rolled119()['after_savepoint']['child'], 'detail_id'), [1, 2, 3]],
    'after savepoint clears deferred violations' => [static fn (): mixed => $rolled119()['after_savepoint']['deferred_violations'], []],
    'after savepoint commit status is ok' => [static fn (): mixed => $rolled119()['after_savepoint']['commit_status'], 'ok-after-rollback-to-savepoint'],
    'before parent image remains original' => [static fn (): mixed => array_column($rolled119()['before']['parent'], 'record_id'), [10, 20, 30]],
    'before child image remains original' => [static fn (): mixed => array_column($rolled119()['before']['child'], 'record_id'), [10, 20, 30]],
    'deferred queue before rollback records violation' => [static fn (): mixed => $rolled119()['deferred_before_rollback'][0]['child_key'], 20],
    'discarded returning count records yielded rows' => [static fn (): mixed => $rolled119()['discarded_returning_count'], 3],
    'restored parent keys are original' => [static fn (): mixed => $rolled119()['restored_parent_keys'], [10, 20, 30]],
    'restored child keys are original' => [static fn (): mixed => $rolled119()['restored_child_keys'], [10, 20, 30]],
    'trigger effects are retained as before rollback evidence' => [static fn (): mixed => array_column($rolled119()['trigger_effects_before_rollback'], 'trigger'), ['app_items_au_enqueue_child', 'app_items_au_enqueue_leaf', 'app_items_au_orphan_audit']],
    'foreign key actions are retained as before rollback evidence' => [static fn (): mixed => array_column($rolled119()['foreign_key_actions_before_rollback'], 'to'), [110, 120, 130]],
    'dependencies include original recursive marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-fk-returning-recursive-current-source-next114', $rolled119()['dependencies'], true), true],
    'dependencies include savepoint marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-returning-savepoint', $rolled119()['dependencies'], true), true],
    'dependencies include returning rollback ordering marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-rollback-to-savepoint', $rolled119()['dependencies'], true), true],
    'dependencies include deferred queue clear marker' => [static fn (): mixed => in_array('sqlite-deferred-fk-queue-cleared-by-savepoint-rollback', $rolled119()['dependencies'], true), true],
    'release path does not mark rolled back' => [static fn (): mixed => $released119()['rolled_back'], false],
    'release path keeps updated parent ids' => [static fn (): mixed => array_column($released119()['after_savepoint']['parent'], 'record_id'), [110, 120, 130]],
    'release path keeps cascaded child ids' => [static fn (): mixed => array_column($released119()['after_savepoint']['child'], 'record_id'), [110, 120, 130, 20]],
    'release path keeps deferred violations' => [static fn (): mixed => count($released119()['after_savepoint']['deferred_violations']), 1],
    'release path keeps blocked commit status' => [static fn (): mixed => $released119()['after_savepoint']['commit_status'], 'deferred-constraint-failed'],
    'release path does not discard returning rows' => [static fn (): mixed => $released119()['discarded_returning_count'], 0],
    'release path yielded rows are not rollback marked' => [static fn (): mixed => array_column($released119()['yielded'], 'rolled_back_after_yield'), [false, false, false]],
    'non recursive savepoint yields one returning row' => [static fn (): mixed => count($nonRecursive119()['returning_rows']), 1],
    'non recursive savepoint statement updates only first parent' => [static fn (): mixed => array_column($nonRecursive119()['after_statement']['parent'], 'record_id'), [110, 20, 30]],
    'non recursive savepoint restores all parents' => [static fn (): mixed => array_column($nonRecursive119()['after_savepoint']['parent'], 'record_id'), [10, 20, 30]],
    'non recursive savepoint clears deferred violations' => [static fn (): mixed => $nonRecursive119()['after_savepoint']['deferred_violations'], []],
    'non recursive savepoint discards one returning row' => [static fn (): mixed => $nonRecursive119()['discarded_returning_count'], 1],
    'custom savepoint is accepted' => [static fn (): mixed => $plan119(['savepoint' => 'app_batch_two'])['savepoint'], 'app_batch_two'],
    'bad savepoint name throws' => [static fn (): mixed => $plan119(['savepoint' => 'bad-name']), InvalidArgumentException::class],
    'bad parent key throws' => [static fn (): mixed => SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::updateParentsWithinSavepoint($parents119, $children119, $updates119, ['parent_key' => 'bad-key', 'child_key' => 'record_id']), InvalidArgumentException::class],
    'missing child key throws from restored key collection' => [static function () use ($parents119, $updates119, $foreignKey119): mixed {
        return SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::updateParentsWithinSavepoint($parents119, [['detail_id' => 1]], $updates119, $foreignKey119);
    }, InvalidArgumentException::class],
];

foreach ($cases119 as $name => [$callback, $expected]) {
    $tests['trigger deferred returning savepoint ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
