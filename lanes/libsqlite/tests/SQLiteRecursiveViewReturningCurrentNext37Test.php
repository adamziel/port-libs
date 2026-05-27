<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveViewReturningPlan;

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
        'name' => 'active_options_after_insert_recursive_upsert',
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
        'name' => 'active_options_after_update_recursive_upsert',
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
$returning = [
    'option_id',
    'option_name',
    ['expr' => 'view.source', 'as' => 'source'],
    ['expr' => 'excluded.option_id', 'as' => 'base_option_id'],
    ['expr' => 'current.option_id', 'as' => 'current_option_id'],
    ['expr' => 'yield.event', 'as' => 'event'],
    static fn (array $view, array $incoming, array $current, array $yielded, string $viewName): string => $viewName . ':' . $yielded['event'] . ':' . $view['option_name'] . ':' . $current['option_id'],
];
$viewRows = [
    ['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'autoload' => 'yes', 'level' => 1, 'source' => 'import-view'],
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'autoload' => 'no', 'level' => 1, 'source' => 'import-view'],
];
$run = static fn (array $rows = null, array $triggerSet = null, array $options = [], array $projection = null): array => SQLiteRecursiveViewReturningPlan::execute(
    $parents,
    $children,
    $rows ?? $viewRows,
    ['option_name'],
    $assignments,
    $foreignKey,
    $triggerSet ?? $triggers,
    $projection ?? $returning,
    array_merge(['view_name' => 'active_options', 'savepoint' => 'wp-active-view-import'], $options),
);
$runDefaultProjection = static fn (): array => SQLiteRecursiveViewReturningPlan::execute(
    $parents,
    $children,
    $viewRows,
    ['option_name'],
    $assignments,
    $foreignKey,
    $triggers,
    null,
    ['view_name' => 'active_options', 'savepoint' => 'wp-active-view-import'],
);
$successful = static fn (): array => $run();

$rollbackTriggers = $triggers;
$rollbackTriggers[] = [
    'name' => 'active_options_abort_second_child',
    'timing' => 'after',
    'event' => 'insert',
    'action' => 'raise',
    'when' => ['new.option_name', '=', 'fresh_plugin:child:child'],
    'raise' => 'rollback',
    'reason' => 'view-recursive-child-conflict',
    'values' => ['name' => 'new.option_name'],
];
$rolledBack = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'autoload' => 'no', 'level' => 1, 'source' => 'import-view'],
], $rollbackTriggers);
$recursiveOff = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'autoload' => 'no', 'level' => 1, 'source' => 'import-view'],
], null, ['recursive_triggers' => false]);

