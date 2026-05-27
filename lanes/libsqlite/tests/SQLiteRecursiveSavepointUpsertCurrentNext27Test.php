<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveSavepointUpsertPlan;

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
$triggers = [
    [
        'name' => 'wp_options_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_au_meta',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'changed', 'meta_value' => 'new.option_value'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_after_insert_recursive_upsert',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 3],
        'row' => [
            'option_id' => 'new_increment.option_id',
            'option_name' => 'concat:new.option_name:child',
            'option_value' => 'concat:new.option_value:child',
            'level' => 'new_increment.level',
            'autoload' => 'new.autoload',
        ],
        'values' => ['name' => 'new.option_name', 'level' => 'new.level'],
    ],
    [
        'name' => 'wp_options_after_update_recursive_upsert',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.level', '<', 3],
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
$run = static fn (array $incoming, array $triggerSet = null, array $fk = null, array $options = []) => SQLiteRecursiveSavepointUpsertPlan::execute(
    'wp-option-import',
    $parents,
    $children,
    $incoming,
    ['option_name'],
    $assignments,
    $fk ?? $foreignKey,
    $triggerSet ?? $triggers,
    $options,
);
$successful = static fn (): array => $run([
    ['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'level' => 1, 'autoload' => 'yes'],
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'level' => 1, 'autoload' => 'no'],
]);
$rollbackTriggers = $triggers;
$rollbackTriggers[] = [
    'name' => 'wp_options_abort_second_child',
    'timing' => 'after',
    'event' => 'insert',
    'action' => 'raise',
    'when' => ['new.option_name', '=', 'fresh_plugin:child:child'],
    'raise' => 'rollback',
    'reason' => 'recursive-child-conflict',
    'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
];
$rolledBack = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'level' => 1, 'autoload' => 'no'],
], $rollbackTriggers);
$recursiveOff = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'level' => 1, 'autoload' => 'no'],
], null, null, ['recursive_triggers' => false]);
$immediateFkTriggers = $triggers;
$immediateFkTriggers[0]['set_child_key'] = 999;
$immediateFk = static fn (): array => $run([
    ['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'level' => 1, 'autoload' => 'yes'],
], $immediateFkTriggers, ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => false]);

