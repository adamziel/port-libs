<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningRecursiveViewPlan;

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
    'option_name',
    ['expr' => 'view.source', 'as' => 'view_source'],
    ['expr' => 'excluded.option_id', 'as' => 'incoming_id'],
    ['expr' => 'current.option_id', 'as' => 'current_id'],
    ['expr' => 'yield.event', 'as' => 'event'],
    static fn (array $viewRow, array $incoming, array $current, array $yielded, string $viewName): string => $viewName . ':' . $yielded['event'] . ':' . $current['option_name'],
];
$viewRows = [
    ['option_id' => 20, 'option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'autoload' => 'yes', 'level' => 1, 'source' => 'current-view'],
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'autoload' => 'no', 'level' => 1, 'source' => 'current-view'],
];

$run = static fn (array $rows = null, array $triggerSet = null, array $projection = null, array $options = []): array => SQLiteTriggerUpsertReturningRecursiveViewPlan::execute(
    $parents,
    $children,
    $rows ?? $viewRows,
    ['option_name'],
    $assignments,
    $foreignKey,
    $triggerSet ?? $triggers,
    $projection ?? $returning,
    array_merge(['view_name' => 'active_options', 'savepoint' => 'wp-current-next53'], $options),
);

$successful = static fn (): array => $run();
$recursiveOff = static fn (): array => $run([
    ['option_id' => 40, 'option_name' => 'single_plugin', 'option_value' => 'one', 'autoload' => 'no', 'level' => 1, 'source' => 'manual'],
], null, null, ['recursive_triggers' => false]);
$rolledBackTriggers = $triggers;
$rolledBackTriggers[] = [
    'name' => 'active_options_abort_second_child',
    'timing' => 'after',
    'event' => 'insert',
    'action' => 'raise',
    'when' => ['new.option_name', '=', 'fresh_plugin:child:child'],
    'raise' => 'rollback',
    'reason' => 'recursive-view-next-row-abort',
    'values' => ['name' => 'new.option_name'],
];
$rolledBack = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'autoload' => 'no', 'level' => 1, 'source' => 'current-view'],
], $rolledBackTriggers);