$cases = [
    'view name recorded' => [static fn (): mixed => $successful()['view'], 'active_options'],
    'view trigger recorded' => [static fn (): mixed => $successful()['view_trigger'], 'active_options_instead_of_upsert'],
    'savepoint recorded' => [static fn (): mixed => $successful()['savepoint'], 'wp-active-view-import'],
    'successful changes include two top level and four recursive rows' => [static fn (): mixed => $successful()['changes'], 6],
    'successful returning count includes top level changed rows only' => [static fn (): mixed => $successful()['returning_count'], 2],
    'successful top level yielded count' => [static fn (): mixed => count($successful()['top_level_yielded']), 2],
    'successful top level events' => [static fn (): mixed => array_column($successful()['top_level_yielded'], 'event'), ['update', 'insert']],
    'successful all yielded preserves depth first recursion' => [static fn (): mixed => array_column($successful()['yielded'], 'option_name'), ['plugin_seed:child:child', 'plugin_seed:child', 'plugin_seed', 'fresh_plugin:child:child', 'fresh_plugin:child', 'fresh_plugin']],
    'successful parent names include recursive rows' => [static fn (): mixed => array_column($successful()['parent'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'successful parent keys include view update and recursive inserts' => [static fn (): mixed => array_column($successful()['parent'], 'option_id'), [1, 20, 21, 22, 30, 31, 32]],
    'successful child keys remain valid after view trigger recursion' => [static fn (): mixed => array_column($successful()['child'], 'option_id'), [1, 20, 20, 21, 22, 30, 31, 32]],
    'successful incoming view rows preserve source label' => [static fn (): mixed => array_column($successful()['incoming_view_rows'], 'source'), ['import-view', 'import-view']],
    'successful incoming view rows preserve view name' => [static fn (): mixed => array_column($successful()['incoming_view_rows'], 'view'), ['active_options', 'active_options']],
    'successful returning option names are statement view rows' => [static fn (): mixed => array_column($successful()['returning_rows'], 'option_name'), ['plugin_seed', 'fresh_plugin']],
    'successful returning source is view source' => [static fn (): mixed => array_column($successful()['returning_rows'], 'source'), ['import-view', 'import-view']],
    'successful returning base option ids are incoming ids' => [static fn (): mixed => array_column($successful()['returning_rows'], 'base_option_id'), [20, 30]],
    'successful returning current option ids match committed rows' => [static fn (): mixed => array_column($successful()['returning_rows'], 'current_option_id'), [20, 30]],
    'successful returning events identify update then insert' => [static fn (): mixed => array_column($successful()['returning_rows'], 'event'), ['update', 'insert']],
    'successful callable returning labels include view and current key' => [static fn (): mixed => array_column($successful()['returning_rows'], 'expr6'), ['active_options:update:plugin_seed:20', 'active_options:insert:fresh_plugin:30']],
    'successful updated row is plugin seed only' => [static fn (): mixed => array_column($successful()['updated'], 'option_name'), ['plugin_seed']],
    'successful inserted rows include recursive and fresh rows' => [static fn (): mixed => array_column($successful()['inserted'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'successful trigger effects include view recursive update trigger' => [static fn (): mixed => in_array('active_options_after_update_recursive_upsert', array_column($successful()['trigger_effects'], 'trigger'), true), true],
    'successful trigger effects include view recursive insert trigger' => [static fn (): mixed => in_array('active_options_after_insert_recursive_upsert', array_column($successful()['trigger_effects'], 'trigger'), true), true],
    'successful trigger depths include nested levels' => [static fn (): mixed => array_values(array_unique(array_column($successful()['trigger_effects'], 'depth'))), [0, 1, 2]],
    'successful has no foreign key violations' => [static fn (): mixed => $successful()['foreign_key_violations'], []],
    'successful view dependencies recorded' => [static fn (): mixed => $successful()['view_dependencies'], ['sqlite-instead-of-view-trigger', 'sqlite-recursive-trigger-current-savepoint', 'sqlite-returning-current-row']],
    'successful base dependencies preserved' => [static fn (): mixed => $successful()['dependencies'], ['sqlite-upsert-trigger-yield', 'sqlite-recursive-trigger-current-savepoint']],
    'default projection returns view row image' => [static fn (): mixed => $runDefaultProjection()['returning_rows'][0], ['view' => 'active_options', 'option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'autoload' => 'yes', 'level' => 1, 'source' => 'import-view']],
    'star projection nests view row image' => [static fn (): mixed => $run(null, null, [], ['*'])['returning_rows'][0]['*']['option_value'], 'seed-new'],
    'plain projection reads view column' => [static fn (): mixed => $run(null, null, [], ['option_value'])['returning_rows'][0]['option_value'], 'seed-new'],
    'view qualified projection reads source' => [static fn (): mixed => $run(null, null, [], [['expr' => 'view.source', 'as' => 'origin']])['returning_rows'][0]['origin'], 'import-view'],
    'current projection reads committed row' => [static fn (): mixed => $run(null, null, [], [['expr' => 'current.autoload', 'as' => 'current_autoload']])['returning_rows'][0]['current_autoload'], 'yes'],
    'yield projection reads top level depth' => [static fn (): mixed => $run(null, null, [], [['expr' => 'yield.depth', 'as' => 'depth']])['returning_rows'][0]['depth'], 0],
    'view literal projection returns view name' => [static fn (): mixed => $run(null, null, [], [['expr' => 'view', 'as' => 'view_name']])['returning_rows'][0]['view_name'], 'active_options'],

    'rollback marks rolled back' => [static fn (): mixed => $rolledBack()['rolled_back'], true],
    'rollback reason is trigger reason' => [static fn (): mixed => $rolledBack()['rollback_reason'], 'view-recursive-child-conflict'],
    'rollback restores original parent names' => [static fn (): mixed => array_column($rolledBack()['parent'], 'option_name'), ['siteurl', 'plugin_seed']],
    'rollback attempted parent includes view recursive rows' => [static fn (): mixed => array_column($rolledBack()['attempted_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback returning rows are cleared with statement result' => [static fn (): mixed => $rolledBack()['returning_rows'], []],
    'rollback top level yielded is empty after savepoint restore' => [static fn (): mixed => $rolledBack()['top_level_yielded'], []],
    'rollback changes are cleared' => [static fn (): mixed => $rolledBack()['changes'], 0],
    'rollback savepoint preserved' => [static fn (): mixed => $rolledBack()['savepoint_preserved'], true],
    'rollback discarded rows include attempted view insert tree' => [static fn (): mixed => array_column($rolledBack()['discarded'], 'option_name'), ['fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback savepoint effect is appended' => [static fn (): mixed => $rolledBack()['trigger_effects'][array_key_last($rolledBack()['trigger_effects'])]['action'], 'rollback-to-current-savepoint'],

    'recursive disabled returns one top level returning row' => [static fn (): mixed => $recursiveOff()['returning_count'], 1],
    'recursive disabled parent names include no children' => [static fn (): mixed => array_column($recursiveOff()['parent'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_plugin']],
    'recursive disabled changes one row' => [static fn (): mixed => $recursiveOff()['changes'], 1],
    'recursive disabled yielded depth zero only' => [static fn (): mixed => array_column($recursiveOff()['yielded'], 'depth'), [0]],
    'recursive disabled returning event is insert' => [static fn (): mixed => $recursiveOff()['returning_rows'][0]['event'], 'insert'],

    'empty savepoint rejected' => [static fn (): mixed => $run([], null, ['savepoint' => '']), InvalidArgumentException::class],
    'bad view name rejected' => [static fn (): mixed => $run([], null, ['view_name' => 'bad-view']), InvalidArgumentException::class],
    'missing option id rejected' => [static fn (): mixed => $run([['option_name' => 'bad', 'option_value' => 'x']]), InvalidArgumentException::class],
    'missing option name rejected' => [static fn (): mixed => $run([['option_id' => 40, 'option_value' => 'x']]), InvalidArgumentException::class],
    'missing option value rejected' => [static fn (): mixed => $run([['option_id' => 40, 'option_name' => 'bad']]), InvalidArgumentException::class],
    'bad returning alias rejected' => [static fn (): mixed => $run(null, null, [], [['expr' => 'view.option_name', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'missing view returning column rejected' => [static fn (): mixed => $run(null, null, [], ['missing_column']), InvalidArgumentException::class],
    'missing current returning column rejected' => [static fn (): mixed => $run(null, null, [], [['expr' => 'current.missing', 'as' => 'missing']]), InvalidArgumentException::class],
    'missing yield returning column rejected' => [static fn (): mixed => $run(null, null, [], [['expr' => 'yield.missing', 'as' => 'missing']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['recursive view returning current next37 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
