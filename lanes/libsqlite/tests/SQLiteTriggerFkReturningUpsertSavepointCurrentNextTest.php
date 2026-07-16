<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan;

$parents = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.test', 'level' => 0, 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'plugin_seed', 'key_value' => 'seed-old', 'level' => 1, 'load_policy' => 'no'],
];
$children = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'plugin'],
];
$assignments = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
];
$foreignKey = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true];
$returning = [
    'setting_id',
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value_after'],
    ['expr' => 'yield.event', 'as' => 'event_name'],
    ['expr' => 'yield.depth', 'as' => 'trigger_depth'],
];
$orphanTriggers = [
    [
        'name' => 'app_settings_after_insert_orphan_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 999, 'meta_key' => 'load_policy', 'meta_value' => 'new.load_policy'],
        'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_after_insert_recursive_upsert',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 2],
        'row' => [
            'setting_id' => 'new_increment.setting_id',
            'key_name' => 'concat:new.key_name:child',
            'key_value' => 'concat:new.key_value:child',
            'level' => 'new_increment.level',
            'load_policy' => 'new.load_policy',
        ],
        'values' => ['name' => 'new.key_name', 'level' => 'new.level'],
    ],
];
$validTriggers = [
    [
        'name' => 'app_settings_after_insert_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'meta_key' => 'load_policy', 'meta_value' => 'new.load_policy'],
        'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
    ],
];
$currentRows = [
    ['setting_id' => 30, 'key_name' => 'broken_plugin', 'key_value' => 'broken', 'level' => 1, 'load_policy' => 'no'],
];
$nextRows = [
    ['setting_id' => 40, 'key_name' => 'fixed_plugin', 'key_value' => 'fixed', 'level' => 1, 'load_policy' => 'yes'],
];

$run = static fn (array $current = null, array $next = null, array $currentTriggers = null, array $nextTriggers = null, array $currentOptions = [], array $projection = null): array => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute(
    'app_import',
    $parents,
    $children,
    $current ?? $currentRows,
    $next ?? $nextRows,
    ['key_name'],
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
    ['setting_id' => 20, 'key_name' => 'plugin_seed', 'key_value' => 'seed-new', 'level' => 1, 'load_policy' => 'yes'],
], null, [[
    'name' => 'app_settings_after_update_orphan_meta',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'insert-child',
    'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 999, 'meta_key' => 'changed', 'meta_value' => 'new.key_value'],
    'values' => ['name' => 'new.key_name'],
]], $validTriggers);
$star = static fn (): array => $run(null, null, $validTriggers, $validTriggers, [], ['*']);

