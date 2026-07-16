<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveSavepointUpsertPlan;

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
$triggers = [
    [
        'name' => 'app_settings_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.setting_id',
        'set_child_key' => 'new.setting_id',
        'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'meta_key' => 'load_policy', 'meta_value' => 'new.load_policy'],
        'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_au_meta',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'meta_key' => 'changed', 'meta_value' => 'new.key_value'],
        'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_after_insert_recursive_upsert',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 3],
        'row' => [
            'setting_id' => 'new_increment.setting_id',
            'key_name' => 'concat:new.key_name:child',
            'key_value' => 'concat:new.key_value:child',
            'level' => 'new_increment.level',
            'load_policy' => 'new.load_policy',
        ],
        'values' => ['name' => 'new.key_name', 'level' => 'new.level'],
    ],
    [
        'name' => 'app_settings_after_update_recursive_upsert',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 3],
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
$run = static fn (array $incoming, array $triggerSet = null, array $fk = null, array $options = []) => SQLiteRecursiveSavepointUpsertPlan::execute(
    'app-setting-import',
    $parents,
    $children,
    $incoming,
    ['key_name'],
    $assignments,
    $fk ?? $foreignKey,
    $triggerSet ?? $triggers,
    $options,
);
$successful = static fn (): array => $run([
    ['setting_id' => 20, 'key_name' => 'plugin_seed', 'key_value' => 'seed-new', 'level' => 1, 'load_policy' => 'yes'],
    ['setting_id' => 30, 'key_name' => 'fresh_plugin', 'key_value' => 'fresh', 'level' => 1, 'load_policy' => 'no'],
]);
$rollbackTriggers = $triggers;
$rollbackTriggers[] = [
    'name' => 'app_settings_abort_second_child',
    'timing' => 'after',
    'event' => 'insert',
    'action' => 'raise',
    'when' => ['new.key_name', '=', 'fresh_plugin:child:child'],
    'raise' => 'rollback',
    'reason' => 'recursive-child-conflict',
    'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
];
$rolledBack = static fn (): array => $run([
    ['setting_id' => 30, 'key_name' => 'fresh_plugin', 'key_value' => 'fresh', 'level' => 1, 'load_policy' => 'no'],
], $rollbackTriggers);
$recursiveOff = static fn (): array => $run([
    ['setting_id' => 30, 'key_name' => 'fresh_plugin', 'key_value' => 'fresh', 'level' => 1, 'load_policy' => 'no'],
], null, null, ['recursive_triggers' => false]);
$immediateFkTriggers = $triggers;
$immediateFkTriggers[0]['set_child_key'] = 999;
$immediateFk = static fn (): array => $run([
    ['setting_id' => 20, 'key_name' => 'plugin_seed', 'key_value' => 'seed-new', 'level' => 1, 'load_policy' => 'yes'],
], $immediateFkTriggers, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => false]);