$cases = [
    'successful savepoint name is preserved' => [static fn (): mixed => $successful()['savepoint'], 'wp-option-import'],
    'successful does not roll back' => [static fn (): mixed => $successful()['rolled_back'], false],
    'successful rollback scope is none' => [static fn (): mixed => $successful()['rollback_scope'], 'none'],
    'successful changes include two top-level and four recursive rows' => [static fn (): mixed => $successful()['changes'], 6],
    'successful recursive option enabled' => [static fn (): mixed => $successful()['recursive_triggers'], true],
    'successful max depth defaults to sqlite limit' => [static fn (): mixed => $successful()['max_depth'], 1000],
    'successful parent names include recursive update children' => [static fn (): mixed => array_column($successful()['parent'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'successful parent keys rekey updated seed and append children' => [static fn (): mixed => array_column($successful()['parent'], 'option_id'), [1, 20, 21, 22, 30, 31, 32]],
    'successful parent levels follow recursion' => [static fn (): mixed => array_column($successful()['parent'], 'level'), [0, 1, 2, 3, 1, 2, 3]],
    'successful updated rows contain seed only' => [static fn (): mixed => array_column($successful()['updated'], 'option_name'), ['plugin_seed']],
    'successful inserted rows contain recursive and fresh rows' => [static fn (): mixed => array_column($successful()['inserted'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'successful child keys are all valid after recursive upserts' => [static fn (): mixed => array_column($successful()['child'], 'option_id'), [1, 20, 20, 21, 22, 30, 31, 32]],
    'successful child meta keys track changed and autoload rows' => [static fn (): mixed => array_column($successful()['child'], 'meta_key'), ['source', 'source', 'changed', 'autoload', 'autoload', 'autoload', 'autoload', 'autoload']],
    'successful has no foreign key violations' => [static fn (): mixed => $successful()['foreign_key_violations'], []],
    'successful yielded events reflect depth-first recursion' => [static fn (): mixed => array_column($successful()['yielded'], 'event'), ['insert', 'insert', 'update', 'insert', 'insert', 'insert']],
    'successful yielded names reflect depth-first recursion' => [static fn (): mixed => array_column($successful()['yielded'], 'option_name'), ['plugin_seed:child:child', 'plugin_seed:child', 'plugin_seed', 'fresh_plugin:child:child', 'fresh_plugin:child', 'fresh_plugin']],
    'successful yielded depths include nested rows' => [static fn (): mixed => array_column($successful()['yielded'], 'depth'), [2, 1, 0, 2, 1, 0]],
    'successful yielded source triggers mark recursive rows' => [static fn (): mixed => array_column($successful()['yielded'], 'source_trigger'), ['wp_options_after_insert_recursive_upsert', 'wp_options_after_update_recursive_upsert', null, 'wp_options_after_insert_recursive_upsert', 'wp_options_after_insert_recursive_upsert', null]],
    'successful first yield is deepest plugin seed child' => [static fn (): mixed => $successful()['yielded'][0]['new_key'], 22],
    'successful seed yield old key exposes current conflict row' => [static fn (): mixed => $successful()['yielded'][2]['old_key'], 2],
    'successful trigger effects include before update first' => [static fn (): mixed => $successful()['trigger_effects'][0]['trigger'], 'wp_options_bu_rekey_meta'],
    'successful trigger effects include recursive upsert trigger' => [static fn (): mixed => in_array('wp_options_after_update_recursive_upsert', array_column($successful()['trigger_effects'], 'trigger'), true), true],
    'successful trigger effect depths include nested inserts' => [static fn (): mixed => array_values(array_unique(array_column($successful()['trigger_effects'], 'depth'))), [0, 1, 2]],
    'successful current parent equals parent' => [static fn (): mixed => $successful()['current_parent'], $successful()['parent']],
    'successful attempted parent equals committed parent' => [static fn (): mixed => $successful()['attempted_parent'], $successful()['parent']],
    'successful savepoint is not preserved because rows changed' => [static fn (): mixed => $successful()['savepoint_preserved'], false],
    'successful dependencies name recursive upsert and savepoint' => [static fn (): mixed => $successful()['dependencies'], ['sqlite-upsert-trigger-yield', 'sqlite-recursive-trigger-current-savepoint']],

    'rollback restores original parent names' => [static fn (): mixed => array_column($rolledBack()['parent'], 'option_name'), ['siteurl', 'plugin_seed']],
    'rollback restores original child keys' => [static fn (): mixed => array_column($rolledBack()['child'], 'option_id'), [1, 2]],
    'rollback current parent equals restored parent' => [static fn (): mixed => $rolledBack()['current_parent'], $rolledBack()['parent']],
    'rollback attempted parent includes discarded fresh rows' => [static fn (): mixed => array_column($rolledBack()['attempted_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback attempted child includes trigger side effects' => [static fn (): mixed => array_column($rolledBack()['attempted_child'], 'option_id'), [1, 2, 30, 31, 32]],
    'rollback clears committed inserts' => [static fn (): mixed => $rolledBack()['inserted'], []],
    'rollback clears committed updates' => [static fn (): mixed => $rolledBack()['updated'], []],
    'rollback clears changes count' => [static fn (): mixed => $rolledBack()['changes'], 0],
    'rollback marks aborted' => [static fn (): mixed => $rolledBack()['aborted'], true],
    'rollback marks savepoint scope' => [static fn (): mixed => $rolledBack()['rollback_scope'], 'savepoint'],
    'rollback records trigger reason' => [static fn (): mixed => $rolledBack()['rollback_reason'], 'recursive-child-conflict'],
    'rollback preserves savepoint image' => [static fn (): mixed => $rolledBack()['savepoint_preserved'], true],
    'rollback discarded rows include recursive attempted inserts' => [static fn (): mixed => array_column($rolledBack()['discarded'], 'option_name'), ['fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback appends savepoint effect' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['action'], 'rollback-to-current-savepoint'],
    'rollback savepoint effect names savepoint' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['savepoint'], 'wp-option-import'],
    'rollback savepoint effect counts discarded rows' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['discarded_count'], 3],
    'rollback clears yielded rows before statement result' => [static fn (): mixed => array_column($rolledBack()['yielded'], 'option_name'), []],

    'recursive disabled inserts only top-level fresh row' => [static fn (): mixed => array_column($recursiveOff()['parent'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_plugin']],
    'recursive disabled changes one row' => [static fn (): mixed => $recursiveOff()['changes'], 1],
    'recursive disabled records option false' => [static fn (): mixed => $recursiveOff()['recursive_triggers'], false],
    'recursive disabled child count includes top insert metadata only' => [static fn (): mixed => count($recursiveOff()['child']), 3],
    'recursive disabled yielded depth zero only' => [static fn (): mixed => array_column($recursiveOff()['yielded'], 'depth'), [0]],

    'immediate fk violation rolls back current savepoint' => [static fn (): mixed => $immediateFk()['rolled_back'], true],
    'immediate fk violation reason recorded' => [static fn (): mixed => $immediateFk()['rollback_reason'], 'foreign-key-immediate'],
    'immediate fk violation restores child rows' => [static fn (): mixed => array_column($immediateFk()['child'], 'option_id'), [1, 2]],
    'immediate fk violation attempted child has orphan' => [static fn (): mixed => array_column($immediateFk()['attempted_child'], 'option_id'), [1, 999]],
    'immediate fk violation clears committed changes' => [static fn (): mixed => $immediateFk()['changes'], 0],

    'bad savepoint name rejected' => [static fn (): mixed => SQLiteRecursiveSavepointUpsertPlan::execute('', $parents, $children, [], ['option_name'], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'empty unique column list rejected' => [static fn (): mixed => SQLiteRecursiveSavepointUpsertPlan::execute('sp', $parents, $children, [], [], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'bad unique column rejected' => [static fn (): mixed => SQLiteRecursiveSavepointUpsertPlan::execute('sp', $parents, $children, [], ['1bad'], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'bad conflict action rejected' => [static fn (): mixed => $run([], null, null, ['conflict_action' => 'replace']), InvalidArgumentException::class],
    'bad trigger action rejected' => [static fn (): mixed => $run([['option_id' => 40, 'option_name' => 'bad', 'option_value' => 'x', 'level' => 1, 'autoload' => 'no']], [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'delete-parent',
    ]]), InvalidArgumentException::class],
    'bad when operator rejected' => [static function () use ($run, $triggers): mixed {
        $triggers[3]['when'][1] = 'like';
        return $run([['option_id' => 41, 'option_name' => 'bad_when', 'option_value' => 'x', 'level' => 1, 'autoload' => 'no']], $triggers);
    }, InvalidArgumentException::class],
    'old reference on insert rejected' => [static fn (): mixed => $run([['option_id' => 42, 'option_name' => 'bad_old', 'option_value' => 'x', 'level' => 1, 'autoload' => 'no']], [[
        'name' => 'bad_old',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old_key' => 'old.option_id'],
    ]]), InvalidArgumentException::class],
    'missing incoming unique column rejected' => [static fn (): mixed => $run([['option_id' => 43, 'option_value' => 'x', 'level' => 1, 'autoload' => 'no']]), InvalidArgumentException::class],
    'bad max depth rejected' => [static fn (): mixed => $run([], null, null, ['max_depth' => 0]), InvalidArgumentException::class],
    'max depth rollback signal restores savepoint' => [static fn (): mixed => $run([['option_id' => 44, 'option_name' => 'depth', 'option_value' => 'x', 'level' => 1, 'autoload' => 'no']], null, null, ['max_depth' => 1])['rolled_back'], true],
    'max depth rollback reason recorded' => [static fn (): mixed => $run([['option_id' => 44, 'option_name' => 'depth', 'option_value' => 'x', 'level' => 1, 'autoload' => 'no']], null, null, ['max_depth' => 1])['rollback_reason'], 'recursive-trigger-depth'],
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