$cases = [
    'rollback status marks returned then rolled back' => [static fn (): mixed => $rollback()['status'], 'current-returned-then-rolled-back-next-applied'],
    'rollback release status is deferred fk failed' => [static fn (): mixed => $rollback()['release_status'], 'deferred-foreign-key-failed'],
    'rollback preserves savepoint parents before next' => [static fn (): mixed => array_column($rollback()['parent'], 'key_name'), ['siteurl', 'plugin_seed']],
    'rollback preserves savepoint children before next' => [static fn (): mixed => array_column($rollback()['child'], 'setting_id'), [1, 2]],
    'rollback current visible parent includes recursive attempted row' => [static fn (): mixed => array_column($rollback()['current_visible_parent'], 'key_name'), ['siteurl', 'plugin_seed', 'broken_plugin', 'broken_plugin:child']],
    'rollback current visible child includes orphan trigger rows' => [static fn (): mixed => array_column($rollback()['current_visible_child'], 'setting_id'), [1, 2, 999, 999]],
    'rollback returning rows are emitted before release failure' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'key_name'), ['broken_plugin:child', 'broken_plugin']],
    'rollback returning rows preserve depth first order' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'trigger_depth'), [1, 0]],
    'rollback returning event names are insert' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'event_name'), ['insert', 'insert']],
    'rollback returning projects option values' => [static fn (): mixed => array_column($rollback()['current_returning_rows'], 'value_after'), ['broken:child', 'broken']],
    'rollback attempted yields remain diagnostic evidence' => [static fn (): mixed => array_column($rollback()['attempted_current_yields'], 'key_name'), ['broken_plugin:child', 'broken_plugin']],
    'rollback records repeated deferred fk release diagnostics' => [static fn (): mixed => count($rollback()['current_fk_violations']), 5],
    'rollback violation phases include statement and after trigger' => [static fn (): mixed => array_values(array_unique(array_column($rollback()['current_fk_violations'], 'phase'))), ['statement', 'after-trigger']],
    'rollback marks current rolled back on release' => [static fn (): mixed => $rollback()['current_rolled_back_on_release'], true],
    'rollback reason names deferred release' => [static fn (): mixed => $rollback()['rollback_reason'], 'deferred-foreign-key-release'],
    'rollback current committed changes are cleared' => [static fn (): mixed => $rollback()['current_changes'], 0],
    'rollback total attempted changes keeps current plus next' => [static fn (): mixed => $rollback()['total_attempted_changes'], 3],
    'rollback committed changes include next only' => [static fn (): mixed => $rollback()['committed_changes'], 1],
    'rollback next starts from savepoint image' => [static fn (): mixed => $rollback()['next_started_from_savepoint'], true],
    'rollback savepoint preserved after release' => [static fn (): mixed => $rollback()['savepoint_preserved_after_release'], true],
    'rollback discarded current parent names' => [static fn (): mixed => array_column($rollback()['discarded_current_parent'], 'key_name'), ['broken_plugin', 'broken_plugin:child']],
    'rollback discarded current child keys' => [static fn (): mixed => array_column($rollback()['discarded_current_child'], 'setting_id'), [999, 999]],
    'rollback next parent applies retry row' => [static fn (): mixed => array_column($rollback()['next_parent'], 'key_name'), ['siteurl', 'plugin_seed', 'fixed_plugin']],
    'rollback next child applies retry metadata' => [static fn (): mixed => array_column($rollback()['next_child'], 'setting_id'), [1, 2, 40]],
    'rollback next returning projects fixed row' => [static fn (): mixed => $rollback()['next_returning_rows'][0]['key_name'], 'fixed_plugin'],
    'rollback next has no fk violations' => [static fn (): mixed => $rollback()['next_fk_violations'], []],
    'rollback current trigger effects include orphan trigger' => [static fn (): mixed => $rollback()['current_trigger_effects'][0]['trigger'], 'app_settings_after_insert_orphan_meta'],
    'rollback dependencies name current next75' => [static fn (): mixed => in_array('sqlite-savepoint-current-next75', $rollback()['dependencies'], true), true],

    'commit status marks released next applied' => [static fn (): mixed => $commit()['status'], 'current-released-next-applied'],
    'commit release status is released' => [static fn (): mixed => $commit()['release_status'], 'released'],
    'commit current is not rolled back' => [static fn (): mixed => $commit()['current_rolled_back_on_release'], false],
    'commit parent keeps current and next rows' => [static fn (): mixed => array_column($commit()['next_parent'], 'key_name'), ['siteurl', 'plugin_seed', 'broken_plugin', 'fixed_plugin']],
    'commit child keys are all valid' => [static fn (): mixed => array_column($commit()['next_child'], 'setting_id'), [1, 2, 30, 40]],
    'commit current changes are retained' => [static fn (): mixed => $commit()['current_changes'], 1],
    'commit committed changes include current and next' => [static fn (): mixed => $commit()['committed_changes'], 2],
    'commit does not start next from original savepoint' => [static fn (): mixed => $commit()['next_started_from_savepoint'], false],
    'commit rollback reason is null' => [static fn (): mixed => $commit()['rollback_reason'], null],

    'no rollback preserves current violation rows' => [static fn (): mixed => $noRollback()['current_rolled_back_on_release'], false],
    'no rollback status still released next applied' => [static fn (): mixed => $noRollback()['status'], 'current-released-next-applied'],
    'no rollback next parent includes broken recursive row' => [static fn (): mixed => array_column($noRollback()['next_parent'], 'key_name'), ['siteurl', 'plugin_seed', 'broken_plugin', 'broken_plugin:child', 'fixed_plugin']],
    'no rollback committed changes include current recursive and next' => [static fn (): mixed => $noRollback()['committed_changes'], 3],

    'update rollback returning reports update event' => [static fn (): mixed => $updateRollback()['current_returning_rows'][0]['event_name'], 'update'],
    'update rollback returning reports old conflict row depth zero' => [static fn (): mixed => $updateRollback()['current_returning_rows'][0]['trigger_depth'], 0],
    'update rollback restores original parent key' => [static fn (): mixed => $updateRollback()['parent'][1]['setting_id'], 2],
    'update rollback visible parent has rekeyed seed' => [static fn (): mixed => $updateRollback()['current_visible_parent'][1]['setting_id'], 20],
    'update rollback discarded parent includes rekeyed seed' => [static fn (): mixed => array_column($updateRollback()['discarded_current_parent'], 'setting_id'), [20]],

    'star returning projects complete row' => [static fn (): mixed => $star()['current_returning_rows'][0]['*']['key_name'], 'broken_plugin'],
    'callable returning term is evaluated' => [static fn (): mixed => $run(null, null, $validTriggers, $validTriggers, [], [static fn (array $row, array $yield): string => $row['key_name'] . ':' . $yield['event']])['current_returning_rows'][0]['expr0'], 'broken_plugin:insert'],
    'bad savepoint throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('bad-name', $parents, $children, $currentRows, $nextRows, ['key_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, $returning), InvalidArgumentException::class],
    'empty current rows throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('app_import', $parents, $children, [], $nextRows, ['key_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, $returning), InvalidArgumentException::class],
    'empty next rows throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('app_import', $parents, $children, $currentRows, [], ['key_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, $returning), InvalidArgumentException::class],
    'empty returning throws' => [static fn (): mixed => SQLiteTriggerFkReturningUpsertSavepointCurrentNextPlan::execute('app_import', $parents, $children, $currentRows, $nextRows, ['key_name'], $assignments, $foreignKey, $orphanTriggers, $validTriggers, []), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => $run(null, null, $validTriggers, $validTriggers, [], [['expr' => 'key_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
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