$cases = [
    'successful savepoint name is preserved' => [static fn (): mixed => $successful()['savepoint'], 'app-setting-import'],
    'successful does not roll back' => [static fn (): mixed => $successful()['rolled_back'], false],
    'successful rollback scope is none' => [static fn (): mixed => $successful()['rollback_scope'], 'none'],
    'successful changes include two top-level and four recursive rows' => [static fn (): mixed => $successful()['changes'], 6],
    'successful recursive option enabled' => [static fn (): mixed => $successful()['recursive_triggers'], true],
    'successful max depth defaults to sqlite limit' => [static fn (): mixed => $successful()['max_depth'], 1000],
    'successful parent names include recursive update children' => [static fn (): mixed => array_column($successful()['parent'], 'key_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'successful parent keys rekey updated seed and append children' => [static fn (): mixed => array_column($successful()['parent'], 'setting_id'), [1, 20, 21, 22, 30, 31, 32]],
    'successful parent levels follow recursion' => [static fn (): mixed => array_column($successful()['parent'], 'level'), [0, 1, 2, 3, 1, 2, 3]],
    'successful updated rows contain seed only' => [static fn (): mixed => array_column($successful()['updated'], 'key_name'), ['plugin_seed']],
    'successful inserted rows contain recursive and fresh rows' => [static fn (): mixed => array_column($successful()['inserted'], 'key_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'successful child keys are all valid after recursive upserts' => [static fn (): mixed => array_column($successful()['child'], 'setting_id'), [1, 20, 20, 21, 22, 30, 31, 32]],
    'successful child meta keys track changed and load_policy rows' => [static fn (): mixed => array_column($successful()['child'], 'meta_key'), ['source', 'source', 'changed', 'load_policy', 'load_policy', 'load_policy', 'load_policy', 'load_policy']],
    'successful has no foreign key violations' => [static fn (): mixed => $successful()['foreign_key_violations'], []],
    'successful yielded events reflect depth-first recursion' => [static fn (): mixed => array_column($successful()['yielded'], 'event'), ['insert', 'insert', 'update', 'insert', 'insert', 'insert']],
    'successful yielded names reflect depth-first recursion' => [static fn (): mixed => array_column($successful()['yielded'], 'key_name'), ['plugin_seed:child:child', 'plugin_seed:child', 'plugin_seed', 'fresh_plugin:child:child', 'fresh_plugin:child', 'fresh_plugin']],
    'successful yielded depths include nested rows' => [static fn (): mixed => array_column($successful()['yielded'], 'depth'), [2, 1, 0, 2, 1, 0]],
    'successful yielded source triggers mark recursive rows' => [static fn (): mixed => array_column($successful()['yielded'], 'source_trigger'), ['app_settings_after_insert_recursive_upsert', 'app_settings_after_update_recursive_upsert', null, 'app_settings_after_insert_recursive_upsert', 'app_settings_after_insert_recursive_upsert', null]],
    'successful first yield is deepest plugin seed child' => [static fn (): mixed => $successful()['yielded'][0]['new_key'], 22],
    'successful seed yield old key exposes current conflict row' => [static fn (): mixed => $successful()['yielded'][2]['old_key'], 2],
    'successful trigger effects include before update first' => [static fn (): mixed => $successful()['trigger_effects'][0]['trigger'], 'app_settings_bu_rekey_meta'],
    'successful trigger effects include recursive upsert trigger' => [static fn (): mixed => in_array('app_settings_after_update_recursive_upsert', array_column($successful()['trigger_effects'], 'trigger'), true), true],
    'successful trigger effect depths include nested inserts' => [static fn (): mixed => array_values(array_unique(array_column($successful()['trigger_effects'], 'depth'))), [0, 1, 2]],
    'successful current parent equals parent' => [static fn (): mixed => $successful()['current_parent'], $successful()['parent']],
    'successful attempted parent equals committed parent' => [static fn (): mixed => $successful()['attempted_parent'], $successful()['parent']],
    'successful savepoint is not preserved because rows changed' => [static fn (): mixed => $successful()['savepoint_preserved'], false],
    'successful dependencies name recursive upsert and savepoint' => [static fn (): mixed => $successful()['dependencies'], ['sqlite-upsert-trigger-yield', 'sqlite-recursive-trigger-current-savepoint']],

    'rollback restores original parent names' => [static fn (): mixed => array_column($rolledBack()['parent'], 'key_name'), ['siteurl', 'plugin_seed']],
    'rollback restores original child keys' => [static fn (): mixed => array_column($rolledBack()['child'], 'setting_id'), [1, 2]],
    'rollback current parent equals restored parent' => [static fn (): mixed => $rolledBack()['current_parent'], $rolledBack()['parent']],
    'rollback attempted parent includes discarded fresh rows' => [static fn (): mixed => array_column($rolledBack()['attempted_parent'], 'key_name'), ['siteurl', 'plugin_seed', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback attempted child includes trigger side effects' => [static fn (): mixed => array_column($rolledBack()['attempted_child'], 'setting_id'), [1, 2, 30, 31, 32]],
    'rollback clears committed inserts' => [static fn (): mixed => $rolledBack()['inserted'], []],
    'rollback clears committed updates' => [static fn (): mixed => $rolledBack()['updated'], []],
    'rollback clears changes count' => [static fn (): mixed => $rolledBack()['changes'], 0],
    'rollback marks aborted' => [static fn (): mixed => $rolledBack()['aborted'], true],
    'rollback marks savepoint scope' => [static fn (): mixed => $rolledBack()['rollback_scope'], 'savepoint'],
    'rollback records trigger reason' => [static fn (): mixed => $rolledBack()['rollback_reason'], 'recursive-child-conflict'],
    'rollback preserves savepoint image' => [static fn (): mixed => $rolledBack()['savepoint_preserved'], true],
    'rollback discarded rows include recursive attempted inserts' => [static fn (): mixed => array_column($rolledBack()['discarded'], 'key_name'), ['fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback appends savepoint effect' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['action'], 'rollback-to-current-savepoint'],
    'rollback savepoint effect names savepoint' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['savepoint'], 'app-setting-import'],
    'rollback savepoint effect counts discarded rows' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['discarded_count'], 3],
    'rollback clears yielded rows before statement result' => [static fn (): mixed => array_column($rolledBack()['yielded'], 'key_name'), []],

    'recursive disabled inserts only top-level fresh row' => [static fn (): mixed => array_column($recursiveOff()['parent'], 'key_name'), ['siteurl', 'plugin_seed', 'fresh_plugin']],
    'recursive disabled changes one row' => [static fn (): mixed => $recursiveOff()['changes'], 1],
    'recursive disabled records option false' => [static fn (): mixed => $recursiveOff()['recursive_triggers'], false],
    'recursive disabled child count includes top insert metadata only' => [static fn (): mixed => count($recursiveOff()['child']), 3],
    'recursive disabled yielded depth zero only' => [static fn (): mixed => array_column($recursiveOff()['yielded'], 'depth'), [0]],

    'immediate fk violation rolls back current savepoint' => [static fn (): mixed => $immediateFk()['rolled_back'], true],
    'immediate fk violation reason recorded' => [static fn (): mixed => $immediateFk()['rollback_reason'], 'foreign-key-immediate'],
    'immediate fk violation restores child rows' => [static fn (): mixed => array_column($immediateFk()['child'], 'setting_id'), [1, 2]],
    'immediate fk violation attempted child has orphan' => [static fn (): mixed => array_column($immediateFk()['attempted_child'], 'setting_id'), [1, 999]],
    'immediate fk violation clears committed changes' => [static fn (): mixed => $immediateFk()['changes'], 0],

    'bad savepoint name rejected' => [static fn (): mixed => SQLiteRecursiveSavepointUpsertPlan::execute('', $parents, $children, [], ['key_name'], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'empty unique column list rejected' => [static fn (): mixed => SQLiteRecursiveSavepointUpsertPlan::execute('sp', $parents, $children, [], [], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'bad unique column rejected' => [static fn (): mixed => SQLiteRecursiveSavepointUpsertPlan::execute('sp', $parents, $children, [], ['1bad'], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'bad conflict action rejected' => [static fn (): mixed => $run([], null, null, ['conflict_action' => 'replace']), InvalidArgumentException::class],
    'bad trigger action rejected' => [static fn (): mixed => $run([['setting_id' => 40, 'key_name' => 'bad', 'key_value' => 'x', 'level' => 1, 'load_policy' => 'no']], [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'delete-parent',
    ]]), InvalidArgumentException::class],
    'bad when operator rejected' => [static function () use ($run, $triggers): mixed {
        $triggers[3]['when'][1] = 'like';
        return $run([['setting_id' => 41, 'key_name' => 'bad_when', 'key_value' => 'x', 'level' => 1, 'load_policy' => 'no']], $triggers);
    }, InvalidArgumentException::class],
    'old reference on insert rejected' => [static fn (): mixed => $run([['setting_id' => 42, 'key_name' => 'bad_old', 'key_value' => 'x', 'level' => 1, 'load_policy' => 'no']], [[
        'name' => 'bad_old',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old_key' => 'old.setting_id'],
    ]]), InvalidArgumentException::class],
    'missing incoming unique column rejected' => [static fn (): mixed => $run([['setting_id' => 43, 'key_value' => 'x', 'level' => 1, 'load_policy' => 'no']]), InvalidArgumentException::class],
    'bad max depth rejected' => [static fn (): mixed => $run([], null, null, ['max_depth' => 0]), InvalidArgumentException::class],
    'max depth rollback signal restores savepoint' => [static fn (): mixed => $run([['setting_id' => 44, 'key_name' => 'depth', 'key_value' => 'x', 'level' => 1, 'load_policy' => 'no']], null, null, ['max_depth' => 1])['rolled_back'], true],
    'max depth rollback reason recorded' => [static fn (): mixed => $run([['setting_id' => 44, 'key_name' => 'depth', 'key_value' => 'x', 'level' => 1, 'load_policy' => 'no']], null, null, ['max_depth' => 1])['rollback_reason'], 'recursive-trigger-depth'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['recursive savepoint upsert current next27 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