$cases = [
    'records savepoint' => [static fn (): mixed => $successful()['savepoint'], 'wp-current-next53'],
    'records view name' => [static fn (): mixed => $successful()['view'], 'active_options'],
    'records generated view trigger name' => [static fn (): mixed => $successful()['view_trigger'], 'active_options_instead_of_upsert'],
    'changes include top level and recursive rows' => [static fn (): mixed => $successful()['changes'], 6],
    'returning count only top level rows' => [static fn (): mixed => $successful()['returning_count'], 2],
    'recursive returning rows are suppressed' => [static fn (): mixed => $successful()['recursive_returning_suppressed'], 4],
    'statement returning names are top level only' => [static fn (): mixed => $successful()['statement_returning_names'], ['plugin_seed', 'fresh_plugin']],
    'statement current ids match current row images' => [static fn (): mixed => $successful()['statement_current_option_ids'], [20, 30]],
    'trace has one entry per view row' => [static fn (): mixed => count($successful()['current_next_trace']), 2],
    'trace statuses are changed' => [static fn (): mixed => array_column($successful()['current_next_trace'], 'status'), ['changed', 'changed']],
    'trace events update then insert' => [static fn (): mixed => array_column($successful()['current_next_trace'], 'event'), ['update', 'insert']],
    'trace current row for update has new value' => [static fn (): mixed => $successful()['current_next_trace'][0]['current_row']['option_value'], 'seed-new'],
    'trace current row for insert has fresh value' => [static fn (): mixed => $successful()['current_next_trace'][1]['current_row']['option_value'], 'fresh'],
    'trace view rows preserve current source label' => [static fn (): mixed => array_column(array_column($successful()['current_next_trace'], 'view_row'), 'source'), ['current-view', 'current-view']],
    'trace returning rows preserve view source' => [static fn (): mixed => array_column(array_column($successful()['current_next_trace'], 'returning_row'), 'view_source'), ['current-view', 'current-view']],
    'trace returning rows include incoming ids' => [static fn (): mixed => array_column(array_column($successful()['current_next_trace'], 'returning_row'), 'incoming_id'), [20, 30]],
    'trace returning rows include current ids' => [static fn (): mixed => array_column(array_column($successful()['current_next_trace'], 'returning_row'), 'current_id'), [20, 30]],
    'trace returning rows include events' => [static fn (): mixed => array_column(array_column($successful()['current_next_trace'], 'returning_row'), 'event'), ['update', 'insert']],
    'trace callable return labels update insert' => [static fn (): mixed => array_column(array_column($successful()['current_next_trace'], 'returning_row'), 'expr5'), ['active_options:update:plugin_seed', 'active_options:insert:fresh_plugin']],
    'update next recursive names sorted by depth' => [static fn (): mixed => $successful()['current_next_trace'][0]['next_recursive_names'], ['plugin_seed:child', 'plugin_seed:child:child']],
    'insert next recursive names sorted by depth' => [static fn (): mixed => $successful()['current_next_trace'][1]['next_recursive_names'], ['fresh_plugin:child', 'fresh_plugin:child:child']],
    'update next recursive source trigger' => [static fn (): mixed => $successful()['current_next_trace'][0]['next_recursive_source_triggers'], ['active_options_after_update_recursive_upsert', 'active_options_after_insert_recursive_upsert']],
    'insert next recursive source trigger' => [static fn (): mixed => $successful()['current_next_trace'][1]['next_recursive_source_triggers'], ['active_options_after_insert_recursive_upsert']],
    'recursive yielded names include no top level rows' => [static fn (): mixed => array_column($successful()['recursive_yielded'], 'option_name'), ['plugin_seed:child:child', 'plugin_seed:child', 'fresh_plugin:child:child', 'fresh_plugin:child']],
    'recursive yielded depths are nested only' => [static fn (): mixed => array_values(array_unique(array_column($successful()['recursive_yielded'], 'depth'))), [2, 1]],
    'parent rows include update current before next rows' => [static fn (): mixed => array_column($successful()['parent'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'parent ids include recursive increments' => [static fn (): mixed => array_column($successful()['parent'], 'option_id'), [1, 20, 21, 22, 30, 31, 32]],
    'child keys include updated and recursive rows' => [static fn (): mixed => array_column($successful()['child'], 'option_id'), [1, 20, 20, 21, 22, 30, 31, 32]],
    'updated list only statement update row' => [static fn (): mixed => array_column($successful()['updated'], 'option_name'), ['plugin_seed']],
    'inserted list includes next rows and statement insert' => [static fn (): mixed => array_column($successful()['inserted'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'trigger effects include update recursive trigger' => [static fn (): mixed => in_array('active_options_after_update_recursive_upsert', array_column($successful()['trigger_effects'], 'trigger'), true), true],
    'trigger effects include insert recursive trigger' => [static fn (): mixed => in_array('active_options_after_insert_recursive_upsert', array_column($successful()['trigger_effects'], 'trigger'), true), true],
    'trigger effect ordinals identify both view rows' => [static fn (): mixed => array_values(array_unique(array_column($successful()['trigger_effects'], 'ordinal'))), [0, 1]],
    'no foreign key violations' => [static fn (): mixed => $successful()['foreign_key_violations'], []],
    'dependencies include base upsert yield' => [static fn (): mixed => in_array('sqlite-upsert-trigger-yield', $successful()['dependencies'], true), true],
    'dependencies include current row boundary' => [static fn (): mixed => in_array('sqlite-trigger-upsert-returning-current-row', $successful()['dependencies'], true), true],
    'dependencies include next recursive source' => [static fn (): mixed => in_array('sqlite-recursive-view-next-row-source', $successful()['dependencies'], true), true],
    'view dependencies preserved' => [static fn (): mixed => $successful()['view_dependencies'], ['sqlite-instead-of-view-trigger', 'sqlite-recursive-trigger-current-savepoint', 'sqlite-returning-current-row']],

    'recursive off changes one row' => [static fn (): mixed => $recursiveOff()['changes'], 1],
    'recursive off suppresses no recursive returning rows' => [static fn (): mixed => $recursiveOff()['recursive_returning_suppressed'], 0],
    'recursive off trace has empty next rows' => [static fn (): mixed => $recursiveOff()['current_next_trace'][0]['next_recursive_rows'], []],
    'recursive off statement returning name' => [static fn (): mixed => $recursiveOff()['statement_returning_names'], ['single_plugin']],
    'recursive off current id' => [static fn (): mixed => $recursiveOff()['statement_current_option_ids'], [40]],
    'recursive off parent has no child suffix rows' => [static fn (): mixed => array_column($recursiveOff()['parent'], 'option_name'), ['siteurl', 'plugin_seed', 'single_plugin']],

    'rollback marks savepoint rollback' => [static fn (): mixed => $rolledBack()['rolled_back'], true],
    'rollback reason is recursive trigger reason' => [static fn (): mixed => $rolledBack()['rollback_reason'], 'recursive-view-next-row-abort'],
    'rollback restores parent rows' => [static fn (): mixed => array_column($rolledBack()['parent'], 'option_name'), ['siteurl', 'plugin_seed']],
    'rollback preserves attempted next rows' => [static fn (): mixed => array_column($rolledBack()['attempted_parent'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback clears statement returning rows' => [static fn (): mixed => $rolledBack()['returning_rows'], []],
    'rollback clears top level yielded' => [static fn (): mixed => $rolledBack()['top_level_yielded'], []],
    'rollback trace marks skipped because current row restored' => [static fn (): mixed => $rolledBack()['current_next_trace'][0]['status'], 'skipped'],
    'rollback recursive yielded is cleared with statement yield rows' => [static fn (): mixed => $rolledBack()['recursive_yielded'], []],
    'rollback discarded rows include top and next rows' => [static fn (): mixed => array_column($rolledBack()['discarded'], 'option_name'), ['fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'rollback savepoint is preserved' => [static fn (): mixed => $rolledBack()['savepoint_preserved'], true],

    'star projection nests current view row' => [static fn (): mixed => $run(null, null, ['*'])['returning_rows'][0]['*']['option_value'], 'seed-new'],
    'current projection reads current row' => [static fn (): mixed => $run(null, null, [['expr' => 'current.autoload', 'as' => 'current_autoload']])['returning_rows'][0]['current_autoload'], 'yes'],
    'yield projection reads event' => [static fn (): mixed => $run(null, null, [['expr' => 'yield.event', 'as' => 'kind']])['returning_rows'][0]['kind'], 'update'],
    'view projection reads source' => [static fn (): mixed => $run(null, null, [['expr' => 'view.source', 'as' => 'source']])['returning_rows'][0]['source'], 'current-view'],
    'empty savepoint rejected' => [static fn (): mixed => $run([], null, null, ['savepoint' => '']), InvalidArgumentException::class],
    'bad view name rejected' => [static fn (): mixed => $run([], null, null, ['view_name' => 'active-options']), InvalidArgumentException::class],
    'missing option id rejected' => [static fn (): mixed => $run([['option_name' => 'bad', 'option_value' => 'x']]), InvalidArgumentException::class],
    'missing current projection rejected' => [static fn (): mixed => $run(null, null, [['expr' => 'current.missing', 'as' => 'missing']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger upsert returning recursive view current next53 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
