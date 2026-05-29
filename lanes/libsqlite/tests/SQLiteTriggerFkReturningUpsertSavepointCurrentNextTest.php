<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'level' => 0, 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'level' => 1, 'autoload' => 'no'],
];
$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'plugin'],
];
$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];
$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$returning = [
    'option_id',
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value_after'],
    ['expr' => 'yield.event', 'as' => 'event_name'],
    ['expr' => 'yield.depth', 'as' => 'trigger_depth'],
];
$orphanTriggers = [
    [
        'name' => 'wp_options_after_insert_orphan_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 999, 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_after_insert_recursive_upsert',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 2],
        'row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name:child',
            'option_value' => 'concat:new.option_value:child',
            'level' => 'new_increment.level',
            'autoload' => 'new.autoload',
        ],
        'values' => ['name' => 'new.option_name', 'level' => 'new.level'],
    ],
];
$validTriggers = [
    [
        'name' => 'wp_options_after_insert_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];
$currentRows = [
    ['option_id' => 30, 'option_name' => 'broken_plugin', 'option_value' => 'broken', 'level' => 1, 'autoload' => 'no'],
];
$nextRows = [
    ['option_id' => 40, 'option_name' => 'fixed_plugin', 'option_value' => 'fixed', 'level' => 1, 'autoload' => 'yes'],
];

$run = static fn (array $current = null, array $next = null, array $currentTriggers = null, array $nextTriggers = null, array $currentOptions = [], array $projection = null): array => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute(
    'wp_import',
    $parents,
    $children,
    $current ?? $currentRows,
    $next ?? $nextRows,
    ['option_name'],
    $assignments,
    $foreignKey,
    $currentTriggers ?? $orphanTriggers,
    $nextTriggers ?? $validTriggers,
    $projection ?? $returning,
    $currentOptions
);

$rollback = static fn (): array => $run();
$commit = static fn (): array => $run(null, null, $validTriggers, $validTriggers);
$noRollback = static fn (): array => $run(null, null, $orphanTriggers, $validTriggers, ['rollback_on_deferred_violation' => false]);
$updateRollback = static fn (): array => $run([
    ['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'level' => 1, 'autoload' => 'yes'],
], null, [[
    'name' => 'wp_options_after_update_orphan_meta',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'insert-child',
    'row' => ['meta_id' => 'new.option_id', 'option_id' => 999, 'meta_key' => 'changed', 'meta_value' => 'new.option_value'],
    'values' => ['name' => 'new.option_name'],
]], $validTriggers);
$star = static fn (): array => $run(null, null, $validTriggers, $validTriggers, [], ['*']);

$cases = [
    'rollback status marks returned then rolled back' => [static fn (): mixed => $rollback()['status'], 'current-returned-then-rolled-back-next-applied'],
    'rollback release status is deferred fk failed' => [static fn (): mixed => $rollback()['release_status'], 'deferred-foreign-key-failed'],
    'rollback preserves savepoint parents before next' => [static fn (): mixed => array_column($rollback()['parent'], 'option_name'), ['siteurl', 'plugin_seed']],
    'rollback preserves savepoint children before next' => [static fn (): mixed => array_column($rollback()['child'], 'option_id'), [1, 2]],
    'rollback current visible parent includes recursive attempted row' => [static fn (): mixed => array_column($rollback()['current_visible_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'broken_plugin', 'broken_plugin:child']],
    'rollback current visible child includes orphan trigger rows' => [static fn (): mixed => array_column($rollback()['current_visible_child'], 'option_id'), [1, 2, 999, 999]],
    'rollback returning rows are emitted before release failure' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'option_name'), ['broken_plugin:child', 'broken_plugin']],
    'rollback returning rows preserve depth first order' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'trigger_depth'), [1, 0]],
    'rollback returning event names are insert' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'event_name'), ['insert', 'insert']],
    'rollback returning projects option values' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'value_after'), ['broken:child', 'broken']],
    'rollback attempted yields remain diagnostic evidence' => [static fn (): mixed => array_column($rollback()['attempted_current_yields'], 'option_name'), ['broken_plugin:child', 'broken_plugin']],
    'rollback records repeated deferred fk release diagnostics' => [static fn (): mixed => count($rollback()['current_fk_violations']), 5],
    'rollback violation phases include statement and after trigger' => [static fn (): mixed => array_values(array_unique(array_column($rollback()['current_fk_violations'], 'phase'))), ['statement', 'after-trigger']],
    'rollback marks current rolled back on release' => [static fn (): mixed => $rollback()['current_rolled_back_on_release'], true],
    'rollback reason names deferred release' => [static fn (): mixed => $rollback()['rollback_reason'], 'deferred-foreign-key-release'],
    'rollback current committed changes are cleared' => [static fn (): mixed => $rollback()['current_changes'], 0],
    'rollback total attempted changes keeps current plus next' => [static fn (): mixed => $rollback()['total_attempted_changes'], 3],
    'rollback committed changes include next only' => [static fn (): mixed => $rollback()['committed_changes'], 1],
    'rollback next starts from savepoint image' => [static fn (): mixed => $rollback()['next_started_from_savepoint'], true],
    'rollback savepoint preserved after release' => [static fn (): mixed => $rollback()['savepoint_preserved_after_release'], true],
    'rollback discarded current parent names' => [static fn (): mixed => array_column($rollback()['discarded_current_parent'], 'option_name'), ['broken_plugin', 'broken_plugin:child']],
    'rollback discarded current child keys' => [static fn (): mixed => array_column($rollback()['discarded_current_child'], 'option_id'), [999, 999]],
    'rollback next parent applies retry row' => [static fn (): mixed => array_column($rollback()['next_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'fixed_plugin']],
    'rollback next child applies retry metadata' => [static fn (): mixed => array_column($rollback()['next_child'], 'option_id'), [1, 2, 40]],
    'rollback next returning projects fixed row' => [static fn (): mixed => $rollback()['next_returning_rows'][0]['option_name'], 'fixed_plugin'],
    'rollback next has no fk violations' => [static fn (): mixed => $rollback()['next_fk_violations'], []],
    'rollback current trigger effects include orphan trigger' => [static fn (): mixed => $rollback()['current_trigger_effects'][0]['trigger'], 'wp_options_after_insert_orphan_meta'],
    'rollback dependencies name current next75' => [static fn (): mixed => in_array('sqlite-savepoint-current-next75', $rollback()['dependencies'], true), true],

    'commit status marks released next applied' => [static fn (): mixed => $commit()['status'], 'current-released-next-applied'],
    'commit release status is released' => [static fn (): mixed => $commit()['release_status'], 'released'],
    'commit current is not rolled back' => [static fn (): mixed => $commit()['current_rolled_back_on_release'], false],
    'commit parent keeps current and next rows' => [static fn (): mixed => array_column($commit()['next_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'broken_plugin', 'fixed_plugin']],
    'commit child keys are all valid' => [static fn (): mixed => array_column($commit()['next_child'], 'option_id'), [1, 2, 30, 40]],
    'commit current changes are retained' => [static fn (): mixed => $commit()['current_changes'], 1],
    'commit committed changes include current and next' => [static fn (): mixed => $commit()['committed_changes'], 2],
    'commit does not start next from original savepoint' => [static fn (): mixed => $commit()['next_started_from_savepoint'], false],
    'commit rollback reason is null' => [static fn (): mixed => $commit()['rollback_reason'], null],

    'no rollback preserves current violation rows' => [static fn (): mixed => $noRollback()['current_rolled_back_on_release'], false],
    'no rollback status still released next applied' => [static fn (): mixed => $noRollback()['status'], 'current-released-next-applied'],
    'no rollback next parent includes broken recursive row' => [static fn (): mixed => array_column($noRollback()['next_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'broken_plugin', 'broken_plugin:child', 'fixed_plugin']],
    'no rollback committed changes include current recursive and next' => [static fn (): mixed => $noRollback()['committed_changes'], 3],

    'update rollback returning reports update event' => [static fn (): mixed => $updateRollback()['current_returning_rows'][0]['event_name'], 'update'],
    'update rollback returning reports old conflict row depth zero' => [static fn (): mixed => $updateRollback()['current_returning_rows'][0]['trigger_depth'], 0],
    'update rollback restores original parent key' => [static fn (): mixed => $updateRollback()['parent'][1]['option_id'], 2],
    'update rollback visible parent has rekeyed seed' => [static fn (): mixed => $updateRollback()['current_visible_parent'][1]['option_id'], 20],
    'update rollback discarded parent includes rekeyed seed' => [static fn (): mixed => array_column($updateRollback()['discarded_current_parent'], 'option_id'), [20]],

    'star returning projects complete row' => [static fn (): mixed => $star()['current_returning_rows'][0]['*']['option_name'], 'broken_plugin'],
    'callable returning term is evaluated' => [static fn (): mixed => $run(null, null, $validTriggers, $validTriggers, [], [static fn (array $row, array $yield): string => $row['option_name'] . ':' . $yield['event']])['current_returning_rows'][0]['expr0'], 'broken_plugin:insert'],
    'bad savepoint throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('bad-name', $parents, $children, $currentRows, $nextRows, ['option_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, $returning), InvalidArgumentException::class],
    'empty current rows throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('wp_import', $parents, $children, [], $nextRows, ['option_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, $returning), InvalidArgumentException::class],
    'empty next rows throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('wp_import', $parents, $children, $currentRows, [], ['option_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, $returning), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('wp_import', $parents, $children, $currentRows, $nextRows, ['option_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, []), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => $run(null, null, $validTriggers, $validTriggers, [], [['expr' => 'option_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'missing returning column throws' => [static fn (): mixed => $run(null, null, $validTriggers, $validTriggers, [], ['missing_column']), InvalidArgumentException::class],
    'missing yield column throws' => [static fn (): mixed => $run(null, null, $validTriggers, $validTriggers, [], [['expr' => 'yield.missing', 'as' => 'missing_yield']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger fk returning upsert savepoint current next75 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
